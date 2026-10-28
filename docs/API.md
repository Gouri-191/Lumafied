# AICTE FDR Portal — REST API Specification (`/api/v1`)
## Problem Statement SIH26265

All endpoints require standard `Accept: application/json`. Protected endpoints require `Authorization: Bearer <sanctum_token>`.

### 1. Authentication Endpoints
- `POST /api/v1/auth/login`
  - Body: `{ email: string, password: string }`
  - Returns: `{ token: string, user: User }`
- `GET /api/v1/auth/me`
  - Returns: Authenticated user profile and institutional association.
- `POST /api/v1/auth/logout`
  - Revokes current personal access token.
- `POST /api/v1/auth/change-password`
  - Body: `{ current_password: string, new_password: string, new_password_confirmation: string }`

### 2. Institutions
- `GET /api/v1/institutions`
  - Query params: `search`, `category`, `page`, `per_page`.
- `POST /api/v1/institutions` (Admin only)
  - Body: `{ aicte_id, name, category, address, city, state, pincode, contact_email, contact_phone }`
- `GET /api/v1/institutions/{id}`
  - Returns: Institution profile with FDR counts.
- `PATCH /api/v1/institutions/{id}`
- `GET /api/v1/institutions/{id}/users`

### 3. FDR Management
- `GET /api/v1/fdrs`
  - Query params: `search`, `lifecycle_status`, `risk_level`, `maturing_soon`, `institution_id`.
  - Enforces strict tenant isolation: Submitter only receives own institution records.
- `POST /api/v1/fdrs`
  - Body: `{ bank_name, branch_name, ifsc_code, fdr_number, principal_amount, interest_rate, issue_date, maturity_date, tenure_months, holder_name, beneficiary_name, purpose }`
  - Creates draft FDR record with tracking ID `FDR-YYYY-XXXXXXXX`.
- `GET /api/v1/fdrs/{id}`
  - Returns: Full FDR dossier including documents, OCR extraction, discrepancies, bank requests, status history.
- `PATCH /api/v1/fdrs/{id}`
  - Allowed only in `draft` or `clarification_requested` state.
- `POST /api/v1/fdrs/{id}/submit`
  - Transitions `draft` to `submitted`. Triggers verification case generation and automated discrepancy validation engine. Requires at least one uploaded document.
- `POST /api/v1/fdrs/{id}/validate`
  - Triggers automated compliance checks against business rules.
- `GET /api/v1/fdrs/{id}/history`
  - Returns state machine audit history.
- `GET /api/v1/fdrs/{id}/discrepancies`
  - Returns detected discrepancies.
- `POST /api/v1/fdrs/{id}/discrepancies/{discrepancyId}/resolve`
  - Body: `{ resolution_status: 'resolved' | 'waived', resolution_notes: string }`

### 4. Cryptographic Document Management
- `POST /api/v1/fdrs/{id}/documents`
  - Multipart form upload with envelope encryption (AES-256-GCM). Computes SHA-256 hash. Dispatches OCR extraction.
- `GET /api/v1/documents/{id}/download`
  - Authenticates user session, decrypts ciphertext in-memory using unwrapped DEK and GCM auth tag, streams plaintext file to browser.
- `GET /api/v1/documents/{id}/integrity`
  - Validates GCM authentication tag and SHA-256 integrity hash.

### 5. OCR & Data Scrutiny
- `GET /api/v1/fdrs/{id}/ocr-results`
  - Returns parsed OCR key-value pairs and confidence scores.
- `POST /api/v1/fdrs/{id}/ocr-results/{ocrId}/corrections`
  - Body: `{ corrected_data: object, correction_reason: string }`

### 6. Verification Case Management
- `GET /api/v1/verification-cases`
- `GET /api/v1/verification-cases/{id}`
- `POST /api/v1/verification-cases/{id}/assign` (AICTE Admin only)
  - Body: `{ officer_id: number, priority: string, due_date: string }`
- `POST /api/v1/verification-cases/{id}/clarify`
  - For Officer: asks question (`{ clarification_text }`).
  - For Submitter: provides response (`{ clarification_response }`).
- `POST /api/v1/verification-cases/{id}/recommend` (Verification Officer only)
  - Body: `{ recommendation: 'approve' | 'reject', notes: string }`
- `POST /api/v1/verification-cases/{id}/approve` (Approving Authority only)
  - Body: `{ comments: string }`. Strictly blocks submitter self-approval!
- `POST /api/v1/verification-cases/{id}/reject` (Approving Authority only)

### 7. Bank Verification Integration
- `POST /api/v1/fdrs/{id}/bank-verification`
  - Body: `{ simulate_outcome: 'confirmed' | 'mismatch' | 'not_found' }`
  - Returns: Simulated CBS confirmation record with `simulated_flag: true`.
- `GET /api/v1/bank-verifications/{id}`

### 8. Lifecycle & Maturity
- `GET /api/v1/lifecycle/maturity-calendar`
  - Returns FDRs grouped by: `expired`, `maturing_30_days`, `maturing_90_days`, `active_healthy`.
- `POST /api/v1/fdrs/{id}/request-release`
  - Body: `{ reason: string, no_dues_certificate_ref: string }`
- `POST /api/v1/lifecycle/releases/{id}/approve`

### 9. Dashboards & Reports
- `GET /api/v1/reports/summary`
  - Returns aggregate capital secured, institutions, pending reviews, open discrepancies.
- `GET /api/v1/reports/export-csv`
  - Streams CSV with formula injection sanitization (`'` prepended to formula characters).

### 10. Immutable Audit Trail
- `GET /api/v1/audit-events`
  - Restricted to Auditor and AICTE Admins. Filterable by action, actor, resource.
