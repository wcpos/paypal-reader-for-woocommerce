# PayPal Reader for WooCommerce / WCPOS
## M1 Discovery Brief and Provisional v1 Design

- **Status:** M1 NO-GO recorded (doc/GitHub-informed mock validation complete; live proof still missing)
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

This session used official Zettle / PayPal Reader Connect documentation as the source-backed input for the M1 spike, but it did **not** include live merchant credentials or live reader execution. The local spike implementation and evidence scaffolding were completed in this workspace; the live dependency checks remain outstanding.

### References reviewed
- Stylora Reader Connect service (secondary pattern reference only, not authoritative)
  https://github.com/Tamerb86/stylora/blob/main/server/services/reader-connect.ts

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

### Documented facts carried into the design
- Official Zettle docs describe two relevant grant types for this investigation: **authorization code** for public integrations and **assertion grant** for private integrations.
- Official Zettle docs distinguish **self-hosted apps** and **partner-hosted apps**.
- Official Zettle docs say assertion grant uses `grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer` against `https://oauth.zettle.com/token`.
- Official Zettle docs say assertion-grant access tokens have **no refresh token** and a **7200 second** lifetime.
- Reader Connect documentation exposes the integrator link/session/payment flow that the plugin would need to exercise live before M2.
- Reader Connect docs show `READ:USERINFO` and `WRITE:USERINFO` for linking/session work and `READ:PAYMENT` plus `WRITE:PAYMENT` for payment work, but this session did not live-validate that scope set end to end.
- Reader Connect docs show that session configuration returns `location`, `authorized`, and `failed`, and that the returned `location` is the WebSocket URL to use as issued.
- Reader Connect docs describe payment/result identifiers such as `internalTraceId`, `CARD_PAYMENT_UUID`, `trackingId`, and `checkoutUUID`; in this session those remain documented lookup candidates only, not proven verification references.

### Session limit that controls this spec
No live `ZETTLE_*` credentials or hardware were available here, so no live auth, link, session, status, payment, cancel, or reconciliation checks were executed. Instead, this session used official docs plus GitHub examples to build a disposable mock OAuth/Reader Connect simulator and run contract tests against it. The design therefore still stops at the M1 gate with a documented **NO-GO** for live implementation work.

---

## 7. M1 discovery spike

## 7.1 What this session completed
This workspace now contains the local M1 spike implementation, evidence scaffolding, and a doc-driven mock simulator for the Reader Connect flow. That includes the disposable scripts, harness code, markdown evidence templates, a mock OAuth/Reader Connect server, and transcript tests for status, payment success, and ambiguous cancellation behavior.

## 7.2 What this session did not complete
This session did **not** have live `ZETTLE_*` credentials or a physical reader, so it did **not** execute any live auth, link claim/reuse, session creation, WebSocket status, payment, cancel, or browser-drop reconciliation checks. No live reader transcript was captured here.

## 7.3 Actual M1 outcome for this session
The spike answered the local implementation question more deeply than before by validating request/response shapes against a disposable mock contract. It still did **not** satisfy the M1 proof requirement. Because the live evidence was still missing at the end of the session, M1 ended here at the gate with **NO-GO** and M2 must not start from this session alone.

## 7.4 Outputs now present
The following outputs exist after this session:

1. local spike code for the documented auth/link/session/payment investigation path
2. evidence-pack scaffolding for auth, scopes, mock validation, payment run, reconciliation, and go/no-go recording
3. a disposable mock OAuth/Reader Connect simulator with contract tests
4. a written record that live proof is still missing
5. an updated design spec that converts the session findings into explicit stop/go language

### Evidence-pack inventory from this session
Present now:
- `paypal-reader-for-woocommerce-m1-spike/evidence/m1/scopes.md`
- `paypal-reader-for-woocommerce-m1-spike/evidence/m1/auth-decision.md`
- `paypal-reader-for-woocommerce-m1-spike/evidence/m1/mock-validation.md`
- `paypal-reader-for-woocommerce-m1-spike/evidence/m1/payment-run-001.md`
- `paypal-reader-for-woocommerce-m1-spike/evidence/m1/reconciliation-browser-drop.md`
- `paypal-reader-for-woocommerce-m1-spike/evidence/m1/go-no-go.md`

Present only as offline/no-live-proof records:
- `payment-run-001.md`
- `reconciliation-browser-drop.md`

Still missing before M2:
- a real auth transcript
- a real link/session/status transcript
- a real verified payment transcript
- a real reconciliation/disconnect transcript

## 7.5 Outputs still required before M2
The following M1 outputs remain unresolved and must be produced by a later live run before M2:

1. a live-tested auth model decision with real merchant credentials
2. a live-tested exact scope list
3. a named and live-tested server-side verification source of truth
4. a live-tested browser disconnect / reconciliation path
5. a live-backed confirmation of the retry rule after ambiguity
6. a live-backed confirmation of whether any refund API is safe enough for v1
7. at least one successful live payment transcript tied to verification evidence

## 7.6 Hard gate status
The M1 hard gate remains closed. This session proved the workspace is prepared for both live testing later and meaningful no-hardware contract validation now, but not that the PayPal Reader / Zettle integration path is production-viable.

---

## 8. Critical open design decisions

## 8.1 Auth model

### Session finding
The auth model is **not proven** in this session. Official docs still make **self-hosted assertion grant** the leading candidate for a merchant-installed private integration, while **authorization code** remains the documented public-integration grant and **partner-hosted** remains a separate documented app model.

### Source-backed auth facts
- Assertion grant is documented for private integrations / self-hosted apps.
- The token request uses `grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer` against `https://oauth.zettle.com/token`.
- Assertion-grant tokens are documented as having no refresh token and a 7200 second lifetime.

### What this session actually proved
- The local spike code and evidence scaffolding for the assertion-grant path were completed in this workspace.
- No live `ZETTLE_*` credentials were available.
- No live token exchange, link, session, status, or payment execution was run here.

### Current decision at the M1 gate
Treat `self-hosted assertion grant` as the **documented leading candidate only**, not an approved implementation decision. A later live run must still prove whether self-hosted assertion grant is sufficient or whether a partner-hosted/public model is required.

## 8.2 Verification source of truth

### Session finding
The verification source of truth remains **unresolved**. This session did not execute any authoritative server-side lookup for a completed payment.

### What is established
- Browser-visible references such as `CARD_PAYMENT_UUID`, `trackingId`, `checkoutUUID`, and `internalTraceId` are still treated as candidate lookup inputs only.
- No browser-delivered result may be treated as proof of payment.

### What is missing
- No payment UUID lookup was exercised.
- No Purchase API or alternate retrieval endpoint was exercised.
- No webhook or server-side event source was validated.

### Gate consequence
Because no authoritative verification mechanism was named **and** tested in this session, order completion remains blocked and this session ends with M1 **NO-GO**.

## 8.3 Browser disconnect and reconciliation

### Session finding
No live browser disconnect or reconciliation scenario was executed in this session. There was no live reader/browser session available to observe tab close, refresh, crash, or network-drop behavior.

### What is established locally
- The spike workspace includes scaffolding to record reconciliation evidence when a live run is available.
- The correct safety posture remains `reconciliation first, retry later`.

### Rule carried forward from this session
Until a later live run proves a narrower recovery path, any lost-client state after `payment_requested` must be treated as ambiguous: mark the attempt `reconciliation_required`, block new attempts, and do not reopen checkout until server-side reconciliation proves the prior attempt unpaid.

### Gate consequence
Because no live reconciliation evidence exists yet, this design area remains blocked at the M1 gate and contributes to the session NO-GO.

## 8.4 Idempotency and retries

### Session finding
No live retry or duplicate-attempt scenario was executed in this session, so the retry model is not proven by hardware evidence here.

### Design decision carried forward to the gate
Use a backend-owned attempt ID and reuse it as the Reader Connect `internalTraceId` for that attempt. If payment state becomes ambiguous, the retry rule is **not** to issue a new payment request immediately.

### Required rule after ambiguity
The rule from this session is `BLOCK_UNTIL_RECONCILED`: the cashier may not retry until the prior attempt is conclusively failed, cancelled, or reconciled as unpaid by the server-side source of truth.

### Gate consequence
This is the correct safety decision for now, but it remains live-unverified and therefore does not clear M1 on its own.

## 8.5 Refund scope

### Session finding
No live refund capability was investigated in this session. No refund API was exercised, and no WooCommerce-to-Zettle refund mapping was proven.

### Decision for the M1 gate
Refund scope remains **manual in v1** for this session. Merchant-facing documentation should continue to state that refunds are handled manually in PayPal/Zettle tools unless a later live investigation proves a safe server-side refund flow.

### Gate consequence
Refunds do not block the local spike itself, but the absence of live refund proof means the scope decision must stay conservative.

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
This session only confirmed the **documented** amount shape, not live market behavior. The design should still treat amounts as:

- WooCommerce order total converted to **integer minor units** before the Reader Connect request is built
- currency stored explicitly on the attempt record
- requested amount stored separately from any final settled amount

Reader Connect documentation describes the request amount in fractional monetary units, which aligns with an integer-minor-unit internal model. Live currency/store validation is still required before M2 because this session did not execute any real payment requests.

### 13.4 Tipping scope
No live tipping flow was investigated in this session. The only defensible M1-gate position is to keep tipping **out of the required checkout path**.

Session decision:
- default v1 behavior: **no tipping**
- do not make tipping a dependency for M2 or the first beta
- revisit only after the base auth/session/payment/verification path is proven live

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

### M1 gate status from this session
This session did **not** satisfy the release-entry criteria for M2 or beta work. The local spike implementation and evidence scaffolding are present, but the following proof is still missing:

- live auth validation
- live reader link validation
- live session creation validation
- live WebSocket status validation
- at least one live payment run
- a named and tested server-side verification lookup
- at least one live disconnect/reconciliation check

### Beta release criteria after a future GO
Before `0.1.0-beta.1`:
- a later M1 run has converted this session's NO-GO into a documented GO
- auth, linking, session creation, and status checks are validated live
- one successful verified payment on real hardware is documented
- the reconciliation path is tested at least once with live evidence
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

This session stops at the M1 gate with **NO-GO**. The correct recommendation is **do not start M2 or production plugin implementation from this session's evidence**.

### Final M1 decision
- Auth model: `self-hosted assertion grant remains the documented leading candidate, but it is not live-proven in this session`
- Required scopes: `documented candidate set only: READ:USERINFO, WRITE:USERINFO, READ:PAYMENT, WRITE:PAYMENT; not live-validated in this session`
- Verification source of truth: `unresolved; no authoritative server-side verification mechanism was exercised here`
- Retry rule after ambiguity: `BLOCK_UNTIL_RECONCILED`
- Refund scope: `manual in v1`

### Recommendation for the next session
Proceed only with a fresh live M1 run that has working `ZETTLE_*` credentials, a real linked reader, and explicit capture of auth, link, session, status, payment, cancel, and reconciliation evidence. Until that proof exists, keep the architecture as a documented candidate only and preserve the NO-GO status recorded here.
