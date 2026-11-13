<?php

namespace Tests\Feature\Notifications;

use App\Services\Notifications\Android\FcmGateway;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmGatewayTest extends TestCase
{
    private string $credentials;

    private string $openssl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openssl = tempnam(storage_path('framework/testing'), 'openssl-test-');
        file_put_contents($this->openssl, "[req]\ndistinguished_name=dn\n[dn]\n");
        $key = openssl_pkey_new(['config' => $this->openssl, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem, null, ['config' => $this->openssl]);
        $this->credentials = tempnam(storage_path('framework/testing'), 'fcm-test-');
        file_put_contents($this->credentials, json_encode(['client_email' => 'test@example.invalid', 'private_key' => $pem]));
        config(['android.enabled' => true, 'android.project_id' => 'test-project', 'android.credentials' => $this->credentials]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (isset($this->credentials)) {
            unlink($this->credentials);
        } if (isset($this->openssl)) {
            unlink($this->openssl);
        } parent::tearDown();
    }

    public function test_oauth_and_generic_data_use_http_v1(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access']), 'fcm.googleapis.com/*' => Http::response(['name' => 'accepted'])]);
        $gateway = new FcmGateway;
        $this->assertNull($gateway->problem());
        $this->assertSame('sent', $gateway->send('test-token', ['version' => '1', 'notice_id' => 'opaque', 'binding' => 'binding']));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/projects/test-project/messages:send') && $r['message']['data']['notice_id'] === 'opaque' && ! isset($r['message']['notification']));
    }

    public function test_error_mapping_preserves_retriable_and_invalid_tokens(): void
    {
        foreach ([503 => 'transient', 429 => 'transient', 400 => 'failed', 404 => 'expired'] as $status => $expected) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'test-access']),
                'fcm.googleapis.com/*' => Http::response(['error' => ['details' => [['errorCode' => $status === 404 ? 'UNREGISTERED' : 'OTHER']]]], $status)]);
            $this->assertSame($expected, (new FcmGateway)->send('token', ['version' => '1']));
        }
    }

    public function test_disabled_and_public_credentials_are_rejected(): void
    {
        config(['android.enabled' => false]);
        $this->assertNotNull((new FcmGateway)->problem());
        config(['android.enabled' => true, 'android.credentials' => public_path('manifest.json')]);
        $this->assertNotNull((new FcmGateway)->problem());
    }
}
