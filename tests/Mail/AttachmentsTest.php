<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Mail;

use Recado\Sdk\Exception\AttachmentsTooLargeException;
use Recado\Sdk\Exception\UnsupportedFeatureException;
use Recado\Sdk\Laravel\Mail\RecadoHeaders;
use Recado\Sdk\Laravel\Mail\RecadoTransport;
use Recado\Sdk\Laravel\Mail\PayloadMapper;
use Recado\Sdk\RecadoClient;
use Recado\Sdk\Tests\Mail\Support\SpyLogger;
use Recado\Sdk\Tests\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Mime\Email;

/**
 * Attachment behavior of the mail transport / payload mapper: the default
 * 'send' mode maps Symfony DataParts onto the /send `attachments` field, the
 * legacy 'fail'/'ignore' modes stay available, multi-recipient sends with
 * attachments fan out as per-recipient singles (the /send/batch endpoint
 * rejects attachments), the decoded total size is guarded locally, and
 * attachments participate in the content idempotency key.
 */
final class AttachmentsTest extends TestCase
{
    public function test_send_mode_is_the_default_and_maps_attachments_to_the_payload(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'msg-1', 'status' => 'queued']]),
        ], $history);

        // No `attachments` config key at all — 'send' must be the default.
        $transport = $this->transport($client);

        $email = $this->email()->to('jane@example.com')->subject('Invoice')->html('<p>Attached.</p>')
            ->attach('%PDF-1.4 fake', 'invoice.pdf', 'application/pdf')
            ->attach('plain text', 'notes.txt', 'text/plain');

        $transport->send($email);

        $this->assertCount(1, $history);
        $this->assertStringEndsWith('/send', $history[0]['request']->getUri()->getPath());

        $payload = $this->body($history, 0);
        $this->assertCount(2, $payload['attachments']);
        $this->assertSame(
            ['filename' => 'invoice.pdf', 'content_type' => 'application/pdf', 'content' => base64_encode('%PDF-1.4 fake')],
            $payload['attachments'][0],
        );
        $this->assertSame(
            ['filename' => 'notes.txt', 'content_type' => 'text/plain', 'content' => base64_encode('plain text')],
            $payload['attachments'][1],
        );
    }

    public function test_unnamed_attachment_gets_a_fallback_filename_with_media_type_extension(): void
    {
        $email = $this->email()->subject('S')->html('<p>x</p>')
            ->attach('%PDF-1.4 fake', null, 'application/pdf');

        $payload = PayloadMapper::base($email, []);

        $this->assertSame('attachment.pdf', $payload['attachments'][0]['filename']);
        $this->assertSame('application/pdf', $payload['attachments'][0]['content_type']);
    }

    public function test_attachments_are_included_on_template_payloads(): void
    {
        $email = $this->email()->subject('ignored')->html('<p>ignored</p>')
            ->attach('bytes', 'terms.pdf', 'application/pdf');
        $email->getHeaders()->addTextHeader(RecadoHeaders::TEMPLATE, 'welcome');

        $payload = PayloadMapper::base($email, []);

        $this->assertSame('welcome', $payload['template']);
        $this->assertSame('terms.pdf', $payload['attachments'][0]['filename']);
        $this->assertArrayNotHasKey('subject', $payload);
    }

    public function test_fail_mode_still_throws_unsupported_feature_exception(): void
    {
        $email = $this->email()->subject('S')->html('<p>x</p>')
            ->attach('binary', 'file.txt', 'text/plain');

        $this->expectException(UnsupportedFeatureException::class);

        PayloadMapper::base($email, ['attachments' => 'fail']);
    }

    public function test_ignore_mode_still_drops_attachments_with_a_warning(): void
    {
        $logger = new SpyLogger();

        $email = $this->email()->subject('S')->html('<p>x</p>')
            ->attach('binary', 'file.txt', 'text/plain');

        $payload = PayloadMapper::base($email, ['attachments' => 'ignore'], $logger);

        $this->assertArrayNotHasKey('attachments', $payload);
        $this->assertTrue($logger->has('warning'));
    }

    public function test_total_decoded_size_over_the_limit_throws_locally_before_any_request(): void
    {
        $history = [];
        $client = $this->clientWithResponses([], $history);
        $transport = $this->transport($client);

        $sixMb = str_repeat('a', 6 * 1024 * 1024);

        // Two 6 MB files: each fits the per-file cap, the 12 MB total does not.
        $email = $this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>')
            ->attach($sixMb, 'one.bin', 'application/octet-stream')
            ->attach($sixMb, 'two.bin', 'application/octet-stream');

        try {
            $transport->send($email);
            $this->fail('Expected an AttachmentsTooLargeException.');
        } catch (AttachmentsTooLargeException $e) {
            $this->assertSame('attachments_too_large', $e->getErrorCode());
        }

        $this->assertCount(0, $history, 'No HTTP request may be made for an oversized send.');
    }

    public function test_multiple_recipients_with_attachments_fan_out_as_per_recipient_singles(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'a', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'b', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'c', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client, ['idempotency' => 'content']);

        $email = $this->email()
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Report')
            ->html('<p>Attached.</p>')
            ->attach('csv,data', 'report.csv', 'text/csv');

        $transport->send($email);

        $this->assertCount(3, $history);

        $keys = [];

        foreach ([0, 1, 2] as $i) {
            $request = $history[$i]['request'];
            $this->assertStringEndsWith('/send', $request->getUri()->getPath(), 'Attachments must never hit /send/batch.');

            $payload = $this->body($history, $i);
            $this->assertSame('report.csv', $payload['attachments'][0]['filename']);

            $keys[] = $request->getHeaderLine('Idempotency-Key');
        }

        $this->assertSame(
            ['to@example.com', 'cc@example.com', 'bcc@example.com'],
            array_map(fn (int $i): string => $this->body($history, $i)['to'], [0, 1, 2]),
        );

        // Each recipient gets its own content key — a shared key would make the
        // platform silently dedupe every recipient after the first.
        $this->assertCount(3, array_unique($keys));
        $this->assertNotContains('', $keys);
    }

    public function test_fan_out_derives_distinct_keys_from_an_explicit_override(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'a', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'b', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()
            ->to('a@example.com')
            ->cc('b@example.com')
            ->subject('S')
            ->html('<p>x</p>')
            ->attach('bytes', 'f.txt', 'text/plain');
        $email->getHeaders()->addTextHeader(RecadoHeaders::IDEMPOTENCY_KEY, 'order-42');

        $transport->send($email);

        $first = $history[0]['request']->getHeaderLine('Idempotency-Key');
        $second = $history[1]['request']->getHeaderLine('Idempotency-Key');

        $this->assertStringStartsWith('order-42:', $first);
        $this->assertStringStartsWith('order-42:', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_attachments_participate_in_the_content_idempotency_key(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => '1', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => '2', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => '3', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client, ['idempotency' => 'content']);

        $make = fn (string $content): Email => $this->email()
            ->to('jane@example.com')
            ->subject('Same')
            ->html('<p>same</p>')
            ->attach($content, 'file.txt', 'text/plain');

        // Same content + recipient + attachment → same key (retry dedup)...
        $transport->send($make('identical bytes'));
        $transport->send($make('identical bytes'));
        // ...while a different attachment body → a different key.
        $transport->send($make('different bytes'));

        $k1 = $history[0]['request']->getHeaderLine('Idempotency-Key');
        $k2 = $history[1]['request']->getHeaderLine('Idempotency-Key');
        $k3 = $history[2]['request']->getHeaderLine('Idempotency-Key');

        $this->assertStringStartsWith('txn_', $k1);
        $this->assertSame($k1, $k2);
        $this->assertNotSame($k1, $k3);
    }

    /**
     * A base Email with the mandatory From header set (Symfony requires it for
     * message conversion). Individual tests add recipients/subject/body.
     */
    private function email(): Email
    {
        return (new Email)->from('platform@example.com');
    }

    /**
     * @param array<string, mixed> $mailConfig
     */
    private function transport(RecadoClient $client, array $mailConfig = []): RecadoTransport
    {
        return new RecadoTransport($client, $mailConfig);
    }

    /**
     * Decode the JSON body of the request captured at the given history index.
     *
     * @param array<int, array{request: RequestInterface}> $history
     *
     * @return array<string, mixed>
     */
    private function body(array $history, int $index): array
    {
        return json_decode((string) $history[$index]['request']->getBody(), true);
    }
}
