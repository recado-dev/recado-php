<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Mail;

use Recado\Sdk\Exception\UnsupportedFeatureException;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Laravel\Events\MessageSuppressed;
use Recado\Sdk\Laravel\Mail\RecadoHeaders;
use Recado\Sdk\Laravel\Mail\RecadoTransport;
use Recado\Sdk\RecadoClient;
use Recado\Sdk\Tests\Mail\Support\SpyDispatcher;
use Recado\Sdk\Tests\Mail\Support\SpyLogger;
use Recado\Sdk\Tests\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

final class RecadoTransportTest extends TestCase
{
    public function test_single_recipient_posts_to_send_without_from_or_reply_to(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'msg-1', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()
            ->to('jane@example.com')
            ->from('sender@example.com')
            ->replyTo('reply@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>')
            ->text('Hi');

        $transport->send($email);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://recado.example.com/api/v1/send', (string) $request->getUri());

        $payload = $this->body($history, 0);
        $this->assertSame('jane@example.com', $payload['to']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('<p>Hi</p>', $payload['body']);
        $this->assertSame('Hi', $payload['text']);
        $this->assertArrayNotHasKey('from', $payload);
        $this->assertArrayNotHasKey('reply_to', $payload);
    }

    public function test_multiple_recipients_post_to_batch_with_one_message_each(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'messages' => [
                    ['index' => 0, 'status' => 'queued', 'id' => 'a'],
                    ['index' => 1, 'status' => 'queued', 'id' => 'b'],
                ],
                'queued' => 2,
                'failed' => 0,
            ]]),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->subject('News')
            ->html('<p>Body</p>');

        $transport->send($email);

        $this->assertSame('https://recado.example.com/api/v1/send/batch', (string) $history[0]['request']->getUri());

        $payload = $this->body($history, 0);
        $this->assertCount(2, $payload['messages']);
        $this->assertSame('to@example.com', $payload['messages'][0]['to']);
        $this->assertSame('cc@example.com', $payload['messages'][1]['to']);
        $this->assertSame('News', $payload['messages'][0]['subject']);
        $this->assertSame('<p>Body</p>', $payload['messages'][1]['body']);
    }

    public function test_attachments_fail_mode_throws_unsupported_feature_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([], $history);
        $transport = $this->transport($client, ['attachments' => 'fail']);

        $email = $this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>')
            ->attach('binary', 'file.txt', 'text/plain');

        $this->expectException(UnsupportedFeatureException::class);

        $transport->send($email);
    }

    public function test_attachments_ignore_mode_sends_and_warns(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'msg-1', 'status' => 'queued']]),
        ], $history);

        $logger = new SpyLogger();
        $transport = $this->transport($client, ['attachments' => 'ignore'], null, $logger);

        $email = $this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>')
            ->attach('binary', 'file.txt', 'text/plain');

        $transport->send($email);

        $this->assertCount(1, $history);
        $this->assertTrue($logger->has('warning'));
    }

    public function test_recipient_suppressed_does_not_throw_and_dispatches_event(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Recipient is suppressed.', 'code' => 'recipient_suppressed']),
        ], $history);

        $events = new SpyDispatcher();
        $transport = $this->transport($client, [], $events);

        $email = $this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>');

        $transport->send($email);

        $suppressed = $events->ofType(MessageSuppressed::class);
        $this->assertCount(1, $suppressed);
        $this->assertSame('jane@example.com', $suppressed[0]->recipient);
        $this->assertSame('recipient_suppressed', $suppressed[0]->reason);
    }

    public function test_batch_suppressed_item_dispatches_event_without_failing(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'messages' => [
                    ['index' => 0, 'status' => 'queued', 'id' => 'a'],
                    ['index' => 1, 'status' => 'suppressed', 'code' => 'recipient_suppressed'],
                ],
                'queued' => 1,
                'failed' => 0,
            ]]),
        ], $history);

        $events = new SpyDispatcher();
        $transport = $this->transport($client, [], $events);

        $email = $this->email()
            ->to('ok@example.com')
            ->cc('blocked@example.com')
            ->subject('S')
            ->html('<p>x</p>');

        $transport->send($email);

        $suppressed = $events->ofType(MessageSuppressed::class);
        $this->assertCount(1, $suppressed);
        $this->assertSame('blocked@example.com', $suppressed[0]->recipient);
    }

    public function test_quota_exceeded_throws_transport_exception_with_previous(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Monthly quota exceeded.', 'code' => 'quota_exceeded']),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>');

        try {
            $transport->send($email);
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $previous = $e->getPrevious();
            $this->assertInstanceOf(ValidationException::class, $previous);
            $this->assertSame('quota_exceeded', $previous->getErrorCode());
        }
    }

    public function test_sending_domain_not_verified_throws_transport_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, ['message' => 'Sending domain not verified.', 'code' => 'sending_domain_not_verified']),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>');

        $this->expectException(TransportException::class);

        $transport->send($email);
    }

    public function test_batch_hard_failure_throws_transport_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'messages' => [
                    ['index' => 0, 'status' => 'queued', 'id' => 'a'],
                    ['index' => 1, 'status' => 'failed', 'code' => 'quota_exceeded'],
                ],
                'queued' => 1,
                'failed' => 1,
            ]]),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()
            ->to('ok@example.com')
            ->cc('over@example.com')
            ->subject('S')
            ->html('<p>x</p>');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/over@example.com/');

        $transport->send($email);
    }

    public function test_content_idempotency_key_is_present_and_stable(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => '1', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => '2', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client, ['idempotency' => 'content']);

        $make = fn (): Email => $this->email()->to('jane@example.com')->subject('Stable')->html('<p>same</p>');

        $transport->send($make());
        $transport->send($make());

        $first = $history[0]['request']->getHeaderLine('Idempotency-Key');
        $second = $history[1]['request']->getHeaderLine('Idempotency-Key');

        $this->assertNotSame('', $first);
        $this->assertSame($first, $second);
        $this->assertStringStartsWith('txn_', $first);
    }

    public function test_content_idempotency_key_differs_per_recipient(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => '1', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => '2', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client, ['idempotency' => 'content']);

        // Identical content to two different recipients MUST get distinct keys —
        // otherwise the platform silently dedupes and the second never sends.
        $transport->send($this->email()->to('a@example.com')->subject('Same')->html('<p>same</p>'));
        $transport->send($this->email()->to('b@example.com')->subject('Same')->html('<p>same</p>'));

        $first = $history[0]['request']->getHeaderLine('Idempotency-Key');
        $second = $history[1]['request']->getHeaderLine('Idempotency-Key');

        $this->assertStringStartsWith('txn_', $first);
        $this->assertStringStartsWith('txn_', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_batch_idempotency_key_depends_on_the_recipient_list(): void
    {
        $history = [];
        $batch = fn (int $n): mixed => $this->jsonResponse(202, ['data' => [
            'messages' => array_map(
                static fn (int $i): array => ['index' => $i, 'status' => 'queued', 'id' => (string) $i],
                range(0, $n - 1),
            ),
            'queued' => $n,
            'failed' => 0,
        ]]);
        $client = $this->clientWithResponses([$batch(2), $batch(2), $batch(2)], $history);

        $transport = $this->transport($client, ['idempotency' => 'content']);

        // Same content + same recipient set (order swapped) → same key (retry dedup);
        // same content + a different recipient set → distinct key.
        $transport->send($this->email()->to('a@example.com')->cc('b@example.com')->subject('B')->html('<p>x</p>'));
        $transport->send($this->email()->to('b@example.com')->cc('a@example.com')->subject('B')->html('<p>x</p>'));
        $transport->send($this->email()->to('c@example.com')->cc('d@example.com')->subject('B')->html('<p>x</p>'));

        $k1 = $history[0]['request']->getHeaderLine('Idempotency-Key');
        $k2 = $history[1]['request']->getHeaderLine('Idempotency-Key');
        $k3 = $history[2]['request']->getHeaderLine('Idempotency-Key');

        $this->assertSame($k1, $k2);
        $this->assertNotSame($k1, $k3);
    }

    public function test_explicit_idempotency_header_is_respected(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => '1', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>');
        $email->getHeaders()->addTextHeader(RecadoHeaders::IDEMPOTENCY_KEY, 'order-42');

        $transport->send($email);

        $this->assertSame('order-42', $history[0]['request']->getHeaderLine('Idempotency-Key'));
    }

    public function test_random_idempotency_keys_differ_between_sends(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => '1', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => '2', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client, ['idempotency' => 'random']);

        $transport->send($this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>'));
        $transport->send($this->email()->to('jane@example.com')->subject('S')->html('<p>x</p>'));

        $first = $history[0]['request']->getHeaderLine('Idempotency-Key');
        $second = $history[1]['request']->getHeaderLine('Idempotency-Key');

        $this->assertNotSame('', $first);
        $this->assertNotSame('', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_template_header_builds_template_payload(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => '1', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client);

        $email = $this->email()->to('jane@example.com')->subject('ignored')->html('<p>ignored</p>');
        $email->getHeaders()->addTextHeader(RecadoHeaders::TEMPLATE, 'welcome');
        $email->getHeaders()->addTextHeader(RecadoHeaders::VARIABLES, (string) json_encode(['first_name' => 'Jane']));

        $transport->send($email);

        $payload = $this->body($history, 0);
        $this->assertSame('welcome', $payload['template']);
        $this->assertSame(['first_name' => 'Jane'], $payload['variables']);
        $this->assertArrayNotHasKey('subject', $payload);
        $this->assertArrayNotHasKey('body', $payload);
        $this->assertArrayNotHasKey('text', $payload);
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
    private function transport(
        RecadoClient $client,
        array $mailConfig = [],
        ?SpyDispatcher $events = null,
        ?SpyLogger $logger = null,
    ): RecadoTransport {
        return new RecadoTransport($client, $mailConfig, $events, $logger);
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
