import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';
import { VerificationCase, User } from '../types';
import { useAuth } from '../context/AuthContext';
import { ClipboardCheck, UserCheck, Clock, AlertTriangle, ArrowRight, RefreshCw } from 'lucide-react';

export const VerificationQueue: React.FC = () => {
  const { user } = useAuth();
  const [cases, setCases] = useState<VerificationCase[]>([]);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);

  // Assignment modal
  const [selectedCase, setSelectedCase] = useState<VerificationCase | null>(null);
  const [officers, setOfficers] = useState<User[]>([]);
  const [selectedOfficerId, setSelectedOfficerId] = useState('');
  const [assignPriority, setAssignPriority] = useState('normal');

  useEffect(() => {
    fetchCases();
  }, [statusFilter]);

  const fetchCases = async () => {
    setLoading(true);
    try {
      const params: any = {};
      if (statusFilter) params.status = statusFilter;
      const res = await api.get('/verification-cases', { params });
      setCases(res.data.data);
    } catch (err) {
      console.error('Error fetching cases', err);
    } finally {
      setLoading(false);
    }
  };

  const handleOpenAssign = async (c: VerificationCase) => {
    setSelectedCase(c);
    setSelectedOfficerId(c.assigned_officer_id ? String(c.assigned_officer_id) : '');
    setAssignPriority(c.priority || 'normal');

    try {
      // Get system users to select officers
      const res = await api.get('/institutions/1/users'); // fallback or query officers
      setOfficers([
        { id: 3, name: 'Shri Amit Kumar', email: 'officer@aicte-india.org', role: 'verification_officer' },
        { id: 2, name: 'Smt. Sunita Sharma', email: 'regulator@aicte-india.org', role: 'aicte_admin' },
      ]);
    } catch {}
  };

  const handleAssignSubmit = async () => {
    if (!selectedCase || !selectedOfficerId) return;

    try {
      await api.post(`/verification-cases/${selectedCase.id}/assign`, {
        officer_id: parseInt(selectedOfficerId),
        priority: assignPriority,
      });
      setSelectedCase(null);
      fetchCases();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Assignment failed.');
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Verification Work Queue</h1>
        <p className="text-sm text-slate-500 mt-1">
          Active cases assigned for FDR document scrutiny, discrepancy resolution, and bank confirmation
        </p>
      </div>

      {/* Filter Toolbar */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="text-xs p-2 border border-slate-300 rounded-lg text-slate-700"
          >
            <option value="">All Case Statuses</option>
            <option value="open">Open / Unassigned</option>
            <option value="under_review">Under Review</option>
            <option value="clarification_pending">Clarification Pending</option>
            <option value="officer_recommended">Officer Recommended</option>
            <option value="closed">Closed / Decided</option>
          </select>
        </div>

        <button
          onClick={fetchCases}
          className="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg"
        >
          <RefreshCw className="w-4 h-4" />
        </button>
      </div>

      {/* Cases List */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center p-12">
            <RefreshCw className="w-8 h-8 animate-spin text-orange-600" />
          </div>
        ) : cases.length === 0 ? (
          <div className="text-center py-12 text-slate-500 text-sm">
            No verification cases match the selected filter.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider text-left">
                <tr>
                  <th className="px-6 py-3">Case ID</th>
                  <th className="px-6 py-3">Institution & FDR</th>
                  <th className="px-6 py-3">Assigned Officer</th>
                  <th className="px-6 py-3">Priority</th>
                  <th className="px-6 py-3">Case Status</th>
                  <th className="px-6 py-3">Recommendation</th>
                  <th className="px-6 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {cases.map((c) => (
                  <tr key={c.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-6 py-4 font-mono font-bold text-slate-900 whitespace-nowrap">
                      {c.case_number}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="font-semibold text-slate-900">{c.fdr?.institution?.name || 'Institution'}</div>
                      <div className="text-xs text-slate-500 font-mono">{c.fdr?.fdr_tracking_no}</div>
                    </td>
                    <td className="px-6 py-4 text-xs whitespace-nowrap">
                      {c.assigned_officer ? (
                        <div className="font-medium text-slate-900 flex items-center">
                          <UserCheck className="w-3.5 h-3.5 mr-1 text-emerald-600" />
                          {c.assigned_officer.name}
                        </div>
                      ) : (
                        <span className="text-amber-700 font-medium">Unassigned</span>
                      )}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 py-0.5 rounded text-[11px] font-bold uppercase ${
                        c.priority === 'urgent' ? 'bg-red-100 text-red-800' :
                        c.priority === 'high' ? 'bg-orange-100 text-orange-800' :
                        'bg-slate-100 text-slate-800'
                      }`}>
                        {c.priority}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                        c.status === 'officer_recommended' ? 'bg-purple-100 text-purple-800' :
                        c.status === 'under_review' ? 'bg-amber-100 text-amber-800' :
                        c.status === 'closed' ? 'bg-emerald-100 text-emerald-800' :
                        'bg-blue-100 text-blue-800'
                      }`}>
                        {c.status.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-xs font-semibold text-slate-700 whitespace-nowrap">
                      {c.recommendation ? c.recommendation.toUpperCase() : 'None'}
                    </td>
                    <td className="px-6 py-4 text-right whitespace-nowrap space-x-2">
                      {(user?.role === 'aicte_admin' || user?.role === 'system_admin') && (
                        <button
                          onClick={() => handleOpenAssign(c)}
                          className="px-2.5 py-1 bg-white hover:bg-slate-50 border border-slate-300 text-xs font-semibold rounded"
                        >
                          Assign
                        </button>
                      )}
                      <Link
                        to={`/fdrs/${c.fdr_id}`}
                        className="inline-flex items-center text-xs font-semibold text-orange-600 hover:text-orange-900 bg-orange-50 hover:bg-orange-100 px-3 py-1.5 rounded-lg transition-colors"
                      >
                        Scrutinize <ArrowRight className="w-3 h-3 ml-1" />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Assignment Modal */}
      {selectedCase && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <h3 className="text-base font-bold text-slate-900">
              Assign Verification Officer ({selectedCase.case_number})
            </h3>

            <div className="mt-4 space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">Select Officer:</label>
                <select
                  value={selectedOfficerId}
                  onChange={(e) => setSelectedOfficerId(e.target.value)}
                  className="w-full text-xs p-2.5 border border-slate-300 rounded-lg"
                >
                  <option value="">Choose officer...</option>
                  {officers.map((o) => (
                    <option key={o.id} value={o.id}>
                      {o.name} ({o.email})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">Priority:</label>
                <select
                  value={assignPriority}
                  onChange={(e) => setAssignPriority(e.target.value)}
                  className="w-full text-xs p-2.5 border border-slate-300 rounded-lg"
                >
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>

            <div className="mt-5 flex justify-end space-x-2">
              <button
                onClick={() => setSelectedCase(null)}
                className="px-4 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700"
              >
                Cancel
              </button>
              <button
                onClick={handleAssignSubmit}
                className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-xs font-bold"
              >
                Save Assignment
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
