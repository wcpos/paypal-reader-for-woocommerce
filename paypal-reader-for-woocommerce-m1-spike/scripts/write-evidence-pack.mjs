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
- The evidence writer and markdown templates are present so live results can be recorded without changing the surrounding flow.

## Verification status
- Live auth verification: not executed in this session.
- Link claim or reuse: not executed in this session.
- Session configuration and status confirmation: not executed in this session.
- Payment and cancellation verification: not executed in this session.
- Reconciliation/browser-drop verification: not executed in this session.

## Scope note
This scope statement is limited to what was implemented locally and what was not proven live. It does not claim any reader behavior that was not observed in this session.
`,
  ],
  [
    evidencePath('evidence/m1/auth-decision.md'),
    `# Auth decision

## Decision
- Provisional NO-GO.

## Basis for the decision
- Local implementation is complete in this workspace.
- This session did not execute live authentication against the reader environment.
- Link claim, session configuration, and status verification were also not executed here.
- Payment verification was not executed in this session.
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
    evidencePath('evidence/m1/go-no-go.md'),
    `# M1 go / no-go

## Decision
- NO-GO for M1 in this session.

## Why
- Local implementation is complete in this workspace.
- No live auth, link, session, status, payment, cancellation, or reconciliation evidence was captured here.
- Local implementation alone is not sufficient to mark the milestone complete.

## Required to flip to GO
- Live auth/link/session/status verification.
- At least one successful live payment run.
- Cancellation evidence if required by the plan/spec.
- Reconciliation/browser-drop evidence tied to the live run.

## Current status
This evidence pack reflects the present offline state only. The missing live proof remains the blocker to a GO decision.
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
