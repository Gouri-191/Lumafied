# AICTE FDR Portal — Role-Based User Guide

## 1. Role: Institution Submitter
- **Login**: Use institutional credentials (e.g. `submitter.apex@institute.edu`).
- **Create Submission**:
  1. Click **New Submission** in the top navigation or sidebar.
  2. Upload the scanned Fixed Deposit Receipt (PDF, PNG, JPG).
  3. Enter Bank Name, Branch, IFSC, FDR Number, Principal Amount, Dates, and Beneficiary.
  4. Click **Save & Proceed to Review**.
- **Submit for Verification**:
  - Open the FDR record from the register.
  - Review the extracted OCR values and verify they match the receipt.
  - Click **Submit for Verification**. The FDR status advances to `SUBMITTED`.
- **Respond to Clarifications**:
  - If a Verification Officer requires more details, status changes to `CLARIFICATION_REQUESTED`.
  - Enter the requested explanation in the clarification box and submit.

## 2. Role: Verification Officer
- **Login**: Use officer credentials (e.g. `officer@aicte-india.org`).
- **Scrutinize Cases**:
  1. Navigate to **Verification Work Queue**.
  2. Select an assigned case to open the dossier.
  3. Inspect the original physical document via **Download** or verify the cryptographic envelope via **Verify Tag**.
  4. Compare the **Side-by-Side OCR Extraction** against the submitted figures.
  5. Inspect the **Discrepancy Matrix**. Resolve any minor issues with written remarks.
  6. Trigger **Bank Verification Inquiry** to simulate or confirm the CBS lien.
  7. Submit recommendation: **Recommend Approval** or **Recommend Rejection**.

## 3. Role: Approving Authority
- **Login**: Use authority credentials (e.g. `authority@aicte-india.org`).
- **Render Final Decision**:
  1. Open an FDR in `UNDER_REVIEW` status.
  2. Inspect the Verification Officer's recommendation and recorded evidence.
  3. Click **Approve FDR** or **Reject FDR**.
  4. Enter official remarks and confirm. The decision is permanently logged in the audit trail.
  5. *Note*: The system enforces **Separation of Duties**; you cannot approve an FDR that you submitted.

## 4. Role: Independent Auditor
- **Login**: Use auditor credentials (e.g. `auditor@aicte-india.org`).
- **Compliance Audit**:
  1. Access **Immutable Audit Trail** in the sidebar.
  2. Filter by actor email, action type, or date.
  3. Click **View Diff** on any entry to inspect JSON payloads, IP addresses, and state before/after diffs.
  4. Export CSV reports from the FDR register for external reconciliation.
