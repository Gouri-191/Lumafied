import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { TreasuryMetrics, MaturityLadderBucket, BankConcentrationItem, ForecastMonth } from '../types';
import {
  Landmark,
  TrendingUp,
  ShieldAlert,
  Percent,
  Coins,
  Layers,
  Calendar,
  AlertTriangle,
  ArrowUpRight,
  Info,
  CheckCircle2,
  RefreshCw,
  Building2,
} from 'lucide-react';

export const TreasuryDashboard: React.FC = () => {
  const [metrics, setMetrics] = useState<TreasuryMetrics | null>(null);
  const [maturityLadder, setMaturityLadder] = useState<Record<string, MaturityLadderBucket> | null>(null);
  const [concentration, setConcentration] = useState<BankConcentrationItem[]>([]);
  const [forecast, setForecast] = useState<ForecastMonth[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [selectedBucketKey, setSelectedBucketKey] = useState<string>('0_30');

  const fetchTreasuryData = async () => {
    setLoading(true);
    try {
      const [metricsRes, ladderRes, concRes, forecastRes] = await Promise.all([
        api.get('/treasury/metrics'),
        api.get('/treasury/maturity-ladder'),
        api.get('/treasury/bank-concentration'),
        api.get('/treasury/forecast'),
      ]);

      if (metricsRes.data?.success) setMetrics(metricsRes.data.data);
      if (ladderRes.data?.success) setMaturityLadder(ladderRes.data.data);
      if (concRes.data?.success) setConcentration(concRes.data.data);
      if (forecastRes.data?.success) setForecast(forecastRes.data.data);
    } catch (err) {
      console.error('Failed to load treasury analytics', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTreasuryData();
  }, []);

  const formatCurrency = (val: number | string | undefined) => {
    const num = Number(val || 0);
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 2,
    }).format(num);
  };

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] space-y-4">
        <RefreshCw className="w-10 h-10 text-orange-500 animate-spin" />
        <p className="text-slate-400 text-sm font-medium">Loading Treasury & Financial Monitoring Engine...</p>
      </div>
    );
  }

  const selectedBucket = maturityLadder && maturityLadder[selectedBucketKey];

  return (
    <div className="space-y-8 pb-12">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center space-x-2">
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-orange-500/10 text-orange-400 border border-orange-500/20">
              Module 4 · Financial Monitoring
            </span>
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
              Live Verified
            </span>
          </div>
          <h1 className="text-2xl font-bold text-white mt-1">FDR Treasury & Liquidity Management</h1>
          <p className="text-slate-400 text-sm mt-0.5">
            Strict separation of principal, accrued interest, bank-confirmed proceeds, and encumbered regulatory deposits.
          </p>
        </div>

        <button
          onClick={fetchTreasuryData}
          className="inline-flex items-center space-x-2 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-medium rounded-lg border border-slate-700 transition"
        >
          <RefreshCw className="w-4 h-4 text-orange-400" />
          <span>Refresh Financial Telemetry</span>
        </button>
      </div>

      {/* Critical Business Notice: Separation of Financial Measures */}
      <div className="p-4 rounded-xl bg-slate-800/80 border border-amber-500/30 flex items-start space-x-3.5">
        <Info className="w-5 h-5 text-amber-400 shrink-0 mt-0.5" />
        <div className="text-xs text-slate-300 space-y-1">
          <div className="font-semibold text-amber-300 text-sm">Regulatory Financial Accounting Principle</div>
          <p className="leading-relaxed">
            <strong className="text-white">FDR Principal</strong>, <strong className="text-white">Accrued Interest</strong>, <strong className="text-white">Expected Maturity Proceeds</strong>, and <strong className="text-white">Confirmed Bank Proceeds</strong> are mathematically and legally distinct measures. Locked FDR assets do not represent immediately available operational cash until a formal regulatory release and bank liquidation order is executed.
          </p>
        </div>
      </div>

      {/* The 7 Core Distinct Financial Measures */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Metric 1: Outstanding Active Principal */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">1. Active Principal</span>
            <div className="p-2 bg-blue-500/10 rounded-lg text-blue-400">
              <Landmark className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-white">
            {formatCurrency(metrics?.outstanding_active_principal)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
            <span>Deposited Face Value</span>
            <span className="text-blue-400 font-medium">{metrics?.total_active_count} Active FDRs</span>
          </div>
        </div>

        {/* Metric 2: Expected Accrued Interest */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">2. Expected Interest</span>
            <div className="p-2 bg-emerald-500/10 rounded-lg text-emerald-400">
              <TrendingUp className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-emerald-400">
            {formatCurrency(metrics?.expected_interest)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
            <span>Deterministic Yield Growth</span>
            <span className="text-emerald-400 font-medium">+{(metrics?.expected_interest && metrics.outstanding_active_principal ? ((metrics.expected_interest / metrics.outstanding_active_principal) * 100).toFixed(1) : '0')}%</span>
          </div>
        </div>

        {/* Metric 3: Expected Maturity Proceeds */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">3. Expected Maturity Proceeds</span>
            <div className="p-2 bg-purple-500/10 rounded-lg text-purple-400">
              <Coins className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-purple-300">
            {formatCurrency(metrics?.expected_maturity_proceeds)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1">
            Principal + Quarterly Compound Yield
          </div>
        </div>

        {/* Metric 4: Weighted Average Interest Rate */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">4. Portfolio Wtd Rate</span>
            <div className="p-2 bg-orange-500/10 rounded-lg text-orange-400">
              <Percent className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-orange-400">
            {metrics?.weighted_average_rate}% <span className="text-xs font-normal text-slate-400">p.a.</span>
          </div>
          <div className="text-[11px] text-slate-400 mt-1">
            Weighted by Face Value: <code className="text-slate-300">Rw = Σ(Pi·Ri)/ΣPi</code>
          </div>
        </div>

        {/* Metric 5: Confirmed Bank Evidence Proceeds */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">5. Bank-Confirmed Proceeds</span>
            <div className="p-2 bg-teal-500/10 rounded-lg text-teal-400">
              <CheckCircle2 className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-teal-300">
            {formatCurrency(metrics?.confirmed_maturity_proceeds)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1">
            CBS Reconciled & Bitwise Verified
          </div>
        </div>

        {/* Metric 6: Released Liquidity */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">6. Released Funds</span>
            <div className="p-2 bg-indigo-500/10 rounded-lg text-indigo-400">
              <ArrowUpRight className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-indigo-300">
            {formatCurrency(metrics?.released_funds)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1">
            Regulatory Lien Discharged & Paid
          </div>
        </div>

        {/* Metric 7: Encumbered vs Unencumbered */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">7. Regulatory Lien Status</span>
            <div className="p-2 bg-amber-500/10 rounded-lg text-amber-400">
              <Layers className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-amber-400">
            {formatCurrency(metrics?.encumbered_principal)}
          </div>
          <div className="text-[11px] text-slate-400 mt-1 flex justify-between">
            <span>Pledged to AICTE Joint A/C</span>
            <span className="text-slate-400">Free: {formatCurrency(metrics?.unencumbered_principal)}</span>
          </div>
        </div>

        {/* Metric 8: High Risk Attention */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Risk Watchlist</span>
            <div className="p-2 bg-rose-500/10 rounded-lg text-rose-400">
              <ShieldAlert className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-rose-400">
            {metrics?.high_risk_count} <span className="text-xs font-normal text-slate-400">Flagged</span>
          </div>
          <div className="text-[11px] text-rose-300/80 mt-1">
            Discrepancies, Mismatches or Clustered
          </div>
        </div>
      </div>

      {/* Liquidity Maturity Ladder */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-6">
          <div>
            <h2 className="text-lg font-bold text-white flex items-center space-x-2">
              <Calendar className="w-5 h-5 text-orange-400" />
              <span>Liquidity Maturity Ladder</span>
            </h2>
            <p className="text-slate-400 text-xs mt-0.5">
              FDR distribution by contractual maturity timeframe to anticipate reinvestment, extension, or release events.
            </p>
          </div>
        </div>

        {/* Ladder Bucket Selector Tabs */}
        <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 mb-6">
          {maturityLadder &&
            Object.entries(maturityLadder).map(([key, bucket]) => {
              const isSelected = selectedBucketKey === key;
              const isOverdue = key === 'overdue' && bucket.count > 0;
              const isImmediate = key === '0_30' && bucket.count > 0;

              return (
                <button
                  key={key}
                  onClick={() => setSelectedBucketKey(key)}
                  className={`p-3 rounded-lg border text-left transition flex flex-col justify-between ${
                    isSelected
                      ? 'bg-orange-500/15 border-orange-500 text-white shadow-sm'
                      : isOverdue
                      ? 'bg-rose-950/20 border-rose-800/60 hover:bg-rose-950/40 text-slate-300'
                      : isImmediate
                      ? 'bg-amber-950/20 border-amber-800/60 hover:bg-amber-950/40 text-slate-300'
                      : 'bg-slate-800/50 border-slate-700/60 hover:bg-slate-800 text-slate-400'
                  }`}
                >
                  <div className="text-[11px] font-semibold truncate mb-1">
                    {bucket.label.split('(')[0]}
                  </div>
                  <div className="text-base font-bold text-white">
                    {bucket.count} <span className="text-[11px] font-normal text-slate-400">FDRs</span>
                  </div>
                  <div className="text-[11px] font-medium text-slate-300 mt-1">
                    {formatCurrency(bucket.principal)}
                  </div>
                </button>
              );
            })}
        </div>

        {/* Selected Bucket Table */}
        {selectedBucket && (
          <div className="border border-slate-800 rounded-lg overflow-hidden">
            <div className="bg-slate-800/70 px-4 py-3 flex items-center justify-between border-b border-slate-700">
              <span className="text-xs font-semibold text-white uppercase tracking-wider">
                {selectedBucket.label} — {selectedBucket.count} Certificate(s) totalling {formatCurrency(selectedBucket.principal)}
              </span>
            </div>

            {selectedBucket.items.length === 0 ? (
              <div className="p-8 text-center text-slate-500 text-sm">
                No active fixed deposits maturing in this liquidity window.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-slate-800/40 text-slate-400 uppercase tracking-wider text-[11px]">
                    <tr>
                      <th className="py-2.5 px-4">Certificate / Reference</th>
                      <th className="py-2.5 px-4">Bank & Institution</th>
                      <th className="py-2.5 px-4 text-right">Principal Face Value</th>
                      <th className="py-2.5 px-4 text-center">Interest Rate</th>
                      <th className="py-2.5 px-4 text-center">Maturity Date</th>
                      <th className="py-2.5 px-4 text-center">Days Window</th>
                      <th className="py-2.5 px-4 text-right">Expected Proceeds</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800 text-slate-300 font-mono">
                    {selectedBucket.items.map((item) => (
                      <tr key={item.id} className="hover:bg-slate-800/30 transition">
                        <td className="py-3 px-4 font-semibold text-white">
                          <a href={`/fdrs/${item.id}`} className="hover:text-orange-400 transition underline underline-offset-2">
                            {item.fdr_number}
                          </a>
                        </td>
                        <td className="py-3 px-4 font-sans">
                          <div className="font-medium text-white">{item.bank_name}</div>
                          <div className="text-slate-400 text-[11px] truncate max-w-xs">{item.institution}</div>
                        </td>
                        <td className="py-3 px-4 text-right font-bold text-white">
                          {formatCurrency(item.principal_amount)}
                        </td>
                        <td className="py-3 px-4 text-center text-orange-400">
                          {item.interest_rate}% p.a.
                        </td>
                        <td className="py-3 px-4 text-center">
                          {item.maturity_date}
                        </td>
                        <td className="py-3 px-4 text-center">
                          <span
                            className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase ${
                              item.days_remaining < 0
                                ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                                : item.days_remaining <= 30
                                ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                                : 'bg-slate-700/50 text-slate-300'
                            }`}
                          >
                            {item.days_remaining < 0 ? `${Math.abs(item.days_remaining)}d overdue` : `${item.days_remaining}d left`}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-right text-emerald-400 font-semibold">
                          {formatCurrency(item.expected_maturity_amount)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Grid: Bank Concentration & 12-Month Forecast */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Bank Concentration */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
          <div className="flex items-center space-x-2 mb-4">
            <Building2 className="w-5 h-5 text-orange-400" />
            <h2 className="text-lg font-bold text-white">Bank Concentration Risk Analysis</h2>
          </div>
          <p className="text-slate-400 text-xs mb-5">
            Institutional exposure per scheduled commercial bank to ensure statutory risk diversification limits.
          </p>

          <div className="space-y-4">
            {concentration.map((item) => (
              <div key={item.bank_name} className="p-3.5 rounded-lg bg-slate-800/40 border border-slate-800">
                <div className="flex items-center justify-between text-xs font-semibold mb-1.5">
                  <span className="text-white flex items-center space-x-1.5">
                    <span>{item.bank_name}</span>
                    <span className="text-[11px] text-slate-400 font-normal">({item.fdr_count} deposits)</span>
                  </span>
                  <span className="text-orange-400 font-mono">
                    {formatCurrency(item.total_principal)} ({item.portfolio_percentage}%)
                  </span>
                </div>
                {/* Visual Progress Bar */}
                <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden mb-1.5">
                  <div
                    className="bg-gradient-to-r from-orange-500 to-amber-400 h-full rounded-full"
                    style={{ width: `${Math.min(item.portfolio_percentage, 100)}%` }}
                  />
                </div>
                <div className="flex justify-between text-[11px] text-slate-400">
                  <span>Benchmark Weighted Yield:</span>
                  <span className="text-emerald-400 font-semibold">{item.avg_interest_rate}% p.a.</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* 12-Month Maturity & Yield Forecast */}
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
          <div className="flex items-center space-x-2 mb-4">
            <TrendingUp className="w-5 h-5 text-emerald-400" />
            <h2 className="text-lg font-bold text-white">12-Month Liquidity & Inflow Forecast</h2>
          </div>
          <p className="text-slate-400 text-xs mb-5">
            Transparent forward projections of maturities, interest realizations, and rollover checkpoints.
          </p>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-800/50 text-slate-400 uppercase text-[11px] tracking-wider">
                <tr>
                  <th className="py-2.5 px-3">Month</th>
                  <th className="py-2.5 px-3 text-center">Maturing Count</th>
                  <th className="py-2.5 px-3 text-right">Maturing Principal</th>
                  <th className="py-2.5 px-3 text-right">Expected Interest</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800 text-slate-300 font-mono">
                {forecast.slice(0, 8).map((f) => (
                  <tr key={f.month_key} className="hover:bg-slate-800/30">
                    <td className="py-2.5 px-3 font-semibold text-white font-sans">{f.label}</td>
                    <td className="py-2.5 px-3 text-center">
                      {f.maturing_count > 0 ? (
                        <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-500/20 text-orange-400">
                          {f.maturing_count}
                        </span>
                      ) : (
                        <span className="text-slate-600">—</span>
                      )}
                    </td>
                    <td className="py-2.5 px-3 text-right font-medium text-white">
                      {f.maturing_principal > 0 ? formatCurrency(f.maturing_principal) : <span className="text-slate-600">—</span>}
                    </td>
                    <td className="py-2.5 px-3 text-right text-emerald-400">
                      {f.expected_interest > 0 ? formatCurrency(f.expected_interest) : <span className="text-slate-600">—</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TreasuryDashboard;
