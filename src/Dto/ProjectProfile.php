<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * Who this API key acts as, and the defaults every send inherits
 * (GET `/project`).
 *
 * The most useful field is `sandbox`: it is how a client tells a TEST
 * credential from a production one BEFORE it writes anything. Available in a
 * sandbox on purpose — it is how a sandbox key confirms it is one.
 *
 * Nothing here is a secret: no provider credentials, no API keys, no billing
 * state. Read-only by design — there is no `PATCH /project`, because project
 * settings are a dashboard decision.
 */
final readonly class ProjectProfile
{
    /**
     * @param  array<int, string>  $appLocales  The UI locales the public pages can render.
     */
    public function __construct(
        public ?string $name,
        public ?string $uuid,
        public bool $sandbox,
        public ?ProjectTwin $sandboxTwin,
        public ?string $defaultFromEmail,
        public ?string $defaultFromName,
        public ?string $defaultLocale,
        public array $appLocales,
        public bool $doubleOptInEnabled,
        public ?int $marketingFrequencyCap,
        public bool $archiveEnabled,
        public ?string $archiveSlug,
        public ?string $archiveUrl,
        public ?string $publicUrl,
        public ?string $apiBaseUrl,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $from = is_array($data['default_from'] ?? null) ? $data['default_from'] : [];
        $archive = is_array($data['archive'] ?? null) ? $data['archive'] : [];

        $locales = [];

        foreach ($data['app_locales'] ?? [] as $locale) {
            if (is_scalar($locale)) {
                $locales[] = (string) $locale;
            }
        }

        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            uuid: isset($data['uuid']) ? (string) $data['uuid'] : null,
            sandbox: (bool) ($data['sandbox'] ?? false),
            sandboxTwin: is_array($data['sandbox_twin'] ?? null)
                ? ProjectTwin::fromArray($data['sandbox_twin'])
                : null,
            defaultFromEmail: isset($from['email']) ? (string) $from['email'] : null,
            defaultFromName: isset($from['name']) ? (string) $from['name'] : null,
            defaultLocale: isset($data['default_locale']) ? (string) $data['default_locale'] : null,
            appLocales: $locales,
            doubleOptInEnabled: (bool) ($data['double_opt_in_enabled'] ?? false),
            // null is the column's own "no cap" value, so it is kept as null
            // rather than collapsed into a 0.
            marketingFrequencyCap: isset($data['marketing_frequency_cap'])
                ? (int) $data['marketing_frequency_cap']
                : null,
            archiveEnabled: (bool) ($archive['enabled'] ?? false),
            archiveSlug: isset($archive['slug']) ? (string) $archive['slug'] : null,
            archiveUrl: isset($archive['url']) ? (string) $archive['url'] : null,
            publicUrl: isset($data['public_url']) ? (string) $data['public_url'] : null,
            apiBaseUrl: isset($data['api_base_url']) ? (string) $data['api_base_url'] : null,
        );
    }

    /**
     * Whether this credential belongs to a sandbox twin, where every send is
     * intercepted and nothing leaves the platform.
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }
}
