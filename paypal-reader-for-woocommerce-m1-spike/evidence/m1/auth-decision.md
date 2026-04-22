# Auth decision

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
