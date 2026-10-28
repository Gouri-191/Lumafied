import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import { Fdr, OcrExtraction } from '../types';
import {
  ShieldCheck,
  Building2,
  FileText,
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Download,
  ExternalLink,
  ShieldAlert,
  Send,
  UserCheck,
  Clock,
  FileSearch,
  Lock,
  Layers,
  HelpCircle,
  MessageSquare,
  Upload,
} from 'lucide-react';

export const FdrDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const [fdr, setFdr] = useState<Fdr | null>(null);
  const [ocr, setOcr] = useState<OcrExtraction | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [feedbackMsg, setFeedbackMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Modals / forms
  const [showCorrectionModal, setShowCorrectionModal] = useState(false);
  const [correctionField, setCorrectionField] = useState('principal_amount');
  const [correctionValue, setCorrectionValue] = useState('');
  const [correctionReason, setCorrectionReason] = useState('');

  const [showClarificationModal, setShowClarificationModal] = useState(false);
  const [clarificationText, setClarificationText] = useState('');

  const [showDecisionModal, setShowDecisionModal] = useState(false);
  const [decisionType, setDecisionType] = useState<'approved' | 'rejected'>('approved');
  const [decisionComments, setDecisionComments] = useState('');

  const [bankOutcome, setBankOutcome] = useState<'confirmed' | 'mismatch' | 'not_found'>('confirmed');

  const [showUploadModal, setShowUploadModal] = useState(false);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadBankSha, setUploadBankSha] = useState('');
  const [uploadingDoc, setUploadingDoc] = useState(false);

  useEffect(() => {
    fetchFdrDetails();
  }, [id]);

  const fetchFdrDetails = async () => {
    try {
      setLoading(true);
      const res = await api.get(`/fdrs/${id}`);
      setFdr(res.data);

      try {
        const ocrRes = await api.get(`/fdrs/${id}/ocr-results`);
        setOcr(ocrRes.data);
      } catch {
        setOcr(null);
      }
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.response?.data?.message || 'Failed to load FDR dossier.' });
    } finally {
      setLoading(false);
    }
  };

  const handleSubmitForReview = async () => {
    if (!window.confirm('Submit this FDR and its encrypted documentation for formal AICTE verification?')) return;
    setActionLoading(true);
    try {
      await api.post(`/fdrs/${id}/submit`);
      setFeedbackMsg({ type: 'success', text: 'FDR successfully submitted for AICTE regulatory verification.' });
      fetchFdrDetails();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.response?.data?.message || 'Submission failed.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleUploadDocument = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!uploadFile) return;
    setUploadingDoc(true);
    try {
      const payload = new FormData();
      payload.append('file', uploadFile);
      payload.append('document_type', 'fdr_original');
      if (uploadBankSha.trim()) {
        payload.append('bank_sha256', uploadBankSha.trim());
      }
      await api.post(`/fdrs/${id}/documents`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setFeedbackMsg({
        type: 'success',
        text: 'Document uploaded, encrypted with AES-256-GCM, and OCR processed successfully!',
      });
      setShowUploadModal(false);
      setUploadFile(null);
      setUploadBankSha('');
      fetchFdrDetails();
    } catch (err: any) {
      setFeedbackMsg({
        type: 'error',
        text: err.response?.data?.message || 'Failed to upload document.',
      });
    } finally {
      setUploadingDoc(false);
    }
  };

  const handleRunValidation = async () => {
    setActionLoading(true);
    try {
      await api.post(`/fdrs/${id}/validate`);
      setFeedbackMsg({ type: 'success', text: 'Automated compliance rules executed and discrepancy matrix refreshed.' });
      fetchFdrDetails();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.response?.data?.message || 'Validation failed.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleDownloadDocument = async (docId: number, filename: string) => {
    try {
      const response = await api.get(`/documents/${docId}/download`, {
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err: any) {
      alert('Document download failed or decryption authorization denied.');
    }
  };

  const handleVerifyIntegrity = async (docId: number) => {
    try {
      const res = await api.get(`/documents/${docId}/integrity`);
      alert(`INTEGRITY VERIFIED: Status: ${res.data.status} | GCM Tag Authenticated: ${res.data.authenticated_gcm_tag_valid ? 'YES' : 'NO'}`);
    } catch (err: any) {
      alert('Integrity check failed: ' + (err.response?.data?.message || 'Tampering detected'));
    }
  };

  const handleTriggerBankVerification = async () => {
    setActionLoading(true);
    try {
      const res = await api.post(`/fdrs/${id}/bank-verification`, {
        simulate_outcome: bankOutcome,
      });
      setFeedbackMsg({
        type: 'success',
        text: `Bank inquiry completed. Reported status: ${res.data.bank_verification.status.toUpperCase()} (Evidence: ${res.data.bank_verification.verification_evidence_ref})`,
      });
      fetchFdrDetails();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.response?.data?.message || 'Bank verification failed.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleResolveDiscrepancy = async (discrepancyId: number) => {
    const notes = prompt('Enter justification / resolution explanation:');
    if (!notes) return;

    try {
      await api.post(`/fdrs/${id}/discrepancies/${discrepancyId}/resolve`, {
        resolution_status: 'resolved',
        resolution_notes: notes,
      });
      setFeedbackMsg({ type: 'success', text: 'Discrepancy marked as resolved.' });
      fetchFdrDetails();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.response?.data?.message || 'Failed to resolve discrepancy.' });
    }
  };

  const handleOfficerRecommend = async (rec: 'approve' | 'reject' | 'request_clarification') => {
    const notes = prompt(`Enter officer recommendation notes for ${rec.toUpperCase()}:`);
    if (!notes) return;

    setActionLoading(true);
    try {
      await api.post(`/verification-cases/${fdr?.verification_case?.id}/recommend`, {
        recommendation: rec,
        notes,
      });
      setFeedbackMsg({ type: 'success', text: `Recommendation recorded: ${rec.toUpperCase()}` });
      fetchFdrDetails();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.response?.data?.message || 'Recommendation submission failed.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleRenderDecision = async () => {
    if (!decisionComments) {
      alert('Decision remarks are mandatory.');
      return;
    }

    setActionLoading(true);
    try {
      const endpoint = decisionType === 'approved' ? 'approve' : 'reject';
      await api.post(`/verification-cases/${fdr?.verification_case?.id}/${endpoint}`, {
        comments: decisionComments,
      });
      setShowDecisionModal(false);
      setFeedbackMsg({ type: 'success', text: `FDR final decision recorded: ${decisionType.toUpperCase()}` });
      fetchFdrDetails();
    } catch (err: any) {
      setFeedbackMsg({ type: 'error', text: err.response?.data?.message || 'Decision failed.' });
    } finally {
      setActionLoading(false);
    }
  };

  const isSubmitter = user?.id === fdr?.created_by;

  if (loading || !fdr) {
    return (
      <div className="flex items-center justify-center min-h-[500px]">
        <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-orange-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Feedback Banner */}
      {feedbackMsg && (
        <div
          className={`p-4 rounded-xl border flex items-center justify-between text-sm ${
            feedbackMsg.type === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
              : 'bg-red-50 border-red-200 text-red-900'
          }`}
        >
          <div className="flex items-center space-x-2">
            {feedbackMsg.type === 'success' ? (
              <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
            ) : (
              <AlertTriangle className="w-5 h-5 text-red-600 shrink-0" />
            )}
            <span>{feedbackMsg.text}</span>
          </div>
          <button onClick={() => setFeedbackMsg(null)} className="text-xs font-bold underline ml-4">
            Dismiss
          </button>
        </div>
      )}

      {/* Header Banner */}
      <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <div className="flex items-center space-x-3">
            <span className="font-mono text-xl font-bold text-slate-900">{fdr.fdr_tracking_no}</span>
            <span
              className={`px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase ${
                fdr.lifecycle_status === 'approved'
                  ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                  : fdr.lifecycle_status === 'under_review'
                  ? 'bg-amber-100 text-amber-800 border border-amber-300'
                  : fdr.lifecycle_status === 'maturing'
                  ? 'bg-orange-100 text-orange-800 border border-orange-300'
                  : fdr.lifecycle_status === 'release_requested'
                  ? 'bg-purple-100 text-purple-800 border border-purple-300'
                  : fdr.lifecycle_status === 'rejected'
                  ? 'bg-red-100 text-red-800 border border-red-300'
                  : 'bg-slate-100 text-slate-800 border border-slate-300'
              }`}
            >
              {fdr.lifecycle_status.replace('_', ' ')}
            </span>

            <span
              className={`px-2.5 py-0.5 rounded-sm text-xs font-bold uppercase ${
                fdr.risk_level === 'critical'
                  ? 'bg-red-100 text-red-800'
                  : fdr.risk_level === 'high'
                  ? 'bg-orange-100 text-orange-800'
                  : fdr.risk_level === 'medium'
                  ? 'bg-amber-100 text-amber-800'
                  : 'bg-emerald-100 text-emerald-800'
              }`}
            >
              {fdr.risk_level} Risk
            </span>
          </div>

          <p className="text-sm text-slate-600 mt-1 flex items-center">
            <Building2 className="w-4 h-4 mr-1.5 text-slate-400" />
            <strong className="text-slate-900 mr-2">{fdr.institution?.name}</strong>
            <span className="text-xs text-slate-400 font-mono">({fdr.institution?.aicte_id})</span>
          </p>
        </div>

        {/* Action Controls */}
        <div className="flex items-center space-x-3">
          {(fdr.lifecycle_status === 'draft' || fdr.lifecycle_status === 'resubmitted') && (
            <button
              onClick={handleSubmitForReview}
              disabled={actionLoading}
              className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors flex items-center"
            >
              <Send className="w-4 h-4 mr-2" />
              Submit for Verification
            </button>
          )}

          <button
            onClick={handleRunValidation}
            disabled={actionLoading}
            className="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg border border-slate-300 shadow-2xs transition-colors flex items-center"
          >
            <ShieldCheck className="w-4 h-4 mr-2 text-slate-500" />
            Re-run Validation Engine
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left 2 Cols: Main Dossier */}
        <div className="lg:col-span-2 space-y-6">
          {/* Section 1: Financial Particulars */}
          <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-xs">
            <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center">
              <FileText className="w-4 h-4 mr-2 text-orange-600" />
              FDR Financial & Deposit Particulars
            </h2>

            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
              <div className="p-3 bg-slate-50 rounded-lg">
                <div className="text-xs text-slate-500 font-medium">Bank Name</div>
                <div className="text-slate-900 font-semibold mt-0.5">{fdr.bank_name}</div>
              </div>

              <div className="p-3 bg-slate-50 rounded-lg">
                <div className="text-xs text-slate-500 font-medium">Branch & IFSC</div>
                <div className="text-slate-900 font-semibold mt-0.5">{fdr.branch_name}</div>
                <div className="text-xs font-mono text-slate-500">{fdr.ifsc_code}</div>
              </div>

              <div className="p-3 bg-slate-50 rounded-lg">
                <div className="text-xs text-slate-500 font-medium">FDR Certificate No</div>
                <div className="text-slate-900 font-mono font-bold mt-0.5">{fdr.fdr_number}</div>
              </div>

              <div className="p-3 bg-orange-50 border border-orange-200 rounded-lg">
                <div className="text-xs text-orange-800 font-medium">Principal Amount</div>
                <div className="text-lg font-bold text-orange-950 mt-0.5">
                  ₹ {Number(fdr.principal_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                </div>
              </div>

              <div className="p-3 bg-slate-50 rounded-lg">
                <div className="text-xs text-slate-500 font-medium">Interest Rate & Tenure</div>
                <div className="text-slate-900 font-semibold mt-0.5">{fdr.interest_rate || 'N/A'}% p.a.</div>
                <div className="text-xs text-slate-500">{fdr.tenure_months} Months (10 Yrs)</div>
              </div>

              <div className="p-3 bg-slate-50 rounded-lg">
                <div className="text-xs text-slate-500 font-medium">Maturity Date</div>
                <div className="text-slate-900 font-bold mt-0.5">
                  {new Date(fdr.maturity_date).toLocaleDateString('en-GB')}
                </div>
                <div className="text-xs text-slate-500">
                  Issued: {new Date(fdr.issue_date).toLocaleDateString('en-GB')}
                </div>
              </div>

              <div className="sm:col-span-3 p-3 bg-slate-50 rounded-lg">
                <div className="text-xs text-slate-500 font-medium">Registered Joint Beneficiary</div>
                <div className="text-slate-900 font-semibold mt-0.5">{fdr.beneficiary_name}</div>
                <div className="text-xs text-slate-500 mt-1">Holder: {fdr.holder_name}</div>
              </div>
            </div>
          </div>

          {/* Section 2: Cryptographic Document Vault (Envelope Encryption) */}
          <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-xs">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center">
                <Lock className="w-4 h-4 mr-2 text-emerald-600" />
                Secure Document Vault (Envelope Encryption AES-256-GCM)
              </h2>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setShowUploadModal(true)}
                  className="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors flex items-center"
                >
                  <Upload className="w-3.5 h-3.5 mr-1.5" />
                  Upload Receipt Document
                </button>
                <span className="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                  Tamper-Evident GCM Tag
                </span>
              </div>
            </div>

            {(!fdr.documents || fdr.documents.length === 0) ? (
              <div className="p-8 border-2 border-dashed border-amber-300 rounded-xl bg-amber-50/60 text-center">
                <AlertTriangle className="w-10 h-10 text-amber-600 mx-auto mb-2" />
                <h3 className="text-sm font-bold text-amber-950">No Physical Receipt Document Uploaded</h3>
                <p className="text-xs text-amber-800 mt-1 max-w-md mx-auto">
                  CRITICAL: An FDR cannot be verified or approved without an authentic scanned Fixed Deposit Receipt. Upload the receipt to enable OCR extraction and CBS verification.
                </p>
                <button
                  onClick={() => setShowUploadModal(true)}
                  className="mt-4 px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shadow-sm transition-colors inline-flex items-center"
                >
                  <Upload className="w-4 h-4 mr-1.5" />
                  Upload Scanned FDR Receipt Now
                </button>
              </div>
            ) : (
              <div className="space-y-3">
                {fdr.documents.map((doc) => (
                  <div
                    key={doc.id}
                    className="p-4 rounded-xl border border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm"
                  >
                    <div>
                      <div className="font-semibold text-slate-900 flex items-center">
                        <FileText className="w-4 h-4 mr-2 text-slate-500" />
                        {doc.original_filename}
                        <span className="ml-2 text-[10px] font-mono bg-slate-200 text-slate-700 px-1.5 py-0.5 rounded">
                          {(doc.file_size_bytes / 1024).toFixed(1)} KB
                        </span>
                      </div>
                      <div className="text-xs text-slate-500 font-mono mt-1">
                        Computed SHA-256: {doc.file_hash_sha256}
                      </div>
                      {doc.bank_sha_match_status === 'matched' ? (
                        <div className="text-[11px] font-semibold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 mt-1 inline-flex items-center">
                          <CheckCircle2 className="w-3 h-3 mr-1 text-emerald-600" />
                          Direct Bank SHA Match: Bitwise 100% Genuine (Matches Issuing Bank CBS Digest)
                        </div>
                      ) : doc.bank_sha_match_status === 'mismatch' ? (
                        <div className="text-[11px] font-semibold text-rose-800 bg-rose-50 px-2 py-0.5 rounded border border-rose-200 mt-1 inline-flex items-center">
                          <AlertTriangle className="w-3 h-3 mr-1 text-rose-600" />
                          CRITICAL: Bank SHA-256 Digest Mismatch! (Document altered or mismatched)
                        </div>
                      ) : (
                        <div className="text-[11px] text-slate-500 font-mono mt-0.5">
                          Bank CBS Digest: Pending Independent Verification Inquiry
                        </div>
                      )}
                      <div className="text-[11px] text-emerald-700 font-medium mt-0.5">
                        Encrypted with Ephemeral DEK (AES-256-GCM) • Wrapped by Master KEK ({doc.kek_version})
                      </div>
                    </div>

                    <div className="flex items-center space-x-2 shrink-0">
                      <button
                        onClick={() => handleVerifyIntegrity(doc.id)}
                        className="px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 transition-colors"
                      >
                        Verify Tag
                      </button>
                      <button
                        onClick={() => handleDownloadDocument(doc.id, doc.original_filename)}
                        className="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-lg transition-colors flex items-center"
                      >
                        <Download className="w-3.5 h-3.5 mr-1" />
                        Download
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Section 3: OCR Extracted Values vs Submitted Values */}
          <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-xs">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center">
                <FileSearch className="w-4 h-4 mr-2 text-blue-600" />
                OCR Automated Data Extraction & Scrutiny
              </h2>
              {ocr && (
                <span className="text-xs font-bold text-blue-700 bg-blue-50 px-2.5 py-0.5 rounded-full border border-blue-200">
                  Confidence: {Math.round(ocr.overall_confidence * 100)}%
                </span>
              )}
            </div>

            {!ocr ? (
              <div className="text-center py-6 text-slate-400 text-sm">
                No OCR extraction available for this document.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-xs">
                  <thead className="bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider text-left">
                    <tr>
                      <th className="px-4 py-2">Field</th>
                      <th className="px-4 py-2">Institution Submitted</th>
                      <th className="px-4 py-2">Document OCR Extracted</th>
                      <th className="px-4 py-2 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {[
                      {
                        name: 'Bank Name',
                        submitted: fdr.bank_name,
                        extracted: ocr.extracted_data.bank_name,
                      },
                      {
                        name: 'FDR Certificate No',
                        submitted: fdr.fdr_number,
                        extracted: ocr.extracted_data.fdr_number,
                      },
                      {
                        name: 'Principal Amount',
                        submitted: `INR ${Number(fdr.principal_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`,
                        extracted: ocr.extracted_data.principal_amount
                          ? `INR ${Number(ocr.extracted_data.principal_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`
                          : 'N/A',
                      },
                      {
                        name: 'Maturity Date',
                        submitted: fdr.maturity_date,
                        extracted: ocr.extracted_data.maturity_date || 'N/A',
                      },
                    ].map((row, idx) => {
                      const isMatch = row.submitted === row.extracted;
                      return (
                        <tr key={idx} className={!isMatch ? 'bg-amber-50/50' : ''}>
                          <td className="px-4 py-3 font-semibold text-slate-700">{row.name}</td>
                          <td className="px-4 py-3 text-slate-900 font-medium">{row.submitted}</td>
                          <td className="px-4 py-3 font-mono text-slate-900">{row.extracted}</td>
                          <td className="px-4 py-3 text-center">
                            {isMatch ? (
                              <span className="inline-flex items-center text-emerald-700 font-semibold">
                                <CheckCircle2 className="w-4 h-4 mr-1" /> Match
                              </span>
                            ) : (
                              <span className="inline-flex items-center text-amber-700 font-semibold">
                                <AlertTriangle className="w-4 h-4 mr-1" /> Mismatch
                              </span>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>

          {/* Section 4: Automated Discrepancies & Anomalies */}
          <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-xs">
            <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center">
              <ShieldAlert className="w-4 h-4 mr-2 text-red-600" />
              Automated Discrepancy & Anomaly Matrix ({fdr.discrepancies?.length || 0})
            </h2>

            {(!fdr.discrepancies || fdr.discrepancies.length === 0) ? (
              <div className="text-emerald-800 bg-emerald-50 p-4 rounded-xl text-sm flex items-center space-x-2 border border-emerald-200">
                <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                <span>Zero automated discrepancies flagged. Document perfectly aligns with institutional submission.</span>
              </div>
            ) : (
              <div className="space-y-3">
                {fdr.discrepancies.map((d) => (
                  <div
                    key={d.id}
                    className={`p-4 rounded-xl border text-sm ${
                      d.resolution_status === 'resolved'
                        ? 'bg-slate-50 border-slate-200 opacity-75'
                        : d.severity === 'verification_blocked'
                        ? 'bg-red-50 border-red-200 text-red-950'
                        : 'bg-amber-50 border-amber-200 text-amber-950'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-2">
                        <span className="font-mono text-xs font-bold px-2 py-0.5 rounded bg-white border">
                          {d.rule_identifier}
                        </span>
                        <span className="text-xs uppercase font-bold tracking-wider">
                          {d.severity.replace('_', ' ')}
                        </span>
                      </div>
                      <span className={`text-xs font-bold uppercase px-2 py-0.5 rounded ${
                        d.resolution_status === 'resolved' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                      }`}>
                        {d.resolution_status}
                      </span>
                    </div>

                    <p className="mt-2 font-medium">{d.explanation}</p>

                    <div className="grid grid-cols-2 gap-2 mt-2 text-xs bg-white/70 p-2 rounded">
                      <div>Submitted: <strong>{d.submitted_value}</strong></div>
                      <div>Observed: <strong>{d.observed_value}</strong></div>
                    </div>

                    {d.resolution_notes && (
                      <div className="mt-2 text-xs text-slate-600 italic">
                        Resolution Notes: {d.resolution_notes}
                      </div>
                    )}

                    {d.resolution_status === 'open' && (user?.role === 'verification_officer' || user?.role === 'aicte_admin') && (
                      <div className="mt-3 text-right">
                        <button
                          onClick={() => handleResolveDiscrepancy(d.id)}
                          className="px-3 py-1 bg-white hover:bg-slate-100 text-slate-800 text-xs font-semibold rounded border shadow-2xs"
                        >
                          Resolve with Justification
                        </button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Right Col: Verification, Bank Link & Workflow Actions */}
        <div className="space-y-6">
          {/* Bank Verification Box */}
          <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center">
                <Building2 className="w-4 h-4 mr-1.5 text-blue-600" />
                Bank Verification Evidence
              </h3>
              <span className="text-[10px] font-bold bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded">
                SIMULATED ADAPTER
              </span>
            </div>

            <p className="text-xs text-slate-500 leading-relaxed mb-3">
              Independent confirmation inquiry against issuing bank CBS / Core Banking API.
            </p>

            {fdr.bank_verification_requests && fdr.bank_verification_requests.length > 0 ? (
              <div className="p-3 bg-slate-50 rounded-lg text-xs space-y-1.5 border border-slate-200">
                <div className="flex justify-between">
                  <span className="text-slate-500">Bank Status:</span>
                  <strong className="text-slate-900">{fdr.bank_verification_requests[0].status.toUpperCase()}</strong>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Evidence Ref:</span>
                  <span className="font-mono text-slate-700">{fdr.bank_verification_requests[0].verification_evidence_ref}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Simulation:</span>
                  <span className="text-amber-700 font-semibold">Simulated Gateway v1.0</span>
                </div>
              </div>
            ) : (
              <div className="text-xs text-slate-400 py-2">No bank verification inquiry conducted yet.</div>
            )}

            {(user?.role === 'verification_officer' || user?.role === 'aicte_admin') && (
              <div className="mt-4 pt-4 border-t border-slate-100 space-y-2">
                <label className="text-xs font-semibold text-slate-700 block">Simulate Outcome:</label>
                <select
                  value={bankOutcome}
                  onChange={(e: any) => setBankOutcome(e.target.value)}
                  className="w-full text-xs p-2 border border-slate-300 rounded-lg"
                >
                  <option value="confirmed">Confirmed (Lien marked & details match)</option>
                  <option value="mismatch">Mismatch (Bank reports different amount)</option>
                  <option value="not_found">Not Found (FDR not recognized in bank CBS)</option>
                </select>

                <button
                  onClick={handleTriggerBankVerification}
                  disabled={actionLoading || !fdr.documents || fdr.documents.length === 0}
                  className="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Dispatch Bank Verification Inquiry
                </button>
                {(!fdr.documents || fdr.documents.length === 0) && (
                  <p className="text-[11px] font-semibold text-rose-600 mt-1">
                    ⚠️ Cannot verify: Upload a physical FDR receipt document first.
                  </p>
                )}
              </div>
            )}
          </div>

          {/* Workflow & Separation of Duties Decision Box */}
          <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3 flex items-center">
              <UserCheck className="w-4 h-4 mr-1.5 text-emerald-600" />
              Verification & Approval Workflow
            </h3>

            {fdr.verification_case ? (
              <div className="space-y-3 text-xs">
                <div className="flex justify-between pb-2 border-b border-slate-100">
                  <span className="text-slate-500">Case Number:</span>
                  <strong className="font-mono text-slate-900">{fdr.verification_case.case_number}</strong>
                </div>

                <div className="flex justify-between pb-2 border-b border-slate-100">
                  <span className="text-slate-500">Assigned Officer:</span>
                  <span className="text-slate-900 font-medium">
                    {fdr.verification_case.assigned_officer?.name || 'Unassigned'}
                  </span>
                </div>

                {fdr.verification_case.recommendation && (
                  <div className="p-2.5 bg-slate-50 rounded-lg">
                    <div className="font-semibold text-slate-800">
                      Officer Recommendation: {fdr.verification_case.recommendation.toUpperCase()}
                    </div>
                    <p className="text-slate-600 mt-1">{fdr.verification_case.recommendation_notes}</p>
                  </div>
                )}

                {/* Officer Recommendation Buttons */}
                {user?.role === 'verification_officer' && (
                  <div className="pt-2 space-y-2">
                    <span className="font-semibold text-slate-700 block">Submit Officer Recommendation:</span>
                    <div className="grid grid-cols-2 gap-2">
                      <button
                        onClick={() => handleOfficerRecommend('approve')}
                        className="py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded text-xs"
                      >
                        Recommend Approval
                      </button>
                      <button
                        onClick={() => handleOfficerRecommend('reject')}
                        className="py-1.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded text-xs"
                      >
                        Recommend Rejection
                      </button>
                    </div>
                  </div>
                )}

                {/* Approving Authority Decision Box (Separation of Duties) */}
                {(user?.role === 'approving_authority' || user?.role === 'aicte_admin') && (
                  <div className="mt-4 pt-4 border-t border-slate-200">
                    {isSubmitter ? (
                      <div className="p-3 bg-red-50 text-red-800 rounded-lg text-xs border border-red-200 font-medium">
                        ⚠️ Separation of Duties Policy: As the creator/submitter of this FDR, you are strictly prohibited from approving it.
                      </div>
                    ) : (
                      <div className="space-y-2">
                        <span className="font-semibold text-slate-900 block text-xs">
                          Approving Authority Final Verdict:
                        </span>
                        <div className="grid grid-cols-2 gap-2">
                          <button
                            onClick={() => {
                              setDecisionType('approved');
                              setShowDecisionModal(true);
                            }}
                            disabled={!fdr.documents || fdr.documents.length === 0}
                            className="py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-xs disabled:opacity-40 disabled:cursor-not-allowed"
                          >
                            Approve FDR
                          </button>
                          <button
                            onClick={() => {
                              setDecisionType('rejected');
                              setShowDecisionModal(true);
                            }}
                            className="py-2 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs"
                          >
                            Reject FDR
                          </button>
                        </div>
                        {(!fdr.documents || fdr.documents.length === 0) && (
                          <p className="text-[11px] font-semibold text-rose-600 mt-1">
                            ⚠️ Cannot approve: Upload a verified physical receipt document first.
                          </p>
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            ) : (
              <div className="text-xs text-slate-400">Verification case pending activation.</div>
            )}
          </div>

          {/* Audit History Timeline */}
          <div className="bg-white rounded-xl p-5 border border-slate-200 shadow-xs">
            <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3 flex items-center">
              <Clock className="w-4 h-4 mr-1.5 text-slate-600" />
              State Machine Audit Trail
            </h3>

            <div className="space-y-3 max-h-72 overflow-y-auto text-xs">
              {fdr.status_history?.map((sh) => (
                <div key={sh.id} className="border-l-2 border-orange-500 pl-3 py-1">
                  <div className="font-semibold text-slate-900">{sh.action}</div>
                  <p className="text-slate-600 mt-0.5">{sh.comments}</p>
                  <div className="text-[10px] text-slate-400 mt-1">
                    {sh.actor?.name || 'System'} • {new Date(sh.created_at).toLocaleString('en-GB')}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Decision Modal */}
      {showDecisionModal && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <h3 className="text-base font-bold text-slate-900">
              Confirm Regulatory Decision: {decisionType.toUpperCase()}
            </h3>
            <p className="text-xs text-slate-500 mt-1">
              This action creates an irreversible regulatory determination with permanent audit trail.
            </p>

            <div className="mt-4">
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Official Remarks / Reason:
              </label>
              <textarea
                rows={3}
                required
                value={decisionComments}
                onChange={(e) => setDecisionComments(e.target.value)}
                placeholder="Enter formal justification for regulatory records..."
                className="w-full text-xs p-2.5 border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500"
              />
            </div>

            <div className="mt-5 flex justify-end space-x-2">
              <button
                onClick={() => setShowDecisionModal(false)}
                className="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50"
              >
                Cancel
              </button>
              <button
                onClick={handleRenderDecision}
                disabled={actionLoading}
                className={`px-4 py-2 rounded-lg text-xs font-bold text-white ${
                  decisionType === 'approved' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'
                }`}
              >
                Confirm {decisionType.toUpperCase()}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Upload Document Modal */}
      {showUploadModal && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-base font-bold text-slate-900 flex items-center">
                <Upload className="w-5 h-5 mr-2 text-orange-600" />
                Upload Official FDR Receipt Document
              </h3>
              <button
                onClick={() => setShowUploadModal(false)}
                className="text-slate-400 hover:text-slate-600 font-bold"
              >
                ✕
              </button>
            </div>

            <p className="text-xs text-slate-500 mb-4">
              Files are automatically encrypted with unique ephemeral DEKs (AES-256-GCM) and processed through automated OCR text extraction.
            </p>

            <form onSubmit={handleUploadDocument} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Select Receipt File (PDF, PNG, JPG max 15MB) *
                </label>
                <input
                  type="file"
                  required
                  accept=".pdf,.png,.jpg,.jpeg"
                  onChange={(e) => setUploadFile(e.target.files ? e.target.files[0] : null)}
                  className="w-full text-xs p-2 border border-slate-300 rounded-lg file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Bank-Issued Document SHA-256 Digest (Optional)
                </label>
                <p className="text-[11px] text-slate-400 mb-1">
                  If the issuing bank provided an official SHA-256 digest on their e-FDR, paste it here for instant bitwise verification.
                </p>
                <input
                  type="text"
                  placeholder="e.g. 7f9b4c02..."
                  value={uploadBankSha}
                  onChange={(e) => setUploadBankSha(e.target.value)}
                  className="w-full text-xs font-mono p-2 border border-slate-300 rounded-lg"
                />
              </div>

              <div className="pt-3 border-t border-slate-100 flex justify-end space-x-2">
                <button
                  type="button"
                  onClick={() => setShowUploadModal(false)}
                  className="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={uploadingDoc || !uploadFile}
                  className="px-5 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-xs font-bold disabled:opacity-50 flex items-center"
                >
                  {uploadingDoc ? 'Encrypting & Uploading...' : 'Encrypt & Upload Now'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
