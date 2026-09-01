import React, { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { exchangeGoogleCode, login } from '../../api/auth.api';
import useAuthStore from '../../store/authStore';
import { routeForRole } from '../../utils/adminAccess';

const googleErrorMessages = {
  access_denied: 'El acceso con Google fue cancelado.',
  invalid_state: 'La solicitud de Google expiró. Intenta nuevamente.',
  provider_error: 'No fue posible comunicarse con Google. Intenta nuevamente.',
  invalid_domain: 'Utiliza una cuenta institucional @happylearningschool.net.',
  not_registered: 'Tu correo institucional no está registrado en Cuaderno Nota.',
  inactive: 'Tu cuenta está desactivada. Contacta al administrador.',
  account_mismatch: 'Esta cuenta está vinculada a otra identidad de Google.',
};

export default function Login() {
  const navigate = useNavigate();
  const { setAuth } = useAuthStore();
  const googleExchangeStarted = useRef(false);

  const [form, setForm] = useState({ email: '', password: '' });
  const [googleError, setGoogleError] = useState('');

  const mutation = useMutation({
    mutationFn: login,
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      navigate(routeForRole(data.user.role));
    },
  });

  const googleMutation = useMutation({
    mutationFn: exchangeGoogleCode,
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      navigate(routeForRole(data.user.role));
    },
  });

  useEffect(() => {
    if (googleExchangeStarted.current) return;

    const params = new URLSearchParams(window.location.search);
    const code = params.get('google_code');
    const error = params.get('google_error');

    if (!code && !error) return;

    googleExchangeStarted.current = true;
    window.history.replaceState({}, document.title, '/login');

    if (error) {
      setGoogleError(googleErrorMessages[error] || 'No fue posible iniciar sesión con Google.');
      return;
    }

    googleMutation.mutate(code);
  }, []);

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
            {(mutation.isError || googleMutation.isError || googleError) && (
              <div className="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-xs text-red-700 flex items-start gap-2.5">
                <span className="material-symbols-outlined text-[18px] shrink-0">error</span>
                <span>
                  {googleError
                    || googleMutation.error?.response?.data?.message
                    || mutation.error?.response?.data?.message
                    || 'Credenciales inválidas. Por favor intenta de nuevo.'}
                </span>
              </div>
            )}

            <button
              type="button"
              onClick={() => window.location.assign('/api/auth/google/redirect')}
              disabled={googleMutation.isPending}
              className="w-full border border-slate-300 bg-white text-slate-700 font-semibold text-sm py-3.5 rounded-xl hover:bg-slate-50 active:scale-[0.98] transition-all flex items-center justify-center gap-3 disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <svg aria-hidden="true" className="h-5 w-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.5-.2-2.2H12v4.3h5.4a4.7 4.7 0 0 1-2 3v2.8h3.3c1.9-1.8 2.9-4.4 2.9-7.9Z" />
                <path fill="#34A853" d="M12 22c2.7 0 5-.9 6.7-2.4l-3.3-2.8c-.9.6-2.1 1-3.4 1a5.9 5.9 0 0 1-5.5-4.1H3.1v2.9A10 10 0 0 0 12 22Z" />
                <path fill="#FBBC05" d="M6.5 13.7A6 6 0 0 1 6.2 12c0-.6.1-1.2.3-1.7V7.4H3.1A10 10 0 0 0 2 12c0 1.7.4 3.2 1.1 4.6l3.4-2.9Z" />
                <path fill="#EA4335" d="M12 6.2c1.5 0 2.8.5 3.9 1.5l2.9-2.9A9.8 9.8 0 0 0 12 2a10 10 0 0 0-8.9 5.4l3.4 2.9A5.9 5.9 0 0 1 12 6.2Z" />
              </svg>
              {googleMutation.isPending ? 'Validando cuenta...' : 'Continuar con Google'}
            </button>

            <div className="my-6 flex items-center gap-3">
              <div className="h-px flex-1 bg-slate-200" />
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400">o usa tu contraseña</span>
              <div className="h-px flex-1 bg-slate-200" />
            </div>

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
                    placeholder="usuario@happylearningschool.net"
                    required 
                    disabled={mutation.isPending || googleMutation.isPending}
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
                    disabled={mutation.isPending || googleMutation.isPending}
                  />
                </div>
              </div>

              {/* Submit Button */}
              <button 
                className="w-full bg-slate-950 text-white font-semibold text-sm py-3.5 rounded-xl hover:bg-slate-900 active:scale-[0.98] transition-all flex items-center justify-center gap-2 group disabled:bg-slate-400 disabled:cursor-not-allowed" 
                type="submit"
                disabled={mutation.isPending || googleMutation.isPending}
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
