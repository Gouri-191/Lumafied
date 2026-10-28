# AICTE FDR Portal — Requirements Traceability Matrix (RTM)
## Smart India Hackathon — Problem Statement SIH26265

| Req ID | Requirement Description | Implemented Module / File | Database Entities | Automated Tests | Verification Evidence |
|---|---|---|---|---|---|
| **FR-01** | Authentication & RBAC | `AuthController.php`, `AuthContext.tsx`, `User.php` | `users`, `personal_access_tokens` | `test_user_authentication_and_token_issuance` | Token issuance, role enforcement, session revocation |
| **FR-02** | Tenant Isolation | `FdrController.php`, `FdrPolicy.php`, `InstitutionController.php` | `institutions`, `users` | `test_cross_tenant_institution_isolation` | 403 Forbidden returned when user attempts to access other institute records |
| **FR-03** | FDR Registry & Lifecycle | `FdrController.php`, `FdrLifecycleService.php`, `FdrList.tsx`, `FdrDetail.tsx` | `fdrs`, `fdr_status_history` | `test_cannot_submit_fdr_without_supporting_document` | State machine: `draft` -> `submitted` -> `under_review` -> `approved` |
| **FR-04** | Envelope Encryption (AES-256-GCM) | `EnvelopeEncryptionService.php`, `DocumentController.php` | `documents` (DEK, nonce, auth tag, sha256) | `test_envelope_encryption_detects_ciphertext_tampering` | OpenSSL AES-256-GCM with master KEK; bitwise tampering raises exception |
| **FR-05** | Unauthorized Document Access Rejection | `DocumentController.php`, `DocumentPolicy.php` | `documents`, `institutions` | `test_unauthorized_document_download_rejected` | Access denied 403 on cross-tenant document download |
| **FR-06** | OCR Field Extraction | `OcrExtractionService.php`, `OcrController.php` | `ocr_extractions` | Feature verification | Side-by-side comparison with confidence metrics & human corrections |
| **FR-07** | Automated Discrepancy Detection | `DiscrepancyDetectionEngine.php`, `FdrDetail.tsx` | `validation_runs`, `discrepancies` | `test_discrepancy_detection_identifies_financial_mismatch` | Flags `AMOUNT_MISMATCH`, `CROSS_TENANT_DUPLICATE_FDR`, date errors |
| **FR-08** | Verification Case Management | `VerificationCaseController.php`, `VerificationQueue.tsx` | `verification_cases` | Feature verification | Assign officer, record priority, submit officer recommendations |
| **FR-09** | Bank Verification Adapter | `BankVerificationService.php`, `BankVerificationController.php` | `bank_verification_requests` | `test_bank_verification_declares_simulation_flag` | Clearly labeled simulated adapter with CBS evidence references |
| **FR-10** | Separation of Duties | `FdrLifecycleService.php`, `FdrDetail.tsx` | `fdrs`, `fdr_status_history` | `test_separation_of_duties_prevents_submitter_self_approval` | Submitter cannot approve own FDR (422 / 403) |
| **FR-11** | Maturity Calendar & Alerts | `LifecycleController.php`, `MaturityCalendar.tsx` | `fdrs`, `notifications` | Feature verification | Color-coded countdown for &le;30 days, &le;90 days, and expired |
| **FR-12** | CSV Export & Formula Protection | `ReportController.php`, `FdrList.tsx` | `fdrs` | `test_csv_export_sanitizes_formula_injection` | Strips/escapes leading `=`, `+`, `-`, `@` to prevent spreadsheet injection |
| **FR-13** | Immutable Audit Trail | `AuditService.php`, `AuditController.php`, `AuditLogs.tsx` | `audit_events` | Append-only DB schema | Captures event UUID, actor, IP, timestamp, and JSON before/after state diff |
| **FR-14** | Admin Restricted Institution Registration | `InstitutionController.php`, `Institutions.tsx` | `institutions` | `test_institution_registration_restricted_to_admin` | Regular submitters blocked (403); Admins succeed (201) |
| **FR-15** | Responsive Web UI & Persona Switcher | `App.tsx`, `Navbar.tsx`, `Sidebar.tsx`, `Login.tsx` | React 19 + Tailwind v4 | `Login.test.tsx`, `Navbar.test.tsx` (Vitest) | Accessible enterprise portal with 1-click persona switcher for judges |
