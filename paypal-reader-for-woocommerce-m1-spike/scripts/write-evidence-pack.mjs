import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

import { writeMarkdown } from '../src/evidence.mjs';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const evidenceRoot = resolve(scriptDir, '..');
const evidencePath = (relativePath) => resolve(evidenceRoot, relativePath);

const evidencePack = [
  [
    evidencePath('evidence/m1/scopes.md'),
    `# M1 scope status

## Current state
- Local implementation for the M1 spike is complete in this workspace.
- The evidence writer and markdown templates are present so live results can still be recorded later without changing the surrounding flow.
- A disposable mock OAuth and Reader Connect simulator now exercises the documented request and response shapes without requiring live credentials or hardware.

## Verification status
- Live auth verification: not executed in this session.
- Mock auth contract verification: covered by automated tests in this workspace.
- Link claim or reuse: mock-validated only in this session.
- Session configuration and status confirmation: mock-validated only in this session.
- Payment and cancellation verification: mock-validated only in this session.
- Reconciliation/browser-drop verification: not executed in this session.

## Scope note
This scope statement is limited to what was implemented locally, what was validated against the disposable mock, and what was not proven live. It does not claim any reader behavior that was not observed live.
`,
  ],
  [
    evidencePath('evidence/m1/auth-decision.md'),
    `# Auth decision

## Decision
- Provisional NO-GO.

## Basis for the decision
- Official docs still make self-hosted assertion grant the leading candidate for a private merchant-installed integration.
- This session mock-validated the assertion grant request shape and response envelope in the disposable spike workspace.
- This session did not execute live authentication against the Zettle environment.
- Link claim, session configuration, and status verification were also not executed live here.
- Payment verification was not executed live in this session.
- Without live observations, the auth path cannot be treated as proven.

## Evidence required to revisit
- Successful token acquisition in the target environment.
- Successful link claim or reuse against the intended reader.
- Successful session configuration and status confirmation.

## Revisit condition
Re-evaluate the decision only after the live auth and session evidence above has been captured.
`,
  ],
  [
    evidencePath('evidence/m1/mock-validation.md'),
    `# Mock validation

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
`,
  ],
  [
    evidencePath('evidence/m1/go-no-go.md'),
    `# M1 go / no-go

## Decision
- NO-GO for M1 in this session.

## Why
- Local implementation is complete in this workspace.
- Official docs and GitHub examples were distilled into a disposable mock simulator and exercised by automated tests.
- No live auth, link, session, status, payment, cancellation, or reconciliation evidence was captured here.
- Mock validation reduces uncertainty but is still not sufficient to mark the milestone complete.

## Required to flip to GO
- Live auth/link/session/status verification.
- At least one successful live payment run.
- Cancellation evidence if required by the plan/spec.
- Reconciliation/browser-drop evidence tied to the live run.

## Current status
This evidence pack reflects a doc-driven mock-validated offline state only. The missing live proof remains the blocker to a GO decision.
`,
  ],
];

// These live evidence files are intentionally left manual so future real-session
// captures are never overwritten by the pack generator.
const manualEvidenceFiles = [
  evidencePath('evidence/m1/payment-run-001.md'),
  evidencePath('evidence/m1/reconciliation-browser-drop.md'),
];

for (const [path, contents] of evidencePack) {
  await writeMarkdown(path, contents);
}

console.log(
  `Wrote ${evidencePack.length} generated evidence files; left ${manualEvidenceFiles.length} manual evidence files untouched.`,
);
