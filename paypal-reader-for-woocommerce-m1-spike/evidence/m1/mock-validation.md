# Mock validation

## Why this exists
- No live reader or live Zettle credentials were available in this session.
- The next best evidence was to read the official docs closely, inspect a public GitHub example, and encode the documented contract into a disposable simulator.

## Primary sources encoded into the mock
- OAuth assertion grant docs: token request shape, 7200 second expiry, no refresh token expectation.
- Reader Connect link reader docs: link claim payload and link object shape.
- Reader Connect session docs: session configure request shape plus location, authorized, and failed response fields.
- Reader Connect payment docs: STATUS_REQUEST, STATUS_RESPONSE, PAYMENT_REQUEST, PAYMENT_PROGRESS_RESPONSE, PAYMENT_RESULT_RESPONSE, and CANCEL_PAYMENT_REQUEST envelope patterns.
- Reader Connect payment docs warning that cancel does not guarantee cancellation.

## Secondary pattern reference reviewed
- https://github.com/Tamerb86/stylora/blob/main/server/services/reader-connect.ts
- Used only as a secondary implementation example for message handling patterns, not as an authority on the API contract.

## What the automated tests prove locally
- OAuth token exchange request formatting matches the documented assertion grant flow.
- Link claim, link listing, and session configuration can be exercised end to end against the disposable mock.
- Status polling returns a doc-shaped STATUS_RESPONSE.
- A successful payment transcript returns realistic progress states followed by a PAYMENT_RESULT_RESPONSE.
- An ambiguous cancel scenario can still resolve to COMPLETED, matching the docs warning that cancel is not the source of truth.

## Limits
- None of this proves a live merchant account, live reader firmware, real session ticketing, or real payment reconciliation path.
- This file records mock validation only.
