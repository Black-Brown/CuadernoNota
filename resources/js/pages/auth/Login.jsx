import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { login } from '../../api/auth.api';
import useAuthStore from '../../store/authStore';

const roleRoutes = {
  teacher:     '/docente/dashboard',
  coordinator: '/coordinador/dashboard',
  admin:       '/admin/dashboard',
};

export default function Login() {
  const navigate = useNavigate();
  const { setAuth } = useAuthStore();

  const [form, setForm] = useState({ email: '', password: '' });

  const mutation = useMutation({
    mutationFn: login,
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      navigate(roleRoutes[data.user.role] || '/');
    },
  });

  const handleChange = (e) =>
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));

  const handleSubmit = (e) => {
    e.preventDefault();
    mutation.mutate(form);
  };

  const bgStyle = {
    backgroundColor: '#F8FAFC',
    backgroundImage: `
      radial-gradient(#E2E8F0 0.5px, transparent 0.5px), 
      radial-gradient(#E2E8F0 0.5px, #F8FAFC 0.5px)
    `,
    backgroundSize: '24px 24px',
    backgroundPosition: '0 0, 12px 12px',
  };

  return (
    <div style={bgStyle} className="min-h-screen flex flex-col font-sans antialiased text-slate-800">
      
      {/* Main Content Area */}
      <main className="flex-grow flex items-center justify-center px-4 py-12">
        <div className="w-full max-w-[440px] transition-all duration-700">
          
          {/* Branding Area */}
          <div className="text-center mb-8">
            <div className="inline-flex items-center justify-center p-3 bg-white border border-slate-200 rounded-2xl mb-4 shadow-sm">
              <img 
                alt="Cuaderno Digital Logo" 
                className="h-12 w-auto object-contain" 
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuBTCgtRnFaKBuS8iptnFKSZXuZKbxpc9nE8TsF7NpFjDxHLHwe-lnYY4jHy2jKuk0FuCwApZvUdAkiXB2DHkwbatQGMKNFCbYabEvNtElK23qgT-EpAjMuZt2IBgsYMKC6xOFRhSg6AAMhlTTBjyyI9HNygkI5Zax_knEOkYC8X7mKfgRe7uZM5dSMBk4zMub7ipx3-dnX2yNeO_63oao9n7L0Wjp9sikW8NfQ21PvpCUvL4BRtkAe9NrXoBK8nfnAMhyhGAI5eTxdy"
                onError={(e) => {
                  // Fallback if image fails to load
                  e.target.style.display = 'none';
                  e.target.outerHTML = '<span class="material-symbols-outlined text-indigo-600 text-3xl">school</span>';
                }}
              />
            </div>
            <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Cuaderno Digital</h1>
            <p className="text-xs text-slate-500 mt-1 uppercase tracking-wider font-semibold">Gestión Académica Institucional</p>
          </div>

          {/* Login Card */}
          <div className="bg-white border border-slate-200 p-8 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.03)]">
            
            {/* Error Banner */}
            {mutation.isError && (
              <div className="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-xs text-red-700 flex items-start gap-2.5">
                <span className="material-symbols-outlined text-[18px] shrink-0">error</span>
                <span>{mutation.error?.response?.data?.message || 'Credenciales inválidas. Por favor intenta de nuevo.'}</span>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
              
              {/* Email Field */}
              <div className="space-y-1.5">
                <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block" htmlFor="email">
                  Correo Institucional
                </label>
                <div className="relative group">
                  <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px] transition-colors group-focus-within:text-slate-800">
                    mail
                  </span>
                  <input 
                    className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-1 focus:ring-slate-950 focus:border-slate-950 transition-all outline-none text-sm placeholder:text-slate-400" 
                    id="email" 
                    name="email" 
                    type="email"
                    value={form.email}
                    onChange={handleChange}
                    placeholder="usuario@institucion.edu" 
                    required 
                    disabled={mutation.isPending}
                  />
                </div>
              </div>

              {/* Password Field */}
              <div className="space-y-1.5">
                <div className="flex justify-between items-center">
                  <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block" htmlFor="password">
                    Contraseña
                  </label>
                  <a className="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors uppercase tracking-wider" href="#">
                    ¿Olvidó su contraseña?
                  </a>
                </div>
                <div className="relative group">
                  <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px] transition-colors group-focus-within:text-slate-800">
                    lock
                  </span>
                  <input 
                    className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-1 focus:ring-slate-950 focus:border-slate-950 transition-all outline-none text-sm placeholder:text-slate-400" 
                    id="password" 
                    name="password" 
                    type="password"
                    value={form.password}
                    onChange={handleChange}
                    placeholder="••••••••" 
                    required 
                    disabled={mutation.isPending}
                  />
                </div>
              </div>

              {/* Submit Button */}
              <button 
                className="w-full bg-slate-950 text-white font-semibold text-sm py-3.5 rounded-xl hover:bg-slate-900 active:scale-[0.98] transition-all flex items-center justify-center gap-2 group disabled:bg-slate-400 disabled:cursor-not-allowed" 
                type="submit"
                disabled={mutation.isPending}
              >
                {mutation.isPending ? (
                  <>
                    <span className="material-symbols-outlined text-[20px] animate-spin">
                      progress_activity
                    </span>
                    Iniciando Sesión...
                  </>
                ) : (
                  <>
                    Iniciar Sesión
                    <span className="material-symbols-outlined text-[20px] group-hover:translate-x-1 transition-transform">
                      arrow_forward
                    </span>
                  </>
                )}
              </button>
            </form>

            {/* SSL Footer inside Card */}
            <div className="mt-6 pt-6 border-t border-slate-100">
              <div className="flex items-center gap-3">
                <div className="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 shrink-0">
                  <span className="material-symbols-outlined text-[20px]">security</span>
                </div>
                <div>
                  <p className="text-xs font-semibold text-slate-800">Acceso Seguro</p>
                  <p className="text-[10px] text-slate-400">Encriptación SSL de 256 bits activada.</p>
                </div>
              </div>
            </div>

          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="w-full px-8 py-6 border-t border-slate-200 bg-white">
        <div className="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
          <div className="flex items-center gap-2">
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">© 2026 EDUCORE SYSTEMS</span>
            <span className="h-1.5 w-1.5 rounded-full bg-slate-200 hidden md:block"></span>
            <span className="text-xs text-slate-400">V 4.2.0-STABLE</span>
          </div>
          <div className="flex items-center gap-6">
            <a className="flex items-center gap-1 text-xs text-slate-400 hover:text-slate-800 transition-colors" href="#">
              <span className="material-symbols-outlined text-[16px]">contact_support</span>
              Soporte Técnico
            </a>
            <a className="flex items-center gap-1 text-xs text-slate-400 hover:text-slate-800 transition-colors" href="#">
              <span className="material-symbols-outlined text-[16px]">policy</span>
              Privacidad
            </a>
          </div>
        </div>
      </footer>

    </div>
  );
}
