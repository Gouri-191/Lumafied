# AICTE Fixed Deposit Receipt (FDR) Management, Verification & Compliance Portal
## Requirements Specification (SIH26265)

### 1. Problem Overview
Technical institutions seeking approvals or extensions from the All India Council for Technical Education (AICTE) are statutorily required to maintain Fixed Deposit Receipts (FDRs) as financial security / endowment funds. In the traditional workflow:
1. Paper receipts or scanned PDFs are submitted manually or through ad-hoc portals.
2. Verification against issuing banks is slow, manual, and prone to forged documents or altered numbers.
3. Premature liquidation, lien breaches, or silent expirations occur without AICTE awareness.
4. Review processes lack automated validation, side-by-side discrepancy review, and tamper-evident audit trails.

### 2. Functional Requirements
- **FR-01 Authentication & Role-Based Access Control**:
  - Multi-role support: System Administrator, AICTE Regulatory Admin, Institution Admin, Institution Submitter, Verification Officer, Approving Authority, Auditor, Bank Integration API Actor.
  - Multi-tenant institution isolation: Institutions can only view and manage their own submissions.
- **FR-02 Institution Management**:
  - Registration, approval, institutional profile (AICTE ID, Address, Contact, Category).
- **FR-03 FDR Registry & Lifecycle Tracking**:
  - Maintain FDR metadata: FDR number, Bank name, Branch, IFSC/Routing, Principal amount, Currency, Issue date, Maturity date, Tenure, Interest rate, Beneficiary, Joint name (AICTE + Institute), Purpose.
  - State machine with distinct stages:
    - `draft`, `submitted`, `under_review`, `clarification_requested`, `resubmitted`, `verified`, `approved`, `rejected`, `renewal_pending`, `renewed`, `maturing`, `matured`, `release_requested`, `released`, `closed`.
- **FR-04 Secure Document Storage & Envelope Encryption**:
  - File signature, MIME-type, size and page-limit validation.
  - Envelope encryption using AES-256-GCM with unique DEK wrapped by KEK.
  - Cryptographic hashing (SHA-256) of raw documents and tampering detection.
  - Nonce and authentication tag persistence.
- **FR-05 OCR & Automated Field Extraction Pipeline**:
  - Document ingestion into background job queue.
  - Field extraction: Bank name, FDR number, Amount, Issue Date, Maturity Date, Account name.
  - Confidence scoring and human correction interface with full correction auditing.
- **FR-06 Validation & Discrepancy Detection Engine**:
  - Configurable rules: amount mismatch, date validity (maturity > issue), bank mismatch, duplicate FDR number across institutions, duplicate document hash, approaching maturity (<90 days, <30 days).
  - Severity categories: Info, Warning, Requires Review, High-Priority Discrepancy, Verification Blocked.
- **FR-07 Verification Case Management & Human Oversight**:
  - Case generation, assignment to Verification Officers, reviewer notes, clarification requests.
  - Critical Rule: Automated systems flag and recommend; human authorities make final approvals/rejections.
- **FR-08 Bank Verification Integration**:
  - Adapter architecture for bank API / SFTP verification.
  - Production-ready mock adapter for development/demo clearly labeled as simulated.
- **FR-09 Approval Workflow & Separation of Duties**:
  - Multi-tier review: Submitter cannot approve their own submission.
  - Atomicity of state transitions with transactional outbox.
- **FR-10 Notifications & Scheduled Reminders**:
  - In-app notification center for case updates, clarification requests, and maturity alerts.
- **FR-11 Release, Renewal & Closure Workflow**:
  - Formal renewal document submission and verification.
  - Release request processing with justification and no-dues verification.
- **FR-12 Analytics & Reporting**:
  - Aggregated metrics, maturity timeline, institution compliance metrics, secure CSV export with formula injection sanitization.
- **FR-13 Tamper-Evident Audit Logging**:
  - Append-only structured audit events capturing actor, action, resource, timestamp, IP, and state diff.
