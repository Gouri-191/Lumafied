import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { AuditEvent } from '../types';
import { useAuth } from '../context/AuthContext';
import { History, Search, Shield, Filter, Eye, RefreshCw } from 'lucide-react';

export const AuditLogs: React.FC = () => {
  const { user } = useAuth();
  const [logs, setLogs] = useState<AuditEvent[]>([]);
  const [actionFilter, setActionFilter] = useState('');
  const [searchEmail, setSearchEmail] = useState('');
  const [loading, setLoading] = useState(true);
  const [selectedDetails, setSelectedDetails] = useState<any>(null);
  const [chainVerification, setChainVerification] = useState<any>(null);
  const [verifyingChain, setVerifyingChain] = useState(false);

  const handleVerifyChain = async () => {
    setVerifyingChain(true);
    setChainVerification(null);
    try {
      const res = await api.get('/audit-events/verify-chain');
      setChainVerification(res.data);
    } catch (err: any) {
      if (err.response?.status === 409 && err.response?.data) {
        setChainVerification(err.response.data);
      } else {
        alert(err.response?.data?.message || 'Failed to communicate with audit verification server.');
      }
    } finally {
      setVerifyingChain(false);
    }
  };

  useEffect(() => {
    fetchLogs();
  }, [actionFilter, searchEmail]);

  const fetchLogs = async () => {
    setLoading(true);
    try {
      const params: any = {};
      if (actionFilter) params.action = actionFilter;
      if (searchEmail) params.actor_email = searchEmail;

      const res = await api.get('/audit-events', { params });
      const list = Array.isArray(res.data?.data)
        ? res.data.data
        : (Array.isArray(res.data) ? res.data : []);
      setLogs(list);
    } catch (err) {
      console.error('Error fetching audit logs', err);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Immutable Audit Trail</h1>
          <p className="text-sm text-slate-500 mt-1">
            Tamper-evident append-only ledger recording all security, operational, and regulatory events
          </p>
        </div>
        <div className="flex items-center space-x-3">
          <button
            onClick={handleVerifyChain}
            disabled={verifyingChain}
            className="flex items-center space-x-1.5 text-xs font-semibold text-white bg-slate-900 hover:bg-slate-800 px-3.5 py-2 rounded-lg shadow-xs transition-colors disabled:opacity-50"
          >
            {verifyingChain ? (
              <RefreshCw className="w-3.5 h-3.5 animate-spin" />
            ) : (
              <Shield className="w-3.5 h-3.5 text-orange-400" />
            )}
            <span>Verify Cryptographic Chain</span>
          </button>
          <div className="flex items-center space-x-2 text-xs font-semibold text-emerald-800 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-200">
            <Shield className="w-4 h-4 text-emerald-600" />
            <span>Non-Repudiation Merkle Ledger</span>
          </div>
        </div>
      </div>

      {chainVerification && (
        <div className={`p-4 rounded-xl border text-xs ${
          chainVerification.valid
            ? 'bg-emerald-50/90 border-emerald-200 text-emerald-900'
            : 'bg-rose-50 border-rose-200 text-rose-900'
        }`}>
          <div className="flex items-start justify-between">
            <div className="space-y-1">
              <div className="font-bold text-sm flex items-center space-x-1.5">
                <Shield className={`w-4 h-4 ${chainVerification.valid ? 'text-emerald-600' : 'text-rose-600'}`} />
                <span>
                  {chainVerification.valid ? 'Cryptographic Audit Trail Integrity Confirmed' : 'CRITICAL: Cryptographic Tampering Detected!'}
                </span>
              </div>
              <p className="text-slate-600">{chainVerification.message}</p>
              {chainVerification.valid && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2 mt-2 pt-2 border-t border-emerald-200/60 font-mono text-[11px]">
                  <div><span className="font-sans font-semibold text-slate-500">Genesis Block:</span> {chainVerification.genesis_hash.substring(0, 24)}...</div>
                  <div><span className="font-sans font-semibold text-slate-500">Head Block Hash:</span> {chainVerification.head_hash?.substring(0, 24)}...</div>
                  <div><span className="font-sans font-semibold text-slate-500">Total Chained Records:</span> {chainVerification.total_events} events verified</div>
                  <div><span className="font-sans font-semibold text-slate-500">Hashing Primitive:</span> HMAC-SHA256 (Append-Only Merkle Link)</div>
                </div>
              )}
            </div>
            <button
              onClick={() => setChainVerification(null)}
              className="text-slate-400 hover:text-slate-600 font-bold ml-4"
            >
              ✕
            </button>
          </div>
        </div>
      )}

      {/* Filter toolbar */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            type="text"
            placeholder="Search by actor email..."
            value={searchEmail}
            onChange={(e) => setSearchEmail(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-xs border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500"
          />
        </div>

        <div className="w-full sm:w-64">
          <select
            value={actionFilter}
            onChange={(e) => setActionFilter(e.target.value)}
            className="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg text-slate-700"
          >
            <option value="">All Audited Actions</option>
            <option value="LOGIN_SUCCESS">LOGIN_SUCCESS</option>
            <option value="SUBMIT_FDR">SUBMIT_FDR</option>
            <option value="ASSIGN_CASE">ASSIGN_CASE</option>
            <option value="TRIGGER_BANK_VERIFICATION">TRIGGER_BANK_VERIFICATION</option>
            <option value="FINAL_DECISION">FINAL_DECISION</option>
            <option value="RESOLVE_DISCREPANCY">RESOLVE_DISCREPANCY</option>
            <option value="UPLOAD_DOCUMENT">UPLOAD_DOCUMENT</option>
            <option value="DOWNLOAD_DOCUMENT">DOWNLOAD_DOCUMENT</option>
          </select>
        </div>
      </div>

      {/* Main Logs Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center p-12">
            <RefreshCw className="w-8 h-8 animate-spin text-orange-600" />
          </div>
        ) : logs.length === 0 ? (
          <div className="text-center py-12 text-slate-500 text-sm">
            No audit records matching criteria.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-xs">
              <thead className="bg-slate-50 font-semibold text-slate-500 uppercase tracking-wider text-left">
                <tr>
                  <th className="px-6 py-3">Timestamp (UTC)</th>
                  <th className="px-6 py-3">Action</th>
                  <th className="px-6 py-3">Actor / Email</th>
                  <th className="px-6 py-3">Resource</th>
                  <th className="px-6 py-3">IP Address</th>
                  <th className="px-6 py-3">Status</th>
                  <th className="px-6 py-3 text-right">Payload</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-mono">
                {logs.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-6 py-3 text-slate-600 whitespace-nowrap">
                      {new Date(log.created_at).toLocaleString('en-GB')}
                    </td>
                    <td className="px-6 py-3 font-bold text-slate-900 whitespace-nowrap font-sans">
                      <span className="bg-slate-100 text-slate-800 px-2 py-0.5 rounded font-mono text-[11px]">
                        {log.action}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-slate-700 whitespace-nowrap">
                      <div className="font-sans font-medium text-slate-900">{log.actor_email}</div>
                      <div className="text-[10px] text-slate-400 font-sans uppercase">{log.actor_role}</div>
                    </td>
                    <td className="px-6 py-3 text-slate-600 whitespace-nowrap">
                      {log.resource_type} #{log.resource_id || '-'}
                    </td>
                    <td className="px-6 py-3 text-slate-500 whitespace-nowrap">
                      {log.ip_address}
                    </td>
                    <td className="px-6 py-3 whitespace-nowrap">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase font-sans ${
                        log.status === 'success' ? 'bg-emerald-100 text-emerald-800' :
                        log.status === 'blocked' ? 'bg-amber-100 text-amber-800' :
                        'bg-red-100 text-red-800'
                      }`}>
                        {log.status}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-right whitespace-nowrap font-sans">
                      {log.details ? (
                        <button
                          onClick={() => setSelectedDetails(log.details)}
                          className="px-2.5 py-1 bg-white hover:bg-slate-100 border border-slate-300 rounded text-xs font-semibold text-slate-700 inline-flex items-center"
                        >
                          <Eye className="w-3 h-3 mr-1" /> View Diff
                        </button>
                      ) : (
                        <span className="text-slate-300">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* JSON Viewer Modal */}
      {selectedDetails && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <h3 className="text-base font-bold text-slate-900 mb-2">Audit Event Payload / Diff</h3>
            <pre className="p-4 bg-slate-900 text-emerald-400 font-mono text-xs rounded-xl overflow-x-auto max-h-80">
              {JSON.stringify(selectedDetails, null, 2)}
            </pre>
            <div className="mt-4 flex justify-end">
              <button
                onClick={() => setSelectedDetails(null)}
                className="px-4 py-2 bg-slate-800 text-white rounded-lg text-xs font-semibold hover:bg-slate-900"
              >
                Close Viewer
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
