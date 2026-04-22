# PayPal Reader for WooCommerce M1 Spike

This directory is disposable. It exists only to prove the M1 gates from the design spec.

## Rules
- Do not commit real credentials.
- Copy `.env.example` to `.env` if you want a local template, but export the variables into your shell before running the scripts.
- The scripts read exported environment variables only; for example, `set -a; source .env; set +a` in `zsh` will export the values from a `.env` file for the current shell.
- Write all durable findings into `evidence/m1/`.
- Do not start the production plugin repo from this workspace.

## Commands
- `npm test` — runs the unit tests, including the disposable doc-driven mock OAuth/Reader Connect transcript checks
- `npm run get-token`
- `npm run list-links`
- `npm run claim-link`
- `npm run configure-session`
- `npm run write-evidence-pack`

## Browser WebSocket harness

Run a local static server from the spike root:
- `python3 -m http.server 4173`

Then open `http://localhost:4173/tools/ws-harness/` and paste the `location`, `linkId`, `channelId`, token, expiry, attempt ID, and amount from the live spike commands.
