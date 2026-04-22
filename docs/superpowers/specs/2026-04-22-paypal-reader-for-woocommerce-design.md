# PayPal Reader for WooCommerce / WCPOS
## M1 Discovery Brief and Provisional v1 Design

- **Status:** Draft for review
- **Date:** April 22, 2026
- **Author:** OpenAI Codex
- **Target org:** `wcpos`
- **Proposed repository:** `wcpos/paypal-reader-for-woocommerce`

---

## 1. Summary

This project will create a new WooCommerce / WCPOS extension that connects the WCPOS checkout flow to the **PayPal Reader** using **Zettle Reader Connect**.

The extension is intended to let merchants:

- connect their merchant account to the plugin
- link one or more PayPal Readers
- select a linked reader during WCPOS checkout
- initiate card-present payments on the reader
- receive payment progress updates in the POS
- complete the WooCommerce order only after server-side verification

This document is intentionally split into two layers:

1. **M1 discovery brief** — the concrete questions that must be answered before implementation design is locked.
2. **Provisional v1 design** — the leading architecture, data model, and delivery path if M1 validates the current assumptions.

This is **not yet an implementation-final spec**. No production scaffolding should begin until the M1 gates in §7 are passed.

---

## 2. Goals

### Primary goals
- Provide a working PayPal Reader payment method for WCPOS.
- Keep merchant setup manageable from wp-admin.
- Reuse proven patterns from existing WCPOS terminal plugins where they still fit after M1.
- Ensure payment completion is **verified server-side** before a WooCommerce order is marked paid.
- Ship a first beta release and then a stable `0.1.0`.

### Secondary goals
- Support linking multiple readers.
- Surface useful reader state in WCPOS, including readiness and disconnection.
- Provide a GitHub release workflow that builds and publishes a plugin ZIP.
- Give pilot merchants enough diagnostics to support payment troubleshooting.

---

## 3. Non-goals

The first release will **not** aim to:

- support every possible Reader Connect capability
- build a separate hosted backend or relay service unless M1 proves it is required
- support automatic multi-reader routing or load balancing
- support every tipping mode in every market on day one
- replace the existing Stripe or SumUp extensions
- deeply refactor WCPOS core checkout architecture
- provide automated WooCommerce refunds in v1 unless M1 proves a safe, testable refund path

---

## 4. Decisions already made

The following are deliberate decisions, not open questions:

1. **Standalone plugin repo**  
   This integration should live in its own repo and plugin, not inside the Stripe or SumUp repos.

2. **WordPress plugin + POS frontend split**  
   The plugin should own auth, reader management, order-state integrity, and release packaging. The POS frontend should own the cashier interaction loop.

3. **Server-side payment verification is mandatory**  
   A browser-delivered Reader Connect result message is not sufficient to mark an order paid.

4. **Beta-first launch**  
   This should ship to pilot merchants as beta before a stable `0.1.0`.

---

## 5. Why a standalone plugin

A standalone plugin is preferred over extending the existing Stripe or SumUp repos because:

1. **Separate payment provider domain**  
   PayPal Reader / Zettle Reader Connect has distinct auth, session, and payment semantics.

2. **Cleaner release lifecycle**  
   The plugin can version, release, and test independently.

3. **Lower coupling**  
   It avoids forcing Reader Connect abstractions into Stripe- or SumUp-specific code.

4. **Closer to existing WCPOS pattern**  
   WCPOS already uses one plugin per payment terminal integration.

---

## 6. External dependencies and references

This integration is based on Zettle / PayPal Reader Connect and related auth flows.

### Key documentation reviewed
- Reader Connect reference  
  https://developer.zettle.com/docs/payment-integrations/reader-connect/reference
- Reader Connect make payments  
  https://developer.zettle.com/docs/payment-integrations/reader-connect/user-guides/make-payments
- Reader Connect create WebSocket connection  
  https://developer.zettle.com/docs/payment-integrations/reader-connect/user-guides/create-a-websocket-connection
- Reader Connect link reader  
  https://developer.zettle.com/docs/payment-integrations/reader-connect/user-guides/link-reader
- Reader Connect get started  
  https://developer.zettle.com/docs/get-started/user-guides/take-payments-using-reader-connect
- OAuth overview  
  https://developer.zettle.com/docs/api/oauth/overview
- Assertion grant  
  https://developer.zettle.com/docs/api/oauth/user-guides/set-up-app-authorisation/set-up-authorisation-assertion-grant
- Self-hosted app credentials  
  https://developer.zettle.com/docs/get-started/user-guides/create-app-credentials/create-app-credentials-for-self-hosted-app/create-credentials-self-hosted-app

### Important documented signals
- Zettle documents **assertion grant** for **self-hosted apps**.
- Reader Connect reference documents integrator-facing endpoints including `POST /v1/integrator/link-offers/claim`, `GET /v1/integrator/links`, `DELETE /v1/integrator/links/{id}`, and `POST /v1/integrator/sessions`.
- Reader Connect requires at least `READ:USERINFO` and `WRITE:USERINFO` for linking and session setup.
- Reader Connect payment and WebSocket guides also show payment-scoped access requirements: `READ:PAYMENT` and `WRITE:PAYMENT`.
- Integrator session configuration returns a response payload containing `location`, `authorized`, and `failed`; the returned `location` should be used as-is for the WebSocket target URL.
- Link-claim requests use an 8-character pairing code and may include integrator tags such as a merchant-friendly device name.
- `STATUS_REQUEST` returns `STATUS_RESPONSE` values such as `READY`, `BUSY`, and `NOT_CONNECTED`.
- Payment messages use fields such as `PAYMENT_REQUEST`, `PAYMENT_PROGRESS_RESPONSE`, `PAYMENT_RESULT_RESPONSE`, `CANCEL_PAYMENT_REQUEST`, `internalTraceId`, `amount`, and `expiresAt`.
- Reader Connect payment examples describe the amount as **fractional monetary units**.
- Payment result examples include `resultStatus` values such as `COMPLETED`, `FAILED`, and `CANCELED`, with payload fields such as `CARD_PAYMENT_UUID`, masked PAN/card summary data, and nested reference fields like `trackingId` and `checkoutUUID`.

---

## 7. M1 discovery spike

## 7.1 Purpose

M1 exists to resolve the five design decisions that currently block implementation:

1. **Auth model and exact scopes**
2. **Server-side verification source of truth**
3. **Browser disconnect / reconciliation flow**
4. **Idempotency and payment-attempt ownership**
5. **Refund scope for v1**

No plugin scaffolding beyond disposable spike code should begin until these are answered.

## 7.2 Spike boundaries

M1 is a **discovery spike**, not the start of production implementation.

Allowed:

- disposable scripts, curl/Postman collections, or throwaway harness code
- temporary notes and redacted request/response captures
- real-reader manual test runs
- spec updates that convert findings into durable design decisions

Not allowed:

- bootstrapping the production plugin repo
- committing production gateway classes or checkout UI integration
- building release automation
- presenting unresolved guesses as implementation decisions

## 7.3 Required spike outputs

M1 must produce the following concrete outputs:

1. A written decision on **self-hosted vs partner-hosted app model**.
2. A written list of **exact scopes required**.
3. A tested and named **verification mechanism** for completed payments.
4. A tested and documented **reconciliation path** for client disconnects.
5. A written **idempotency model** for retries and duplicate requests.
6. A written **refund decision**: in v1 or explicitly out of scope.
7. A brief technical note on **currency representation** and **tipping scope**.

## 7.4 Hard exit criteria

M1 is complete only if all of the following are true:

- A real merchant credential flow can obtain an access token usable for Reader Connect.
- The exact required scopes are known and tested.
- A reader can be linked successfully from the chosen app model.
- A Reader Connect session can be created successfully.
- A WebSocket can be opened successfully.
- A `STATUS_REQUEST` can be sent and a valid reader status received.
- A `PAYMENT_REQUEST` can be sent and a real payment result observed.
- There is a named, tested server-side mechanism to verify the completed payment.
- There is a defined reconcile path for browser drop / tab close / refresh mid-payment.
- There is a written rule for whether a cashier may retry after an ambiguous payment state.

If any one of these remains unresolved, M2 must not start.

## 7.5 Evidence pack required before M2

M1 should leave behind a compact evidence pack that another engineer can audit without rerunning the whole spike:

1. redacted auth flow notes, including exactly how the merchant obtains the usable credentials
2. the final tested scope list and where each scope was needed
3. a redacted sample of a successful session-configuration response showing `location`, `authorized`, and `failed`
4. at least one redacted real payment transcript showing:
   - the attempt ID/internal trace ID
   - the observed Reader Connect result references
   - the separate server-side verification step
5. a short reconciliation write-up for one disconnect scenario
6. a one-page decision record covering verification, retry policy, and refund scope

---

## 8. Critical open design decisions

## 8.1 Auth model

### Current leading candidate
Use a **self-hosted app** with **assertion grant**, because Zettle documents self-hosted apps this way.

### Current candidate scope set
The leading working assumption is that the plugin will require:

- `READ:USERINFO`
- `WRITE:USERINFO`
- `READ:PAYMENT`
- `WRITE:PAYMENT`

This set is still provisional until M1 proves that:

- assertion-grant tokens can actually exercise the full reader + payment flow
- no additional scopes are required
- no documented scope can be dropped safely

### What M1 must verify
- Can the self-hosted assertion-grant model access the full Reader Connect and payment functionality needed for this plugin?
- What are the exact required scopes?
- Is a partner-hosted model required for any part of the flow?

### Design consequence
If self-hosted assertion grant works with the required scopes, the plugin can remain merchant-installed without external WCPOS infrastructure. If not, the entire credential and installation story changes and the plugin architecture must be revised before M2.

## 8.2 Verification source of truth

### Current requirement
The plugin must verify payment completion server-side using an authoritative API or server-retrievable record.

### What is still unknown
The exact mechanism is not yet named in this spec. Candidates may include:
- lookup by payment UUID or card payment UUID
- Purchase API lookup
- another Zettle payment retrieval endpoint
- a supported webhook or asynchronous server-side event, if one exists

### Working assumption until M1 proves otherwise
The browser may surface useful references such as `CARD_PAYMENT_UUID`, `trackingId`, `checkoutUUID`, or the original `internalTraceId`, but those references are only **verification inputs**. They are not themselves proof of payment.

### Hard rule
If M1 cannot name and test an authoritative verification mechanism, order completion must not be implemented and the project must not advance to beta.

## 8.3 Browser disconnect and reconciliation

### Problem
Reader Connect payment results are delivered over a browser WebSocket. That creates failure modes such as:
- cashier closes tab mid-payment
- page refresh during authorization
- local network drop after card presentation
- two tabs on the same order

### M1 requirement
M1 must define exactly how the backend and POS recover from an incomplete client-side flow.

### Provisional design direction
Until M1 proves a better pattern, the backend should treat any lost-client state after `payment_requested` as **reconciliation first, retry later**:

1. mark the attempt `reconciliation_required`
2. block new attempts on the same order
3. run server-side verification using the authoritative lookup
4. only reopen checkout if the prior attempt is conclusively not paid

## 8.4 Idempotency and retries

### Problem
A flaky socket or impatient cashier can otherwise trigger duplicate payment attempts.

### M1 requirement
M1 must define who creates the attempt identifier, which identifier is reused across retries, and when a retry is forbidden because payment state is ambiguous.

### Provisional design direction
Until M1 proves otherwise:

- the backend creates the canonical attempt ID
- the attempt ID is reused as the Reader Connect `internalTraceId`
- the backend also creates the session/channel ownership data tied to that attempt
- retries create a **new** attempt only after the previous attempt is conclusively failed, cancelled, or reconciled as unpaid

## 8.5 Refund scope

### Current recommendation
Refund automation is **out of scope for v1** unless M1 proves a safe, server-side refund API and WooCommerce refund mapping.

### Merchant-facing consequence
If refunds remain out of scope, the README and admin UI must say that refunds are handled manually in the merchant's PayPal/Zettle tools for the first release.

---

## 9. Provisional architecture after M1

If M1 validates the current direction, the recommended implementation is:

- a **new standalone plugin repo**
- **WordPress/PHP backend** for auth, reader management, payment attempts, reconciliation, verification, and order completion
- **browser-side WebSocket client** inside the POS checkout flow for live reader communication
- **server-side order finalisation** only after successful verification

This remains the leading architecture because it is closest to the existing WCPOS terminal plugin model while matching Reader Connect’s REST + WebSocket flow.

---

## 10. High-level system design

### 10.1 WordPress plugin backend
Responsible for:
- merchant credential storage
- token retrieval and refresh
- reader link management
- session creation
- payment-attempt creation and persistence
- reconciliation and ambiguous-state handling
- authoritative payment verification
- WooCommerce order updates
- support logging and diagnostics

### 10.2 WCPOS frontend client
Responsible for:
- opening the Reader Connect WebSocket
- sending reader commands
- receiving payment progress and result messages
- displaying reader status and payment progress
- reporting client-visible results back to WordPress
- showing reconciliation-required states to the cashier

### 10.3 Trust boundary

The frontend is allowed to:
- initiate reader actions
- display progress
- report result payloads back to the server

The frontend is **not** the source of truth for payment success.

The backend must:
- validate order state
- own the payment-attempt record
- verify payment success with trusted data
- persist redacted transaction metadata
- call `payment_complete()` only after verification succeeds

### 10.4 Provisional integration contract

The leading contract between backend and frontend is:

1. backend returns linked-reader choices and checkout bootstrap capabilities
2. frontend selects a reader and asks the backend to create or resume a payment attempt
3. backend returns the one-time WebSocket `location`, the chosen `linkId`, a `channelId`, the attempt ID/internal trace ID, and a short-lived access token bundle
4. frontend uses those values exactly as issued and never invents its own trace IDs
5. frontend streams status/progress/result events back to the backend
6. backend decides whether the order is paid, failed, or blocked for reconciliation

---

## 11. Reader and session lifecycle

### 11.1 Linking
A merchant links a reader by:

1. obtaining the pairing/link-offer code shown on the reader
2. entering that code in the plugin admin UI
3. submitting a claim-link-offer request
4. receiving and storing the resulting `linkId`

### 11.2 Stored reader data
The plugin should persist only the reader metadata needed for operations:

- `linkId`
- merchant-friendly display name
- created/updated timestamps
- optional register/location assignment only if M1 or pilot needs justify it

### 11.3 Unlinking
The plugin should support deleting an existing reader link from wp-admin.

### 11.4 Live status
WCPOS should be able to determine live reader state via WebSocket messages such as:

- `READY`
- `BUSY`
- `NOT_CONNECTED`

The POS may render `NOT_CONNECTED` as a more merchant-friendly label such as “Disconnected”, but the design should keep the documented protocol value explicit.

### 11.5 Session bootstrap contract
The leading v1 contract for session setup is:

1. backend chooses the target `linkId` and one attempt-owned `channelId`
2. backend calls `POST /v1/integrator/sessions` with a `links` map keyed by `linkId`
3. backend inspects the returned `authorized` and `failed` maps
4. backend returns the `location` value to the frontend only if the target reader/channel combination is authorized
5. frontend opens the returned WebSocket URL **as-is**

For v1, a payment attempt should commit to one selected reader before the actual `PAYMENT_REQUEST` is sent, even if the POS UI may show multiple linked readers before that point.

### 11.6 Session ownership
For v1, a single reader should be treated as effectively owned by one active POS session at a time. If two sessions contend for the same reader, the second session should be blocked or shown as unavailable rather than attempting cooperative sharing.

---

## 12. Payment-attempt model

### 12.1 Core rule
Every cashier payment action must map to a **server-owned payment attempt** before any `PAYMENT_REQUEST` is sent.

### 12.2 Attempt creation
The backend should create a unique **payment attempt ID** before session bootstrap. This ID should:

- be generated server-side
- be stored against the WooCommerce order
- be used as the canonical internal trace ID sent to Reader Connect
- survive browser reconnects for the same payment attempt

Each attempt record should also capture, at minimum:

- WooCommerce order ID
- selected reader `linkId`
- session `channelId`
- requested amount in minor units
- order/store currency
- timestamps for create/request/result/verification
- verification reference(s), if any are found

### 12.3 Attempt statuses
The backend should track attempt state with statuses such as:

- `created`
- `session_created`
- `payment_requested`
- `cancel_requested`
- `result_reported`
- `verified_success`
- `verified_failure`
- `reconciliation_required`
- `abandoned`

Exact names can change after M1, but the lifecycle must exist.

### 12.4 Retry rule
If payment state is ambiguous, the cashier must **not** be allowed to fire a new attempt automatically. The backend must first reconcile the previous attempt using the authoritative verification mechanism.

### 12.5 Duplicate prevention
The backend must reject any second active attempt for the same order unless the previous attempt is conclusively failed, cancelled, or reconciled as not paid.

---

## 13. Payment lifecycle

### 13.1 Overview
The provisional payment lifecycle is:

1. cashier selects PayPal Reader as the payment method
2. frontend selects a linked reader
3. backend creates a payment attempt ID and attempt-owned `channelId`
4. frontend requests session/bootstrap data from the plugin
5. backend creates a Reader Connect session for the selected reader
6. backend returns the WebSocket `location`, `linkId`, `channelId`, short-lived access token, and attempt ID/internal trace ID
7. frontend opens the one-time WebSocket URL
8. frontend requests reader status
9. frontend sends a `PAYMENT_REQUEST` using the server-owned internal trace ID
10. frontend receives progress messages
11. frontend receives result message or loses connectivity
12. backend reconciles and verifies the attempt
13. backend either completes the order, marks the attempt failed, or marks the attempt as needing manual/operator attention

### 13.2 Payment request payload
The WebSocket message envelope is expected to include:

- `linkId`
- `channelId`
- `messageId`
- `payload.type = PAYMENT_REQUEST`

The `PAYMENT_REQUEST` payload is expected to include:

- short-lived access token
- access-token expiry timestamp
- server-owned internal trace ID
- amount in **minor units** / fractional monetary units as required by Reader Connect
- currency implied by the merchant account / store market, validated in M1
- tipping configuration, if enabled for the first release
- optional partner attribution if required

### 13.3 Currency and amount representation
The v1 data model should treat amounts as:

- WooCommerce order total converted to **integer minor units**
- currency stored explicitly on the attempt record
- requested amount stored separately from any final settled amount

M1 must verify exactly how Reader Connect expects amounts for the markets WCPOS intends to support and document unsupported currency/store combinations.

### 13.4 Tipping scope
Tipping should be treated as a **v1 optional feature flag**, not a core checkout dependency.

Current recommendation:
- default v1 behavior: **no tipping** unless M1 proves a simple, reliable configuration model
- if enabled later in v1, tipping should be configured at the **store/plugin level**, not per cash register and not as an ad hoc cashier input

### 13.5 Cancellation
The frontend should support `CANCEL_PAYMENT_REQUEST`.

However, cancellation is not guaranteed at every stage. The UI must communicate that:
- some in-flight payments may not be cancellable
- a completed payment may need to be refunded rather than cancelled
- an ambiguous post-cancel state requires reconciliation before retry

---

## 14. Reconciliation and disconnect handling

### 14.1 Required failure stories
The design must explicitly handle:

- tab close mid-payment
- page refresh mid-payment
- browser crash
- network drop after card presentation
- browser receives no final result but reader has already completed payment

### 14.2 Backend responsibility
When the browser disappears or fails to report completion, the backend must move the attempt into `reconciliation_required` and run the authoritative verification mechanism.

The backend should also treat the following as reconciliation triggers:

- a result callback that lacks enough information to verify conclusively
- a timeout waiting for a final result after `payment_requested`
- a second tab/session trying to resume the same order while the first payment is unresolved

### 14.3 Frontend responsibility after reload
When the POS reloads an order with an in-flight or ambiguous attempt, the UI should:

- fetch current attempt status from the backend
- show that reconciliation is in progress or required
- prevent the cashier from starting a new attempt until the previous one is resolved
- allow the cashier to refresh status, but not to bypass the block locally

### 14.4 Hard safety rule
The system must prefer **blocking a retry** over risking a duplicate charge.

### 14.5 Duplicate-tab rule
If two browser tabs open the same order:

- only one tab may control the active attempt
- additional tabs should be read-only/blocked for payment initiation
- if ownership is unclear after a disconnect, the backend should fall back to reconciliation rather than guessing which tab “won”

---

## 15. Verification strategy

### 15.1 Principle
A browser-reported success event is insufficient to complete an order.

### 15.2 Required behavior
The backend must:
- validate that the order is still payable
- verify the payment result using an authoritative server-side mechanism named by M1
- persist redacted transaction details
- only then call WooCommerce payment completion

Browser-reported values such as `CARD_PAYMENT_UUID`, `trackingId`, or similar Reader Connect references may be persisted temporarily as candidate lookup inputs, but they do not satisfy verification on their own.

### 15.3 Verification gate
This project must not proceed to M2 until the exact verification endpoint or retrieval mechanism is:

- named
- tested successfully with a real payment
- documented in the spec

### 15.4 Data persisted on order
The plugin should store only the minimum transaction data needed for support and bookkeeping, such as:

- reader `linkId`
- reader display name
- payment attempt ID / internal trace ID
- authoritative payment UUID/reference
- result status
- amount
- currency
- timestamps
- redacted card summary fields if needed for support

### 15.5 Data that should not be persisted by default
The plugin should avoid persisting raw unredacted payloads that may contain unnecessary payment-card or customer data.

Default rule:
- store a normalized, redacted summary
- do not store access tokens
- do not store full raw WebSocket message payloads by default
- do not store any full PAN-equivalent data

### 15.6 Failure handling
If verification fails:
- the order must remain unpaid
- the attempt must be marked failed or ambiguous
- the UI must show a recoverable error or reconciliation-required message
- logs should contain enough data for troubleshooting without exposing secrets

---

## 16. Observability and supportability

### 16.1 Logging requirements
The plugin should log, at minimum:

- order ID
- payment attempt ID
- reader `linkId`
- session/channel identifier if available
- verification outcome
- failure category

### 16.2 Correlation rule
The **payment attempt ID** must be the primary correlation key across:

- browser logs
- plugin logs
- order notes
- support investigations

### 16.3 Redaction rules
Logs must not contain:

- merchant assertion/API key
- access tokens
- full raw payloads with sensitive fields unless explicitly debug-gated and redacted

---

## 17. POS frontend requirements

### 17.1 Core UI responsibilities
The POS UI should allow the cashier to:

- select the PayPal Reader payment method
- see available linked readers
- see whether the selected reader is ready
- initiate payment
- watch payment progress
- cancel when possible
- understand clear success/failure states
- see when reconciliation is blocking a retry

### 17.2 Core frontend states
The minimal v1 state machine should cover:

- `idle`
- `connecting`
- `ready`
- `busy`
- `disconnected`
- `payment_in_progress`
- `reconciliation_required`
- `completed`
- `failed`
- `cancelled`

This should eventually be diagrammed before implementation, but the main point is to keep the state list minimal and driven by actual recovery rules. The frontend should also explicitly map protocol-level `NOT_CONNECTED` reader status into the UI’s disconnected state, rather than inventing separate transport meanings.

---

## 18. WordPress plugin requirements

### 18.1 Admin settings
The settings screen should support:

- merchant credential input appropriate to the final auth model
- self-hosted assertion/API-key input under the current leading auth model
- client ID if required
- auth connection status
- link reader action
- list of linked readers
- unlink action
- optional store-level tipping mode if supported in v1
- explicit note if refunds are manual in v1

### 18.2 Provisional service boundaries
The plugin will likely need backend units for:

- HTTP communication
- token handling
- link management
- session bootstrap
- payment verification
- reconciliation / payment-attempt persistence

Exact class decomposition is intentionally deferred until after M1. Avoid premature abstraction until the auth and verification model is known.

### 18.3 Security requirements
- never expose merchant long-lived credentials to the browser
- sanitize logs
- validate capabilities for admin actions
- validate order access for checkout actions
- verify nonces where appropriate
- avoid trusting browser payment completion as final truth

---

## 19. Provisional repository structure

The repository structure below is **illustrative, not final**. It should only be finalized after M1.

```text
paypal-reader-for-woocommerce/
├── .github/
│   └── workflows/
├── includes/
│   ├── Gateway.php
│   ├── AjaxHandler.php
│   ├── Settings.php
│   ├── Logger.php
│   ├── Services/
│   │   ├── HttpClient.php
│   │   ├── TokenService.php
│   │   ├── LinkService.php
│   │   ├── SessionService.php
│   │   └── PaymentVerificationService.php
│   └── Support/
├── languages/
├── tests/
├── README.md
├── CHANGELOG.md
├── composer.json
└── paypal-reader-for-woocommerce.php
```

The purpose of showing this now is to indicate likely responsibility boundaries, not to lock in exact class names.

---

## 20. Reuse from existing WCPOS extensions

After M1, the implementation should selectively reuse proven patterns from the existing terminal plugins.

### Reuse from SumUp
- standalone plugin packaging
- WooCommerce gateway registration
- wp-admin settings flow
- release workflow shape
- basic service + HTTP client separation

### Reuse from Stripe
- order metadata discipline
- richer payment-state handling
- more defensive test approach
- stronger checkout lifecycle modeling

### Do not blindly reuse
- provider-specific abstractions
- service splits that only make sense for Stripe or SumUp
- assumptions about polling vs WebSocket ownership

---

## 21. CI, test, and validation requirements

### 21.1 Required workflows
The repo should include GitHub Actions for:

- PHP tests
- coding standards / linting
- dependency review
- translation template updates
- release ZIP creation

### 21.2 Test tiers
The project should have three test tiers:

1. **Unit tests**  
   Token handling, amount conversion, attempt state transitions, parser/normalizer logic.

2. **Contract / integration tests**  
   Sandbox-backed or recorded-fixture tests against Zettle-facing request/response shapes once M1 establishes the viable auth and verification APIs.

3. **Manual hardware validation**  
   Real-reader tests for end-to-end payment behavior.

### 21.3 Manual hardware validation matrix
Real-reader testing must cover:

- link reader
- unlink reader
- ready state
- busy state
- `NOT_CONNECTED` / disconnected state
- successful payment
- declined payment
- cancel before card present
- cancel after card presented
- browser refresh mid-payment
- browser close mid-payment
- token expiry recovery
- multiple POS sessions competing for one reader

### 21.4 Release gate
No stable release should ship before real-reader testing proves the core success path, the main failure paths, and at least one disconnect/reconciliation path.

---

## 22. Milestones

### Milestone 1 — Technical spike
Purpose: answer the blocking design questions.

Deliverables:
- auth model decision
- exact scopes list
- named verification mechanism
- disconnect/reconciliation design
- idempotency model
- refund scope decision
- one successful verified payment on real hardware

### Milestone 2 — Plugin scaffold
Only starts if M1 passes.

Deliverables:
- create repo
- bootstrap plugin
- implement the minimal backend units required by the validated M1 decisions
- add baseline CI

### Milestone 3 — Checkout MVP
- admin reader linking
- frontend session creation
- WebSocket connection
- status handling
- payment-attempt lifecycle
- verification-backed order finalisation
- reconciliation-required UX

### Milestone 4 — Beta
- cancel flow
- better error handling
- support logging
- docs
- release ZIP pipeline
- pilot merchant testing

### Milestone 5 — Stable `0.1.0`
- pilot feedback addressed
- known critical issues resolved
- docs complete
- stable release published

### Post-`0.1.0`
- evaluate automated refunds
- evaluate broader tipping modes
- evaluate broader market/currency support
- define criteria for `1.0.0`

---

## 23. Risks

### 23.1 Auth risk
If merchant-installed auth does not map cleanly to Reader Connect payment flows, architecture may need to change.

### 23.2 Verification risk
If the final payment event cannot be verified cleanly server-side, order integrity is at risk.

### 23.3 WebSocket lifecycle risk
Disconnects, reconnects, duplicate tabs, and session collisions may create hard-to-reproduce bugs.

### 23.4 Hardware-only behavior risk
Certain failure modes may only appear on real readers, not in mocked tests.

### 23.5 Scope risk
Trying to support too many advanced Reader Connect features in v1 may delay the beta unnecessarily.

---

## 24. Release criteria

### Beta release criteria
Before `0.1.0-beta.1`:
- M1 has passed all exit criteria
- auth is validated
- linking is validated
- session creation is validated
- one successful verified payment on real hardware is documented
- reconciliation path is tested at least once
- README setup flow is drafted
- release ZIP workflow is working

### Stable release criteria
Before `0.1.0`:
- multiple real hardware runs completed
- payment verification proven reliable
- failure/cancel/disconnect flows tested
- merchant setup documented
- no known order-integrity issue remains open
- refund scope is explicitly documented as either supported or manual/out-of-scope

---

## 25. Recommendation

Proceed with a **standalone WordPress plugin** and treat the current architecture as the **leading candidate**, not the final design.

The immediate next step is **M1 discovery**, focused on:

1. locking the auth model and scopes
2. naming and testing the server-side verification mechanism
3. defining the disconnect/reconciliation flow
4. defining idempotency and retry rules
5. making an explicit refund-scope decision

If M1 validates the current direction, continue with a standalone plugin using:

- server-side auth handling
- browser-side Reader Connect WebSocket orchestration
- backend-owned payment attempts
- strict server-side payment verification before WooCommerce order completion

That gives the project the lowest-friction path from prototype to first beta while preserving payment integrity and matching existing WCPOS extension patterns.
