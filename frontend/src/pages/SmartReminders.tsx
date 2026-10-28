import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { FdrReminder } from '../types';
import {
  Bell,
  AlertTriangle,
  Clock,
  ShieldAlert,
  CheckCircle2,
  Send,
  Zap,
  RefreshCw,
  Mail,
  UserCheck,
} from 'lucide-react';

export const SmartReminders: React.FC = () => {
  const [reminders, setReminders] = useState<FdrReminder[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [scanning, setScanning] = useState<boolean>(false);
  const [scanResult, setScanResult] = useState<string | null>(null);

  const fetchReminders = async () => {
    setLoading(true);
    try {
      const res = await api.get('/reminders');
      if (res.data?.success) {
        setReminders(res.data.data || []);
      }
    } catch (err) {
      console.error('Failed to load reminders', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReminders();
  }, []);

  const handleTriggerScan = async () => {
    setScanning(true);
    setScanResult(null);
    try {
      const res = await api.post('/reminders/trigger');
      if (res.data?.success) {
        setScanResult(
          `Interval scan complete: Generated ${res.data.data.new_reminders_generated} new alerts, Escalated ${res.data.data.escalated_to_supervisors} overdue items to supervisor.`
        );
        fetchReminders();
      }
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to trigger reminder interval scan.');
    } finally {
      setScanning(false);
    }
  };

  const urgentCount = reminders.filter((r) => r.priority === 'urgent' || r.reminder_type === 'post_maturity_overdue').length;
  const escalatedCount = reminders.filter((r) => r.status === 'escalated' || r.escalated_to_id).length;

  return (
    <div className="space-y-8 pb-12">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
          <div className="flex items-center space-x-2">
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
              Module 2 · Smart Reminders Engine
            </span>
            <span className="px-2.5 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-rose-500/10 text-rose-400 border border-rose-500/20">
              Supervisor Escalation
            </span>
          </div>
          <h1 className="text-2xl font-bold text-white mt-1">Maturity & Compliance Reminders Engine</h1>
          <p className="text-slate-400 text-sm mt-0.5">
            Configurable interval monitoring (90d, 60d, 30d, 15d, 7d, 1d) with automated supervisor escalation for overdue deposits.
          </p>
        </div>

        <button
          onClick={handleTriggerScan}
          disabled={scanning}
          className="inline-flex items-center space-x-2 px-4 py-2 bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-sm font-semibold rounded-lg shadow transition disabled:opacity-50"
        >
          {scanning ? (
            <>
              <RefreshCw className="w-4 h-4 animate-spin" />
              <span>Scanning Intervals & Rules...</span>
            </>
          ) : (
            <>
              <Zap className="w-4 h-4" />
              <span>Run Automated Interval Scan</span>
            </>
          )}
        </button>
      </div>

      {scanResult && (
        <div className="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 flex items-center space-x-2">
          <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0" />
          <span>{scanResult}</span>
        </div>
      )}

      {/* Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Scheduled Reminders</span>
            <div className="p-2 bg-blue-500/10 rounded-lg text-blue-400">
              <Bell className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-white">{reminders.length} Active</div>
          <div className="text-[11px] text-slate-400 mt-1">Pending regulatory delivery & notification</div>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Urgent & Overdue</span>
            <div className="p-2 bg-rose-500/10 rounded-lg text-rose-400">
              <AlertTriangle className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-rose-400">{urgentCount} Flagged</div>
          <div className="text-[11px] text-rose-300/80 mt-1">Maturing within 7 days or past contractual term</div>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-semibold uppercase tracking-wider">Supervisor Escalated</span>
            <div className="p-2 bg-amber-500/10 rounded-lg text-amber-400">
              <ShieldAlert className="w-5 h-5" />
            </div>
          </div>
          <div className="text-2xl font-bold text-amber-400">{escalatedCount} Escalated</div>
          <div className="text-[11px] text-slate-400 mt-1">Reassigned to Regional / National Admin</div>
        </div>
      </div>

      {/* Interval Architecture Roadmap */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h2 className="text-sm font-bold text-white mb-2">Automated Interval Cascade</h2>
        <p className="text-slate-400 text-xs mb-4">
          Statutory notifications automatically emit at each regulatory waypoint prior to maturity:
        </p>

        <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
          {[
            { label: '90 Days', note: 'Early Planning', color: 'border-slate-700 bg-slate-800/40 text-slate-300' },
            { label: '60 Days', note: 'Board Notice', color: 'border-slate-700 bg-slate-800/40 text-slate-300' },
            { label: '30 Days', note: 'Mandatory Filing', color: 'border-amber-700/50 bg-amber-950/20 text-amber-300' },
            { label: '15 Days', note: 'Bank Instruction', color: 'border-amber-700/50 bg-amber-950/20 text-amber-300' },
            { label: '7 Days', note: 'Urgent Flag', color: 'border-rose-700/50 bg-rose-950/20 text-rose-300' },
            { label: '1 Day', note: 'Imminent Cutoff', color: 'border-rose-700/50 bg-rose-950/20 text-rose-300' },
            { label: 'Post-Maturity', note: 'Supervisor Escalation', color: 'border-purple-700/50 bg-purple-950/20 text-purple-300' },
          ].map((step, idx) => (
            <div key={idx} className={`p-3 rounded-lg border text-center ${step.color}`}>
              <div className="font-bold text-xs">{step.label}</div>
              <div className="text-[10px] text-slate-400 mt-0.5">{step.note}</div>
            </div>
          ))}
        </div>
      </div>

      {/* Reminders Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h2 className="text-base font-bold text-white mb-4 flex items-center space-x-2">
          <Clock className="w-5 h-5 text-orange-400" />
          <span>Active Compliance Reminders Ledger</span>
        </h2>

        {loading ? (
          <div className="p-8 text-center text-slate-500 text-xs">Loading reminder events...</div>
        ) : reminders.length === 0 ? (
          <div className="p-8 text-center text-slate-500 text-xs">
            No active reminders found. Click "Run Automated Interval Scan" above to evaluate active FDRs.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-800/50 text-slate-400 uppercase text-[11px] tracking-wider">
                <tr>
                  <th className="py-2.5 px-3">Type & Interval</th>
                  <th className="py-2.5 px-3">FDR Certificate & Institution</th>
                  <th className="py-2.5 px-3 text-center">Target Maturity</th>
                  <th className="py-2.5 px-3">Recipient & Alert Summary</th>
                  <th className="py-2.5 px-3 text-center">Priority</th>
                  <th className="py-2.5 px-3 text-center">Escalation Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800 text-slate-300">
                {reminders.map((r) => {
                  const isOverdue = r.reminder_type === 'post_maturity_overdue';
                  const isEscalated = r.status === 'escalated' || r.escalated_to_id;

                  return (
                    <tr key={r.id} className="hover:bg-slate-800/30">
                      <td className="py-3 px-3">
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase font-mono ${
                            isOverdue
                              ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                              : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                          }`}
                        >
                          {r.reminder_type.replace('_', ' ')}
                        </span>
                      </td>

                      <td className="py-3 px-3">
                        {r.fdr ? (
                          <a href={`/fdrs/${r.fdr_id}`} className="font-mono font-bold text-white hover:text-orange-400 transition underline underline-offset-2">
                            {r.fdr.fdr_number}
                          </a>
                        ) : (
                          <span className="font-mono font-bold text-white">FDR #{r.fdr_id}</span>
                        )}
                        <div className="text-slate-400 text-[11px] truncate max-w-xs">
                          {r.institution ? r.institution.name : 'AICTE Regulatory Institution'}
                        </div>
                      </td>

                      <td className="py-3 px-3 text-center font-mono">
                        {r.target_date}
                      </td>

                      <td className="py-3 px-3">
                        <div className="flex items-center space-x-1.5 text-slate-400 text-[11px] mb-0.5">
                          <Mail className="w-3 h-3 text-slate-500" />
                          <span>{r.recipient_email}</span>
                        </div>
                        <div className="text-slate-300 text-xs font-sans line-clamp-2">{r.message}</div>
                      </td>

                      <td className="py-3 px-3 text-center">
                        <span
                          className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                            r.priority === 'urgent'
                              ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30'
                              : r.priority === 'high'
                              ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                              : 'bg-slate-700/50 text-slate-300'
                          }`}
                        >
                          {r.priority}
                        </span>
                      </td>

                      <td className="py-3 px-3 text-center">
                        {isEscalated ? (
                          <div className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                            <UserCheck className="w-3 h-3" />
                            <span>Escalated to Supervisor</span>
                          </div>
                        ) : (
                          <span className="text-[11px] text-slate-500">Standard Delivery</span>
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
    </div>
  );
};

export default SmartReminders;
