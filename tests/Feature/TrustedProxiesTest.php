<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
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
        putenv('TRUSTED_PROXIES=*');
        $this->refreshApplication();

        try {
            $this->get('/up', ['X-Forwarded-Proto' => 'https'])->assertOk();

            $this->assertTrue(request()->isSecure());
        } finally {
            putenv('TRUSTED_PROXIES');
            Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        }
    }
}
