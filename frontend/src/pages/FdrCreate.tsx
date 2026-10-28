import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import {
  Upload,
  FileText,
  AlertCircle,
  ArrowLeft,
  Sparkles,
  CheckCircle2,
  Loader2,
  RefreshCw,
  XCircle,
} from 'lucide-react';

interface ScanNotice {
  count: number;
  bank: string;
  confidence: number;
}

export const FdrCreate: React.FC = () => {
  const { user } = useAuth();
  const navigate = useNavigate();

  const [formData, setFormData] = useState({
    bank_name: 'State Bank of India',
    branch_name: '',
    ifsc_code: '',
    fdr_number: '',
    account_number_ref: '',
    principal_amount: '',
    interest_rate: '7.25',
    issue_date: '',
    maturity_date: '',
    tenure_months: '120',
    holder_name: user?.institution?.name || '',
    beneficiary_name: `AICTE & ${user?.institution?.name || 'College'} Joint A/C`,
    purpose: 'Statutory Security Deposit for AICTE Approval',
  });

  const [file, setFile] = useState<File | null>(null);
  const [bankSha256, setBankSha256] = useState('');
  const [loading, setLoading] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [scanNotice, setScanNotice] = useState<ScanNotice | null>(null);
  const [autoFilledFields, setAutoFilledFields] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [dragActive, setDragActive] = useState(false);

  // Trigger OCR extraction and auto-filling
  const handleFileSelect = async (selectedFile: File) => {
    setFile(selectedFile);
    setError(null);
    setScanNotice(null);
    setAutoFilledFields([]);

    setScanning(true);
    try {
      const scanPayload = new FormData();
      scanPayload.append('file', selectedFile);

      const res = await api.post('/ocr/scan-preview', scanPayload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      if (res.data?.success) {
        const { detected_fields, sha256, fields_detected_count, overall_confidence } = res.data;
        const populated: string[] = [];

        setFormData((prev) => {
          const next = { ...prev };
          if (detected_fields.bank_name) {
            next.bank_name = detected_fields.bank_name;
            populated.push('bank_name');
          }
          if (detected_fields.branch_name) {
            next.branch_name = detected_fields.branch_name;
            populated.push('branch_name');
          }
          if (detected_fields.ifsc_code) {
            next.ifsc_code = detected_fields.ifsc_code;
            populated.push('ifsc_code');
          }
          if (detected_fields.fdr_number) {
            next.fdr_number = detected_fields.fdr_number;
            populated.push('fdr_number');
          }
          if (detected_fields.account_number_ref) {
            next.account_number_ref = detected_fields.account_number_ref;
            populated.push('account_number_ref');
          }
          if (detected_fields.principal_amount !== undefined) {
            next.principal_amount = String(detected_fields.principal_amount);
            populated.push('principal_amount');
          }
          if (detected_fields.interest_rate !== undefined) {
            next.interest_rate = String(detected_fields.interest_rate);
            populated.push('interest_rate');
          }
          if (detected_fields.tenure_months !== undefined) {
            next.tenure_months = String(detected_fields.tenure_months);
            populated.push('tenure_months');
          }
          if (detected_fields.issue_date) {
            next.issue_date = detected_fields.issue_date;
            populated.push('issue_date');
          }
          if (detected_fields.maturity_date) {
            next.maturity_date = detected_fields.maturity_date;
            populated.push('maturity_date');
          }
          if (detected_fields.holder_name) {
            next.holder_name = detected_fields.holder_name;
            populated.push('holder_name');
          }
          if (detected_fields.beneficiary_name) {
            next.beneficiary_name = detected_fields.beneficiary_name;
            populated.push('beneficiary_name');
          }
          return next;
        });

        if (sha256) {
          setBankSha256(sha256);
          populated.push('bank_sha256');
        }

        setAutoFilledFields(populated);
        setScanNotice({
          count: fields_detected_count || populated.length,
          bank: detected_fields.bank_name || 'Bank Document',
          confidence: Math.round((overall_confidence || 0.95) * 100),
        });
      }
    } catch (err: any) {
      console.warn('OCR scan preview failed:', err);
      // Non-blocking: inform the user gently
      setError('Could not automatically read fields from this file. You can enter the details manually below.');
    } finally {
      setScanning(false);
    }
  };

  const handleClearAutoFill = () => {
    setAutoFilledFields([]);
    setScanNotice(null);
    setFormData({
      bank_name: 'State Bank of India',
      branch_name: '',
      ifsc_code: '',
      fdr_number: '',
      account_number_ref: '',
      principal_amount: '',
      interest_rate: '7.25',
      issue_date: '',
      maturity_date: '',
      tenure_months: '120',
      holder_name: user?.institution?.name || '',
      beneficiary_name: `AICTE & ${user?.institution?.name || 'College'} Joint A/C`,
      purpose: 'Statutory Security Deposit for AICTE Approval',
    });
    setBankSha256('');
  };

  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setDragActive(true);
    } else if (e.type === 'dragleave') {
      setDragActive(false);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileSelect(e.dataTransfer.files[0]);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!file) {
      setError('Please upload the scanned physical Fixed Deposit Receipt (PDF or Image).');
      return;
    }

    setLoading(true);
    try {
      // 1. Create FDR Draft record
      const fdrRes = await api.post('/fdrs', {
        ...formData,
        principal_amount: parseFloat(formData.principal_amount),
        interest_rate: parseFloat(formData.interest_rate),
        tenure_months: parseInt(formData.tenure_months),
      });

      const fdrId = fdrRes.data.id;

      // 2. Upload and encrypt original document
      const filePayload = new FormData();
      filePayload.append('file', file);
      filePayload.append('document_type', 'fdr_original');
      if (bankSha256.trim()) {
        filePayload.append('bank_sha256', bankSha256.trim());
      }

      await api.post(`/fdrs/${fdrId}/documents`, filePayload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      navigate(`/fdrs/${fdrId}`);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to create FDR submission.');
    } finally {
      setLoading(false);
    }
  };

  const isAuto = (key: string) => autoFilledFields.includes(key);

  const autoBadge = (
    <span className="ml-1.5 inline-flex items-center text-[10px] font-semibold text-emerald-700 bg-emerald-100/90 px-1.5 py-0.5 rounded-full border border-emerald-300">
      <Sparkles className="w-2.5 h-2.5 mr-0.5 text-emerald-600" /> Auto-detected
    </span>
  );

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      <div className="flex items-center space-x-3">
        <button
          onClick={() => navigate('/fdrs')}
          className="p-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-slate-900">New Fixed Deposit Receipt Submission</h1>
          <p className="text-sm text-slate-500">
            Submit physical FDR certificate and financial parameters for AICTE regulatory verification
          </p>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-center space-x-2">
          <AlertCircle className="w-5 h-5 shrink-0 text-red-500" />
          <span>{error}</span>
        </div>
      )}

      {scanNotice && (
        <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-start justify-between animate-fadeIn shadow-2xs">
          <div className="flex items-start space-x-3">
            <div className="p-1.5 bg-emerald-100 text-emerald-700 rounded-lg shrink-0 mt-0.5">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <h4 className="text-sm font-bold text-emerald-900">
                  AI/OCR Auto-Detection Complete ({scanNotice.confidence}% Confidence)
                </h4>
                <span className="text-[11px] font-semibold bg-emerald-200 text-emerald-800 px-2 py-0.5 rounded-full">
                  {scanNotice.count} Fields Populated
                </span>
              </div>
              <p className="text-xs text-emerald-700 mt-1">
                Successfully recognized <strong>{scanNotice.bank}</strong> certificate. Form fields below have been pre-filled from your document. You can freely review, edit, or override any field prior to saving.
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={handleClearAutoFill}
            className="text-xs font-semibold text-emerald-800 hover:text-emerald-950 px-2.5 py-1 rounded bg-white border border-emerald-300 hover:bg-emerald-50 shrink-0 ml-4 transition-colors"
          >
            Clear Auto-fill
          </button>
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-white rounded-2xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        {/* Document Upload Area */}
        <div
          onDragEnter={handleDrag}
          onDragLeave={handleDrag}
          onDragOver={handleDrag}
          onDrop={handleDrop}
          className={`p-6 border-2 border-dashed rounded-xl text-center transition-all ${
            dragActive
              ? 'border-orange-500 bg-orange-50/70 scale-[1.01]'
              : scanning
              ? 'border-indigo-400 bg-indigo-50/40'
              : file
              ? 'border-emerald-300 bg-emerald-50/30'
              : 'border-slate-300 bg-slate-50/50 hover:bg-slate-50'
          }`}
        >
          {scanning ? (
            <div className="py-4 space-y-3">
              <div className="relative mx-auto w-12 h-12 flex items-center justify-center">
                <Loader2 className="w-10 h-10 text-orange-600 animate-spin" />
                <Sparkles className="w-5 h-5 text-indigo-600 absolute animate-pulse" />
              </div>
              <h3 className="text-sm font-bold text-slate-900">
                AI Engine Scanning Document...
              </h3>
              <p className="text-xs text-slate-500 max-w-md mx-auto">
                Analyzing PDF structure, extracting bank name, branch, IFSC code, certificate number, tenure, amounts, and dates...
              </p>
            </div>
          ) : (
            <>
              <Upload className="w-10 h-10 text-orange-600 mx-auto mb-2" />
              <h3 className="text-sm font-bold text-slate-900">Upload Physical FDR Document (Required)</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                Accepts PDF, JPEG, PNG up to 15MB. Encrypted via Envelope Encryption (AES-256-GCM). When uploaded, details are automatically detected and populated below.
              </p>

              <input
                type="file"
                id="fdr_file"
                accept=".pdf,.png,.jpg,.jpeg"
                onChange={(e) => {
                  if (e.target.files && e.target.files[0]) {
                    handleFileSelect(e.target.files[0]);
                  }
                }}
                className="hidden"
              />

              <div className="mt-4 flex items-center justify-center space-x-3">
                <label
                  htmlFor="fdr_file"
                  className="cursor-pointer inline-flex items-center px-4 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs hover:border-slate-400 transition-colors"
                >
                  <FileText className="w-4 h-4 mr-1.5 text-slate-500" />
                  {file ? file.name : 'Select or Drop FDR Receipt File'}
                </label>
                {file && (
                  <button
                    type="button"
                    onClick={() => handleFileSelect(file)}
                    className="inline-flex items-center px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium transition-colors"
                    title="Re-run AI auto-detection scan"
                  >
                    <RefreshCw className="w-3.5 h-3.5 mr-1" /> Re-scan
                  </button>
                )}
              </div>

              {file && (
                <div className="inline-flex items-center space-x-2 text-xs text-emerald-700 font-semibold mt-3 px-3 py-1 bg-emerald-100/80 rounded-full border border-emerald-200">
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  <span>Selected: {file.name} ({(file.size / 1024).toFixed(1)} KB)</span>
                </div>
              )}
            </>
          )}

          <div className="mt-4 pt-4 border-t border-slate-200 text-left max-w-xl mx-auto">
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">
                Bank-Issued Document SHA-256 Digest (Bitwise Integrity)
              </label>
              {isAuto('bank_sha256') && autoBadge}
            </div>
            <p className="text-[11px] text-slate-500 mb-1.5">
              Auto-computed directly from uploaded document bytes or official e-FDR cryptographic signature for bitwise tamper verification.
            </p>
            <input
              type="text"
              placeholder="e.g. 9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"
              value={bankSha256}
              onChange={(e) => setBankSha256(e.target.value)}
              className={`w-full text-xs font-mono p-2 border rounded-lg focus:ring-2 focus:ring-orange-500 transition-colors ${
                isAuto('bank_sha256') ? 'border-emerald-300 bg-emerald-50/20' : 'border-slate-300'
              }`}
            />
          </div>
        </div>

        {/* Financial Fields */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Bank Name *</label>
              {isAuto('bank_name') && autoBadge}
            </div>
            <input
              type="text"
              required
              value={formData.bank_name}
              onChange={(e) => setFormData({ ...formData, bank_name: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                isAuto('bank_name') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
              placeholder="e.g. Canara Bank / State Bank of India"
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Branch Name *</label>
              {isAuto('branch_name') && autoBadge}
            </div>
            <input
              type="text"
              required
              value={formData.branch_name}
              onChange={(e) => setFormData({ ...formData, branch_name: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                isAuto('branch_name') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
              placeholder="e.g. Koramangala 4th Block Branch"
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Bank IFSC Code *</label>
              {isAuto('ifsc_code') && autoBadge}
            </div>
            <input
              type="text"
              required
              value={formData.ifsc_code}
              onChange={(e) => setFormData({ ...formData, ifsc_code: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg font-mono uppercase transition-colors ${
                isAuto('ifsc_code') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
              placeholder="CNRB0001420"
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">FDR Certificate Number *</label>
              {isAuto('fdr_number') && autoBadge}
            </div>
            <input
              type="text"
              required
              value={formData.fdr_number}
              onChange={(e) => setFormData({ ...formData, fdr_number: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg font-mono font-semibold transition-colors ${
                isAuto('fdr_number') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
              placeholder="CNRB-FDR-99887766"
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Bank Account Number (Ref)</label>
              {isAuto('account_number_ref') && autoBadge}
            </div>
            <input
              type="text"
              value={formData.account_number_ref}
              onChange={(e) => setFormData({ ...formData, account_number_ref: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg font-mono transition-colors ${
                isAuto('account_number_ref') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
              placeholder="e.g. 0212101009845"
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Principal Amount (INR) *</label>
              {isAuto('principal_amount') && autoBadge}
            </div>
            <input
              type="number"
              step="0.01"
              required
              value={formData.principal_amount}
              onChange={(e) => setFormData({ ...formData, principal_amount: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg font-semibold transition-colors ${
                isAuto('principal_amount') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
              placeholder="5000000.00"
            />
          </div>

          <div className="grid grid-cols-2 gap-2">
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-semibold text-slate-700">Interest Rate (% p.a.)</label>
                {isAuto('interest_rate') && autoBadge}
              </div>
              <input
                type="number"
                step="0.01"
                value={formData.interest_rate}
                onChange={(e) => setFormData({ ...formData, interest_rate: e.target.value })}
                className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                  isAuto('interest_rate') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
                }`}
              />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-semibold text-slate-700">Tenure (Months)</label>
                {isAuto('tenure_months') && autoBadge}
              </div>
              <input
                type="number"
                value={formData.tenure_months}
                onChange={(e) => setFormData({ ...formData, tenure_months: e.target.value })}
                className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                  isAuto('tenure_months') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
                }`}
                placeholder="120"
              />
            </div>
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Date of Issue *</label>
              {isAuto('issue_date') && autoBadge}
            </div>
            <input
              type="date"
              required
              value={formData.issue_date}
              onChange={(e) => setFormData({ ...formData, issue_date: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                isAuto('issue_date') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Date of Maturity *</label>
              {isAuto('maturity_date') && autoBadge}
            </div>
            <input
              type="date"
              required
              value={formData.maturity_date}
              onChange={(e) => setFormData({ ...formData, maturity_date: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                isAuto('maturity_date') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Holder / Institution Name *</label>
              {isAuto('holder_name') && autoBadge}
            </div>
            <input
              type="text"
              required
              value={formData.holder_name}
              onChange={(e) => setFormData({ ...formData, holder_name: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                isAuto('holder_name') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-semibold text-slate-700">Beneficiary Name (Joint Name) *</label>
              {isAuto('beneficiary_name') && autoBadge}
            </div>
            <input
              type="text"
              required
              value={formData.beneficiary_name}
              onChange={(e) => setFormData({ ...formData, beneficiary_name: e.target.value })}
              className={`w-full text-sm p-2.5 border rounded-lg transition-colors ${
                isAuto('beneficiary_name') ? 'border-emerald-400 bg-emerald-50/20 focus:border-emerald-500' : 'border-slate-300'
              }`}
            />
          </div>

          <div className="sm:col-span-2">
            <label className="block text-xs font-semibold text-slate-700 mb-1">Regulatory Purpose</label>
            <input
              type="text"
              value={formData.purpose}
              onChange={(e) => setFormData({ ...formData, purpose: e.target.value })}
              className="w-full text-sm p-2.5 border border-slate-300 rounded-lg"
            />
          </div>
        </div>

        <div className="flex justify-end space-x-3 pt-4 border-t border-slate-200">
          <button
            type="button"
            onClick={() => navigate('/fdrs')}
            className="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={loading || scanning}
            className="px-6 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm disabled:opacity-50 transition-colors inline-flex items-center"
          >
            {loading ? (
              <>
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                Encrypting & Uploading...
              </>
            ) : (
              'Save & Proceed to Review'
            )}
          </button>
        </div>
      </form>
    </div>
  );
};
