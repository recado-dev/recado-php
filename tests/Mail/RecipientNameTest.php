<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests\Mail;

use Psr\Http\Message\RequestInterface;
use Recado\Sdk\Laravel\Mail\PayloadMapper;
use Recado\Sdk\Laravel\Mail\RecadoHeaders;
use Recado\Sdk\Laravel\Mail\RecadoTransport;
use Recado\Sdk\RecadoClient;
use Recado\Sdk\Tests\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * A recipient's display name is forwarded to /send as `name`, so the platform
 * can fill the first/last name of the contact it upserts. A message whose
 * recipients carry no display name keeps producing the pre-name payload and the
 * pre-name idempotency key.
 */
final class RecipientNameTest extends TestCase
{
    public function test_the_recipient_display_name_is_mapped(): void
    {
        $email = (new Email)
            ->to(new Address('ada@example.com', 'Ada Lovelace'))
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->assertSame(['name' => 'Ada Lovelace'], PayloadMapper::recipientName($email, 'ada@example.com'));
        // Matching is case-insensitive: envelope addresses may differ in case.
        $this->assertSame(['name' => 'Ada Lovelace'], PayloadMapper::recipientName($email, 'ADA@example.com'));
    }

    public function test_a_recipient_without_a_display_name_yields_nothing(): void
    {
        $email = (new Email)
            ->to('ada@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->assertSame([], PayloadMapper::recipientName($email, 'ada@example.com'));
        $this->assertSame([], PayloadMapper::recipientName($email, 'someone-else@example.com'));
        $this->assertSame([], PayloadMapper::recipientName($email, ''));
    }

    public function test_single_send_posts_the_display_name(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'msg-1', 'status' => 'queued']]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to(new Address('ada@example.com', 'Ada Lovelace'))
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->transport($client)->send($email);

        $payload = $this->body($history, 0);
        $this->assertSame('ada@example.com', $payload['to']);
        $this->assertSame('Ada Lovelace', $payload['name']);
    }

    public function test_a_single_send_without_a_display_name_stays_byte_identical(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'msg-1', 'status' => 'queued']]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to('ada@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->transport($client)->send($email);

        $this->assertSame(
            [
                'to' => 'ada@example.com',
                'subject' => 'Hello',
                'body' => '<p>Hi</p>',
                'from' => 'alex@example.com',
            ],
            $this->body($history, 0),
        );
    }

    public function test_batch_items_each_carry_their_own_display_name(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => [
                'messages' => [
                    ['index' => 0, 'status' => 'queued', 'id' => 'a'],
                    ['index' => 1, 'status' => 'queued', 'id' => 'b'],
                    ['index' => 2, 'status' => 'queued', 'id' => 'c'],
                ],
                'queued' => 3,
                'failed' => 0,
            ]]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to(new Address('ada@example.com', 'Ada Lovelace'))
            ->cc(new Address('grace@example.com', 'Grace Hopper'))
            ->bcc('anon@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->transport($client)->send($email);

        $messages = $this->body($history, 0)['messages'];

        $this->assertSame('Ada Lovelace', $messages[0]['name']);
        // Never the first To's name: each recipient gets its own.
        $this->assertSame('Grace Hopper', $messages[1]['name']);
        $this->assertArrayNotHasKey('name', $messages[2]);
    }

    public function test_attachment_fan_out_keeps_the_per_recipient_display_name(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'a', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'b', 'status' => 'queued']]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to(new Address('ada@example.com', 'Ada Lovelace'))
            ->cc(new Address('grace@example.com', 'Grace Hopper'))
            ->subject('Hello')
            ->html('<p>Hi</p>');
        $email->attach('report', 'report.txt', 'text/plain');

        $this->transport($client)->send($email);

        $this->assertSame('Ada Lovelace', $this->body($history, 0)['name']);
        $this->assertSame('Grace Hopper', $this->body($history, 1)['name']);
    }

    public function test_the_to_address_wins_over_a_repeated_bcc(): void
    {
        // The same address on two headers: To is searched first, so the
        // recipient keeps the name it was actually addressed with.
        $email = (new Email)
            ->to(new Address('ada@example.com', 'Ada Lovelace'))
            ->bcc(new Address('ada@example.com', 'Archive Copy'))
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $this->assertSame(['name' => 'Ada Lovelace'], PayloadMapper::recipientName($email, 'ada@example.com'));
    }

    public function test_an_explicit_idempotency_key_stays_per_recipient_on_the_fan_out(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'a', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'b', 'status' => 'queued']]),
        ], $history);

        $email = (new Email)
            ->from('alex@example.com')
            ->to(new Address('ada@example.com', 'Ada Lovelace'))
            ->cc(new Address('grace@example.com', 'Grace Hopper'))
            ->subject('Hello')
            ->html('<p>Hi</p>');
        $email->attach('report', 'report.txt', 'text/plain');
        $email->getHeaders()->addTextHeader(RecadoHeaders::IDEMPOTENCY_KEY, 'nightly-digest');

        $this->transport($client)->send($email);

        $keys = array_map(
            static fn (array $entry): string => $entry['request']->getHeaderLine('Idempotency-Key'),
            $history,
        );

        // Derived per recipient from the override, so the platform does not
        // dedupe every recipient after the first; the names still travel.
        $this->assertNotSame($keys[0], $keys[1]);

        foreach ($keys as $key) {
            $this->assertStringStartsWith('nightly-digest:', $key);
        }

        $this->assertSame('Ada Lovelace', $this->body($history, 0)['name']);
        $this->assertSame('Grace Hopper', $this->body($history, 1)['name']);
    }

    public function test_the_display_name_changes_the_idempotency_key(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(202, ['data' => ['id' => 'a', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'b', 'status' => 'queued']]),
            $this->jsonResponse(202, ['data' => ['id' => 'c', 'status' => 'queued']]),
        ], $history);

        $transport = $this->transport($client);

        $base = static fn (): Email => (new Email)
            ->from('alex@example.com')
            ->subject('Hello')
            ->html('<p>Hi</p>');

        $transport->send($base()->to('ada@example.com'));
        $transport->send($base()->to(new Address('ada@example.com', 'Ada Lovelace')));
        $transport->send($base()->to(new Address('ada@example.com', 'Ada L.')));

        $keys = array_map(
            static fn (array $entry): string => $entry['request']->getHeaderLine('Idempotency-Key'),
            $history,
        );

        $this->assertCount(3, array_unique($keys), 'Each display name must produce its own key.');
    }

    public function test_a_batch_display_name_changes_the_shared_key(): void
    {
        $history = [];
        $batchResponse = $this->jsonResponse(202, ['data' => [
            'messages' => [
                ['index' => 0, 'status' => 'queued', 'id' => 'a'],
                ['index' => 1, 'status' => 'queued', 'id' => 'b'],
            ],
            'queued' => 2,
            'failed' => 0,
        ]]);

        $client = $this->clientWithResponses([$batchResponse, $batchResponse], $history);

        $transport = $this->transport($client);

        $transport->send(
            (new Email)
                ->from('alex@example.com')
                ->to('ada@example.com')
                ->cc('grace@example.com')
                ->subject('Hello')
                ->html('<p>Hi</p>'),
        );
        $transport->send(
            (new Email)
                ->from('alex@example.com')
                ->to(new Address('ada@example.com', 'Ada Lovelace'))
                ->cc('grace@example.com')
                ->subject('Hello')
                ->html('<p>Hi</p>'),
        );

        $this->assertNotSame(
            $history[0]['request']->getHeaderLine('Idempotency-Key'),
            $history[1]['request']->getHeaderLine('Idempotency-Key'),
        );
    }

    private function transport(RecadoClient $client): RecadoTransport
    {
        return new RecadoTransport($client);
    }

    /**
     * @param  array<int, array{request: RequestInterface}>  $history
     * @return array<string, mixed>
     */
    private function body(array $history, int $index): array
    {
        return json_decode((string) $history[$index]['request']->getBody(), true);
    }
}
