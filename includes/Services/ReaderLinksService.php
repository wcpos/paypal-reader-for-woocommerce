<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader\Services;

/**
 * Wraps the Zettle Reader Connect "links" and "link-offers" endpoints:
 * listing the readers paired to this integrator for the merchant, pairing
 * a new one via the code shown on the reader, and unpairing.
 */
class ReaderLinksService {
    private ZettleApiClient $client;

    public function __construct(ZettleApiClient $client) {
        $this->client = $client;
    }

    /**
     * @return array<int,array{linkId:string,label:string,raw:array}>
     */
    public function list_links(): array {
        $response = $this->client->request('GET', '/links');
        $entries = is_array($response['links'] ?? null) ? $response['links'] : (is_array($response) ? $response : []);

        $links = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $link_id = (string) ($entry['linkId'] ?? $entry['id'] ?? '');
            if ($link_id === '') {
                continue;
            }
            $label = (string) ($entry['tags']['deviceName'] ?? $entry['deviceName'] ?? $entry['name'] ?? $link_id);
            $links[] = [
                'linkId' => $link_id,
                'label'  => $label,
                'raw'    => $entry,
            ];
        }

        return $links;
    }

    public function claim(string $code, string $device_name): array {
        $code = trim($code);
        if ($code === '') {
            throw new \InvalidArgumentException('Pairing code is required.');
        }

        $response = $this->client->request('POST', '/link-offers/claim', [
            'code' => $code,
            'tags' => [
                'deviceName' => $device_name !== '' ? $device_name : 'WCPOS PayPal Reader',
            ],
        ]);

        $link_id = (string) ($response['linkId'] ?? $response['id'] ?? '');
        if ($link_id === '') {
            throw new \RuntimeException('Zettle did not return a linkId for the claimed reader.');
        }

        return [
            'linkId' => $link_id,
            'label'  => (string) ($response['tags']['deviceName'] ?? $device_name),
            'raw'    => is_array($response) ? $response : [],
        ];
    }

    public function delete(string $link_id): void {
        $link_id = trim($link_id);
        if ($link_id === '') {
            throw new \InvalidArgumentException('linkId is required.');
        }

        $this->client->request('DELETE', '/links/' . rawurlencode($link_id));
    }
}
