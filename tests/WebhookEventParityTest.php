<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Webhooks\WebhookEvent;

/**
 * Guards the SDK event catalog against the platform's own enum. The app file
 * only exists inside the monorepo; in the split recado-php mirror the test
 * skips itself.
 */
final class WebhookEventParityTest extends TestCase
{
    public function test_the_catalog_matches_the_platform_enum_minus_ping(): void
    {
        $path = dirname(__DIR__, 3).'/app/Enums/WebhookEvent.php';

        if (! is_file($path)) {
            $this->markTestSkipped('The platform enum is only available inside the monorepo.');
        }

        preg_match_all("/case\s+\w+\s*=\s*'([^']+)'\s*;/", (string) file_get_contents($path), $matches);

        $platform = array_values(array_diff($matches[1], ['ping']));

        $this->assertNotEmpty($platform);
        $this->assertSame($platform, WebhookEvent::values());
    }

    public function test_contact_list_confirmed_is_subscribable(): void
    {
        $this->assertSame('contact.list_confirmed', WebhookEvent::ContactListConfirmed->value);
    }
}
