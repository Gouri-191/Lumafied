# AICTE FDR Portal — Hackathon Live Demonstration Script (SIH26265)

Follow this 5-minute step-by-step walkthrough during the presentation to Smart India Hackathon judges.

---

### Step 1: The Problem & System Dashboard (1 min)
1. Navigate to `http://localhost:5173/login`.
2. Click **AICTE Admin** on the quick persona switcher (`regulator@aicte-india.org`) and click **Sign in to Portal**.
3. Point out the **Secured Capital KPI** (e.g. ₹ 5.00 Cr in active approved deposits).
4. Point out the **Critical Anomaly Banner** highlighting high-risk cases.
5. Emphasize that all metrics are live aggregations computed directly from the MySQL 8 backend.

---

### Step 2: Multi-Tenant Institution Submission (1 min)
1. Click the logout icon in the top right.
2. Click **Apex Submitter** (`submitter.apex@institute.edu`) on the login switcher.
3. Show that the submitter only sees Apex Institute's FDRs (Tenant Isolation).
4. Click **New FDR Submission**.
5. Upload a receipt and input certificate details.
6. Submit the FDR: Show how the receipt is immediately envelope-encrypted with **AES-256-GCM** and stored with a unique SHA-256 digest in private storage.

---

### Step 3: Automated OCR Extraction & Discrepancy Detection (1 min)
1. Open FDR `FDR-2026-APEX-02` (Punjab National Bank).
2. Scroll to the **Side-by-Side OCR Scrutiny Table**:
   - Show how the system extracted `INR 25,00,000.00` with 94% confidence.
   - Show the discrepancy highlighted against the submitted value of `INR 35,00,000.00`.
3. Point to the **Discrepancy Matrix**:
   - `AMOUNT_MISMATCH` flagged with **High Priority**.
   - Show that automated AI/OCR **does not make the decision**—it flags findings for human scrutiny.

---

### Step 4: Verification Officer Scrutiny & Bank CBS Inquiry (1 min)
1. Switch to **Verification Officer** (`officer@aicte-india.org`).
2. Open `FDR-2026-APEX-01` in the verification queue.
3. Click **Verify Tag** on the encrypted document vault to prove cryptographic tamper-detection.
4. Scroll to **Bank Verification Evidence**:
   - Point out the clear **SIMULATED ADAPTER** label (meeting the strict rule against fabricating bank confirmations).
   - Select `Confirmed` and click **Dispatch Bank Verification Inquiry**.
   - Show the recorded CBS reference number: `CBS-CONF-...`.
5. Under Officer Recommendation, click **Recommend Approval**.

---

### Step 5: Separation of Duties & Approving Authority Verdict (1 min)
1. Switch to **Approving Authority** (`authority@aicte-india.org`).
2. Open the recommended case.
3. Demonstrate that the Approving Authority has the exclusive power to render the final verdict.
4. Click **Approve FDR**, enter official remarks, and confirm.
5. Show the updated state machine timeline: `submitted` -> `under_review` -> `approved`.
6. Switch to **Independent Auditor** (`auditor@aicte-india.org`) and show the **Immutable Audit Trail** capturing every timestamp, actor, IP address, and payload diff.
