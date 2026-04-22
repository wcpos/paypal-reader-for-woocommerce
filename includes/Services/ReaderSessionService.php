<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader\Services;

/**
 * Wraps POST /sessions on Reader Connect. Given a linkId + channelId, Zettle
 * returns a short-lived WebSocket endpoint (the `location`) that the browser
 * will connect to for live payment status. The PHP side also supplies the
 * access token the browser includes in the PAYMENT_REQUEST payload.
 */
class ReaderSessionService {
    private ZettleApiClient $client;

    public function __construct(ZettleApiClient $client) {
        $this->client = $client;
    }

    /**
     * @return array{location:string,authorized:array,accessToken:string,expiresAt:int}
     */
    public function open_session(string $link_id, string $channel_id): array {
        $link_id = trim($link_id);
        $channel_id = trim($channel_id);
        if ($link_id === '' || $channel_id === '') {
            throw new \InvalidArgumentException('linkId and channelId are required to open a session.');
        }

        $response = $this->client->request('POST', '/sessions', [
            'links' => [
                $link_id => [$channel_id],
            ],
        ]);

        $location = (string) ($response['location'] ?? '');
        if ($location === '') {
            throw new \RuntimeException('Zettle /sessions response did not include a WebSocket location.');
        }

        $access_token = $this->client->get_access_token();
        // Zettle access tokens from the JWT-bearer grant live for 7200s by
        // default. We surface an approximate expiresAt so the browser can
        // include it in PAYMENT_REQUEST; the WS session itself is
        // server-controlled.
        $expires_at = (int) (microtime(true) * 1000) + 30 * 60 * 1000;

        return [
            'location'    => $location,
            'authorized'  => is_array($response['authorized'] ?? null) ? $response['authorized'] : [],
            'accessToken' => $access_token,
            'expiresAt'   => $expires_at,
        ];
    }
}
