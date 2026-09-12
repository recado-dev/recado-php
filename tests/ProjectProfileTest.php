<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

/**
 * GET /project — who this API key acts as.
 */
final class ProjectProfileTest extends TestCase
{
    public function test_it_maps_the_profile_of_a_production_key(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'name' => 'Acme',
                'uuid' => '6f0b1e9c-2a1d-4d4e-9a3f-0b7c4e2f1a55',
                'sandbox' => false,
                'sandbox_twin' => [
                    'name' => 'Acme (Test)',
                    'uuid' => '1d4c5e7a-9b02-4f31-8c6d-2a5b9e0f3c11',
                ],
                'default_from' => ['email' => 'hello@acme.com', 'name' => 'Acme'],
                'default_locale' => 'en',
                'app_locales' => ['en', 'es'],
                'double_opt_in_enabled' => true,
                'marketing_frequency_cap' => 3,
                'archive' => [
                    'enabled' => true,
                    'slug' => 'acme',
                    'url' => 'https://acme.recado.dev/n/acme',
                ],
                'public_url' => 'https://acme.recado.dev',
                'api_base_url' => 'https://api.recado.dev/v1',
            ]]),
        ], $history);

        $profile = $client->project()->get();

        $this->assertFalse($profile->isSandbox());
        $this->assertSame('Acme (Test)', $profile->sandboxTwin->name);
        $this->assertSame('hello@acme.com', $profile->defaultFromEmail);
        $this->assertSame(['en', 'es'], $profile->appLocales);
        $this->assertTrue($profile->doubleOptInEnabled);
        $this->assertSame(3, $profile->marketingFrequencyCap);
        $this->assertTrue($profile->archiveEnabled);
        $this->assertSame('https://acme.recado.dev/n/acme', $profile->archiveUrl);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/project', $request->getUri()->getPath());
    }

    public function test_a_sandbox_key_reports_itself_and_has_no_twin_of_its_own(): void
    {
        $history = [];
        $client = $this->clientWithResponses([
            $this->jsonResponse(200, ['data' => [
                'name' => 'Acme (Test)',
                'uuid' => '1d4c5e7a-9b02-4f31-8c6d-2a5b9e0f3c11',
                'sandbox' => true,
                'sandbox_twin' => null,
                'default_from' => ['email' => 'hello@acme.com', 'name' => 'Acme'],
                'default_locale' => 'en',
                'app_locales' => ['en'],
                'double_opt_in_enabled' => false,
                // null is the column's own "unlimited" value.
                'marketing_frequency_cap' => null,
                'archive' => ['enabled' => false, 'slug' => null, 'url' => null],
                'public_url' => 'https://acme-test.recado.dev',
                'api_base_url' => 'https://api.recado.dev/v1',
            ]]),
        ], $history);

        $profile = $client->project()->get();

        // This is the whole point of the endpoint: telling a test credential
        // from a production one BEFORE writing anything.
        $this->assertTrue($profile->isSandbox());
        $this->assertNull($profile->sandboxTwin);
        $this->assertNull($profile->marketingFrequencyCap);
        $this->assertFalse($profile->archiveEnabled);
        $this->assertNull($profile->archiveUrl);
    }
}
