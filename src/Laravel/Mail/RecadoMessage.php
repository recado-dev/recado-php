<?php

declare(strict_types=1);

namespace Recado\Sdk\Laravel\Mail;

/**
 * Fluent builder for a transactional send from a notification's `toRecado()`
 * method (see {@see \Recado\Sdk\Laravel\Notifications\RecadoChannel}). Supports
 * inline subject/body/text sends and stored-template sends, an optional explicit
 * idempotency key and an optional explicit recipient that overrides the
 * notifiable's routing.
 */
final class RecadoMessage
{
    private ?string $subject = null;

    private ?string $html = null;

    private ?string $text = null;

    private ?string $template = null;

    /** @var array<string, mixed> */
    private array $variables = [];

    private ?string $idempotencyKey = null;

    private ?string $to = null;

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function html(string $html): static
    {
        $this->html = $html;

        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function template(string $slug): static
    {
        $this->template = $slug;

        return $this;
    }

    /**
     * @param array<string, mixed> $vars
     */
    public function variables(array $vars): static
    {
        $this->variables = $vars;

        return $this;
    }

    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function to(string $email): static
    {
        $this->to = $email;

        return $this;
    }

    /**
     * Render a Blade view into the HTML body. Degrades gracefully outside a
     * Laravel application (no view factory bound), leaving the body untouched so
     * the class stays usable without illuminate/view.
     *
     * @param array<string, mixed> $data
     */
    public function view(string $view, array $data = []): static
    {
        if (function_exists('app') && app()->bound('view')) {
            $this->html = (string) app('view')->make($view, $data)->render();
        }

        return $this;
    }

    /**
     * The explicit idempotency key, if one was set; otherwise null so the
     * channel's configured strategy decides.
     */
    public function explicitKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * The explicit recipient, if one was set; otherwise null so the channel
     * resolves it from the notifiable.
     */
    public function recipient(): ?string
    {
        return $this->to;
    }

    /**
     * The /send content payload (no `to`, no idempotency key). Either a template
     * payload or an inline subject/body one.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->template !== null) {
            return [
                'template' => $this->template,
                'variables' => $this->variables,
            ];
        }

        $payload = [
            'subject' => (string) $this->subject,
            'body' => (string) ($this->html ?? $this->text ?? ''),
        ];

        if ($this->text !== null) {
            $payload['text'] = $this->text;
        }

        if ($this->variables !== []) {
            $payload['variables'] = $this->variables;
        }

        return $payload;
    }
}
