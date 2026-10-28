# AICTE FDR Portal — Database Architecture & Entity Specification

## 1. Engine & Storage Design
- **Database Engine**: MySQL 8.4+ (InnoDB, UTF8mb4 Unicode 520ci)
- **Primary Keys**: Surrogate unsigned 64-bit BigInt (`id`) with indexed secondary business identifiers (`aicte_id`, `fdr_tracking_no`, `case_number`, `uuid`).
- **Financial Precision**: `DECIMAL(15, 2)` for monetary values (strictly avoids floating-point inaccuracies).
- **Concurrency & Transactions**: Pessimistic/Optimistic row locking with explicit database transactions during state transitions.
- **Audit Immutability**: `audit_events` and `fdr_status_history` are append-only.

## 2. Entity Dictionary

### `institutions`
- `id` (PK, BigInt unsigned)
- `aicte_id` (VARCHAR 50, Unique) — Official AICTE Permanent Institute ID.
- `name` (VARCHAR 200) — Registered Institutional Name.
- `category` (VARCHAR 100) — Engineering / Technology, Management, Polytechnic, etc.
- `address`, `city`, `state`, `pincode` (VARCHAR)
- `contact_email`, `contact_phone` (VARCHAR)
- `verification_status` (VARCHAR 30) — `verified`, `pending`, `suspended`.

### `users`
- `id` (PK, BigInt unsigned)
- `name`, `email` (Unique, VARCHAR 191), `password` (Hashed via Bcrypt / Argon2id).
- `role` (VARCHAR 50) — `system_admin`, `aicte_admin`, `institution_admin`, `institution_submitter`, `verification_officer`, `approving_authority`, `auditor`, `bank_integration`.
- `institution_id` (FK to `institutions.id`, Nullable for AICTE/system staff).
- `status` (VARCHAR 30) — `active`, `inactive`, `locked`.
- `designation`, `phone` (VARCHAR).

### `fdrs`
- `id` (PK, BigInt unsigned)
- `fdr_tracking_no` (VARCHAR 50, Unique) — Human-readable tracking number (e.g., `FDR-2026-XXXX`).
- `institution_id` (FK to `institutions.id`).
- `bank_name`, `branch_name`, `ifsc_code` (VARCHAR).
- `fdr_number` (VARCHAR 100) — Number printed on physical Fixed Deposit Receipt.
- `account_number_ref` (VARCHAR 100, Masked/Confidential).
- `principal_amount` (DECIMAL 15, 2).
- `currency` (VARCHAR 10, default `INR`).
- `interest_rate` (DECIMAL 5, 2).
- `issue_date` (DATE), `maturity_date` (DATE).
- `tenure_months` (INT).
- `holder_name` (VARCHAR 150) — Institute / Society name.
- `beneficiary_name` (VARCHAR 150) — E.g., "AICTE & College Joint Account".
- `purpose` (VARCHAR 200).
- `lifecycle_status` (VARCHAR 40) — `draft`, `submitted`, `under_review`, `clarification_requested`, `resubmitted`, `verified`, `approved`, `rejected`, `renewal_pending`, `renewed`, `maturing`, `matured`, `release_requested`, `released`, `closed`.
- `verification_status` (VARCHAR 40) — `pending`, `in_progress`, `verified_clean`, `verified_with_discrepancy`, `rejected`.
- `risk_level` (VARCHAR 30) — `low`, `medium`, `high`, `critical`.

### `documents`
- `id` (PK, BigInt unsigned)
- `uuid` (UUID, Unique) — Public reference ID for safe referencing.
- `fdr_id` (FK to `fdrs.id`, Nullable).
- `institution_id` (FK to `institutions.id`).
- `document_type` (VARCHAR 50) — `fdr_original`, `bank_confirmation_letter`, etc.
- `original_filename` (VARCHAR 255).
- `storage_path` (VARCHAR 255) — Random UUID storage filename in private vault.
- `file_size_bytes` (BigInt).
- `mime_type` (VARCHAR 100).
- `file_hash_sha256` (VARCHAR 64) — Cryptographic digest of uploaded file.
- `encryption_algorithm` (VARCHAR 40) — `AES-256-GCM`.
- `encrypted_dek` (TEXT) — Ephemeral Data Encryption Key wrapped by master KEK.
- `nonce` (VARCHAR 64) — 96-bit (12-byte) initialization vector (Base64).
- `auth_tag` (VARCHAR 64) — 128-bit (16-byte) authentication tag for tamper detection (Base64).
- `kek_version` (VARCHAR 20).
- `is_quarantined` (BOOLEAN).
- `scan_status` (VARCHAR 40) — `clean`, `quarantined`, `mock_bypassed`.

### `ocr_extractions`
- `id` (PK, BigInt unsigned)
- `document_id` (FK to `documents.id`), `fdr_id` (FK to `fdrs.id`).
- `status` (`pending`, `completed`, `failed`).
- `extracted_data` (JSON) — Parsed key-value pairs with confidence scores.
- `raw_text` (LONGTEXT) — Raw parsed text.
- `overall_confidence` (DECIMAL 5, 2).
- `corrected_data` (JSON, Nullable) — Human-audited overrides.
- `corrected_by` (FK to `users.id`), `corrected_at` (TIMESTAMP).

### `validation_runs` & `discrepancies`
- Links automated checks to FDR records.
- Rules: `AMOUNT_MISMATCH`, `DATE_INCONSISTENCY`, `DUPLICATE_FDR_CROSS_TENANT`, `APPROACHING_MATURITY`, etc.
- Severity levels: `info`, `warning`, `requires_review`, `high_priority`, `verification_blocked`.
- Resolution fields: `resolution_status`, `resolution_notes`, `resolved_by`, `resolved_at`.

### `verification_cases`
- `case_number` (VARCHAR 50, Unique).
- `fdr_id`, `institution_id`.
- `assigned_officer_id` (FK to `users.id`).
- `priority`, `status` (`open`, `under_review`, `clarification_pending`, `officer_recommended`, `closed`).
- `recommendation` (`approve`, `reject`, `request_clarification`).
- `clarification_text`, `clarification_response`.

### `bank_verification_requests`
- `request_reference` (VARCHAR 60, Unique).
- `fdr_id`, `bank_name`, `mode` (`mock`, `api`).
- `status` (`pending`, `confirmed`, `mismatch`, `not_found`, `failed`).
- `response_payload` (JSON).
- `simulated_flag` (BOOLEAN) — Explicitly declares test/mock status to prevent false claims of live banking links.

### `audit_events`
- `event_uuid` (VARCHAR 64, Unique).
- `user_id`, `institution_id`, `actor_email`, `actor_role`.
- `action`, `resource_type`, `resource_id`.
- `ip_address`, `user_agent`, `details` (JSON).
- `status` (`success`, `failure`, `blocked`).
- `created_at` (TIMESTAMP).
