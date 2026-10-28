import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';
import { Fdr } from '../types';
import { Search, Filter, PlusCircle, FileDown, RefreshCw, Eye } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

export const FdrList: React.FC = () => {
  const { user } = useAuth();
  const [fdrs, setFdrs] = useState<Fdr[]>([]);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [riskFilter, setRiskFilter] = useState('');
  const [maturingSoon, setMaturingSoon] = useState(false);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });

  useEffect(() => {
    fetchFdrs(1);
  }, [search, statusFilter, riskFilter, maturingSoon]);

  const fetchFdrs = async (page = 1) => {
    setLoading(true);
    try {
      const params: any = { page };
      if (search) params.search = search;
      if (statusFilter) params.lifecycle_status = statusFilter;
      if (riskFilter) params.risk_level = riskFilter;
      if (maturingSoon) params.maturing_soon = true;

      const res = await api.get('/fdrs', { params });
      setFdrs(res.data.data);
      setPagination({
        current_page: res.data.current_page,
        last_page: res.data.last_page,
        total: res.data.total,
      });
    } catch (err) {
      console.error('Error fetching FDRs', err);
    } finally {
      setLoading(false);
    }
  };

  const [downloadingCsv, setDownloadingCsv] = useState(false);

  const handleExportCsv = async () => {
    setDownloadingCsv(true);
    try {
      const response = await api.get('/reports/export-csv', {
        responseType: 'blob',
      });
      const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `AICTE_FDR_Compliance_Export_${new Date().toISOString().slice(0, 10)}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Failed to export compliance CSV', err);
      alert('Failed to download compliance CSV. Please ensure you are logged in with export permissions.');
    } finally {
      setDownloadingCsv(false);
    }
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Fixed Deposit Receipts Registry</h1>
          <p className="text-sm text-slate-500 mt-1">
            Authoritative register of statutory FDRs maintained for AICTE regulatory oversight
          </p>
        </div>

        <div className="flex items-center space-x-3">
          {(user?.role === 'institution_submitter' || user?.role === 'system_admin') && (
            <Link
              to="/fdrs/new"
              className="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors"
            >
              <PlusCircle className="w-4 h-4 mr-2" />
              New Submission
            </Link>
          )}

          <button
            onClick={handleExportCsv}
            disabled={downloadingCsv}
            className="inline-flex items-center px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-lg border border-slate-300 shadow-2xs transition-colors disabled:opacity-50"
          >
            <FileDown className="w-4 h-4 mr-2 text-slate-500" />
            {downloadingCsv ? 'Preparing CSV...' : 'Export CSV'}
          </button>
        </div>
      </div>

      {/* Filter & Search Bar */}
      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs space-y-3">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div className="relative md:col-span-2">
            <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
            <input
              type="text"
              placeholder="Search by tracking no, bank, FDR no, holder..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500"
            />
          </div>

          <div>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500 text-slate-700"
            >
              <option value="">All Lifecycle States</option>
              <option value="draft">Draft</option>
              <option value="submitted">Submitted</option>
              <option value="under_review">Under Review</option>
              <option value="approved">Approved</option>
              <option value="maturing">Maturing</option>
              <option value="release_requested">Release Requested</option>
              <option value="released">Released</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>

          <div>
            <select
              value={riskFilter}
              onChange={(e) => setRiskFilter(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500 text-slate-700"
            >
              <option value="">All Risk Levels</option>
              <option value="low">Low Risk</option>
              <option value="medium">Medium Risk</option>
              <option value="high">High Risk</option>
              <option value="critical">Critical Risk</option>
            </select>
          </div>
        </div>

        <div className="flex items-center justify-between pt-2 border-t border-slate-100 text-xs text-slate-600">
          <label className="flex items-center space-x-2 cursor-pointer">
            <input
              type="checkbox"
              checked={maturingSoon}
              onChange={(e) => setMaturingSoon(e.target.checked)}
              className="rounded text-orange-600 focus:ring-orange-500 w-4 h-4"
            />
            <span className="font-medium text-slate-700">Filter FDRs maturing within 90 days</span>
          </label>

          <span className="text-slate-500">
            Total Records: <strong className="text-slate-900">{pagination.total}</strong>
          </span>
        </div>
      </div>

      {/* Main Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center p-12">
            <RefreshCw className="w-8 h-8 animate-spin text-orange-600" />
          </div>
        ) : fdrs.length === 0 ? (
          <div className="text-center py-12 text-slate-500">
            <p className="text-base font-semibold">No Fixed Deposit records found</p>
            <p className="text-xs mt-1">Try adjusting your filters or search keywords</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider text-left">
                <tr>
                  <th className="px-6 py-3">Tracking No</th>
                  <th className="px-6 py-3">Institution</th>
                  <th className="px-6 py-3">Bank & Certificate</th>
                  <th className="px-6 py-3">Amount (INR)</th>
                  <th className="px-6 py-3">Dates (Issue / Maturity)</th>
                  <th className="px-6 py-3">Lifecycle</th>
                  <th className="px-6 py-3">Risk Level</th>
                  <th className="px-6 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {fdrs.map((fdr) => (
                  <tr key={fdr.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-6 py-4 font-mono font-semibold text-slate-900 whitespace-nowrap">
                      {fdr.fdr_tracking_no}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="font-medium text-slate-900">{fdr.institution?.name}</div>
                      <div className="text-xs font-mono text-slate-400">{fdr.institution?.aicte_id}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-slate-900 font-medium">{fdr.bank_name}</div>
                      <div className="text-xs font-mono text-slate-500">{fdr.fdr_number}</div>
                    </td>
                    <td className="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">
                      ₹ {Number(fdr.principal_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                    </td>
                    <td className="px-6 py-4 text-xs text-slate-600 whitespace-nowrap">
                      <div>Issued: {new Date(fdr.issue_date).toLocaleDateString('en-GB')}</div>
                      <div className="font-semibold text-slate-900">Matures: {new Date(fdr.maturity_date).toLocaleDateString('en-GB')}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                        fdr.lifecycle_status === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                        fdr.lifecycle_status === 'under_review' ? 'bg-amber-100 text-amber-800' :
                        fdr.lifecycle_status === 'maturing' ? 'bg-orange-100 text-orange-800' :
                        fdr.lifecycle_status === 'release_requested' ? 'bg-purple-100 text-purple-800' :
                        fdr.lifecycle_status === 'rejected' ? 'bg-red-100 text-red-800' :
                        'bg-slate-100 text-slate-800'
                      }`}>
                        {fdr.lifecycle_status.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-bold uppercase ${
                        fdr.risk_level === 'critical' ? 'bg-red-100 text-red-800' :
                        fdr.risk_level === 'high' ? 'bg-orange-100 text-orange-800' :
                        fdr.risk_level === 'medium' ? 'bg-amber-100 text-amber-800' :
                        'bg-emerald-100 text-emerald-800'
                      }`}>
                        {fdr.risk_level}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-right whitespace-nowrap">
                      <Link
                        to={`/fdrs/${fdr.id}`}
                        className="inline-flex items-center text-xs font-semibold text-orange-600 hover:text-orange-900 bg-orange-50 hover:bg-orange-100 px-3 py-1.5 rounded-lg transition-colors"
                      >
                        <Eye className="w-3.5 h-3.5 mr-1" />
                        View Dossier
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Controls */}
        {pagination.last_page > 1 && (
          <div className="px-6 py-3 border-t border-slate-200 flex items-center justify-between text-xs text-slate-600">
            <span>
              Page {pagination.current_page} of {pagination.last_page}
            </span>
            <div className="flex space-x-2">
              <button
                disabled={pagination.current_page <= 1}
                onClick={() => fetchFdrs(pagination.current_page - 1)}
                className="px-3 py-1 border border-slate-300 rounded-md disabled:opacity-40 hover:bg-slate-50"
              >
                Previous
              </button>
              <button
                disabled={pagination.current_page >= pagination.last_page}
                onClick={() => fetchFdrs(pagination.current_page + 1)}
                className="px-3 py-1 border border-slate-300 rounded-md disabled:opacity-40 hover:bg-slate-50"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
