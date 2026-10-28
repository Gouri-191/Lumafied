import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { Institution } from '../types';
import { useAuth } from '../context/AuthContext';
import { Building, Plus, Search, Mail, Phone, MapPin, CheckCircle2 } from 'lucide-react';

export const Institutions: React.FC = () => {
  const { user } = useAuth();
  const [institutions, setInstitutions] = useState<Institution[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);

  const [formData, setFormData] = useState({
    aicte_id: '',
    name: '',
    category: 'Engineering & Technology',
    address: '',
    city: '',
    state: '',
    pincode: '',
    contact_email: '',
    contact_phone: '',
  });

  useEffect(() => {
    fetchInstitutions();
  }, [search]);

  const fetchInstitutions = async () => {
    setLoading(true);
    try {
      const res = await api.get('/institutions', { params: { search } });
      setInstitutions(res.data.data);
    } catch (err) {
      console.error('Error loading institutions', err);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await api.post('/institutions', formData);
      setShowModal(false);
      setFormData({
        aicte_id: '',
        name: '',
        category: 'Engineering & Technology',
        address: '',
        city: '',
        state: '',
        pincode: '',
        contact_email: '',
        contact_phone: '',
      });
      fetchInstitutions();
    } catch (err: any) {
      alert(err.response?.data?.message || 'Failed to register institution.');
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Institutions Directory</h1>
          <p className="text-sm text-slate-500 mt-1">
            Registered educational entities and their statutory FDR compliance portfolios
          </p>
        </div>

        {(user?.role === 'aicte_admin' || user?.role === 'system_admin') && (
          <button
            onClick={() => setShowModal(true)}
            className="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm"
          >
            <Plus className="w-4 h-4 mr-1.5" />
            Register Institution
          </button>
        )}
      </div>

      <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
        <div className="relative">
          <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            type="text"
            placeholder="Search by institute name, AICTE permanent ID, city..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-orange-500"
          />
        </div>
      </div>

      {/* Grid of Institutions */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {institutions.map((inst) => (
          <div key={inst.id} className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-3">
            <div className="flex items-start justify-between">
              <div>
                <span className="font-mono text-xs font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded">
                  {inst.aicte_id}
                </span>
                <h3 className="font-bold text-slate-900 mt-1.5 line-clamp-1">{inst.name}</h3>
                <span className="text-xs text-slate-500">{inst.category}</span>
              </div>
              <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
            </div>

            <div className="space-y-1.5 text-xs text-slate-600 pt-2 border-t border-slate-100">
              <div className="flex items-center">
                <MapPin className="w-3.5 h-3.5 mr-1.5 text-slate-400 shrink-0" />
                <span className="truncate">{inst.city}, {inst.state} ({inst.pincode})</span>
              </div>
              <div className="flex items-center">
                <Mail className="w-3.5 h-3.5 mr-1.5 text-slate-400 shrink-0" />
                <span className="truncate">{inst.contact_email}</span>
              </div>
              <div className="flex items-center">
                <Phone className="w-3.5 h-3.5 mr-1.5 text-slate-400 shrink-0" />
                <span>{inst.contact_phone || 'N/A'}</span>
              </div>
            </div>

            <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
              <span className="text-slate-500">
                Active FDRs: <strong className="text-slate-900">{inst.fdrs_count || 0}</strong>
              </span>
              <span className="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded font-semibold">
                Compliant
              </span>
            </div>
          </div>
        ))}
      </div>

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <h3 className="text-lg font-bold text-slate-900">Register New Educational Institution</h3>
            <form onSubmit={handleCreate} className="mt-4 space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">AICTE Permanent ID *</label>
                <input
                  type="text"
                  required
                  value={formData.aicte_id}
                  onChange={(e) => setFormData({ ...formData, aicte_id: e.target.value })}
                  className="w-full text-xs p-2 border border-slate-300 rounded-lg font-mono"
                  placeholder="1-987654321"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">Institution Legal Name *</label>
                <input
                  type="text"
                  required
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full text-xs p-2 border border-slate-300 rounded-lg"
                />
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-xs font-semibold text-slate-700 block mb-1">City *</label>
                  <input
                    type="text"
                    required
                    value={formData.city}
                    onChange={(e) => setFormData({ ...formData, city: e.target.value })}
                    className="w-full text-xs p-2 border border-slate-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-700 block mb-1">State *</label>
                  <input
                    type="text"
                    required
                    value={formData.state}
                    onChange={(e) => setFormData({ ...formData, state: e.target.value })}
                    className="w-full text-xs p-2 border border-slate-300 rounded-lg"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-xs font-semibold text-slate-700 block mb-1">Pincode *</label>
                  <input
                    type="text"
                    required
                    value={formData.pincode}
                    onChange={(e) => setFormData({ ...formData, pincode: e.target.value })}
                    className="w-full text-xs p-2 border border-slate-300 rounded-lg"
                  />
                </div>
                <div>
                  <label className="text-xs font-semibold text-slate-700 block mb-1">Official Email *</label>
                  <input
                    type="email"
                    required
                    value={formData.contact_email}
                    onChange={(e) => setFormData({ ...formData, contact_email: e.target.value })}
                    className="w-full text-xs p-2 border border-slate-300 rounded-lg"
                  />
                </div>
              </div>

              <div className="mt-5 flex justify-end space-x-2 pt-2 border-t">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 border rounded-lg text-xs font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg text-xs font-bold"
                >
                  Register
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
