import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';
import { Fdr } from '../types';
import { CalendarDays, AlertTriangle, Clock, CheckCircle2, ShieldAlert, ArrowRight } from 'lucide-react';

export const MaturityCalendar: React.FC = () => {
  const [data, setData] = useState<{
    expired: Fdr[];
    maturing_30_days: Fdr[];
    maturing_90_days: Fdr[];
    active_healthy: Fdr[];
  }>({
    expired: [],
    maturing_30_days: [],
    maturing_90_days: [],
    active_healthy: [],
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchCalendar();
  }, []);

  const fetchCalendar = async () => {
    setLoading(true);
    try {
      const res = await api.get('/lifecycle/maturity-calendar');
      setData(res.data);
    } catch (err) {
      console.error('Error fetching maturity calendar', err);
    } finally {
      setLoading(false);
    }
  };

  const renderFdrCard = (fdr: Fdr, badgeColor: string, badgeLabel: string) => (
    <div key={fdr.id} className="p-4 bg-white rounded-xl border border-slate-200 shadow-xs text-sm space-y-2">
      <div className="flex items-center justify-between">
        <span className="font-mono font-bold text-slate-900">{fdr.fdr_tracking_no}</span>
        <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded ${badgeColor}`}>
          {badgeLabel}
        </span>
      </div>

      <div className="font-medium text-slate-900 truncate">{fdr.institution?.name}</div>

      <div className="flex justify-between text-xs text-slate-600">
        <span>{fdr.bank_name}</span>
        <span className="font-bold text-slate-900">
          ₹ {Number(fdr.principal_amount).toLocaleString('en-IN')}
        </span>
      </div>

      <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
        <span className="text-slate-500 font-medium">
          Maturity: <strong className="text-slate-900">{new Date(fdr.maturity_date).toLocaleDateString('en-GB')}</strong>
        </span>
        <Link to={`/fdrs/${fdr.id}`} className="text-orange-600 hover:text-orange-800 font-semibold flex items-center">
          Dossier <ArrowRight className="w-3 h-3 ml-0.5" />
        </Link>
      </div>
    </div>
  );

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">Fixed Deposit Maturity & Renewal Calendar</h1>
        <p className="text-sm text-slate-500 mt-1">
          Proactive timeline monitoring for approaching tenures, renewal reminders, and lien release requests
        </p>
      </div>

      {loading ? (
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-600"></div>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {/* Expired Column */}
          <div className="space-y-3">
            <div className="bg-red-50 p-3 rounded-xl border border-red-200 flex items-center justify-between">
              <span className="font-bold text-xs uppercase tracking-wider text-red-900 flex items-center">
                <ShieldAlert className="w-4 h-4 mr-1 text-red-600" /> Expired ({data.expired.length})
              </span>
            </div>
            {data.expired.length === 0 ? (
              <div className="p-4 text-center text-xs text-slate-400 bg-white rounded-xl border border-dashed">
                Zero expired FDRs
              </div>
            ) : (
              data.expired.map((f) => renderFdrCard(f, 'bg-red-100 text-red-800', 'Expired'))
            )}
          </div>

          {/* 30 Days Column */}
          <div className="space-y-3">
            <div className="bg-orange-50 p-3 rounded-xl border border-orange-200 flex items-center justify-between">
              <span className="font-bold text-xs uppercase tracking-wider text-orange-900 flex items-center">
                <AlertTriangle className="w-4 h-4 mr-1 text-orange-600" /> Due in &le;30 Days ({data.maturing_30_days.length})
              </span>
            </div>
            {data.maturing_30_days.length === 0 ? (
              <div className="p-4 text-center text-xs text-slate-400 bg-white rounded-xl border border-dashed">
                None in 30-day window
              </div>
            ) : (
              data.maturing_30_days.map((f) => renderFdrCard(f, 'bg-orange-100 text-orange-800', 'Critical'))
            )}
          </div>

          {/* 90 Days Column */}
          <div className="space-y-3">
            <div className="bg-amber-50 p-3 rounded-xl border border-amber-200 flex items-center justify-between">
              <span className="font-bold text-xs uppercase tracking-wider text-amber-900 flex items-center">
                <Clock className="w-4 h-4 mr-1 text-amber-600" /> Due in &le;90 Days ({data.maturing_90_days.length})
              </span>
            </div>
            {data.maturing_90_days.length === 0 ? (
              <div className="p-4 text-center text-xs text-slate-400 bg-white rounded-xl border border-dashed">
                None in 90-day window
              </div>
            ) : (
              data.maturing_90_days.map((f) => renderFdrCard(f, 'bg-amber-100 text-amber-800', 'Approaching'))
            )}
          </div>

          {/* Active Healthy Column */}
          <div className="space-y-3">
            <div className="bg-emerald-50 p-3 rounded-xl border border-emerald-200 flex items-center justify-between">
              <span className="font-bold text-xs uppercase tracking-wider text-emerald-900 flex items-center">
                <CheckCircle2 className="w-4 h-4 mr-1 text-emerald-600" /> Healthy &gt;90 Days ({data.active_healthy.length})
              </span>
            </div>
            {data.active_healthy.length === 0 ? (
              <div className="p-4 text-center text-xs text-slate-400 bg-white rounded-xl border border-dashed">
                No active records
              </div>
            ) : (
              data.active_healthy.map((f) => renderFdrCard(f, 'bg-emerald-100 text-emerald-800', 'Healthy'))
            )}
          </div>
        </div>
      )}
    </div>
  );
};
