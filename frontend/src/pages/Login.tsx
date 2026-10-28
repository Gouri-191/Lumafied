import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Shield, Lock, Mail, AlertCircle, ArrowRight, CheckCircle2 } from 'lucide-react';

export const Login: React.FC = () => {
  const [email, setEmail] = useState('admin@aicte-india.org');
  const [password, setPassword] = useState('Password@123');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      await login(email, password);
      navigate('/');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Invalid credentials or connection error.');
    } finally {
      setLoading(false);
    }
  };

  const demoAccounts = [
    { role: 'System Admin', email: 'admin@aicte-india.org', desc: 'Full administration, configurations, and tenant oversight' },
    { role: 'AICTE Admin', email: 'regulator@aicte-india.org', desc: 'Case assignment, institutional compliance & approvals' },
    { role: 'Verification Officer', email: 'officer@aicte-india.org', desc: 'Scrutinizes OCR, resolves discrepancies & issues recommendations' },
    { role: 'Approving Authority', email: 'authority@aicte-india.org', desc: 'Final approval/rejection authority (Separation of duties)' },
    { role: 'Apex Submitter', email: 'submitter.apex@institute.edu', desc: 'Apex Institute registrar (Submits & manages FDRs)' },
    { role: 'Independent Auditor', email: 'auditor@aicte-india.org', desc: 'CAG Panel compliance auditor (Read-only immutable logs)' },
  ];

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
      <div className="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-orange-600 text-white shadow-lg shadow-orange-600/30 mb-4">
          <Shield className="w-9 h-9" />
        </div>
        <h2 className="text-2xl font-bold tracking-tight text-white">AICTE FDR Portal</h2>
        <p className="mt-1 text-sm text-slate-400">
          Fixed Deposit Receipt Management, Verification & Compliance
        </p>
        <div className="inline-block mt-2 bg-slate-800 text-orange-400 text-xs px-2.5 py-0.5 rounded-full font-medium border border-slate-700">
          Smart India Hackathon • Problem Statement SIH26265
        </div>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div className="bg-white py-8 px-6 shadow-xl rounded-2xl sm:px-10 border border-slate-100">
          {error && (
            <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-center space-x-2">
              <AlertCircle className="w-5 h-5 shrink-0 text-red-500" />
              <span>{error}</span>
            </div>
          )}

          <form className="space-y-4" onSubmit={handleSubmit}>
            <div>
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                Official Email Address
              </label>
              <div className="mt-1 relative rounded-lg shadow-2xs">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <Mail className="h-5 h-5" />
                </div>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm"
                  placeholder="name@aicte-india.org"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                Password
              </label>
              <div className="mt-1 relative rounded-lg shadow-2xs">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <Lock className="h-5 h-5" />
                </div>
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="block w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-sm"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-orange-600 hover:bg-orange-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors disabled:opacity-50"
            >
              {loading ? 'Authenticating...' : 'Sign in to Portal'}
              <ArrowRight className="w-4 h-4 ml-2" />
            </button>
          </form>

          {/* Quick Demo Switcher */}
          <div className="mt-6 pt-6 border-t border-slate-200">
            <span className="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
              ⚡ Quick Demo Persona Switcher (SIH Judges)
            </span>
            <div className="grid grid-cols-2 gap-2">
              {demoAccounts.map((acc) => (
                <button
                  key={acc.email}
                  type="button"
                  onClick={() => {
                    setEmail(acc.email);
                    setPassword('Password@123');
                  }}
                  className={`text-left p-2 rounded-lg border text-xs transition-all ${
                    email === acc.email
                      ? 'border-orange-500 bg-orange-50 text-orange-950 font-medium'
                      : 'border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700'
                  }`}
                >
                  <div className="font-semibold flex items-center justify-between">
                    <span>{acc.role}</span>
                    {email === acc.email && <CheckCircle2 className="w-3.5 h-3.5 text-orange-600" />}
                  </div>
                  <div className="text-[10px] text-slate-500 truncate mt-0.5">{acc.email}</div>
                </button>
              ))}
            </div>
            <p className="text-[11px] text-slate-400 mt-2 text-center">
              All demo accounts configured with password: <code className="bg-slate-100 px-1 py-0.5 rounded text-slate-700">Password@123</code>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
