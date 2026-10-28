# AICTE FDR Portal — Lifecycle & Verification Workflow Specification

## 1. Lifecycle State Machine
The FDR lifecycle maintains strict state progression:

```
[draft] ──(Upload receipt & Submit)──► [submitted]
                                            │
                                  (Assign Officer)
                                            ▼
                              ┌──────► [under_review]
                              │             │
        (Respond Clarification)   (Request Clarification)
                              │             ▼
                              └────── [clarification_requested]
                                            │
                                            ├─► (Officer Recommend)
                                            │
                              ┌─────────────┴─────────────┐
                              ▼                           ▼
                         [approved]                  [rejected]
                              │
                      (Approaching Due)
                              ▼
                         [maturing] ──► [matured]
                              │             │
                     (Renewal Request)  (Release Request)
                              │             │
                              ▼             ▼
                          [renewed]    [release_requested]
                                            │
                                    (Approve Release)
                                            ▼
                                        [released] ──► [closed]
```

## 2. Separation of Duties Policy
- **Institutional Submitter**: Can create, edit drafts, upload documents, and submit for verification.
- **Verification Officer**: Scrutinizes physical receipt, reviews OCR findings, resolves discrepancies with justification, requests clarification, and submits formal recommendations (`recommend_approve` / `recommend_reject`).
- **Approving Authority**: Has sole authority to render the final regulatory verdict (`approved` / `rejected`).
- **Critical Policy Guard**: A user who created/submitted an FDR is **strictly prohibited from approving their own submission**, even if they possess administrative credentials. This rule is enforced server-side inside `FdrLifecycleService::renderDecision`.

## 3. Discrepancy Resolution Protocol
- Automated rule checks run on submission.
- Detected discrepancies must either be resolved with documented justification or waived by an authorized officer prior to approving authority review.
- Every discrepancy resolution records: `resolved_by`, `resolution_notes`, `resolved_at`.
