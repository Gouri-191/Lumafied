# AICTE FDR Portal — OCR Pipeline & Data Scrutiny Specification

## 1. Pipeline Overview
The OCR pipeline automates the extraction of key regulatory and financial data points from scanned Fixed Deposit Receipts.

### 2. Extracted Fields & Heuristics
- **Bank Name**: Detected through normalized dictionary matching across approved scheduled commercial banks.
- **Branch & IFSC Code**: Extracted via 11-character Indian Financial System Code regex (`^[A-Z]{4}0[A-Z0-9]{6}$`).
- **FDR Certificate / Account Number**: Parsed via certificate/receipt pattern matchers.
- **Principal Amount**: Normalized numeric extraction stripping Indian currency notation (`₹`, `INR`, `Rs.`, commas).
- **Date of Issue & Date of Maturity**: Date normalization converting diverse formats (`DD/MM/YYYY`, `YYYY-MM-DD`) into ISO-8601 standard `YYYY-MM-DD`.
- **Beneficiary / Joint Holder**: Identifies statutory lien notation in favor of AICTE.

## 3. Confidence Scoring & Human Scrutiny
- Each field extraction assigns a confidence score between `0.00` and `1.00`.
- Overall document confidence is calculated as the harmonic mean of individual field confidences.
- **Critical Business Rule**: High OCR confidence never replaces human judgment. Final approval is made exclusively by an authorized human authority.
- Reviewers can submit human corrections (`submitCorrections`), which triggers automatic re-evaluation of discrepancy rules and logs a permanent audit entry.
