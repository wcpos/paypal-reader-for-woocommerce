<?php

declare(strict_types=1);

namespace WCPOS\WooCommercePOS\PayPalReader\Services;

class AttemptStore {
    /**
     * @var array<int,array<string,mixed>>
     */
    private array $memory = [];

    public function get_for_order(int $order_id): ?array {
        if ($order_id <= 0) {
            return null;
        }

        $all = $this->all();

        return isset($all[$order_id]) && is_array($all[$order_id]) ? $all[$order_id] : null;
    }

    public function save_for_order(int $order_id, array $attempt): array {
        $all = $this->all();
        $all[$order_id] = $attempt;
        $this->persist($all);

        return $attempt;
    }

    public function delete_for_order(int $order_id): void {
        $all = $this->all();
        unset($all[$order_id]);
        $this->persist($all);
    }

    private function all(): array {
        if (function_exists('get_option')) {
            $stored = get_option('prwc_mock_attempts', []);
            return is_array($stored) ? $stored : [];
        }

        return $this->memory;
    }

    private function persist(array $attempts): void {
        if (function_exists('update_option')) {
            update_option('prwc_mock_attempts', $attempts, false);
            return;
        }

        $this->memory = $attempts;
    }
}
