import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';
import {
  Building2,
  FileCheck2,
  AlertTriangle,
  Clock,
  ShieldAlert,
  IndianRupee,
  PlusCircle,
  FileDown,
  ArrowUpRight,
  TrendingUp,
} from 'lucide-react';
import { Fdr } from '../types';

export const Dashboard: React.FC = () => {
  const { user } = useAuth();
  const [metrics, setMetrics] = useState<any>(null);
  const [distribution, setDistribution] = useState<Record<string, number>>({});
  const [recentFdrs, setRecentFdrs] = useState<Fdr[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      const [summaryRes, fdrsRes] = await Promise.all([
        api.get('/reports/summary'),
        api.get('/fdrs?per_page=5'),
      ]);

      setMetrics(summaryRes.data.metrics);
      setDistribution(summaryRes.data.lifecycle_distribution || {});
      setRecentFdrs(fdrsRes.data.data || []);
    } catch (err) {
      console.error('Error fetching dashboard metrics', err);
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

  const formatCurrencyCrores = (amount: number) => {
    if (!amount) return '₹ 0.00 Cr';
    const crores = amount / 10000000;
    return `₹ ${crores.toFixed(2)} Cr`;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[500px]">
        <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-orange-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Top Banner */}
      <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">
            Welcome, {user?.name}
          </h1>
          <p className="text-sm text-slate-500 mt-1">
            {user?.role === 'institution_submitter'
              ? `Institutional Submitter Dashboard • ${user?.institution?.name || 'Assigned Institution'}`
              : 'Regulatory Overview & End-to-End Compliance Operations Dashboard'}
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
            {downloadingCsv ? 'Preparing CSV...' : 'Export Compliance CSV'}
          </button>
        </div>
      </div>

      {/* KPI Counters */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Secured Capital</span>
            <div className="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
              <IndianRupee className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-slate-900 mt-2">
            {formatCurrencyCrores(metrics?.total_amount_secured_inr || 0)}
          </div>
          <div className="text-xs text-emerald-600 flex items-center mt-1 font-medium">
            <TrendingUp className="w-3.5 h-3.5 mr-1" />
            {metrics?.approved_count || 0} Approved Active FDRs
          </div>
        </div>

        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Institutions</span>
            <div className="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
              <Building2 className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-slate-900 mt-2">
            {metrics?.total_institutions || 0}
          </div>
          <div className="text-xs text-slate-500 mt-1">
            Registered & Monitored
          </div>
        </div>

        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Open Discrepancies</span>
            <div className="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
              <AlertTriangle className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-slate-900 mt-2">
            {metrics?.open_discrepancies || 0}
          </div>
          <div className="text-xs text-amber-600 mt-1 font-medium">
            Requires Scrutiny & Resolution
          </div>
        </div>

        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Maturing in 90 Days</span>
            <div className="w-9 h-9 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
              <Clock className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-slate-900 mt-2">
            {metrics?.maturing_90_days || 0}
          </div>
          <div className="text-xs text-orange-600 mt-1 font-medium">
            Renewal Notifications Dispatched
          </div>
        </div>
      </div>

      {/* Critical Anomaly Alert Banner if High Risk Cases Exist */}
      {(metrics?.high_risk_cases || 0) > 0 && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between text-red-900">
          <div className="flex items-center space-x-3">
            <ShieldAlert className="w-6 h-6 text-red-600 shrink-0" />
            <div>
              <div className="font-bold text-sm">
                Integrity & Compliance Alert: {metrics?.high_risk_cases} High/Critical Risk Cases Detected
              </div>
              <p className="text-xs text-red-700 mt-0.5">
                Automated detection flagged potential financial discrepancies or cross-tenant duplicate FDR numbers.
              </p>
            </div>
          </div>
          <Link
            to="/verification-cases"
            className="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shrink-0 transition-colors"
          >
            Inspect Cases
          </Link>
        </div>
      )}

      {/* Lifecycle Breakdown Cards */}
      <div className="bg-white rounded-xl p-6 border border-slate-200 shadow-xs">
        <h3 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">
          FDR Lifecycle Distribution
        </h3>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
          {[
            { label: 'Draft', key: 'draft', color: 'bg-slate-100 text-slate-700 border-slate-200' },
            { label: 'Submitted', key: 'submitted', color: 'bg-blue-50 text-blue-700 border-blue-200' },
            { label: 'Under Review', key: 'under_review', color: 'bg-amber-50 text-amber-700 border-amber-200' },
            { label: 'Approved', key: 'approved', color: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
            { label: 'Maturing / Due', key: 'maturing', color: 'bg-orange-50 text-orange-700 border-orange-200' },
            { label: 'Release Pending', key: 'release_requested', color: 'bg-purple-50 text-purple-700 border-purple-200' },
          ].map((item) => (
            <div key={item.key} className={`p-3 rounded-lg border ${item.color}`}>
              <div className="text-2xl font-bold">{distribution[item.key] || 0}</div>
              <div className="text-xs font-medium mt-1">{item.label}</div>
            </div>
          ))}
        </div>
      </div>

      {/* Recent Submissions Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h3 className="text-base font-bold text-slate-900">Recent FDR Submissions</h3>
          <Link to="/fdrs" className="text-xs font-semibold text-orange-600 hover:text-orange-700 flex items-center">
            View all register records <ArrowUpRight className="w-3.5 h-3.5 ml-1" />
          </Link>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-sm">
            <thead className="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wider text-left">
              <tr>
                <th className="px-6 py-3">Tracking No</th>
                <th className="px-6 py-3">Institution</th>
                <th className="px-6 py-3">Bank & FDR No</th>
                <th className="px-6 py-3">Principal Amount</th>
                <th className="px-6 py-3">Maturity Date</th>
                <th className="px-6 py-3">Lifecycle Status</th>
                <th className="px-6 py-3">Risk Level</th>
                <th className="px-6 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 bg-white">
              {recentFdrs.map((fdr) => (
                <tr key={fdr.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="px-6 py-4 font-mono font-semibold text-slate-900 whitespace-nowrap">
                    {fdr.fdr_tracking_no}
                  </td>
                  <td className="px-6 py-4 text-slate-700 whitespace-nowrap">
                    <div className="font-medium text-slate-900">{fdr.institution?.name}</div>
                    <div className="text-xs text-slate-400">{fdr.institution?.aicte_id}</div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-slate-900 font-medium">{fdr.bank_name}</div>
                    <div className="text-xs font-mono text-slate-500">{fdr.fdr_number}</div>
                  </td>
                  <td className="px-6 py-4 font-semibold text-slate-900 whitespace-nowrap">
                    ₹ {Number(fdr.principal_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                  </td>
                  <td className="px-6 py-4 text-slate-600 whitespace-nowrap">
                    {new Date(fdr.maturity_date).toLocaleDateString('en-GB')}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                      fdr.lifecycle_status === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                      fdr.lifecycle_status === 'under_review' ? 'bg-amber-100 text-amber-800' :
                      fdr.lifecycle_status === 'maturing' ? 'bg-orange-100 text-orange-800' :
                      fdr.lifecycle_status === 'release_requested' ? 'bg-purple-100 text-purple-800' :
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
                      className="text-xs font-semibold text-orange-600 hover:text-orange-900 bg-orange-50 hover:bg-orange-100 px-3 py-1.5 rounded-lg transition-colors"
                    >
                      View Dossier
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
