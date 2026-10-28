import React, { useState, useEffect } from 'react';
import { useAuth } from '../../context/AuthContext';
import { LogOut, Bell, Shield, Building2, UserCircle, CheckCircle2 } from 'lucide-react';
import api from '../../services/api';
import { AppNotification } from '../../types';

export const Navbar: React.FC = () => {
  const { user, logout } = useAuth();
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [showNotifications, setShowNotifications] = useState(false);

  useEffect(() => {
    if (user) {
      api.get('/notifications')
        .then((res) => {
          setNotifications(res.data.data || []);
        })
        .catch(() => {});
    }
  }, [user]);

  const unreadCount = notifications.filter((n) => !n.is_read).length;

  const markAllRead = async () => {
    try {
      await api.post('/notifications/read-all');
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
    } catch {}
  };

  const getRoleBadge = (role?: string) => {
    switch (role) {
      case 'system_admin':
        return <span className="bg-purple-100 text-purple-800 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-purple-300">System Admin</span>;
      case 'aicte_admin':
        return <span className="bg-blue-100 text-blue-800 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-blue-300">AICTE Admin</span>;
      case 'verification_officer':
        return <span className="bg-amber-100 text-amber-800 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-amber-300">Verification Officer</span>;
      case 'approving_authority':
        return <span className="bg-emerald-100 text-emerald-800 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-emerald-300">Approving Authority</span>;
      case 'auditor':
        return <span className="bg-indigo-100 text-indigo-800 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-indigo-300">Independent Auditor</span>;
      default:
        return <span className="bg-slate-100 text-slate-800 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-slate-300">Institution Submitter</span>;
    }
  };

  return (
    <header className="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          <div className="flex items-center space-x-3">
            <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-orange-600 text-white font-bold text-lg shadow-sm">
              <Shield className="w-6 h-6 text-white" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <span className="text-lg font-bold text-slate-900 tracking-tight">AICTE FDR Portal</span>
                <span className="text-[10px] font-semibold tracking-wider bg-slate-800 text-orange-400 px-1.5 py-0.5 rounded uppercase">SIH26265</span>
              </div>
              <p className="text-xs text-slate-500">Fixed Deposit Receipt Lifecycle & Independent Verification</p>
            </div>
          </div>

          <div className="flex items-center space-x-4">
            {/* Notification Dropdown */}
            <div className="relative">
              <button
                onClick={() => setShowNotifications(!showNotifications)}
                className="relative p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors"
                title="Notifications"
              >
                <Bell className="w-5 h-5" />
                {unreadCount > 0 && (
                  <span className="absolute top-1 right-1 w-2.5 h-2.5 bg-red-600 rounded-full ring-2 ring-white animate-pulse" />
                )}
              </button>

              {showNotifications && (
                <div className="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-slate-200 py-2 z-50">
                  <div className="flex items-center justify-between px-4 py-2 border-b border-slate-100">
                    <span className="font-semibold text-sm text-slate-900">Notifications ({unreadCount})</span>
                    {unreadCount > 0 && (
                      <button
                        onClick={markAllRead}
                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                      >
                        Mark all read
                      </button>
                    )}
                  </div>
                  <div className="max-h-72 overflow-y-auto divide-y divide-slate-100">
                    {notifications.length === 0 ? (
                      <div className="px-4 py-6 text-center text-xs text-slate-500">No recent notifications</div>
                    ) : (
                      notifications.slice(0, 5).map((n) => (
                        <div key={n.id} className={`px-4 py-3 text-xs ${n.is_read ? 'bg-white' : 'bg-slate-50'}`}>
                          <div className="font-semibold text-slate-900">{n.title}</div>
                          <p className="text-slate-600 mt-1 line-clamp-2">{n.message}</p>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* User Profile & Role Info */}
            {user && (
              <div className="flex items-center space-x-3 pl-3 border-l border-slate-200">
                <div className="text-right">
                  <div className="text-sm font-semibold text-slate-900">{user.name}</div>
                  <div className="flex items-center justify-end space-x-1.5 mt-0.5">
                    {getRoleBadge(user.role)}
                    {user.institution && (
                      <span className="text-xs text-slate-500 flex items-center">
                        <Building2 className="w-3 h-3 mr-0.5 text-slate-400" />
                        {user.institution.name.substring(0, 18)}...
                      </span>
                    )}
                  </div>
                </div>
                <button
                  onClick={logout}
                  className="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                  title="Sign out"
                >
                  <LogOut className="w-5 h-5" />
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </header>
  );
};
