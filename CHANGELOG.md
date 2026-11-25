# Changelog

## 0.1.1 - 2026-11-24

- Reader payments start, confirm and cancel only for a cashier WCPOS lets edit the order.
- Taking a payment now needs WCPOS active: without its cashier checks, start, confirm and cancel are refused.
- A payment confirms only on a started, unpaid attempt, and a failed result never records the payment as completed.
- The pay page's script data now requires the order key.
