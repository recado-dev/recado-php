<?php

declare(strict_types=1);

namespace Recado\Sdk\Dto;

/**
 * A waiting list: a hosted pre-launch signup page (`/w/{slug}`) with referral
 * positions.
 *
 * Authoring stays in the dashboard — creating a waitlist also creates its
 * dedicated contact list and claims a GLOBALLY unique public slug — so the SDK
 * exposes the read side plus the one irreversible action, `launch()`.
 *
 * `stats` is always present on `get()` and `launch()`, and opt-in on the
 * listing (`include=stats`); it is null otherwise. `publicUrl` is resolved on
 * the project's canonical public host (a verified custom domain, else the
 * platform subdomain), never on the API host.
 */
final readonly class Waitlist
{
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $slug,
        public ?string $title,
        public ?string $description,
        public ?string $status,
        public ?bool $showCount,
        public ?bool $referralsEnabled,
        public ?string $postLaunchRedirectUrl,
        public ?string $publicUrl,
        public ?int $listId,
        public ?int $launchCampaignId,
        public ?int $membersCount,
        public ?string $launchedAt,
        public ?string $createdAt,
        public ?WaitlistStats $stats,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (int) $data['id'] : null,
            name: isset($data['name']) ? (string) $data['name'] : null,
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            title: isset($data['title']) ? (string) $data['title'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            showCount: isset($data['show_count']) ? (bool) $data['show_count'] : null,
            referralsEnabled: isset($data['referrals_enabled']) ? (bool) $data['referrals_enabled'] : null,
            postLaunchRedirectUrl: isset($data['post_launch_redirect_url'])
                ? (string) $data['post_launch_redirect_url']
                : null,
            publicUrl: isset($data['public_url']) ? (string) $data['public_url'] : null,
            listId: isset($data['list_id']) ? (int) $data['list_id'] : null,
            launchCampaignId: isset($data['launch_campaign_id']) ? (int) $data['launch_campaign_id'] : null,
            membersCount: isset($data['members_count']) ? (int) $data['members_count'] : null,
            launchedAt: isset($data['launched_at']) ? (string) $data['launched_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            stats: is_array($data['stats'] ?? null) ? WaitlistStats::fromArray($data['stats']) : null,
        );
    }

    /**
     * Whether the waitlist has launched. There is no unlaunch, here or in the
     * dashboard, so this is a one-way flag.
     */
    public function isLaunched(): bool
    {
        return $this->status === 'launched';
    }
}
