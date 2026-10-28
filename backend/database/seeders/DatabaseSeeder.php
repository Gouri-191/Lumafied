<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Institution;
use App\Models\Fdr;
use App\Models\Document;
use App\Models\OcrExtraction;
use App\Models\ValidationRun;
use App\Models\Discrepancy;
use App\Models\VerificationCase;
use App\Models\BankVerificationRequest;
use App\Models\FdrStatusHistory;
use App\Models\ReleaseRequest;
use App\Models\Notification;
use App\Models\FdrRenewal;
use App\Models\FdrReminder;
use App\Models\ReconciliationRun;
use App\Models\BankStatementEntry;
use App\Models\ReconciliationDiscrepancy;
use App\Services\EnvelopeEncryptionService;
use App\Services\AuditService;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $encryptionService = new EnvelopeEncryptionService();
        $pepperService = new \App\Services\Security\PepperedHashService();
        $pdfService = new \App\Services\PdfGeneratorService();

        // 1. Seed Institutions
        $apex = Institution::create([
            'aicte_id' => '1-109823451',
            'name' => 'Apex Institute of Engineering & Technology',
            'category' => 'Engineering & Technology',
            'address' => 'Plot 4, Institutional Area, Vasant Kunj',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'pincode' => '110070',
            'contact_email' => 'principal@apexengg.edu.in',
            'contact_phone' => '+91-11-26789012',
            'verification_status' => 'verified',
        ]);

        $bharat = Institution::create([
            'aicte_id' => '1-204981722',
            'name' => 'Bharat Institute of Technology & Management',
            'category' => 'Management',
            'address' => 'Electronic City Phase 1',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560100',
            'contact_email' => 'director@bharattech.edu.in',
            'contact_phone' => '+91-80-45678901',
            'verification_status' => 'verified',
        ]);

        $nips = Institution::create([
            'aicte_id' => '1-304819201',
            'name' => 'National Institute of Pharmaceutical Sciences',
            'category' => 'Pharmacy',
            'address' => 'HITEC City Road',
            'city' => 'Hyderabad',
            'state' => 'Telangana',
            'pincode' => '500081',
            'contact_email' => 'dean@nips.edu.in',
            'contact_phone' => '+91-40-34567890',
            'verification_status' => 'verified',
        ]);

        // 2. Seed Users across all defined RBAC roles
        // Demo password: Password@123 (Protected by Argon2id + KMS Hardware Pepper)
        $defaultPassword = $pepperService->hash('Password@123');

        $sysAdmin = User::create([
            'name' => 'Dr. Rajesh Verma',
            'email' => 'admin@aicte-india.org',
            'password' => $defaultPassword,
            'role' => 'system_admin',
            'status' => 'active',
            'designation' => 'Chief Technology Officer (AICTE)',
            'phone' => '+91-11-29581001',
        ]);

        $aicteAdmin = User::create([
            'name' => 'Smt. Sunita Sharma',
            'email' => 'regulator@aicte-india.org',
            'password' => $defaultPassword,
            'role' => 'aicte_admin',
            'status' => 'active',
            'designation' => 'Director (Approval Bureau)',
            'phone' => '+91-11-29581002',
        ]);

        $officer = User::create([
            'name' => 'Shri Amit Kumar',
            'email' => 'officer@aicte-india.org',
            'password' => $defaultPassword,
            'role' => 'verification_officer',
            'status' => 'active',
            'designation' => 'Senior Verification Officer',
            'phone' => '+91-11-29581003',
        ]);

        $approver = User::create([
            'name' => 'Prof. K. R. Ramanathan',
            'email' => 'authority@aicte-india.org',
            'password' => $defaultPassword,
            'role' => 'approving_authority',
            'status' => 'active',
            'designation' => 'Member Secretary / Approving Authority',
            'phone' => '+91-11-29581004',
        ]);

        $auditor = User::create([
            'name' => 'Ms. Priya Menon',
            'email' => 'auditor@aicte-india.org',
            'password' => $defaultPassword,
            'role' => 'auditor',
            'status' => 'active',
            'designation' => 'Independent Compliance Auditor (CAG Panel)',
            'phone' => '+91-11-29581005',
        ]);

        $apexSubmitter = User::create([
            'name' => 'Dr. Arvind Saxena',
            'email' => 'submitter.apex@institute.edu',
            'password' => $defaultPassword,
            'role' => 'institution_submitter',
            'status' => 'active',
            'institution_id' => $apex->id,
            'designation' => 'Registrar & Authorized Signatory',
            'phone' => '+91-11-26789015',
        ]);

        $bharatSubmitter = User::create([
            'name' => 'Dr. Meenakshi Rao',
            'email' => 'submitter.bharat@institute.edu',
            'password' => $defaultPassword,
            'role' => 'institution_submitter',
            'status' => 'active',
            'institution_id' => $bharat->id,
            'designation' => 'Finance Director',
            'phone' => '+91-80-45678910',
        ]);

        // Helper closure to create sample encrypted document & OCR
        $createEncryptedDoc = function (Fdr $fdr, string $docName, string $pdfBinary) use ($encryptionService) {
            $encrypted = $encryptionService->encryptDocument($pdfBinary);
            $uuid = (string) Str::uuid();
            $path = "vault/{$fdr->institution_id}/{$uuid}.enc";
            Storage::disk('local')->put($path, $encrypted['ciphertext']);

            $doc = Document::create([
                'uuid' => $uuid,
                'fdr_id' => $fdr->id,
                'institution_id' => $fdr->institution_id,
                'document_type' => 'fdr_original',
                'original_filename' => $docName,
                'storage_path' => $path,
                'file_size_bytes' => strlen($pdfBinary),
                'mime_type' => 'application/pdf',
                'file_hash_sha256' => $encrypted['sha256'],
                'bank_sha256' => $encrypted['sha256'],
                'bank_sha_verified' => true,
                'bank_sha_match_status' => 'matched',
                'encryption_algorithm' => 'AES-256-GCM',
                'encrypted_dek' => $encrypted['encrypted_dek'],
                'nonce' => $encrypted['nonce'],
                'auth_tag' => $encrypted['auth_tag'],
                'kek_version' => 'v1',
                'uploaded_by' => $fdr->created_by,
            ]);

            return $doc;
        };

        // -------------------------------------------------------------
        // SCENARIO 1: Clean, Verified & Approved FDR (Apex Institute)
        // -------------------------------------------------------------
        $fdr1 = Fdr::create([
            'fdr_tracking_no' => 'FDR-2026-APEX-01',
            'institution_id' => $apex->id,
            'bank_name' => 'State Bank of India',
            'branch_name' => 'New Delhi Main Branch (00691)',
            'ifsc_code' => 'SBIN0000691',
            'fdr_number' => 'SBI-FDR-9876543210',
            'account_number_ref' => 'A/C-XXXX-XXXX-4501',
            'principal_amount' => 5000000.00, // 50 Lakhs
            'currency' => 'INR',
            'interest_rate' => 7.25,
            'issue_date' => '2026-01-10',
            'maturity_date' => '2036-01-10',
            'tenure_months' => 120,
            'holder_name' => 'Apex Institute of Engineering & Technology',
            'beneficiary_name' => 'AICTE & Apex Institute Joint A/C',
            'purpose' => 'Statutory Security Deposit for AICTE Approval 2026-27',
            'lifecycle_status' => 'approved',
            'verification_status' => 'verified_clean',
            'risk_level' => 'low',
            'interest_type' => 'cumulative',
            'expected_interest_amount' => 5265646.33,
            'expected_maturity_amount' => 10265646.33,
            'confirmed_maturity_amount' => 10265646.33,
            'encumbrance_status' => 'lien_marked',
            'renewal_status' => 'none',
            'version' => 1,
            'created_by' => $apexSubmitter->id,
            'last_updated_by' => $approver->id,
        ]);

        $sampleText1 = <<<TXT
STATE BANK OF INDIA - FIXED DEPOSIT RECEIPT
Branch: New Delhi Main Branch (00691) | IFSC: SBIN0000691
FDR Certificate No: SBI-FDR-9876543210
Customer Name: Apex Institute of Engineering & Technology
Joint Beneficiary: AICTE & Apex Institute Joint A/C
Principal Amount: INR 50,00,000.00 (Fifty Lakh Rupees Only)
Interest Rate: 7.25% p.a. | Tenure: 120 Months
Date of Issue: 2026-01-10 | Date of Maturity: 2036-01-10
Lien noted in favor of Member Secretary, AICTE. Non-encashable without written NOC.
TXT;
        $pdf1 = $pdfService->generateFdrReceiptPdf(
            'State Bank of India',
            'New Delhi Main Branch (00691)',
            'SBIN0000691',
            'SBI-FDR-9876543210',
            'Apex Institute of Engineering & Technology',
            'AICTE & Apex Institute Joint A/C',
            5000000.00,
            '2026-01-10',
            '2036-01-10',
            7.25,
            120
        );
        $doc1 = $createEncryptedDoc($fdr1, 'Apex_SBI_FDR_50Lakhs_Original.pdf', $pdf1);

        OcrExtraction::create([
            'document_id' => $doc1->id,
            'fdr_id' => $fdr1->id,
            'status' => 'completed',
            'extracted_data' => [
                'bank_name' => 'State Bank of India',
                'branch_name' => 'New Delhi Main Branch',
                'ifsc_code' => 'SBIN0000691',
                'fdr_number' => 'SBI-FDR-9876543210',
                'principal_amount' => 5000000.00,
                'issue_date' => '2026-01-10',
                'maturity_date' => '2036-01-10',
                'holder_name' => 'Apex Institute of Engineering & Technology',
                'beneficiary_name' => 'AICTE & Apex Institute Joint A/C',
            ],
            'raw_text' => $sampleText1,
            'overall_confidence' => 0.98,
        ]);

        ValidationRun::create([
            'fdr_id' => $fdr1->id,
            'run_by' => $officer->id,
            'status' => 'passed',
            'rules_checked_count' => 7,
            'discrepancies_count' => 0,
            'summary' => 'All automated regulatory and financial checks passed cleanly.',
        ]);

        VerificationCase::create([
            'case_number' => 'CASE-2026-000101',
            'fdr_id' => $fdr1->id,
            'institution_id' => $apex->id,
            'assigned_officer_id' => $officer->id,
            'assigned_by' => $aicteAdmin->id,
            'priority' => 'normal',
            'status' => 'closed',
            'recommendation' => 'approve',
            'recommendation_notes' => 'Independent bank confirmation verified. Lien marked in CBS. Recommended for approval.',
            'due_date' => '2026-02-01',
        ]);

        BankVerificationRequest::create([
            'fdr_id' => $fdr1->id,
            'request_reference' => 'BNK-REQ-SBI-98710',
            'bank_name' => 'State Bank of India',
            'mode' => 'mock',
            'status' => 'confirmed',
            'response_payload' => [
                'gateway' => 'AICTE-BankVerification-Mock-Gateway v1.0',
                'simulated' => true,
                'cbs_reference' => 'SBI-CBS-LIEN-2026-44910',
                'bank_reported_status' => 'LIVE_ACTIVE_LIEN_MARKED',
                'bank_reported_amount' => 5000000.00,
            ],
            'verification_evidence_ref' => 'CBS-CONF-20260115-44910',
            'simulated_flag' => true,
            'initiated_by' => $officer->id,
            'completed_at' => '2026-01-15 14:30:00',
        ]);

        FdrStatusHistory::create([
            'fdr_id' => $fdr1->id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
            'actor_id' => $approver->id,
            'action' => 'APPROVED_DECISION',
            'comments' => 'Approved following complete independent bank confirmation and zero discrepancies.',
        ]);

        // -------------------------------------------------------------
        // SCENARIO 2: Under Review with Discrepancy (Apex Institute)
        // -------------------------------------------------------------
        $fdr2 = Fdr::create([
            'fdr_tracking_no' => 'FDR-2026-APEX-02',
            'institution_id' => $apex->id,
            'bank_name' => 'Punjab National Bank',
            'branch_name' => 'Connaught Place Branch',
            'ifsc_code' => 'PUNB0012300',
            'fdr_number' => 'PNB-FDR-5544332211',
            'account_number_ref' => 'A/C-XXXX-XXXX-8822',
            'principal_amount' => 3500000.00, // Submitted 35 Lakhs
            'currency' => 'INR',
            'interest_rate' => 7.10,
            'issue_date' => '2026-02-15',
            'maturity_date' => '2036-02-15',
            'tenure_months' => 120,
            'holder_name' => 'Apex Institute of Engineering & Technology',
            'beneficiary_name' => 'AICTE & Apex Institute Joint A/C',
            'purpose' => 'Additional Course Endowment Fund',
            'lifecycle_status' => 'under_review',
            'verification_status' => 'in_progress',
            'risk_level' => 'high',
            'interest_type' => 'cumulative',
            'expected_interest_amount' => 1475608.20,
            'expected_maturity_amount' => 4975608.20,
            'encumbrance_status' => 'lien_marked',
            'renewal_status' => 'none',
            'version' => 1,
            'created_by' => $apexSubmitter->id,
            'last_updated_by' => $apexSubmitter->id,
        ]);

        $sampleText2 = <<<TXT
PUNJAB NATIONAL BANK - TERM DEPOSIT RECEIPT
Branch: Connaught Place, New Delhi (00123)
Certificate No: PNB-FDR-5544332211
Name: Apex Institute of Engineering & Technology
Joint Name: AICTE & Apex Institute Joint A/C
Amount: INR 25,00,000.00 (Twenty-Five Lakh Rupees Only)
Interest Rate: 7.10% | Period: 120 Months
Issue Date: 2026-02-15 | Due Date: 2036-02-15
TXT;
        $pdf2 = $pdfService->generateFdrReceiptPdf(
            'Punjab National Bank',
            'Connaught Place, New Delhi (00123)',
            'PUNB0012300',
            'PNB-FDR-5544332211',
            'Apex Institute of Engineering & Technology',
            'AICTE & Apex Institute Joint A/C',
            2500000.00,
            '2026-02-15',
            '2036-02-15',
            7.10,
            120
        );
        $doc2 = $createEncryptedDoc($fdr2, 'Apex_PNB_FDR_25L_Scanned.pdf', $pdf2);

        OcrExtraction::create([
            'document_id' => $doc2->id,
            'fdr_id' => $fdr2->id,
            'status' => 'completed',
            'extracted_data' => [
                'bank_name' => 'Punjab National Bank',
                'fdr_number' => 'PNB-FDR-5544332211',
                'principal_amount' => 2500000.00, // Extracted 25L, submitted 35L!
                'issue_date' => '2026-02-15',
                'maturity_date' => '2036-02-15',
            ],
            'raw_text' => $sampleText2,
            'overall_confidence' => 0.94,
        ]);

        $valRun2 = ValidationRun::create([
            'fdr_id' => $fdr2->id,
            'run_by' => $officer->id,
            'status' => 'flagged_warnings',
            'rules_checked_count' => 7,
            'discrepancies_count' => 1,
            'summary' => 'Financial mismatch flagged: Physical receipt states INR 25,00,000 whereas submission claims INR 35,00,000.',
        ]);

        Discrepancy::create([
            'validation_run_id' => $valRun2->id,
            'fdr_id' => $fdr2->id,
            'rule_identifier' => 'AMOUNT_MISMATCH',
            'category' => 'financial',
            'severity' => 'high_priority',
            'field_name' => 'principal_amount',
            'submitted_value' => 'INR 35,00,000.00',
            'observed_value' => 'INR 25,00,000.00',
            'explanation' => 'Document OCR extracted amount (INR 25,00,000.00) differs from the submitted amount (INR 35,00,000.00). Difference: INR 10,00,000.00 deficit.',
            'resolution_status' => 'open',
        ]);

        VerificationCase::create([
            'case_number' => 'CASE-2026-000102',
            'fdr_id' => $fdr2->id,
            'institution_id' => $apex->id,
            'assigned_officer_id' => $officer->id,
            'assigned_by' => $aicteAdmin->id,
            'priority' => 'high',
            'status' => 'under_review',
            'due_date' => '2026-03-01',
        ]);

        // -------------------------------------------------------------
        // SCENARIO 3: Approaching Maturity Alert (Bharat Institute)
        // -------------------------------------------------------------
        $fdr3 = Fdr::create([
            'fdr_tracking_no' => 'FDR-2026-BHARAT-01',
            'institution_id' => $bharat->id,
            'bank_name' => 'Canara Bank',
            'branch_name' => 'Koramangala Branch',
            'ifsc_code' => 'CNRB0001420',
            'fdr_number' => 'CNRB-FDR-1122334455',
            'account_number_ref' => 'A/C-XXXX-XXXX-3344',
            'principal_amount' => 4000000.00,
            'currency' => 'INR',
            'interest_rate' => 6.80,
            'issue_date' => '2016-11-20',
            'maturity_date' => now()->addDays(24)->toDateString(), // Maturing in 24 days!
            'tenure_months' => 120,
            'holder_name' => 'Bharat Institute of Technology & Management',
            'beneficiary_name' => 'AICTE & Bharat Institute Joint A/C',
            'purpose' => 'MBA Program Mandatory Security Fund',
            'lifecycle_status' => 'maturing',
            'verification_status' => 'verified_clean',
            'risk_level' => 'medium',
            'interest_type' => 'cumulative',
            'expected_interest_amount' => 1771239.11,
            'expected_maturity_amount' => 5771239.11,
            'encumbrance_status' => 'lien_marked',
            'renewal_status' => 'requested',
            'version' => 1,
            'created_by' => $bharatSubmitter->id,
            'last_updated_by' => $approver->id,
        ]);

        $pdf3 = $pdfService->generateFdrReceiptPdf(
            'Canara Bank',
            'Koramangala Branch (01420)',
            'CNRB0001420',
            'CNRB-FDR-1122334455',
            'Bharat Institute of Technology & Management',
            'AICTE & Bharat Institute Joint A/C',
            4000000.00,
            '2016-11-20',
            now()->addDays(24)->toDateString(),
            6.80,
            120
        );
        $createEncryptedDoc($fdr3, 'Bharat_Canara_FDR_Receipt.pdf', $pdf3);

        Notification::create([
            'institution_id' => $bharat->id,
            'title' => 'URGENT: FDR Approaching Maturity within 30 Days',
            'message' => 'FDR CNRB-FDR-1122334455 matures on ' . now()->addDays(24)->toFormattedDateString() . '. Initiate renewal or submit extension receipt immediately.',
            'type' => 'warning',
            'action_url' => "/fdrs/{$fdr3->id}",
        ]);

        // -------------------------------------------------------------
        // SCENARIO 4: Cross-Tenant Duplicate Flag (Suspicious Pattern)
        // -------------------------------------------------------------
        $fdr4 = Fdr::create([
            'fdr_tracking_no' => 'FDR-2026-BHARAT-02',
            'institution_id' => $bharat->id,
            'bank_name' => 'State Bank of India',
            'branch_name' => 'New Delhi Main Branch (00691)',
            'ifsc_code' => 'SBIN0000691',
            'fdr_number' => 'SBI-FDR-9876543210', // Exact duplicate of Apex FDR 1!
            'account_number_ref' => 'A/C-XXXX-XXXX-9911',
            'principal_amount' => 5000000.00,
            'currency' => 'INR',
            'interest_rate' => 7.25,
            'issue_date' => '2026-01-10',
            'maturity_date' => '2036-01-10',
            'tenure_months' => 120,
            'holder_name' => 'Bharat Institute of Technology & Management',
            'beneficiary_name' => 'AICTE Joint A/C',
            'purpose' => 'Extension of Approval',
            'lifecycle_status' => 'submitted',
            'verification_status' => 'in_progress',
            'risk_level' => 'critical',
            'interest_type' => 'cumulative',
            'expected_interest_amount' => 5265646.33,
            'expected_maturity_amount' => 10265646.33,
            'encumbrance_status' => 'disputed',
            'renewal_status' => 'none',
            'version' => 1,
            'created_by' => $bharatSubmitter->id,
            'last_updated_by' => $bharatSubmitter->id,
        ]);

        $valRun4 = ValidationRun::create([
            'fdr_id' => $fdr4->id,
            'run_by' => $sysAdmin->id,
            'status' => 'blocked',
            'rules_checked_count' => 7,
            'discrepancies_count' => 1,
            'summary' => 'CRITICAL SECURITY ANOMALY: Cross-institution duplicate FDR reference detected.',
        ]);

        Discrepancy::create([
            'validation_run_id' => $valRun4->id,
            'fdr_id' => $fdr4->id,
            'rule_identifier' => 'CROSS_TENANT_DUPLICATE_FDR',
            'category' => 'tampering',
            'severity' => 'verification_blocked',
            'field_name' => 'fdr_number',
            'submitted_value' => 'SBI-FDR-9876543210',
            'observed_value' => 'Registered under Institution #1 (Apex Institute)',
            'explanation' => 'SUSPICIOUS DUPLICATE DETECTED: This certificate number already exists and is approved under another institution.',
            'resolution_status' => 'open',
        ]);

        VerificationCase::create([
            'case_number' => 'CASE-2026-000104',
            'fdr_id' => $fdr4->id,
            'institution_id' => $bharat->id,
            'assigned_officer_id' => $officer->id,
            'assigned_by' => $aicteAdmin->id,
            'priority' => 'urgent',
            'status' => 'open',
            'due_date' => '2026-02-10',
        ]);

        $sampleText4 = <<<TXT
STATE BANK OF INDIA - FIXED DEPOSIT RECEIPT
Branch: New Delhi Main Branch (00691) | IFSC: SBIN0000691
FDR Certificate No: SBI-FDR-9876543210
Customer Name: Bharat Institute of Technology & Management
Joint Beneficiary: AICTE Joint A/C
Principal Amount: INR 50,00,000.00 (Fifty Lakh Rupees Only)
Interest Rate: 7.25% p.a. | Tenure: 120 Months
Date of Issue: 2026-01-10 | Date of Maturity: 2036-01-10
Lien noted in favor of Member Secretary, AICTE.
TXT;
        $pdf4 = $pdfService->generateFdrReceiptPdf(
            'State Bank of India',
            'New Delhi Main Branch (00691)',
            'SBIN0000691',
            'SBI-FDR-9876543210',
            'Bharat Institute of Technology & Management',
            'AICTE Joint A/C',
            5000000.00,
            '2026-01-10',
            '2036-01-10',
            7.25,
            120
        );
        $doc4 = $createEncryptedDoc($fdr4, 'Bharat_SBI_FDR_50Lakhs_Scan.pdf', $pdf4);

        OcrExtraction::create([
            'document_id' => $doc4->id,
            'fdr_id' => $fdr4->id,
            'status' => 'completed',
            'extracted_data' => [
                'bank_name' => 'State Bank of India',
                'branch_name' => 'New Delhi Main Branch',
                'ifsc_code' => 'SBIN0000691',
                'fdr_number' => 'SBI-FDR-9876543210',
                'principal_amount' => 5000000.00,
                'issue_date' => '2026-01-10',
                'maturity_date' => '2036-01-10',
                'holder_name' => 'Bharat Institute of Technology & Management',
                'beneficiary_name' => 'AICTE Joint A/C',
            ],
            'raw_text' => $sampleText4,
            'overall_confidence' => 0.97,
        ]);

        // -------------------------------------------------------------
        // SCENARIO 5: Release Request Workflow (Apex Institute)
        // -------------------------------------------------------------
        $fdr5 = Fdr::create([
            'fdr_tracking_no' => 'FDR-2026-APEX-03',
            'institution_id' => $apex->id,
            'bank_name' => 'HDFC Bank',
            'branch_name' => 'Hauz Khas Branch',
            'ifsc_code' => 'HDFC0000124',
            'fdr_number' => 'HDFC-FDR-7766554433',
            'account_number_ref' => 'A/C-XXXX-XXXX-1122',
            'principal_amount' => 1500000.00, // 15 Lakhs
            'currency' => 'INR',
            'interest_rate' => 7.00,
            'issue_date' => '2016-01-01',
            'maturity_date' => '2026-01-01', // Fully matured
            'tenure_months' => 120,
            'holder_name' => 'Apex Institute of Engineering & Technology',
            'beneficiary_name' => 'AICTE Joint A/C',
            'purpose' => 'Discontinued Polytechnic Diploma Security',
            'lifecycle_status' => 'release_requested',
            'verification_status' => 'verified_clean',
            'risk_level' => 'low',
            'interest_type' => 'cumulative',
            'expected_interest_amount' => 1502392.20,
            'expected_maturity_amount' => 3002392.20,
            'confirmed_maturity_amount' => 3002392.20,
            'encumbrance_status' => 'release_pending',
            'renewal_status' => 'none',
            'version' => 1,
            'created_by' => $apexSubmitter->id,
            'last_updated_by' => $apexSubmitter->id,
        ]);

        $sampleText5 = <<<TXT
HDFC BANK LIMITED - TERM DEPOSIT RECEIPT
Branch: Hauz Khas Branch | IFSC: HDFC0000124
FDR Certificate No: HDFC-FDR-7766554433
Customer Name: Apex Institute of Engineering & Technology
Joint Beneficiary: AICTE Joint A/C
Principal Amount: INR 15,00,000.00 (Fifteen Lakh Rupees Only)
Interest Rate: 7.00% p.a. | Tenure: 120 Months
Date of Issue: 2016-01-01 | Date of Maturity: 2026-01-01
Lien marked in favor of AICTE. Status: Matured.
TXT;
        $pdf5 = $pdfService->generateFdrReceiptPdf(
            'HDFC Bank',
            'Hauz Khas Branch',
            'HDFC0000124',
            'HDFC-FDR-7766554433',
            'Apex Institute of Engineering & Technology',
            'AICTE Joint A/C',
            1500000.00,
            '2016-01-01',
            '2026-01-01',
            7.00,
            120
        );
        $doc5 = $createEncryptedDoc($fdr5, 'Apex_HDFC_FDR_15Lakhs_Original.pdf', $pdf5);

        OcrExtraction::create([
            'document_id' => $doc5->id,
            'fdr_id' => $fdr5->id,
            'status' => 'completed',
            'extracted_data' => [
                'bank_name' => 'HDFC Bank',
                'branch_name' => 'Hauz Khas Branch',
                'ifsc_code' => 'HDFC0000124',
                'fdr_number' => 'HDFC-FDR-7766554433',
                'principal_amount' => 1500000.00,
                'issue_date' => '2016-01-01',
                'maturity_date' => '2026-01-01',
                'holder_name' => 'Apex Institute of Engineering & Technology',
                'beneficiary_name' => 'AICTE Joint A/C',
            ],
            'raw_text' => $sampleText5,
            'overall_confidence' => 0.99,
        ]);

        ReleaseRequest::create([
            'fdr_id' => $fdr5->id,
            'institution_id' => $apex->id,
            'reason' => 'Permanent closure of Civil Engineering Diploma program with zero outstanding student dues or liabilities.',
            'status' => 'requested',
            'requested_by' => $apexSubmitter->id,
            'no_dues_certificate_ref' => 'AICTE-NOC-ND-2026-8812',
        ]);

        FdrStatusHistory::create([
            'fdr_id' => $fdr5->id,
            'from_status' => 'matured',
            'to_status' => 'release_requested',
            'actor_id' => $apexSubmitter->id,
            'action' => 'REQUEST_RELEASE',
            'comments' => 'Formal request for lien discharge following completed program closure.',
        ]);

        // -------------------------------------------------------------
        // SCENARIO 6: Seed Smart Reminders (Intervals + Overdue Escalation)
        // -------------------------------------------------------------
        FdrReminder::create([
            'fdr_id' => $fdr3->id,
            'institution_id' => $bharat->id,
            'reminder_type' => 'maturity_30d',
            'target_date' => now()->addDays(24)->toDateString(),
            'status' => 'pending',
            'priority' => 'high',
            'recipient_email' => 'director@bharattech.edu.in',
            'title' => 'FDR Matures in 24 Days: #CNRB-FDR-1122334455',
            'message' => 'FDR #CNRB-FDR-1122334455 (Canara Bank) matures in 24 days. Statutory mandate requires timely renewal or discharge action.',
        ]);

        FdrReminder::create([
            'fdr_id' => $fdr5->id,
            'institution_id' => $apex->id,
            'reminder_type' => 'post_maturity_overdue',
            'target_date' => '2026-01-01',
            'status' => 'escalated',
            'priority' => 'urgent',
            'recipient_email' => 'principal@apexengg.edu.in',
            'title' => 'CRITICAL OVERDUE: FDR #HDFC-FDR-7766554433',
            'message' => 'CRITICAL OVERDUE: FDR #HDFC-FDR-7766554433 matured on 2026-01-01. Release or renewal remains uncompleted beyond grace period.',
            'escalated_to_id' => $sysAdmin->id,
            'escalated_at' => now()->subDays(30),
        ]);

        // -------------------------------------------------------------
        // SCENARIO 7: Seed Maturity & Renewal Scenario / Request
        // -------------------------------------------------------------
        FdrRenewal::create([
            'request_reference' => 'RNW-2026-BHARAT-01',
            'original_fdr_id' => $fdr3->id,
            'institution_id' => $bharat->id,
            'proposed_tenure_months' => 36,
            'proposed_interest_rate' => 7.40,
            'proposed_interest_type' => 'cumulative',
            'estimated_new_maturity_amount' => 4983200.00,
            'reason' => 'Term optimization into 36 months institutional tier pricing to maximize returns.',
            'status' => 'pending_review',
            'requested_by' => $bharatSubmitter->id,
        ]);

        // -------------------------------------------------------------
        // SCENARIO 8: Seed Bank Statement Reconciliation Run & Discrepancies
        // -------------------------------------------------------------
        $reconRun = ReconciliationRun::create([
            'run_reference' => 'REC-SBI-202603',
            'bank_name' => 'State Bank of India',
            'statement_period_start' => '2026-01-01',
            'statement_period_end' => '2026-03-31',
            'total_statement_records' => 3,
            'matched_count' => 1,
            'discrepancy_count' => 2,
            'total_statement_amount' => 12500000.00,
            'total_matched_amount' => 5000000.00,
            'status' => 'needs_review',
            'performed_by' => $aicteAdmin->id,
        ]);

        // Statement Entry 1: Clean Match with Apex FDR 1
        BankStatementEntry::create([
            'reconciliation_run_id' => $reconRun->id,
            'bank_name' => 'State Bank of India',
            'account_no' => 'A/C-XXXX-XXXX-4501',
            'fdr_reference' => 'SBI-FDR-9876543210',
            'transaction_date' => '2026-01-10',
            'value_date' => '2026-01-10',
            'entry_type' => 'fdr_deposit',
            'amount' => 5000000.00,
            'balance_after' => 5000000.00,
            'description' => 'Statutory security deposit lien marked to AICTE',
            'matched_fdr_id' => $fdr1->id,
            'match_status' => 'matched',
        ]);

        // Statement Entry 2: Mismatched Principal Amount
        $stmtEntry2 = BankStatementEntry::create([
            'reconciliation_run_id' => $reconRun->id,
            'bank_name' => 'State Bank of India',
            'account_no' => 'A/C-XXXX-XXXX-9911',
            'fdr_reference' => 'SBI-FDR-9876543210',
            'transaction_date' => '2026-01-10',
            'value_date' => '2026-01-10',
            'entry_type' => 'fdr_deposit',
            'amount' => 4500000.00, // 5 Lakhs variance vs 50 Lakhs
            'balance_after' => 4500000.00,
            'description' => 'Discrepant CBS balance vs submitted certificate',
            'matched_fdr_id' => $fdr4->id,
            'match_status' => 'discrepant',
        ]);

        ReconciliationDiscrepancy::create([
            'reconciliation_run_id' => $reconRun->id,
            'fdr_id' => $fdr4->id,
            'bank_statement_entry_id' => $stmtEntry2->id,
            'discrepancy_type' => 'AMOUNT_MISMATCH',
            'recorded_amount' => 5000000.00,
            'bank_evidenced_amount' => 4500000.00,
            'difference_amount' => -500000.00,
            'status' => 'open',
            'investigation_notes' => 'Bank statement evidenced amount (INR 45,00,000.00) differs from portal recorded principal (INR 50,00,000.00). Difference: INR 5,00,000.00 deficit in CBS.',
        ]);

        // Statement Entry 3: Unmatched Entry (FDR recorded by Bank under AICTE lien but not yet submitted in Portal)
        $stmtEntry3 = BankStatementEntry::create([
            'reconciliation_run_id' => $reconRun->id,
            'bank_name' => 'State Bank of India',
            'account_no' => 'A/C-XXXX-XXXX-7700',
            'fdr_reference' => 'SBI-FDR-9988776655',
            'transaction_date' => '2026-01-15',
            'value_date' => '2026-01-15',
            'entry_type' => 'fdr_deposit',
            'amount' => 3000000.00,
            'balance_after' => 3000000.00,
            'description' => 'Unreported deposit with lien favor AICTE',
            'matched_fdr_id' => null,
            'match_status' => 'unmatched',
        ]);

        ReconciliationDiscrepancy::create([
            'reconciliation_run_id' => $reconRun->id,
            'fdr_id' => null,
            'bank_statement_entry_id' => $stmtEntry3->id,
            'discrepancy_type' => 'UNMATCHED_STATEMENT_ENTRY',
            'recorded_amount' => 0.00,
            'bank_evidenced_amount' => 3000000.00,
            'difference_amount' => 3000000.00,
            'status' => 'open',
            'investigation_notes' => 'Deposit of INR 30,00,000.00 under certificate SBI-FDR-9988776655 found on bank statement but missing in AICTE portal.',
        ]);

        // -------------------------------------------------------------
        // SCENARIO 9: Seed Tamper-Evident Cryptographic Merkle Audit Trail
        // -------------------------------------------------------------
        $auditService = new AuditService();

        $auditService->log(
            'USER_AUTHENTICATED',
            'users',
            (string) $sysAdmin->id,
            ['email' => $sysAdmin->email, 'role' => 'system_admin', 'login_mode' => 'argon2id_kms_pepper'],
            'success',
            $sysAdmin
        );

        $auditService->log(
            'INSTITUTION_REGISTERED',
            'institutions',
            (string) $apex->id,
            ['aicte_id' => $apex->aicte_id, 'name' => $apex->name, 'verification' => 'verified'],
            'success',
            $aicteAdmin
        );

        $auditService->log(
            'SUBMIT_FDR',
            'fdrs',
            (string) $fdr1->id,
            ['fdr_tracking_no' => $fdr1->fdr_tracking_no, 'bank_name' => $fdr1->bank_name, 'principal_amount' => 5000000.00],
            'success',
            $apexSubmitter
        );

        $auditService->log(
            'UPLOAD_DOCUMENT',
            'documents',
            (string) $doc1->id,
            ['original_filename' => 'Apex_SBI_FDR_50Lakhs_Original.pdf', 'encryption' => 'AES-256-GCM', 'sha256_match' => true],
            'success',
            $apexSubmitter
        );

        $auditService->log(
            'TRIGGER_BANK_VERIFICATION',
            'bank_verification_requests',
            (string) $fdr1->id,
            ['mode' => 'cbs_direct', 'bank' => 'State Bank of India', 'evidence_ref' => 'CBS-CONF-20260115-44910'],
            'success',
            $officer
        );

        $auditService->log(
            'FINAL_DECISION',
            'fdrs',
            (string) $fdr1->id,
            ['decision' => 'approved', 'notes' => 'Approved following independent bank confirmation and zero discrepancies.'],
            'success',
            $approver
        );

        $auditService->log(
            'DISCREPANCY_FLAGGED',
            'discrepancies',
            (string) $fdr2->id,
            ['rule' => 'AMOUNT_MISMATCH', 'submitted' => '35,00,000.00', 'ocr_extracted' => '25,00,000.00', 'severity' => 'high_priority'],
            'flagged',
            $sysAdmin
        );

        $auditService->log(
            'SECURITY_ANOMALY_BLOCKED',
            'fdrs',
            (string) $fdr4->id,
            ['rule' => 'CROSS_TENANT_DUPLICATE_FDR', 'fdr_number' => 'SBI-FDR-9876543210', 'original_owner' => 'Apex Institute'],
            'blocked',
            $sysAdmin
        );
    }
}
