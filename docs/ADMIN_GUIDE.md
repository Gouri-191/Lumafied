# AICTE FDR Portal — System & Regulatory Administrator Guide

## 1. System Administration Overview
System Administrators (`admin@aicte-india.org`) and Regulatory Administrators (`regulator@aicte-india.org`) maintain system health, institution directory, user roles, and verification assignments.

## 2. Managing Educational Institutions
- Navigate to **Institutions Directory**.
- Click **Register Institution**.
- Fill in:
  - Official AICTE Permanent Institute ID (e.g. `1-109823451`).
  - Legal Institutional Name.
  - Category (Engineering, Management, Polytechnic, Pharmacy, Architecture).
  - Geographic details and official email address.
- Click **Register**. The institution is now available for institutional users to submit FDRs against.

## 3. Assigning Cases in Verification Queue
- Navigate to **Verification Work Queue**.
- Click **Assign** on any open case.
- Select the designated Verification Officer and assign priority (`Normal`, `High`, `Urgent`).
- Set a due date for compliance review.
- The case status transitions to `UNDER_REVIEW`, and the assigned officer is notified.

## 4. Operational Monitoring & Security Audit
- Regularly review the **Immutable Audit Trail** (`/audit-logs`) for:
  - `DOCUMENT_ACCESS_DENIED` events indicating potential unauthorized cross-tenant exploration attempts.
  - `DOCUMENT_TAMPER_DETECTED` events indicating possible tampering with on-disk ciphertexts.
  - High concentrations of `AMOUNT_MISMATCH` or `CROSS_TENANT_DUPLICATE_FDR` discrepancies.
- Monitor the **Maturity Calendar** for FDRs within the 30-day expiration window to issue statutory renewal notices.
