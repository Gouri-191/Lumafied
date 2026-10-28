import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { ReconciliationRun, ReconciliationDiscrepancy } from '../types';
import {
  FileCheck2,
  AlertCircle,
  CheckCircle,
  FileText,
  Upload,
  ArrowRight,
  RefreshCw,
  Search,
  Scale,
  Sparkles,
} from 'lucide-react';

export const Reconciliation: React.FC = () => {
  const [runs, setRuns] = useState<ReconciliationRun[]>([]);
  const [selectedRun, setSelectedRun] = useState<ReconciliationRun | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [importing, setImporting] = useState<boolean>(false);
  const [resolvingId, setResolvingId] = useState<number | null>(null);
  const [resolutionNotes, setResolutionNotes] = useState<string>('');

  // Importer form state
  const [bankName, setBankName] = useState<string>('State Bank of India');
  const [rawJsonEntries, setRawJsonEntries] = useState<string>('');
  const [importStatus, setImportStatus] = useState<string | null>(null);

  const fetchRuns = async () => {
    setLoading(true);
    try {
      const res = await api.get('/reconciliation');
      if (res.data?.success) {
        const runList = res.data.data?.data || res.data.data || [];
        setRuns(runList);
        if (runList.length > 0 && !selectedRun) {
          fetchRunDetail(runList[0].id);
        }
      }
    } catch (err) {
      console.error('Failed to fetch reconciliation runs', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchRunDetail = async (runId: number) => {
    try {
      const res = await api.get(`/reconciliation/${runId}`);
      if (res.data?.success) {
        setSelectedRun(res.data.data);
      }
    } catch (err) {
      console.error('Failed to fetch run detail', err);
    }
  };

  useEffect(() => {
    fetchRuns();
  }, []);

  const formatCurrency = (val: number | string | undefined) => {
    const num = Number(val || 0);
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 2,
    }).format(num);
  };

  const handleLoadDemoStatement = () => {
    const demoEntries = [
      {
        fdr_number: 'SBI-FDR-9876543210',
        account_number: 'A/C-XXXX-XXXX-4501',
        principal_amount: 5000000.0,
        transaction_date: '2026-01-10',
        description: 'Term Deposit Confirmed Lien favor Member Secretary AICTE',
      },
      {
        fdr_number: 'SBI-FDR-9876543210',
        account_number: 'A/C-XXXX-XXXX-9911',
        principal_amount: 4750000.0,
        transaction_date: '2026-01-10',
        description: 'CBS Recorded Principal differs from submitted certificate',
      },
      {
        fdr_number: 'SBI-FDR-EXT-88991122',
        account_number: 'A/C-XXXX-XXXX-3311',
        principal_amount: 2000000.0,
        transaction_date: '2026-02-01',
        description: 'New CBS deposit flagged with AICTE beneficiary but unsubmitted in portal',
      },
    ];

    setRawJsonEntries(JSON.stringify(demoEntries, null, 2));
    setImportStatus('Demo CBS statement loaded. Click "Execute Automated Statement Reconciliation" below.');
  };

  const handleExecuteImport = async () => {
    if (!rawJsonEntries.trim()) {
      alert('Please provide statement entries or click "Load Demo Statement".');
      return;
    }

    let parsed = [];
    try {
      parsed = JSON.parse(rawJsonEntries);
    } catch (e) {
      alert('Invalid JSON format. Please ensure valid JSON array syntax.');
      return;
    }

    setImporting(true);
    setImportStatus(null);
    try {
      const res = await api.post('/reconciliation/import', {
        bank_name: bankName,
        entries: parsed,
      });

      if (res.data?.success) {
        setImportStatus(`Success: Imported run ${res.data.data.run_reference}. Matched: ${res.data.data.matched_count}, Discrepancies: ${res.data.data.discrepancy_count}`);
        fetchRuns();
        if (res.data.data.id) {
          fetchRunDetail(res.data.data.id);
        }
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to execute reconciliation import.');
    } finally {
      setImporting(false);
    }
  };

  const handleResolveDiscrepancy = async (discId: number) => {
    if (!resolutionNotes.trim()) {
      alert('Please enter resolution or audit verification notes.');
      return;
    }

    try {
      const res = await api.post(`/reconciliation/discrepancies/${discId}/resolve`, {
        resolution_notes: resolutionNotes,
      });

      if (res.data?.success) {
        alert('Discrepancy marked as resolved.');
        setResolvingId(null);
        setResolutionNotes('');
        if (selectedRun) fetchRunDetail(selectedRun.id);
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to resolve discrepancy.');
    }
  };

  return (
    <div className="space-y-8 pb-12">
      {/* Page Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center space-x-2">
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-teal-500/10 text-teal-400 border border-teal-500/20">
              Module 6 · Bank & Balance Reconciliation
            </span>
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-blue-500/10 text-blue-400 border border-blue-500/20">
              CBS Core Banking Engine
            </span>
          </div>
          <h1 className="text-2xl font-bold text-white mt-1">Bank Statement & Ledger Reconciliation</h1>
          <p className="text-slate-400 text-sm mt-0.5">
            Automated verification comparing bank ledger records against AICTE portal FDR submissions.
          </p>
        </div>

        <button
          onClick={fetchRuns}
          className="inline-flex items-center space-x-2 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg border border-slate-700 transition"
        >
          <RefreshCw className="w-4 h-4 text-orange-400" />
          <span>Refresh Reconciliation Runs</span>
        </button>
      </div>

      {/* Top Section: Run Importer Form */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
          <div>
            <h2 className="text-base font-bold text-white flex items-center space-x-2">
              <Upload className="w-5 h-5 text-teal-400" />
              <span>Import Bank CBS Statement</span>
            </h2>
            <p className="text-slate-400 text-xs mt-0.5">
              Upload CBS ledger statements to reconcile active FDR certificate values, maturities, and holders.
            </p>
          </div>

          <button
            onClick={handleLoadDemoStatement}
            type="button"
            className="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-teal-500/15 hover:bg-teal-500/25 text-teal-300 border border-teal-500/30 rounded-lg text-xs font-semibold transition"
          >
            <Sparkles className="w-4 h-4 text-teal-400" />
            <span>Load Official CBS Test Statement</span>
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">
              Bank Institution
            </label>
            <select
              value={bankName}
              onChange={(e) => setBankName(e.target.value)}
              className="w-full bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-3 py-2.5 focus:border-orange-500 focus:outline-none"
            >
              <option value="State Bank of India">State Bank of India</option>
              <option value="Punjab National Bank">Punjab National Bank</option>
              <option value="Canara Bank">Canara Bank</option>
              <option value="HDFC Bank">HDFC Bank</option>
            </select>
          </div>

          <div className="md:col-span-3">
            <label className="block text-xs font-semibold text-slate-300 mb-1.5 uppercase tracking-wider">
              Statement Records (JSON Ledger Rows)
            </label>
            <textarea
              rows={4}
              value={rawJsonEntries}
              onChange={(e) => setRawJsonEntries(e.target.value)}
              placeholder='[ { "fdr_number": "SBI-FDR-9876543210", "principal_amount": 5000000.00, ... } ]'
              className="w-full bg-slate-800/80 border border-slate-700 text-slate-200 font-mono text-xs rounded-lg p-3 focus:border-orange-500 focus:outline-none"
            />
          </div>
        </div>

        {importStatus && (
          <div className="p-3 mb-4 rounded-lg bg-teal-500/10 border border-teal-500/20 text-xs text-teal-300">
            {importStatus}
          </div>
        )}

        <div className="flex justify-end">
          <button
            onClick={handleExecuteImport}
            disabled={importing}
            className="inline-flex items-center space-x-2 px-5 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white text-sm font-semibold rounded-lg shadow transition disabled:opacity-50"
          >
            {importing ? (
              <>
                <RefreshCw className="w-4 h-4 animate-spin" />
                <span>Reconciling Against Active FDRs...</span>
              </>
            ) : (
              <>
                <Scale className="w-4 h-4" />
                <span>Execute Automated Statement Reconciliation</span>
              </>
            )}
          </button>
        </div>
      </div>

      {/* Main Grid: Runs List & Detailed Inspector */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Left: Past Reconciliation Runs */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
          <h2 className="text-base font-bold text-white mb-3 flex items-center space-x-2">
            <FileText className="w-4 h-4 text-orange-400" />
            <span>Reconciliation Audit Runs</span>
          </h2>

          {loading ? (
            <div className="p-8 text-center text-slate-500 text-xs">Loading runs...</div>
          ) : runs.length === 0 ? (
            <div className="p-8 text-center text-slate-500 text-xs">No reconciliation runs recorded yet.</div>
          ) : (
            <div className="space-y-3">
              {runs.map((r) => {
                const isSelected = selectedRun?.id === r.id;
                const hasDiscrepancy = r.discrepancy_count > 0;

                return (
                  <div
                    key={r.id}
                    onClick={() => fetchRunDetail(r.id)}
                    className={`p-3.5 rounded-lg border cursor-pointer transition ${
                      isSelected
                        ? 'bg-orange-500/10 border-orange-500/80'
                        : 'bg-slate-800/40 border-slate-800 hover:bg-slate-800/80'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <span className="font-mono text-xs font-bold text-white">{r.run_reference}</span>
                      <span
                        className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                          hasDiscrepancy
                            ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                            : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                        }`}
                      >
                        {hasDiscrepancy ? `${r.discrepancy_count} Discrepancies` : 'Matched Clean'}
                      </span>
                    </div>

                    <div className="text-xs text-slate-300 font-medium mb-1.5">{r.bank_name}</div>

                    <div className="grid grid-cols-2 text-[11px] text-slate-400 gap-1 pt-2 border-t border-slate-800">
                      <div>
                        Stmt Amount: <span className="text-white font-mono">{formatCurrency(r.total_statement_amount)}</span>
                      </div>
                      <div className="text-right">
                        Matched: <span className="text-emerald-400 font-bold">{r.matched_count}</span> / {r.total_statement_records}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Right: Selected Run Deep Dive & Discrepancies */}
        <div className="lg:col-span-2 space-y-6">
          {selectedRun ? (
            <>
              {/* Run Metrics Bar */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4 mb-4">
                  <div>
                    <span className="text-xs font-mono text-orange-400">{selectedRun.run_reference}</span>
                    <h3 className="text-lg font-bold text-white">{selectedRun.bank_name} Reconciliation Detail</h3>
                    <p className="text-xs text-slate-400">
                      Period: {selectedRun.statement_period_start || 'N/A'} to {selectedRun.statement_period_end || 'N/A'}
                    </p>
                  </div>

                  <div className="text-right">
                    <span className="text-xs text-slate-400">Ledger Variance</span>
                    <div className="text-xl font-bold font-mono text-rose-400">
                      {formatCurrency(Math.abs(selectedRun.total_statement_amount - selectedRun.total_matched_amount))}
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-3 text-center">
                  <div className="bg-slate-800/40 p-3 rounded-lg border border-slate-800">
                    <div className="text-xs text-slate-400">Total CBS Statement</div>
                    <div className="text-sm font-bold text-white font-mono mt-0.5">
                      {formatCurrency(selectedRun.total_statement_amount)}
                    </div>
                    <div className="text-[10px] text-slate-500 mt-0.5">{selectedRun.total_statement_records} records</div>
                  </div>

                  <div className="bg-slate-800/40 p-3 rounded-lg border border-slate-800">
                    <div className="text-xs text-slate-400">Matched Portal Value</div>
                    <div className="text-sm font-bold text-emerald-400 font-mono mt-0.5">
                      {formatCurrency(selectedRun.total_matched_amount)}
                    </div>
                    <div className="text-[10px] text-emerald-400/80 mt-0.5">{selectedRun.matched_count} verified</div>
                  </div>

                  <div className="bg-slate-800/40 p-3 rounded-lg border border-slate-800">
                    <div className="text-xs text-slate-400">Discrepancies</div>
                    <div className="text-sm font-bold text-rose-400 font-mono mt-0.5">
                      {selectedRun.discrepancy_count}
                    </div>
                    <div className="text-[10px] text-rose-400/80 mt-0.5">Requires audit review</div>
                  </div>
                </div>
              </div>

              {/* Discrepancies Table / Resolver */}
              <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
                <h4 className="text-sm font-bold text-white mb-3 flex items-center space-x-2">
                  <AlertCircle className="w-4 h-4 text-rose-400" />
                  <span>Ledger Discrepancy Desk</span>
                </h4>

                {(!selectedRun.discrepancies || selectedRun.discrepancies.length === 0) ? (
                  <div className="p-6 text-center text-slate-500 text-xs flex flex-col items-center">
                    <CheckCircle className="w-8 h-8 text-emerald-400 mb-2" />
                    <span>All statement records matched portal deposits with 0.00 variance.</span>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {selectedRun.discrepancies.map((disc) => {
                      const isResolved = disc.status === 'resolved';

                      return (
                        <div
                          key={disc.id}
                          className={`p-4 rounded-lg border transition ${
                            isResolved
                              ? 'bg-slate-800/20 border-slate-800 text-slate-400'
                              : 'bg-rose-950/20 border-rose-800/50 text-slate-200'
                          }`}
                        >
                          <div className="flex items-center justify-between mb-2">
                            <span className="font-mono text-xs font-bold text-orange-400">
                              {disc.discrepancy_type}
                            </span>
                            <span
                              className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                isResolved
                                  ? 'bg-emerald-500/20 text-emerald-400'
                                  : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                              }`}
                            >
                              {disc.status}
                            </span>
                          </div>

                          <div className="grid grid-cols-2 text-xs gap-3 mb-2 font-mono">
                            <div>
                              <span className="text-slate-400 text-[11px] block">Portal Recorded:</span>
                              <span className="text-white font-medium">{formatCurrency(disc.recorded_amount)}</span>
                            </div>
                            <div>
                              <span className="text-slate-400 text-[11px] block">Bank Evidenced:</span>
                              <span className="text-rose-400 font-bold">{formatCurrency(disc.bank_evidenced_amount)}</span>
                            </div>
                          </div>

                          <p className="text-xs text-slate-300 font-sans mb-3 whitespace-pre-line">
                            {disc.investigation_notes}
                          </p>

                          {!isResolved ? (
                            resolvingId === disc.id ? (
                              <div className="pt-3 border-t border-rose-800/40 space-y-2">
                                <label className="text-[11px] font-semibold text-slate-300 block">
                                  Audit Resolution Notes (CFO / Regulatory Officer):
                                </label>
                                <textarea
                                  rows={2}
                                  value={resolutionNotes}
                                  onChange={(e) => setResolutionNotes(e.target.value)}
                                  placeholder="Document reason for variance, adjustment voucher, or clarification reference..."
                                  className="w-full bg-slate-900 border border-slate-700 text-xs text-white p-2.5 rounded-lg focus:outline-none focus:border-orange-500"
                                />
                                <div className="flex justify-end space-x-2">
                                  <button
                                    onClick={() => setResolvingId(null)}
                                    className="px-3 py-1.5 bg-slate-800 text-slate-300 text-xs rounded-lg hover:bg-slate-700"
                                  >
                                    Cancel
                                  </button>
                                  <button
                                    onClick={() => handleResolveDiscrepancy(disc.id)}
                                    className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-lg"
                                  >
                                    Confirm Resolution & Sign
                                  </button>
                                </div>
                              </div>
                            ) : (
                              <button
                                onClick={() => {
                                  setResolvingId(disc.id);
                                  setResolutionNotes('');
                                }}
                                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-lg border border-slate-700 transition"
                              >
                                Record Audit Resolution
                              </button>
                            )
                          ) : (
                            <div className="text-[11px] text-emerald-400 flex items-center space-x-1.5">
                              <CheckCircle className="w-3.5 h-3.5" />
                              <span>Resolved on {disc.resolved_at || 'Recent date'}</span>
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </>
          ) : (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-12 text-center text-slate-500 text-sm">
              Select a reconciliation run from the left ledger to inspect statement details.
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default Reconciliation;
