# AICTE FDR Portal — Security Architecture & Threat Model (OWASP ASVS L2)

## 1. Threat Modeling & Security Controls

| Threat Category | Potential Risk | Implemented Architecture Control | Verification Test |
|---|---|---|---|
| **Broken Access Control** | Institute A accesses Institute B's financial deposits | Model & policy-level tenant boundary validation in controllers and queries (`canAccessInstitution`) | `test_cross_tenant_institution_isolation` (403 Forbidden) |
| **Separation of Duties Bypass** | Institutional Submitter approves their own submission | Atomic check in `FdrLifecycleService::renderDecision` ensuring `created_by !== authority_id` | `test_separation_of_duties_prevents_submitter_self_approval` (422) |
| **Document Tampering in Storage** | Unauthorized modification of stored FDR ciphertext on disk | AES-256-GCM Envelope Encryption with 128-bit authentication tag. Tampering raises `RuntimeException` | `test_envelope_encryption_detects_ciphertext_tampering` |
| **Unauthorized File Download** | Document ID guessing (IDOR) | Controller checks user institution ID against document owner before decrypting in memory | `test_unauthorized_document_download_rejected` (403) |
| **Cross-Tenant Duplicate FDRs** | Institution submits an identical FDR certificate already registered by another college | Discrepancy detection engine queries global database for duplicate numbers outside tenant boundary | Automated rule `CROSS_TENANT_DUPLICATE_FDR` |
| **CSV Formula Injection** | Attacker crafts FDR field starting with `=cmd` to execute arbitrary code in Excel | `ReportController::sanitizeCsvCell` prepends `'` to cells starting with `=`, `+`, `-`, `@`, `\t` | `test_csv_export_sanitizes_formula_injection` |
| **Unsafe File Uploads** | Web shells or executable uploads masquerading as PDFs | MIME-type, extension, and file size validation (max 15MB). Storage outside public web root in private directory | Document validation pipeline |
| **Audit Log Tampering** | Malicious insider deleting historical audit logs | Database schema has no update/delete APIs for `audit_events`. Only append operations permitted | `AuditEvent` model immutability |

## 2. Authentication & Session Security
- API tokens managed through Laravel Sanctum with cryptographic SHA-256 token hashing in `personal_access_tokens`.
- Password hashing using Bcrypt with 12 rounds.
- Session revocation on logout.
- Inactive user accounts locked and denied authentication.

## 3. Data Protection in Transit and at Rest
- In Transit: Requires HTTPS (TLS 1.3 preferred).
- At Rest: AES-256-GCM authenticated encryption for document vault.
- Database: Masked account references for confidential banking information.
