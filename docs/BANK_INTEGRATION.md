# AICTE FDR Portal — Bank Verification Adapter Specification

## 1. Adapter Architecture
The portal is designed with an extensible adapter pattern for verifying FDR existence, lien status, and deposit authenticity against issuing banks.

```
       Verification Officer
                 │
                 ▼
 ┌────────────────────────────────┐
 │   BankVerificationService      │
 └────────────────────────────────┘
                 │
   ┌─────────────┴─────────────┐
   ▼                           ▼
[MockBankVerificationAdapter]  [Scheduled Commercial Bank API]
(Demonstration & Local Dev)    (Production SFTP / Core Banking API)
```

## 2. Mock Adapter Behavior (SIH Demonstration)
- The mock adapter provides high-fidelity simulation of an authorized Core Banking System (CBS) inquiry.
- **Transparency Mandate**:
  - All mock responses explicitly set `simulated_flag = true`.
  - The UI and API clearly label responses as `SIMULATED ADAPTER — Demonstration Data`.
  - Simulated outcomes can be selected for testing: `confirmed`, `mismatch`, `not_found`.

## 3. Production Banking Gateway Requirements
For integration with real bank gateways:
1. Mutual TLS (mTLS) with client certificates.
2. Signed API payloads using RSA-SHA256 / SHA-512 signatures.
3. Replay attack protection using monotonic request nonces and timestamp windows.
4. Dedicated IP whitelisting between AICTE server and Bank CBS gateways.
