<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Mail;

use Psr\Http\Message\RequestInterface;
use Recado\Sdk\Exception\ValidationException;
use Recado\Sdk\Laravel\Mail\PayloadMapper;
use Recado\Sdk\Laravel\Mail\RecadoHeaders;
use Recado\Sdk\Laravel\Mail\RecadoTransport;
use Recado\Sdk\RecadoClient;
use Recado\Sdk\Tests\Mail\Support\SpyLogger;
use Recado\Sdk\Tests\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The message's own From / From name / Reply-To are forwarded to /send as the
 * per-message sender override, on single sends and batch items alike. A message
 * without them keeps producing the pre-forwarding payload, so the project's
 * configured sender still applies server-side.
 */
final class SenderOverrideTest extends TestCase
{
    public function test_from_address_and_name_are_mapped(): void
    {
        $email = (new Email)
            ->from(new Address('alex@example.com', 'Alex'))
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $payload = PayloadMapper::base($email, []);

        $this->assertSame('alex@example.com', $payload['from']);
        $this->assertSame('Alex', $payload['from_name']);
        $this->assertArrayNotHasKey('reply_to', $payload);
    }

    public function test_from_without_a_display_name_omits_from_name(): void
    {
        $email = (new Email)
            ->from('alex@example.com')
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $payload = PayloadMapper::base($email, []);

        $this->assertSame('alex@example.com', $payload['from']);
        $this->assertArrayNotHasKey('from_name', $payload);
    }

    public function test_reply_to_is_mapped(): void
    {
        $email = (new Email)
            ->from('alex@example.com')
            ->replyTo(new Address('support@example.com', 'Support'))
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $payload = PayloadMapper::base($email, []);

        // Only the address is sent: /send takes a bare reply_to email.
        $this->assertSame('support@example.com', $payload['reply_to']);
    }

    public function test_a_message_without_a_sender_produces_the_unchanged_payload(): void
    {
        $email = (new Email)
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>')
            ->text('Hi');

        $payload = PayloadMapper::base($email, []);

        $this->assertSame(
            ['subject' => 'Hello', 'body' => '<p>Hi</p>', 'text' => 'Hi'],
            $payload,
        );
    }

    public function test_template_payloads_carry_the_sender_too(): void
    {
        $email = (new Email)
            ->from(new Address('alex@example.com', 'Alex'))
            ->replyTo('support@example.com')
            ->to('jane@example.com');
        $email->getHeaders()->addTextHeader(RecadoHeaders::TEMPLATE, 'welcome');

        $payload = PayloadMapper::base($email, []);

        $this->assertSame('welcome', $payload['template']);
        $this->assertSame('alex@example.com', $payload['from']);
        $this->assertSame('Alex', $payload['from_name']);
        $this->assertSame('support@example.com', $payload['reply_to']);
    }

    public function test_only_the_first_address_of_each_field_is_sent(): void
    {
        $logger = new SpyLogger;

        $email = (new Email)
            ->from('alex@example.com', 'second@example.com')
            ->replyTo('support@example.com', 'other@example.com')
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $payload = PayloadMapper::base($email, [], $logger);

        $this->assertSame('alex@example.com', $payload['from']);
        $this->assertSame('support@example.com', $payload['reply_to']);
        $this->assertTrue($logger->has('debug'));
    }

    public function test_forward_from_disabled_drops_the_sender_and_logs(): void
    {
        $logger = new SpyLogger;

        $email = (new Email)
            ->from(new Address('alex@example.com', 'Alex'))
            ->replyTo('support@example.com')
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $payload = PayloadMapper::base($email, ['forward_from' => false], $logger);

        $this->assertSame(['subject' => 'Hello', 'body' => '<p>Hi</p>'], $payload);
        $this->assertTrue($logger->has('debug'));
    }

    public function test_single_send_posts_the_sender_override(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'msg-1', 'status' => 'queued']]),
        ], $history);

        $email = (new Email)
            ->from(new Address('alex@example.com', 'Alex'))
            ->replyTo('support@example.com')
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->transport($client)->send($email);

        $payload = $this->body($history, 0);
        $this->assertSame('https://recado.example.com/api/v1/send', (string) $history[0]['request']->getUri());
        $this->assertSame('alex@example.com', $payload['from']);
        $this->assertSame('Alex', $payload['from_name']);
        $this->assertSame('support@example.com', $payload['reply_to']);
    }

    public function test_batch_items_carry_the_sender_override(): void
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

        $email = (new Email)
            ->from(new Address('alex@example.com', 'Alex'))
            ->replyTo('support@example.com')
            ->to('jane@example.com')
            ->cc('john@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->transport($client)->send($email);

        $payload = $this->body($history, 0);
        $this->assertSame('https://recado.example.com/api/v1/send/batch', (string) $history[0]['request']->getUri());

        foreach ($payload['messages'] as $message) {
            $this->assertSame('alex@example.com', $message['from']);
            $this->assertSame('Alex', $message['from_name']);
            $this->assertSame('support@example.com', $message['reply_to']);
        }
    }

    public function test_the_same_content_from_two_senders_gets_distinct_idempotency_keys(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'a', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'b', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client);

        foreach (['alex@example.com', 'hello@example.com'] as $sender) {
            $transport->send(
                (new Email)
                    ->from($sender)
                    ->to('jane@example.com')
                    ->subject('Hello')
                    ->html('<p>Hi</p>'),
            );
        }

        $this->assertNotSame(
            $history[0]['request']->getHeaderLine('Idempotency-Key'),
            $history[1]['request']->getHeaderLine('Idempotency-Key'),
        );
    }

    public function test_an_unverified_sending_domain_raises_an_actionable_exception(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The from address must belong to a verified sending domain.',
                'code' => 'sending_domain_not_verified',
            ]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        try {
            $this->transport($client)->send($email);
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('alex@example.com', $e->getMessage());
            $this->assertStringContainsString('example.com', $e->getMessage());
            $this->assertStringContainsString('Sending domains', $e->getMessage());
            $this->assertStringContainsString('RECADO_MAIL_FORWARD_FROM=false', $e->getMessage());
            $this->assertInstanceOf(ValidationException::class, $e->getPrevious());
        }
    }

    public function test_another_422_code_keeps_the_generic_message(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'Monthly quota exceeded.',
                'code' => 'quota_exceeded',
            ]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to('jane@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        try {
            $this->transport($client)->send($email);
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('rejected the send for jane@example.com', $e->getMessage());
            $this->assertStringNotContainsString('RECADO_MAIL_FORWARD_FROM', $e->getMessage());
        }
    }

    public function test_a_per_item_batch_sender_rejection_carries_the_hint(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'messages' => [
                    ['index' => 0, 'status' => 'failed', 'code' => 'sending_domain_not_verified'],
                    ['index' => 1, 'status' => 'queued', 'id' => 'b'],
                ],
                'queued' => 1,
                'failed' => 1,
            ]]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to('jane@example.com')
            ->cc('john@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        try {
            $this->transport($client)->send($email);
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('sending_domain_not_verified', $e->getMessage());
            $this->assertStringContainsString('the domain "example.com"', $e->getMessage());
            $this->assertStringContainsString('RECADO_MAIL_FORWARD_FROM=false', $e->getMessage());
        }
    }

    public function test_a_whole_batch_sender_rejection_carries_the_hint(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(422, [
                'message' => 'The from address must belong to a verified sending domain.',
                'code' => 'sending_domain_not_verified',
            ]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to('jane@example.com')
            ->cc('john@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        try {
            $this->transport($client)->send($email);
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('alex@example.com', $e->getMessage());
            $this->assertStringContainsString('RECADO_MAIL_FORWARD_FROM=false', $e->getMessage());
        }
    }

    private function transport(RecadoClient $client): RecadoTransport
    {
        return new RecadoTransport($client);
    }

    /**
     * Decode the JSON body of the request captured at the given history index.
     *
     * @param  array<int, array{request: RequestInterface}>  $history
     * @return array<string, mixed>
     */
    private function body(array $history, int $index): array
    {
        return json_decode((string) $history[$index]['request']->getBody(), true);
    }
}
