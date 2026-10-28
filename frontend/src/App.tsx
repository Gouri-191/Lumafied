import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { AppLayout } from './components/Layout/AppLayout';
import { Login } from './pages/Login';
import { Dashboard } from './pages/Dashboard';
import { FdrList } from './pages/FdrList';
import { FdrDetail } from './pages/FdrDetail';
import { FdrCreate } from './pages/FdrCreate';
import { VerificationQueue } from './pages/VerificationQueue';
import { MaturityCalendar } from './pages/MaturityCalendar';
import { Institutions } from './pages/Institutions';
import { AuditLogs } from './pages/AuditLogs';
import { TreasuryDashboard } from './pages/TreasuryDashboard';
import { Reconciliation } from './pages/Reconciliation';
import { RenewalPlanner } from './pages/RenewalPlanner';
import { SmartReminders } from './pages/SmartReminders';

const ProtectedRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-900 text-white font-sans">
        <div className="flex flex-col items-center space-y-3">
          <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-orange-500"></div>
          <span className="text-sm font-medium text-slate-400">Verifying AICTE Session & Cryptographic Credentials...</span>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
};

export const App: React.FC = () => {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<Login />} />

          <Route
            path="/"
            element={
              <ProtectedRoute>
                <AppLayout />
              </ProtectedRoute>
            }
          >
            <Route index element={<Dashboard />} />
            <Route path="fdrs" element={<FdrList />} />
            <Route path="fdrs/new" element={<FdrCreate />} />
            <Route path="fdrs/:id" element={<FdrDetail />} />
            <Route path="verification-cases" element={<VerificationQueue />} />
            <Route path="treasury" element={<TreasuryDashboard />} />
            <Route path="reconciliation" element={<Reconciliation />} />
            <Route path="renewals" element={<RenewalPlanner />} />
            <Route path="reminders" element={<SmartReminders />} />
            <Route path="maturity-calendar" element={<MaturityCalendar />} />
            <Route path="institutions" element={<Institutions />} />
            <Route path="audit-logs" element={<AuditLogs />} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
};

export default App;
