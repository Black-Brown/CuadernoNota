import React from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { logout } from '../api/auth.api';
import { getCurrentPeriod } from '../api/periods.api';
import useAuthStore from '../store/authStore';

export default function DashboardLayout({ children }) {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout: clearAuth } = useAuthStore();

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSettled: () => {
      clearAuth();
      navigate('/login');
    },
  });

  const { data: periodData } = useQuery({
    queryKey: ['currentPeriod'],
    queryFn: getCurrentPeriod,
    staleTime: 5 * 60_000,
  });

  const period = periodData?.period;

  const menuItems = [
    { name: 'Mis Cursos', icon: 'school', path: '/docente/courses' },
    { name: 'Calificaciones', icon: 'grade', path: '/docente/grades' },
  ];

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
      {/* Sidebar */}
      <aside className="fixed h-full w-[260px] left-0 top-0 flex flex-col bg-slate-100 border-r border-slate-200 z-40">
        {/* Header Logo */}
        <div className="px-6 py-6">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-indigo-600 rounded flex items-center justify-center">
              <span className="material-symbols-outlined text-white text-xl fill-1">school</span>
            </div>
            <div>
              <h1 className="text-base font-bold text-slate-800 leading-tight">Cuaderno Notas</h1>
              <p className="text-[10px] text-slate-500 font-semibold tracking-wider uppercase">Portal Docente</p>
            </div>
          </div>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 px-3 space-y-1">
          {menuItems.map((item) => {
            const isActive = location.pathname.startsWith(item.path);
            return (
              <Link
                key={item.path}
                to={item.path}
                className={`flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all duration-150 ${
                  isActive
                    ? 'bg-white text-indigo-600 shadow-sm border border-slate-200/60 font-semibold'
                    : 'text-slate-600 hover:bg-slate-200/50 hover:text-slate-900'
                }`}
              >
                <span className={`material-symbols-outlined text-[20px] ${isActive ? 'text-indigo-600' : 'text-slate-400'}`}>
                  {item.icon}
                </span>
                <span>{item.name}</span>
              </Link>
            );
          })}
        </nav>

        {/* User profile footer */}
        <div className="p-4 mt-auto border-t border-slate-200/50 bg-slate-100/85">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3 min-w-0">
              <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-semibold shrink-0">
                {user?.name ? user.name.substring(0, 2).toUpperCase() : 'US'}
              </div>
              <div className="overflow-hidden">
                <p className="text-xs font-semibold text-slate-800 truncate">{user?.name || 'Docente'}</p>
                <p className="text-[10px] text-slate-500 truncate capitalize">{user?.role || 'docente'}</p>
              </div>
            </div>
            <button
              onClick={() => logoutMutation.mutate()}
              title="Cerrar sesión"
              className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors"
            >
              <span className="material-symbols-outlined text-[20px]">logout</span>
            </button>
          </div>
        </div>
      </aside>

      {/* Header */}
      <header className="fixed top-0 right-0 w-[calc(100%-260px)] h-16 z-30 bg-white border-b border-slate-200 flex items-center justify-between px-6 gap-4">
        <div className="flex items-center gap-4 flex-1">
          <div className="relative w-full max-w-md">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
            <input
              type="text"
              placeholder="Buscar estudiantes, cursos o notas..."
              className="w-full bg-slate-50 border border-slate-200 rounded-lg pl-10 pr-4 py-2 text-sm focus:bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
            />
          </div>
          <div className="flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg cursor-default select-none">
            <span className="material-symbols-outlined text-indigo-500 text-[18px]">calendar_today</span>
            {period ? (
              <div className="flex items-center gap-1.5">
                <span className="text-xs font-bold text-slate-800">{period.name}</span>
                <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">·</span>
                <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">{period.months}</span>
                <span className={`ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${
                  period.status === 'open' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'
                }`}>
                  {period.status === 'open' ? 'Activo' : period.status === 'in_review' ? 'En revisión' : 'Cerrado'}
                </span>
              </div>
            ) : (
              <span className="text-xs font-semibold text-slate-400">Periodo Actual</span>
            )}
          </div>
        </div>

        <div className="flex items-center gap-3">
          <button className="w-9 h-9 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-all">
            <span className="material-symbols-outlined text-[22px]">notifications</span>
          </button>
          <div className="h-6 w-[1px] bg-slate-200 mx-1"></div>
          <div className="flex items-center gap-2">
            <span className="material-symbols-outlined text-slate-400">account_circle</span>
            <span className="text-xs font-medium text-slate-600">{user?.email}</span>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="ml-[260px] pt-16 min-h-screen bg-slate-50">
        <div className="p-6 max-w-7xl mx-auto">
          {children}
        </div>
      </main>
    </div>
  );
}
