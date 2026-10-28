import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import {
  LayoutDashboard,
  FileSpreadsheet,
  PlusCircle,
  ClipboardCheck,
  CalendarDays,
  Building,
  History,
  ShieldCheck,
  Landmark,
  Scale,
  RotateCcw,
  Bell,
} from 'lucide-react';

export const Sidebar: React.FC = () => {
  const { user, hasRole } = useAuth();

  const navItems = [
    {
      to: '/',
      label: 'Dashboard',
      icon: LayoutDashboard,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor', 'institution_submitter', 'institution_admin'],
    },
    {
      to: '/fdrs',
      label: 'FDR Register',
      icon: FileSpreadsheet,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor', 'institution_submitter', 'institution_admin'],
    },
    {
      to: '/fdrs/new',
      label: 'New FDR Submission',
      icon: PlusCircle,
      roles: ['institution_submitter', 'institution_admin', 'system_admin', 'aicte_admin'],
    },
    {
      to: '/verification-cases',
      label: 'Verification Work Queue',
      icon: ClipboardCheck,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority'],
    },
    {
      to: '/treasury',
      label: 'FDR Treasury & Liquidity',
      icon: Landmark,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor', 'institution_submitter', 'institution_admin'],
    },
    {
      to: '/reconciliation',
      label: 'Bank Reconciliation',
      icon: Scale,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor'],
    },
    {
      to: '/renewals',
      label: 'Renewal & Scenarios',
      icon: RotateCcw,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor', 'institution_submitter', 'institution_admin'],
    },
    {
      to: '/reminders',
      label: 'Smart Reminders',
      icon: Bell,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor', 'institution_submitter', 'institution_admin'],
    },
    {
      to: '/maturity-calendar',
      label: 'Maturity Calendar',
      icon: CalendarDays,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor', 'institution_submitter', 'institution_admin'],
    },
    {
      to: '/institutions',
      label: 'Institutions Directory',
      icon: Building,
      roles: ['system_admin', 'aicte_admin', 'verification_officer', 'approving_authority', 'auditor'],
    },
    {
      to: '/audit-logs',
      label: 'Immutable Audit Trail',
      icon: History,
      roles: ['system_admin', 'aicte_admin', 'auditor'],
    },
  ];

  const visibleItems = navItems.filter((item) => user && item.roles.includes(user.role));

  return (
    <aside className="w-64 bg-slate-900 text-slate-300 min-h-[calc(100vh-4rem)] flex flex-col justify-between p-4 border-r border-slate-800">
      <div className="space-y-1">
        <div className="px-3 py-2 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
          Operations & Governance
        </div>
        <nav className="space-y-1">
          {visibleItems.map((item) => {
            const Icon = item.icon;
            return (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) =>
                  `flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all ${
                    isActive
                      ? 'bg-orange-600 text-white shadow-sm'
                      : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                  }`
                }
              >
                <Icon className="w-4 h-4 mr-3 shrink-0" />
                {item.label}
              </NavLink>
            );
          })}
        </nav>
      </div>

      <div className="pt-4 border-t border-slate-800">
        <div className="bg-slate-800/60 rounded-lg p-3 text-xs border border-slate-700/50">
          <div className="flex items-center space-x-1.5 text-emerald-400 font-semibold mb-1">
            <ShieldCheck className="w-4 h-4" />
            <span>Cryptographic Vault</span>
          </div>
          <p className="text-slate-400 text-[11px] leading-relaxed">
            All original documents protected via envelope encryption (AES-256-GCM) with tamper-evident authentication tags.
          </p>
        </div>
      </div>
    </aside>
  );
};
