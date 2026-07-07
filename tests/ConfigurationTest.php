<?php

declare(strict_types=1);

namespace Recado\Sdk\Tests;

use Recado\Sdk\Exception\RecadoConfigurationException;
use Recado\Sdk\Exception\RecadoException;
use Recado\Sdk\RecadoClient;

/**
 * Pins the fail-loud configuration guard: the client refuses to construct with
 * a missing/empty/placeholder base URL or an empty API token, so a
 * misconfigured consumer never silently sends to a dead host.
 */
final class ConfigurationTest extends TestCase
{
    public function test_empty_base_url_throws_a_configuration_exception(): void
    {
        $this->expectException(RecadoConfigurationException::class);
        $this->expectExceptionMessage('RECADO_BASE_URL is not configured');

        new RecadoClient('', 'test-token');
    }

    public function test_whitespace_only_base_url_throws(): void
    {
        $this->expectException(RecadoConfigurationException::class);

        new RecadoClient('   ', 'test-token');
    }

    public function test_placeholder_base_url_throws(): void
    {
        $this->expectException(RecadoConfigurationException::class);
        $this->expectExceptionMessage('placeholder');

        new RecadoClient('https://'.RecadoClient::PLACEHOLDER_BASE_URL_HOST.'/api/v1', 'test-token');
    }

    public function test_placeholder_base_url_without_scheme_also_throws(): void
    {
        $this->expectException(RecadoConfigurationException::class);

        new RecadoClient(RecadoClient::PLACEHOLDER_BASE_URL_HOST.'/api/v1', 'test-token');
    }

    public function test_legacy_mosaiqo_base_url_throws(): void
    {
        // The pre-rebrand mosaiqo/mailer-php v1.x hosted default. The host is
        // being decommissioned, so a stale config must fail loudly instead of
        // POSTing into the void.
        $this->expectException(RecadoConfigurationException::class);
        $this->expectExceptionMessage('decommissioned');

        new RecadoClient('https://'.RecadoClient::LEGACY_BASE_URL_HOST.'/api/v1', 'test-token');
    }

    public function test_legacy_mosaiqo_base_url_without_scheme_also_throws(): void
    {
        $this->expectException(RecadoConfigurationException::class);

        new RecadoClient(RecadoClient::LEGACY_BASE_URL_HOST.'/api/v1', 'test-token');
    }

    public function test_empty_token_throws_a_configuration_exception(): void
    {
        $this->expectException(RecadoConfigurationException::class);
        $this->expectExceptionMessage('RECADO_API_TOKEN is not configured');

        new RecadoClient('https://app.example.com/api/v1', '');
    }

    public function test_whitespace_only_token_throws(): void
    {
        $this->expectException(RecadoConfigurationException::class);

        new RecadoClient('https://app.example.com/api/v1', '   ');
    }

    public function test_configuration_exception_is_a_recado_exception(): void
    {
        // Consumers catching the SDK base type still catch misconfiguration.
        $this->expectException(RecadoException::class);

        new RecadoClient('', 'test-token');
    }

    public function test_a_valid_configuration_constructs_without_throwing(): void
    {
        $client = new RecadoClient('https://app.example.com/api/v1', 'test-token');

        $this->assertInstanceOf(RecadoClient::class, $client);
    }
}
