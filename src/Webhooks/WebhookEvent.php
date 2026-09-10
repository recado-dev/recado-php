<?php

declare(strict_types=1);

namespace Recado\Sdk\Webhooks;

/**
 * The catalog of subscribable outbound webhook events.
 *
 * `ping` is deliberately absent: it is the dashboard's test-button event and
 * cannot be subscribed to. Values are the exact strings the API accepts, so a
 * plain string is always a valid alternative to a case of this enum.
 */
enum WebhookEvent: string
{
    case ContactSubscribed = 'contact.subscribed';
    case ContactUnsubscribed = 'contact.unsubscribed';
    case ContactBounced = 'contact.bounced';
    case ContactComplained = 'contact.complained';
    case ContactTagged = 'contact.tagged';
    case CampaignScheduled = 'campaign.scheduled';
    case CampaignStarted = 'campaign.started';
    case CampaignSent = 'campaign.sent';
    case CampaignFailed = 'campaign.failed';
    case CampaignCancelled = 'campaign.cancelled';
    case MessageDelivered = 'message.delivered';
    case ReaderSubscribed = 'reader.subscribed';
    case ReaderSubscriptionCanceled = 'reader.subscription_canceled';
    case IdentityBreakerTripped = 'identity.breaker_tripped';
    case IdentityWarmupOverflow = 'identity.warmup_overflow';

    /**
     * Every subscribable event value, in catalog order.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $event): string => $event->value, self::cases());
    }
}
