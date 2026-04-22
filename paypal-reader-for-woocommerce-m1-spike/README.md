# PayPal Reader for WooCommerce M1 Spike

This directory is disposable. It exists only to prove the M1 gates from the design spec.

## Rules
- Do not commit real credentials.
- Copy `.env.example` to `.env` and fill in merchant-specific values locally.
- Write all durable findings into `evidence/m1/`.
- Do not start the production plugin repo from this workspace.

## Commands
- `npm test`
- `npm run get-token`
- `npm run list-links`
- `npm run claim-link`
- `npm run configure-session`
- `npm run write-evidence-pack`
