<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_forwarded_proto_is_ignored_by_default(): void
    {
        $this->get('/up', ['X-Forwarded-Proto' => 'https'])->assertOk();

        $this->assertFalse(request()->isSecure());
    }

    public function test_forwarded_proto_is_trusted_when_configured(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->get('/up', ['X-Forwarded-Proto' => 'https'])->assertOk();

        $this->assertTrue(request()->isSecure());
    }

    public function test_env_value_is_parsed(): void
    {
        foreach (['*' => '*', '10.0.0.1, 10.0.0.2' => ['10.0.0.1', '10.0.0.2'], '' => null] as $env => $expected) {
            putenv("TRUSTED_PROXIES={$env}");
            $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $env;

            $this->assertSame($expected, (require config_path('trustedproxy.php'))['proxies']);
        }

        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    }
}
