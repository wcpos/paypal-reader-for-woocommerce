<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader\Services;

use WCPOS\WooCommercePOS\PayPalReader\Logger;
use WCPOS\WooCommercePOS\PayPalReader\Settings;

/**
 * Thin HTTP client for Zettle's OAuth2 token endpoint and Reader Connect REST
 * API. Caches the access token in a WP transient keyed by the client_id so
 * repeated calls within the token's TTL don't re-hit the OAuth endpoint.
 */
class ZettleApiClient {
    public const OAUTH_URL       = 'https://oauth.zettle.com/token';
    public const READER_CONNECT  = 'https://reader-connect.zettle.com/v1/integrator';
    private const TOKEN_GRACE    = 30; // seconds to subtract from expires_in

    private string $client_id;
    private string $assertion;

    public function __construct(string $client_id, string $assertion) {
        $this->client_id = $client_id;
        $this->assertion = $assertion;
    }

    public static function from_settings(?array $settings = null): ?self {
        $settings = $settings ?? Settings::get_gateway_settings();
        $client_id = trim((string) ($settings['client_id'] ?? ''));
        $assertion = trim((string) ($settings['assertion'] ?? ''));

        if ($client_id === '' || $assertion === '') {
            return null;
        }

        return new self($client_id, $assertion);
    }

    /**
     * Returns a valid access token, fetching a new one if the cache is empty
     * or the cached token is within the grace window of expiry.
     */
    public function get_access_token(): string {
        $cache_key = $this->token_cache_key();

        if (function_exists('get_transient')) {
            $cached = get_transient($cache_key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $response = $this->oauth_exchange();
        $access_token = (string) ($response['access_token'] ?? '');
        $expires_in = (int) ($response['expires_in'] ?? 0);

        if ($access_token === '') {
            throw new \RuntimeException('Zettle OAuth response did not include an access_token.');
        }

        if (function_exists('set_transient') && $expires_in > self::TOKEN_GRACE) {
            set_transient($cache_key, $access_token, $expires_in - self::TOKEN_GRACE);
        }

        return $access_token;
    }

    /**
     * Forget any cached token for this client, e.g. after a 401 response.
     */
    public function forget_access_token(): void {
        if (function_exists('delete_transient')) {
            delete_transient($this->token_cache_key());
        }
    }

    /**
     * Make an authenticated Reader Connect REST call. Returns the decoded
     * JSON body on success, or null for 204 responses.
     */
    public function request(string $method, string $path, ?array $body = null): ?array {
        $url = self::READER_CONNECT . $path;
        $token = $this->get_access_token();

        $args = [
            'method'  => strtoupper($method),
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ];

        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            Logger::log('Zettle ' . $method . ' ' . $path . ' failed: ' . $response->get_error_message());
            throw new \RuntimeException('Zettle API request failed: ' . $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        // Token might have been invalidated server-side — drop the cache and
        // surface the error so the caller can retry with fresh creds.
        if ($status === 401) {
            $this->forget_access_token();
        }

        if ($status >= 400) {
            $raw = (string) wp_remote_retrieve_body($response);
            Logger::log('Zettle ' . $method . ' ' . $path . ' returned ' . $status . ': ' . $raw);
            throw new \RuntimeException('Zettle API returned HTTP ' . $status . ': ' . $raw);
        }

        if ($status === 204 || $status === 205) {
            return null;
        }

        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function oauth_exchange(): array {
        $response = wp_remote_post(self::OAUTH_URL, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ],
            'body' => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'client_id'  => $this->client_id,
                'assertion'  => $this->assertion,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('Zettle OAuth exchange failed: ' . $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw    = (string) wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Zettle OAuth exchange returned HTTP ' . $status . ': ' . $raw);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Zettle OAuth response was not valid JSON.');
        }

        return $decoded;
    }

    private function token_cache_key(): string {
        return 'prwc_zettle_token_' . md5($this->client_id);
    }
}
