<?php

namespace Tests\Unit;

use Tests\TestCase;

class GatewaySignatureVectorTest extends TestCase
{
    public function test_php_signature_matches_gateway_vector(): void
    {
        $body = '{"imei":"867250300000001","request_id":"request-1"}';
        $canonical = implode("\n", [
            '1784131200',
            'vector-001',
            'POST',
            '/commands/reboot',
            hash('sha256', $body),
        ]);

        $this->assertSame(
            'c4e71564c22318f128758f34351e4d762ac7d60edca5befeef982487e85dc334',
            hash_hmac('sha256', $canonical, '0123456789abcdef0123456789abcdef'),
        );
    }
}
