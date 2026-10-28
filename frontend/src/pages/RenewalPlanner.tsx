import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { Fdr, FdrRenewal } from '../types';
import { useAuth } from '../context/AuthContext';
import {
  RotateCcw,
  CheckCircle2,
  Clock,
  ArrowRight,
  TrendingUp,
  ShieldCheck,
  RefreshCw,
  GitBranch,
  Sparkles,
  Award,
} from 'lucide-react';

export const RenewalPlanner: React.FC = () => {
  const { user } = useAuth();
  const [renewals, setRenewals] = useState<FdrRenewal[]>([]);
  const [activeFdrs, setActiveFdrs] = useState<Fdr[]>([]);
  const [selectedFdrId, setSelectedFdrId] = useState<number | null>(null);
  const [scenarios, setScenarios] = useState<any | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [approvingId, setApprovingId] = useState<number | null>(null);

  const isOfficial = user && ['system_admin', 'aicte_admin', 'verification_officer'].includes(user.role);

  const fetchRenewalsAndFdrs = async () => {
    setLoading(true);
    try {
      const [renRes, fdrRes] = await Promise.all([
        api.get('/renewals'),
        api.get('/fdrs'),
      ]);

      if (renRes.data?.success) {
        setRenewals(renRes.data.data?.data || renRes.data.data || []);
      }

      if (fdrRes.data?.success) {
        const list: Fdr[] = fdrRes.data.data?.data || fdrRes.data.data || [];
        setActiveFdrs(list);
        if (list.length > 0 && !selectedFdrId) {
          setSelectedFdrId(list[0].id);
          fetchScenarios(list[0].id);
        }
      }
    } catch (err) {
      console.error('Failed to load renewals data', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchScenarios = async (fdrId: number) => {
    try {
      const res = await api.get(`/renewals/scenarios/${fdrId}`);
      if (res.data?.success) {
        setScenarios(res.data.data);
      }
    } catch (err) {
      console.error('Failed to calculate scenario options', err);
    }
  };

  useEffect(() => {
    fetchRenewalsAndFdrs();
  }, []);

  const handleSelectFdr = (id: number) => {
    setSelectedFdrId(id);
    fetchScenarios(id);
  };

  const formatCurrency = (val: number | string | undefined) => {
    const num = Number(val || 0);
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 2,
    }).format(num);
  };

  const handleSubmitRenewal = async (optionKey: string, optData: any) => {
    if (!selectedFdrId) return;

    if (!confirm(`Submit official renewal request under ${optData.title}?`)) return;

    setSubmitting(true);
    try {
      const res = await api.post(`/renewals/request/${selectedFdrId}`, {
        renewal_option: optionKey,
        proposed_principal: optData.proposed_principal,
        proposed_rate: optData.proposed_rate,
        proposed_tenure_months: optData.proposed_tenure_months,
        estimated_maturity_amount: optData.estimated_maturity_amount,
        reason: `Reinvestment decision: ${optData.title}`,
      });

      if (res.data?.success) {
        alert('FDR Renewal Request submitted for regulatory approval.');
        fetchRenewalsAndFdrs();
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to submit renewal request.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleApproveRenewal = async (renewalId: number) => {
    if (!confirm('Authorize renewal? This will generate a new linked FDR version (v+1) and transition the original to "renewed".')) {
      return;
    }

    setApprovingId(renewalId);
    try {
      const res = await api.post(`/renewals/${renewalId}/approve`, {
        decision_notes: 'Authorized pursuant to AICTE Approval Process Handbook (APH) 2026-27.',
      });

      if (res.data?.success) {
        alert('Renewal Approved! Linked child FDR version spawned successfully.');
        fetchRenewalsAndFdrs();
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to approve renewal.');
    } finally {
      setApprovingId(null);
    }
  };

  return (
    <div className="space-y-8 pb-12">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center space-x-2">
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-purple-500/10 text-purple-400 border border-purple-500/20">
              Module 5 · Maturity & Renewal Planning
            </span>
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
              Linked Versioning Engine
            </span>
          </div>
          <h1 className="text-2xl font-bold text-white mt-1">Maturity & Renewal Scenario Planning</h1>
          <p className="text-slate-400 text-sm mt-0.5">
            Model forward scenarios, submit statutory renewal requests, and authorize version-linked deposit continuations.
          </p>
        </div>

        <button
          onClick={fetchRenewalsAndFdrs}
          className="inline-flex items-center space-x-2 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg border border-slate-700 transition"
        >
          <RefreshCw className="w-4 h-4 text-orange-400" />
          <span>Refresh Renewal Queue</span>
        </button>
      </div>

      {/* Linked Versioning Architecture Callout */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 flex items-start space-x-3.5">
        <GitBranch className="w-5 h-5 text-purple-400 shrink-0 mt-0.5" />
        <div className="text-xs text-slate-300 space-y-1">
          <div className="font-semibold text-purple-300 text-sm">Regulatory Linked Versioning Guarantee</div>
          <p className="leading-relaxed">
            In compliance with AICTE audit mandates, renewals never overwrite historical deposit records. Approving a renewal generates a linked child version (<code className="text-purple-300">version = parent.version + 1</code>) preserving full provenance of earlier terms, rates, certificates, and approval decisions.
          </p>
        </div>
      </div>

      {/* Section 1: Active Renewal Authorization Queue */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h2 className="text-base font-bold text-white mb-4 flex items-center space-x-2">
          <Clock className="w-5 h-5 text-orange-400" />
          <span>Pending & Approved Renewal Requests</span>
        </h2>

        {loading ? (
          <div className="p-8 text-center text-slate-500 text-xs">Loading renewals...</div>
        ) : renewals.length === 0 ? (
          <div className="p-8 text-center text-slate-500 text-xs">No renewal requests on record. Model a scenario below to submit.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-800/50 text-slate-400 uppercase text-[11px] tracking-wider">
                <tr>
                  <th className="py-2.5 px-3">Request Ref</th>
                  <th className="py-2.5 px-3">Original FDR / Bank</th>
                  <th className="py-2.5 px-3 text-right">Previous Face Value</th>
                  <th className="py-2.5 px-3 text-center">Proposed Terms</th>
                  <th className="py-2.5 px-3 text-right">Est. New Maturity</th>
                  <th className="py-2.5 px-3 text-center">Status</th>
                  <th className="py-2.5 px-3 text-center">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800 text-slate-300 font-mono">
                {renewals.map((r) => {
                  const isApproved = r.status === 'approved';

                  return (
                    <tr key={r.id} className="hover:bg-slate-800/30">
                      <td className="py-3 px-3 font-semibold text-white">
                        {r.request_reference}
                      </td>
                      <td className="py-3 px-3 font-sans">
                        <div className="font-semibold text-slate-200">
                          {r.original_fdr ? r.original_fdr.fdr_number : `FDR #${r.original_fdr_id}`}
                        </div>
                        <div className="text-[11px] text-slate-400">
                          {r.original_fdr ? r.original_fdr.bank_name : 'N/A'}
                        </div>
                      </td>
                      <td className="py-3 px-3 text-right font-medium text-white">
                        {r.original_fdr ? formatCurrency(r.original_fdr.principal_amount) : '—'}
                      </td>
                      <td className="py-3 px-3 text-center">
                        <span className="text-orange-400 font-semibold">{r.proposed_tenure_months} Mo</span> @ {r.proposed_interest_rate}%
                      </td>
                      <td className="py-3 px-3 text-right text-emerald-400 font-bold">
                        {formatCurrency(r.estimated_new_maturity_amount)}
                      </td>
                      <td className="py-3 px-3 text-center font-sans">
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                            isApproved
                              ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                              : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                          }`}
                        >
                          {r.status}
                        </span>
                      </td>
                      <td className="py-3 px-3 text-center font-sans">
                        {!isApproved && isOfficial ? (
                          <button
                            onClick={() => handleApproveRenewal(r.id)}
                            disabled={approvingId === r.id}
                            className="px-3 py-1 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded text-xs font-semibold shadow disabled:opacity-50 transition"
                          >
                            {approvingId === r.id ? 'Authorizing...' : 'Authorize Renewal'}
                          </button>
                        ) : isApproved ? (
                          <span className="text-[11px] text-emerald-400 flex items-center justify-center space-x-1">
                            <CheckCircle2 className="w-3.5 h-3.5" />
                            <span>Version Spawned</span>
                          </span>
                        ) : (
                          <span className="text-slate-500 text-[11px]">Under Review</span>
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

      {/* Section 2: Interactive 3-Option Scenario Workbench */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div>
            <h2 className="text-base font-bold text-white flex items-center space-x-2">
              <Sparkles className="w-5 h-5 text-purple-400" />
              <span>3-Option Scenario Reinvestment Calculator</span>
            </h2>
            <p className="text-slate-400 text-xs mt-0.5">
              Select an active or maturing deposit to evaluate rollover options, compounding proceeds, or release outcomes.
            </p>
          </div>

          <div className="w-72">
            <select
              value={selectedFdrId || ''}
              onChange={(e) => handleSelectFdr(Number(e.target.value))}
              className="w-full bg-slate-800 border border-slate-700 text-white text-xs rounded-lg px-3 py-2 focus:border-orange-500 focus:outline-none"
            >
              {activeFdrs.map((fdr) => (
                <option key={fdr.id} value={fdr.id}>
                  {fdr.fdr_number} ({fdr.bank_name}) — {formatCurrency(fdr.principal_amount)}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* 3 Scenario Cards */}
        {scenarios && scenarios.options ? (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {/* Option A: Standard Rollover */}
            <div className="p-5 rounded-xl bg-slate-800/40 border border-slate-700 flex flex-col justify-between hover:border-slate-600 transition">
              <div>
                <div className="flex items-center justify-between mb-3">
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-blue-500/20 text-blue-400 border border-blue-500/30">
                    Option A
                  </span>
                  <span className="text-xs text-slate-400">Prevailing Rate</span>
                </div>
                <h3 className="text-sm font-bold text-white mb-1.5">{scenarios.options.option_a.title}</h3>
                <p className="text-xs text-slate-400 mb-4">{scenarios.options.option_a.description}</p>

                <div className="space-y-2 py-3 border-y border-slate-700/60 font-mono text-xs mb-4">
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Reinvested Proceeds:</span>
                    <span className="text-white font-bold">{formatCurrency(scenarios.options.option_a.proposed_principal)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Term & Yield:</span>
                    <span className="text-orange-400">{scenarios.options.option_a.proposed_tenure_months} Mo @ {scenarios.options.option_a.proposed_rate}%</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Compounded Yield:</span>
                    <span className="text-emerald-400">{formatCurrency(scenarios.options.option_a.estimated_interest)}</span>
                  </div>
                  <div className="flex justify-between pt-1 border-t border-slate-700/40 font-bold">
                    <span className="text-slate-300 font-sans">Estimated Maturity:</span>
                    <span className="text-white text-sm">{formatCurrency(scenarios.options.option_a.estimated_maturity_amount)}</span>
                  </div>
                </div>
              </div>

              <button
                onClick={() => handleSubmitRenewal('option_a', scenarios.options.option_a)}
                disabled={submitting}
                className="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-lg transition disabled:opacity-50"
              >
                Submit Option A Rollover
              </button>
            </div>

            {/* Option B: Long-Term Optimization */}
            <div className="p-5 rounded-xl bg-purple-950/20 border border-purple-800/60 flex flex-col justify-between hover:border-purple-600 transition shadow-lg shadow-purple-950/20">
              <div>
                <div className="flex items-center justify-between mb-3">
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-purple-500/20 text-purple-400 border border-purple-500/30 flex items-center space-x-1">
                    <Award className="w-3 h-3" />
                    <span>Option B · Recommended</span>
                  </span>
                  <span className="text-xs text-purple-300 font-semibold">+0.60% Yield</span>
                </div>
                <h3 className="text-sm font-bold text-white mb-1.5">{scenarios.options.option_b.title}</h3>
                <p className="text-xs text-slate-400 mb-4">{scenarios.options.option_b.description}</p>

                <div className="space-y-2 py-3 border-y border-purple-800/40 font-mono text-xs mb-4">
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Reinvested Proceeds:</span>
                    <span className="text-white font-bold">{formatCurrency(scenarios.options.option_b.proposed_principal)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Term & Yield:</span>
                    <span className="text-purple-400 font-bold">{scenarios.options.option_b.proposed_tenure_months} Mo @ {scenarios.options.option_b.proposed_rate}%</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Compounded Yield:</span>
                    <span className="text-emerald-400 font-bold">{formatCurrency(scenarios.options.option_b.estimated_interest)}</span>
                  </div>
                  <div className="flex justify-between pt-1 border-t border-purple-800/40 font-bold">
                    <span className="text-slate-300 font-sans">Estimated Maturity:</span>
                    <span className="text-white text-sm">{formatCurrency(scenarios.options.option_b.estimated_maturity_amount)}</span>
                  </div>
                </div>
              </div>

              <button
                onClick={() => handleSubmitRenewal('option_b', scenarios.options.option_b)}
                disabled={submitting}
                className="w-full py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-semibold rounded-lg transition disabled:opacity-50"
              >
                Submit Option B Optimization
              </button>
            </div>

            {/* Option C: Release & Liquidation */}
            <div className="p-5 rounded-xl bg-slate-800/40 border border-slate-700 flex flex-col justify-between hover:border-slate-600 transition">
              <div>
                <div className="flex items-center justify-between mb-3">
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-400 border border-amber-500/30">
                    Option C
                  </span>
                  <span className="text-xs text-slate-400">Lien Discharge</span>
                </div>
                <h3 className="text-sm font-bold text-white mb-1.5">{scenarios.options.option_c.title}</h3>
                <p className="text-xs text-slate-400 mb-4">{scenarios.options.option_c.description}</p>

                <div className="space-y-2 py-3 border-y border-slate-700/60 font-mono text-xs mb-4">
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Gross Proceeds:</span>
                    <span className="text-white font-bold">{formatCurrency(scenarios.options.option_c.estimated_maturity_amount)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Status Change:</span>
                    <span className="text-amber-400">Lien Discharged</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-400 font-sans">Operational Balance:</span>
                    <span className="text-emerald-400">Available Liquid Funds</span>
                  </div>
                  <div className="flex justify-between pt-1 border-t border-slate-700/40 font-bold">
                    <span className="text-slate-300 font-sans">Net Cash Realization:</span>
                    <span className="text-white text-sm">{formatCurrency(scenarios.options.option_c.estimated_maturity_amount)}</span>
                  </div>
                </div>
              </div>

              <a
                href={`/fdrs/${selectedFdrId}`}
                className="w-full py-2 bg-slate-700 hover:bg-slate-600 text-white text-xs font-semibold rounded-lg text-center transition"
              >
                Go to FDR Release Workflow
              </a>
            </div>
          </div>
        ) : (
          <div className="p-8 text-center text-slate-500 text-sm">
            Calculating scenarios for selected FDR...
          </div>
        )}
      </div>
    </div>
  );
};

export default RenewalPlanner;
