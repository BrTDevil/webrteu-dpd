<?php

declare(strict_types=1);

namespace WeBRTeu\Dpd;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use WeBRTeu\Dpd\Exceptions\DpdException;
use WeBRTeu\Shipping\Support\Redactor;

/**
 * HTTP brut spre DPD România v1: fără token, userName/password/language (+ clientSystemId) în fiecare corp POST.
 * Corpurile conțin parola: se loghează doar după redact(). Fără retry.
 */
class DpdClient
{
    /**
     * Trimite cererea și întoarce răspunsul brut (JSON, PDF sau CSV, după Content-Type).
     *
     * @param  array<string, mixed>  $body
     */
    public function send(string $path, array $body = []): Response
    {
        $payload = $this->credentials() + $body;
        $response = Http::timeout(Config::integer('dpd.timeout', 20))
            ->asJson()
            ->post(rtrim(Config::string('dpd.base_url', 'https://api.dpd.ro/v1/'), '/').'/'.ltrim($path, '/'), $payload);

        $error = $this->isJson($response) ? data_get($response->json(), 'error') : null;

        if ($response->failed() || is_array($error)) {
            Log::warning('dpd.request_failed', [
                'path' => $path,
                'status' => $response->status(),
                'request' => $this->redact($payload),
                'error' => is_array($error) ? $error : $response->body(),
            ]);

            throw DpdException::fromError($path, is_array($error) ? $error : ['message' => sprintf('HTTP %d', $response->status())]);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function json(string $path, array $body = []): array
    {
        $json = $this->send($path, $body)->json();

        return is_array($json) ? $json : [];
    }

    public function isJson(Response $response): bool
    {
        $type = strtolower($response->header('Content-Type'));

        return ! str_contains($type, 'pdf') && ! str_contains($type, 'csv') && is_array($response->json());
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function redact(array $payload): array
    {
        return Redactor::redact($payload, ['userName']);
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $credentials = [
            'userName' => Config::string('dpd.username', ''),
            'password' => Config::string('dpd.password', ''),
            'language' => Config::string('dpd.language', 'RO'),
        ];

        $system = Config::get('dpd.client_system_id');

        if (is_numeric($system)) {
            $credentials['clientSystemId'] = (int) $system;
        }

        return $credentials;
    }
}
