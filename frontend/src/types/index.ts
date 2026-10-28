export type UserRole =
  | 'system_admin'
  | 'aicte_admin'
  | 'institution_admin'
  | 'institution_submitter'
  | 'verification_officer'
  | 'approving_authority'
  | 'auditor'
  | 'bank_integration';

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  designation?: string;
  phone?: string;
  institution_id?: number | null;
  institution?: Institution | null;
}

export interface Institution {
  id: number;
  aicte_id: string;
  name: string;
  category: string;
  address?: string;
  city: string;
  state: string;
  pincode: string;
  contact_email: string;
  contact_phone?: string;
  verification_status: string;
  fdrs_count?: number;
}

export interface DocumentItem {
  id: number;
  uuid: string;
  fdr_id: number;
  institution_id: number;
  document_type: string;
  original_filename: string;
  storage_path: string;
  file_size_bytes: number;
  mime_type: string;
  file_hash_sha256: string;
  bank_sha256?: string;
  bank_sha_verified?: boolean;
  bank_sha_match_status?: 'not_provided' | 'matched' | 'mismatch' | string;
  encryption_algorithm: string;
  kek_version: string;
  scan_status: string;
  created_at: string;
}

export interface OcrExtraction {
  id: number;
  document_id: number;
  fdr_id: number;
  status: string;
  extracted_data: {
    bank_name?: string;
    branch_name?: string;
    ifsc_code?: string;
    fdr_number?: string;
    principal_amount?: number;
    issue_date?: string;
    maturity_date?: string;
    holder_name?: string;
    beneficiary_name?: string;
  };
  raw_text?: string;
  overall_confidence: number;
  corrected_data?: Record<string, any> | null;
  corrected_at?: string | null;
}

export interface Discrepancy {
  id: number;
  validation_run_id: number;
  fdr_id: number;
  rule_identifier: string;
  category: string;
  severity: 'info' | 'warning' | 'requires_review' | 'high_priority' | 'verification_blocked';
  field_name?: string;
  submitted_value?: string;
  observed_value?: string;
  explanation: string;
  resolution_status: 'open' | 'resolved' | 'waived' | 'escalated';
  resolution_notes?: string;
  resolved_at?: string;
  resolver?: { id: number; name: string };
}

export interface VerificationCase {
  id: number;
  case_number: string;
  fdr_id: number;
  institution_id: number;
  assigned_officer_id?: number | null;
  priority: 'low' | 'normal' | 'high' | 'urgent';
  status: 'open' | 'under_review' | 'clarification_pending' | 'officer_recommended' | 'closed';
  recommendation?: 'approve' | 'reject' | 'request_clarification' | null;
  recommendation_notes?: string | null;
  clarification_text?: string | null;
  clarification_response?: string | null;
  due_date?: string;
  assigned_officer?: User | null;
  fdr?: Fdr;
}

export interface BankVerificationRequest {
  id: number;
  fdr_id: number;
  request_reference: string;
  bank_name: string;
  mode: string;
  status: 'pending' | 'confirmed' | 'mismatch' | 'not_found' | 'failed';
  response_payload?: Record<string, any>;
  verification_evidence_ref?: string;
  simulated_flag: boolean;
  completed_at?: string;
}

export interface FdrStatusHistoryItem {
  id: number;
  fdr_id: number;
  from_status?: string;
  to_status: string;
  actor_id?: number;
  action: string;
  comments?: string;
  created_at: string;
  actor?: { id: number; name: string; role: string };
}

export interface Fdr {
  id: number;
  fdr_tracking_no: string;
  institution_id: number;
  bank_name: string;
  branch_name: string;
  ifsc_code: string;
  fdr_number: string;
  account_number_ref?: string;
  principal_amount: number | string;
  currency: string;
  interest_rate?: number | string;
  issue_date: string;
  maturity_date: string;
  tenure_months: number;
  holder_name: string;
  beneficiary_name: string;
  purpose: string;
  lifecycle_status:
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'clarification_requested'
    | 'resubmitted'
    | 'verified'
    | 'approved'
    | 'rejected'
    | 'renewal_pending'
    | 'renewed'
    | 'maturing'
    | 'matured'
    | 'release_requested'
    | 'released'
    | 'closed';
  verification_status: 'pending' | 'in_progress' | 'verified_clean' | 'verified_with_discrepancy' | 'rejected';
  risk_level: 'low' | 'medium' | 'high' | 'critical';
  interest_type?: string;
  expected_interest_amount?: number | string;
  expected_maturity_amount?: number | string;
  confirmed_maturity_amount?: number | string;
  released_amount?: number | string;
  encumbrance_status?: 'unencumbered' | 'lien_marked' | 'release_pending' | 'disputed' | string;
  renewal_status?: 'none' | 'requested' | 'approved' | 'completed' | string;
  parent_fdr_id?: number | null;
  version?: number;
  created_by?: number;
  created_at: string;
  institution?: Institution;
  documents?: DocumentItem[];
  verification_case?: VerificationCase;
  discrepancies?: Discrepancy[];
  bank_verification_requests?: BankVerificationRequest[];
  status_history?: FdrStatusHistoryItem[];
}

export interface TreasuryMetrics {
  total_recorded_principal: number;
  outstanding_active_principal: number;
  expected_interest: number;
  expected_maturity_proceeds: number;
  confirmed_maturity_proceeds: number;
  released_funds: number;
  encumbered_principal: number;
  unencumbered_principal: number;
  weighted_average_rate: number;
  total_active_count: number;
  high_risk_count: number;
}

export interface MaturityLadderItem {
  id: number;
  fdr_number: string;
  bank_name: string;
  institution: string;
  principal_amount: number;
  maturity_date: string;
  days_remaining: number;
  interest_rate: number;
  expected_maturity_amount: number;
}

export interface MaturityLadderBucket {
  label: string;
  min: number;
  max: number;
  count: number;
  principal: number;
  items: MaturityLadderItem[];
}

export interface BankConcentrationItem {
  bank_name: string;
  fdr_count: number;
  total_principal: number;
  portfolio_percentage: number;
  avg_interest_rate: number;
}

export interface ForecastMonth {
  month_key: string;
  label: string;
  maturing_count: number;
  maturing_principal: number;
  expected_interest: number;
  expected_total_proceeds: number;
}

export interface FdrRenewal {
  id: number;
  request_reference: string;
  original_fdr_id: number;
  new_fdr_id?: number | null;
  institution_id: number;
  proposed_tenure_months: number;
  proposed_interest_rate: number;
  proposed_interest_type: string;
  estimated_new_maturity_amount: number;
  reason?: string;
  status: 'pending_review' | 'officer_recommended' | 'approved' | 'rejected' | string;
  effective_date?: string;
  decision_notes?: string;
  created_at: string;
  original_fdr?: Fdr;
  new_fdr?: Fdr;
  institution?: Institution;
  requester?: User;
  approver?: User;
}

export interface FdrReminder {
  id: number;
  fdr_id: number;
  institution_id: number;
  reminder_type: string;
  target_date: string;
  status: 'pending' | 'delivered' | 'escalated' | 'resolved' | string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  recipient_email: string;
  title: string;
  message: string;
  escalated_to_id?: number | null;
  escalated_at?: string | null;
  resolved_at?: string | null;
  created_at: string;
  fdr?: Fdr;
  institution?: Institution;
  escalated_to?: User;
}

export interface BankStatementEntry {
  id: number;
  reconciliation_run_id: number;
  bank_name: string;
  account_no?: string;
  fdr_reference?: string;
  transaction_date: string;
  value_date?: string;
  entry_type: string;
  amount: number;
  balance_after?: number;
  description?: string;
  matched_fdr_id?: number | null;
  match_status: 'matched' | 'unmatched' | 'discrepant' | string;
  matched_fdr?: Fdr;
}

export interface ReconciliationDiscrepancy {
  id: number;
  reconciliation_run_id: number;
  fdr_id?: number | null;
  bank_statement_entry_id?: number | null;
  discrepancy_type: string;
  recorded_amount?: number;
  bank_evidenced_amount?: number;
  difference_amount?: number;
  status: 'open' | 'investigating' | 'resolved' | string;
  investigation_notes?: string;
  resolved_by?: number | null;
  resolved_at?: string | null;
  fdr?: Fdr;
  statement_entry?: BankStatementEntry;
  resolver?: User;
}

export interface ReconciliationRun {
  id: number;
  run_reference: string;
  bank_name: string;
  statement_period_start?: string;
  statement_period_end?: string;
  total_statement_records: number;
  matched_count: number;
  discrepancy_count: number;
  total_statement_amount: number;
  total_matched_amount: number;
  status: 'completed' | 'needs_review' | string;
  created_at: string;
  performer?: User;
  statement_entries?: BankStatementEntry[];
  discrepancies?: ReconciliationDiscrepancy[];
}

export interface AuditEvent {
  id: number;
  event_uuid: string;
  user_id?: number;
  actor_email?: string;
  actor_role?: string;
  action: string;
  resource_type: string;
  resource_id?: string;
  ip_address?: string;
  user_agent?: string;
  details?: Record<string, any>;
  status: string;
  created_at: string;
}

export interface AppNotification {
  id: number;
  title: string;
  message: string;
  type: 'info' | 'warning' | 'action_required' | 'success';
  action_url?: string;
  is_read: boolean;
  created_at: string;
}
