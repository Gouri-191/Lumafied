# AICTE Fixed Deposit Receipt (FDR) Management, Verification & Compliance Portal
### Smart India Hackathon — Problem Statement SIH26265

![Build Status](https://img.shields.io/badge/Build-Passing-emerald)
![PHP](https://img.shields.io/badge/PHP-8.3.28-777BB4?logo=php)
![Laravel](https://img.shields.io/badge/Laravel-11%2F12-FF2D20?logo=laravel)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react)
![TypeScript](https://img.shields.io/badge/TypeScript-5.8-3178C6?logo=typescript)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-v4-06B6D4?logo=tailwindcss)
![Encryption](https://img.shields.io/badge/Encryption-Envelope_AES--256--GCM-blue)

A production-grade, secure, multi-tenant digital portal built for the **All India Council for Technical Education (AICTE)** to manage, track, verify, and audit Fixed Deposit Receipts (FDRs) submitted by educational institutions as mandatory endowment and security deposits.

---

## 🚀 Key Architectural Highlights

1. **Envelope Encryption Vault (AES-256-GCM)**:
   - Physical FDR certificates and PDFs are encrypted using unique ephemeral Data Encryption Keys (DEKs) wrapped by a Master Key Encryption Key (KEK).
   - Tamper-detection via 128-bit authentication tags and raw SHA-256 pre-encryption checksums.
   - Files are stored in private storage outside the web root; downloads are decrypted in-memory to authenticated sessions only.
2. **Automated OCR Extraction & Scrutiny**:
   - Parses Bank Name, Branch, IFSC, Certificate Number, Principal Amount, Dates, and Beneficiary.
   - Computes field and overall confidence scores.
   - Provides a side-by-side human scrutiny interface with audited correction capabilities.
3. **Automated Discrepancy & Fraud Detection**:
   - Flagging of financial mismatches between OCR and submitted figures.
   - Cross-tenant duplicate FDR certificate detection (prevents identical FDRs submitted across institutions).
   - Duplicate binary document hash detection.
   - Approaching maturity thresholds (30 days / 90 days alerts).
4. **Strict Separation of Duties & Human-in-the-Loop Governance**:
   - Submitter cannot approve their own submission (enforced server-side).
   - Verification Officers review evidence and recommend approval/rejection.
   - Approving Authority renders final regulatory determinations.
5. **Simulated Bank CBS Verification Adapter**:
   - Explicitly labeled mock adapter providing realistic Core Banking System (CBS) confirmation queries with recorded evidence reference numbers.
6. **Immutable Append-Only Audit Trail**:
   - Permanent recording of every login, state change, discrepancy resolution, upload, and download with actor role, IP address, user agent, and JSON diffs.
7. **CSV Export with Formula Injection Protection**:
   - Prevents Excel DDE formula injection attacks by sanitizing leading `=`, `+`, `-`, `@` characters.

---

## 👥 Demo Accounts (Configured in Database Seeder)

All accounts share the default development password: **`Password@123`**

| Role | Email | Purpose in Demonstration |
|---|---|---|
| **System Administrator** | `admin@aicte-india.org` | System configuration, health oversight, full institutional access |
| **AICTE Regulatory Admin** | `regulator@aicte-india.org` | Case assignment to officers, institutional approvals, national reporting |
| **Senior Verification Officer** | `officer@aicte-india.org` | Case scrutiny, discrepancy resolution, simulated CBS inquiry, recommendations |
| **Approving Authority** | `authority@aicte-india.org` | Final regulatory determination (Approve / Reject); strictly separation of duties |
| **Independent Auditor** | `auditor@aicte-india.org` | CAG Panel compliance auditor; read-only access to immutable audit log |
| **Apex Institute Submitter** | `submitter.apex@institute.edu` | Apex Institute registrar; creates draft submissions and uploads documents |
| **Bharat Institute Submitter** | `submitter.bharat@institute.edu` | Bharat Institute finance officer (demonstrating tenant isolation) |

*Tip: The web UI features a **1-Click Quick Demo Persona Switcher** on the login page for instantaneous switching between roles during hackathon evaluation!*

---

## 🛠️ Installation & Setup Guide

### 1. Prerequisites
- **PHP**: 8.3+ with `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `gd`, `bcmath`
- **MySQL**: 8.0+ running on port 3306 (default user `root` with blank password, or configured in `.env`)
- **Composer**: 2.x
- **Node.js**: v20+ and npm

### 2. Backend Setup
```bash
# Navigate to backend directory
cd backend

# Install dependencies (already included in this environment)
composer install

# Configure environment variables
# Copy .env.example to .env if not present, then ensure DB credentials:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=aicte_fdr_portal
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations and database seeders
php artisan migrate:fresh --seed

# Start the Laravel API backend
php artisan serve --port=8000
```
Backend API will be live at: `http://127.0.0.1:8000`

### 3. Frontend Setup
```bash
# Navigate to frontend directory
cd frontend

# Install dependencies
npm install

# Build for production or start development server
npm run dev
```
Frontend Web Portal will be live at: `http://localhost:5173`

---

## 🧪 Automated Testing

### Run Backend Security & Compliance Test Suite:
```bash
cd backend
php artisan test
```
*Executes all 12 feature, regulatory, cryptographic, and security tests (100% Passing with 31 assertions).*

### Run Frontend Vitest Test Suite:
```bash
cd frontend
npm test
```
*Executes React Testing Library test specs.*

### Build Frontend Production Assets:
```bash
cd frontend
npm run build
```
*Compiles TypeScript and bundles CSS/JS with zero errors.*

---

## 📚 Complete Project Documentation

Detailed technical documents are available in the [`docs/`](file:///c:/sih/docs/) directory:
- [Requirements Specification](file:///c:/sih/docs/REQUIREMENTS.md)
- [System Architecture](file:///c:/sih/docs/ARCHITECTURE.md)
- [Database Schema & Entity Dictionary](file:///c:/sih/docs/DATABASE.md)
- [REST API Specification](file:///c:/sih/docs/API.md)
- [Security Model & Threat Matrix](file:///c:/sih/docs/SECURITY.md)
- [Envelope Encryption (AES-256-GCM)](file:///c:/sih/docs/ENCRYPTION.md)
- [Lifecycle & State Machine Workflow](file:///c:/sih/docs/WORKFLOW.md)
- [OCR Data Extraction Pipeline](file:///c:/sih/docs/OCR.md)
- [Bank Verification Adapter Specification](file:///c:/sih/docs/BANK_INTEGRATION.md)
- [Production Deployment & Nginx Configuration](file:///c:/sih/docs/DEPLOYMENT.md)
- [Disaster Recovery & Backup Runbook](file:///c:/sih/docs/BACKUP_AND_RECOVERY.md)
- [Automated Testing Suite Verification](file:///c:/sih/docs/TESTING.md)
- [Role-Based User Guide](file:///c:/sih/docs/USER_GUIDE.md)
- [Administrator Guide](file:///c:/sih/docs/ADMIN_GUIDE.md)
- [Hackathon Presentation Demo Script](file:///c:/sih/docs/DEMO_SCRIPT.md)
- [Requirements Traceability Matrix (RTM)](file:///c:/sih/docs/REQUIREMENTS_TRACEABILITY.md)

---

## ⚖️ Hackathon Transparency Matrix

| Area | Status | Evidence | Remaining Issues |
|---|---|---|---|
| **Frontend** | Implemented | Production build succeeds (`dist/`), Vitest tests passing | None |
| **Backend** | Implemented | 41 API routes active, Laravel 11/12 on PHP 8.3 | None |
| **Database** | Implemented | MySQL 8 InnoDB schema, 13 tables, seeded with realistic scenarios | None |
| **Authentication** | Implemented | Laravel Sanctum token auth, Bcrypt passwords, session revocation | None |
| **Authorization** | Implemented | Multi-role RBAC, cross-tenant isolation tested (403) | None |
| **Document Encryption** | Implemented | AES-256-GCM envelope encryption with KEK/DEK, tamper-detection tested | None |
| **OCR Pipeline** | Implemented | Field extraction, confidence scoring, human correction interface | None |
| **Bank Integration** | Simulated | Extensible adapter with clear `simulated_flag: true` declaration | Direct live CBS requires bank VPN/mTLS certificates |
| **Approval Workflow** | Implemented | State machine with separation of duties (submitter cannot approve) | None |
| **Audit Logging** | Implemented | Append-only ledger recording actor, IP, timestamp, action, and JSON diffs | None |
| **Backup & Recovery** | Documented | SQL dump, vault tarball, and KEK restoration procedures | None |
| **Deployment** | Verified | Tested on Windows/PHP 8.3 with production Nginx/Linux guidance | None |
