<?php

declare(strict_types=1);

require_once __DIR__ . '/../paypal-reader-for-woocommerce.php';

use WCPOS\WooCommercePOS\PayPalReader\Services\ReaderLinksService;
use WCPOS\WooCommercePOS\PayPalReader\Services\ReaderSessionService;
use WCPOS\WooCommercePOS\PayPalReader\Services\ZettleApiClient;

// Each validation guard short-circuits before any WP HTTP call, so a
// non-network client is sufficient for these unit tests.
$build_client = static function (): ZettleApiClient {
    return new ZettleApiClient('test-client-id', 'test-assertion');
};

return [
    'ZettleApiClient::from_settings returns null without credentials' => function (): void {
        assert_true(ZettleApiClient::from_settings(['client_id' => '', 'assertion' => '']) === null);
    },
    'ZettleApiClient::from_settings returns null when only one credential is set' => function (): void {
        assert_true(ZettleApiClient::from_settings(['client_id' => 'abc', 'assertion' => '']) === null);
    },
    'ZettleApiClient::from_settings returns a client when both credentials are set' => function (): void {
        $client = ZettleApiClient::from_settings(['client_id' => 'abc', 'assertion' => 'xyz']);
        assert_true($client instanceof ZettleApiClient);
    },
    'ReaderSessionService rejects empty link_id' => function () use ($build_client): void {
        $service = new ReaderSessionService($build_client());
        $threw = false;
        try {
            $service->open_session('', 'channel-1');
        } catch (\InvalidArgumentException $e) {
            $threw = true;
        }
        assert_true($threw, 'open_session should throw on empty link_id');
    },
    'ReaderSessionService rejects empty channel_id' => function () use ($build_client): void {
        $service = new ReaderSessionService($build_client());
        $threw = false;
        try {
            $service->open_session('link-1', '   ');
        } catch (\InvalidArgumentException $e) {
            $threw = true;
        }
        assert_true($threw, 'open_session should throw on whitespace channel_id');
    },
    'ReaderLinksService::claim rejects empty code' => function () use ($build_client): void {
        $service = new ReaderLinksService($build_client());
        $threw = false;
        try {
            $service->claim('', 'Front counter');
        } catch (\InvalidArgumentException $e) {
            $threw = true;
        }
        assert_true($threw, 'claim should throw on empty code');
    },
    'ReaderLinksService::delete rejects empty link_id' => function () use ($build_client): void {
        $service = new ReaderLinksService($build_client());
        $threw = false;
        try {
            $service->delete('');
        } catch (\InvalidArgumentException $e) {
            $threw = true;
        }
        assert_true($threw, 'delete should throw on empty link_id');
    },
];
