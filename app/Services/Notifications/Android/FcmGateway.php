<?php

namespace App\Services\Notifications\Android;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FcmGateway
{
    public function problem(): ?string
    {
        if (! config('android.enabled')) {
            return 'Canal Android desactivado.';
        }
        if (! config('android.project_id')) {
            return 'Falta FIREBASE_PROJECT_ID.';
        }
        if (! $this->credentials()) {
            return 'Credenciales Firebase ausentes, inválidas o dentro del directorio público.';
        }

        return null;
    }

    public function send(string $token, array $payload): string
    {
        try {
            $credentials = $this->credentials();
            if (! $credentials) {
                return 'failed';
            }
            $key = 'android:fcm:oauth:'.hash('sha256', $credentials['client_email'].$credentials['private_key']);
            $access = Cache::remember($key, 3000, function () use ($credentials) {
                $encode = fn ($data) => rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
                $jwt = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode(['iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600]);
                if (! openssl_sign($jwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                    throw new \RuntimeException('Firma FCM fallida.');
                }
                $assertion = $jwt.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

                return Http::timeout(config('android.timeout'))->asForm()->post('https://oauth2.googleapis.com/token',
                    ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion])->throw()->json('access_token');
            });
            $r = Http::timeout(config('android.timeout'))->withToken($access)->post(
                'https://fcm.googleapis.com/v1/projects/'.rawurlencode(config('android.project_id')).'/messages:send',
                ['message' => ['token' => $token, 'data' => $payload, 'android' => ['priority' => 'HIGH', 'ttl' => '3600s']]]);
            if ($r->successful()) {
                return 'sent';
            }
            $code = collect($r->json('error.details', []))->pluck('errorCode')->filter()->first();
            if ($code === 'UNREGISTERED') {
                return 'expired';
            }
            if ($r->status() === 401) {
                Cache::forget($key);
            }

            return in_array($r->status(), [401, 429, 500, 502, 503, 504]) ? 'transient' : 'failed';
        } catch (\Throwable) {
            return 'transient';
        }
    }

    private function credentials(): ?array
    {
        $file = config('android.credentials');
        $path = $file ? realpath($file) : false;
        if (! $path || ! is_file($path) || str_starts_with(strtolower(str_replace('\\', '/', $path)), strtolower(str_replace('\\', '/', public_path())).'/')) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);

        return is_array($data) && ! empty($data['client_email']) && ! empty($data['private_key']) ? $data : null;
    }
}
