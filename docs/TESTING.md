# AICTE FDR Portal — Testing Suite & Security Verification

## 1. Automated Test Execution Commands

### Backend Tests (PHPUnit / Pest)
```bash
cd backend
php artisan test
```

Specific security and compliance test filters:
```bash
# Run Core Security & Cryptographic Tamper Detection Tests
php artisan test --filter=AicteFdrCoreSecurityTest

# Run Lifecycle & Compliance Tests
php artisan test --filter=AicteFdrLifecycleAndComplianceTest
```

### Frontend Tests (Vitest + React Testing Library)
```bash
cd frontend
npm test
# Or with coverage:
npx vitest run --coverage
```

### Frontend Production Build
```bash
cd frontend
npm run build
```

## 2. Test Coverage Summary

| Test Case | Type | Assertion | Actual Result |
|---|---|---|---|
| User Authentication & Token Issuance | Feature | Returns valid Sanctum Bearer token | **PASS (200 OK)** |
| Cross-Tenant Data Isolation | Security | Institute A user blocked from viewing Institute B FDR | **PASS (403 Forbidden)** |
| Unauthorized Document Download | Security | User blocked from downloading another institute's document | **PASS (403 Forbidden)** |
| Separation of Duties Policy | Security | Institutional Submitter blocked from approving own FDR | **PASS (422 Unprocessable)** |
| Envelope Encryption Tamper Detection | Cryptographic | 1-bit corrupted ciphertext raises decryption failure | **PASS (RuntimeException)** |
| Discrepancy Detection (Amount Mismatch) | Regulatory | Flags `AMOUNT_MISMATCH` between OCR and submission | **PASS (High Priority Discrepancy)** |
| Simulated Bank Verification Declaration | Regulatory | Mock requests explicitly state `simulated_flag: true` | **PASS (True)** |
| No Document Submission Block | Validation | FDR cannot transition to `submitted` without document | **PASS (422 Unprocessable)** |
| Admin Restricted Institution Creation | Authorization | Regular submitter cannot create new institutions | **PASS (403 Forbidden)** |
| CSV Formula Injection Sanitization | Security | Dangerous leading characters (`=`, `+`, `-`, `@`) escaped | **PASS (Sanitized with ')** |
| React Login View & Persona Selector | UI Unit | Quick switcher and portal headers render | **PASS (Vitest)** |
| React Navbar & Notification Bell | UI Unit | Portal title, SIH tag, and bell component render | **PASS (Vitest)** |
