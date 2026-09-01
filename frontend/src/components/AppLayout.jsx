import React, { useState } from 'react';
import { useNavigate, useLocation, Link, Outlet } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { logout } from '../api/auth.api';
import useAuthStore from '../store/authStore';

export default function AppLayout({
  portalName,
  menuItems = [],
  user,
  roleLabel,
  searchPlaceholder = 'Buscar...',
  headerContent = null,
  showPeriodSelector = false,
  children,
}) {
  const navigate = useNavigate();
  const location = useLocation();
  const { logout: clearAuth } = useAuthStore();
  const [mobileOpen, setMobileOpen] = useState(false);

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSettled: () => {
      clearAuth();
      navigate('/login');
    },
  });

  const groups = menuItems.length > 0 && Array.isArray(menuItems[0]?.items)
    ? menuItems
    : [{ items: menuItems }];

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased">
      {mobileOpen && (
        <button
          type="button"
          aria-label="Cerrar menú"
          onClick={() => setMobileOpen(false)}
          className="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed h-full w-[260px] left-0 top-0 flex flex-col bg-slate-100 border-r border-slate-200 z-40 transition-transform duration-200 ${
          mobileOpen ? 'translate-x-0' : '-translate-x-full'
        } lg:translate-x-0`}
      >
        {/* Header Logo */}
        <div className="px-6 py-6">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-indigo-600 rounded flex items-center justify-center">
              <span className="material-symbols-outlined text-white text-xl fill-1">school</span>
            </div>
            <div>
              <h1 className="text-base font-bold text-slate-800 leading-tight">Cuaderno Notas</h1>
              <p className="text-[10px] text-slate-500 font-semibold tracking-wider uppercase">{portalName}</p>
            </div>
          </div>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 px-3 space-y-4 overflow-y-auto">
          {groups.map((group, groupIndex) => (
            <div key={group.label || groupIndex} className="space-y-1">
              {group.label && (
                <p className="px-4 pt-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                  {group.label}
                </p>
              )}
              {group.items.map((item) => {
                const isActive = location.pathname.startsWith(item.path);
                return (
                  <Link
                    key={item.path}
                    to={item.path}
                    onClick={() => setMobileOpen(false)}
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
            </div>
          ))}
        </nav>

        {/* User profile footer */}
        <div className="p-4 mt-auto border-t border-slate-200/50 bg-slate-100/85">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3 min-w-0">
              <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-semibold shrink-0">
                {user?.name ? user.name.substring(0, 2).toUpperCase() : 'US'}
              </div>
              <div className="overflow-hidden">
                <p className="text-xs font-semibold text-slate-800 truncate">{user?.name || 'Usuario'}</p>
                <p className="text-[10px] text-slate-500 truncate capitalize">{roleLabel || user?.role || ''}</p>
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
      <header className="fixed top-0 right-0 left-0 lg:left-[260px] h-16 z-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-6 gap-3 lg:gap-4">
        <div className="flex items-center gap-3 flex-1 min-w-0">
          <button
            type="button"
            onClick={() => setMobileOpen(true)}
            className="lg:hidden shrink-0 w-9 h-9 flex items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
            aria-label="Abrir menú"
          >
            <span className="material-symbols-outlined text-[22px]">menu</span>
          </button>

          <div className="relative w-full max-w-md">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
            <input
              type="text"
              placeholder={searchPlaceholder}
              className="w-full bg-slate-50 border border-slate-200 rounded-lg pl-10 pr-4 py-2 text-sm focus:bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
            />
          </div>

          {showPeriodSelector && headerContent && (
            <div className="hidden md:block">{headerContent}</div>
          )}
        </div>

        <div className="flex items-center gap-3">
          <button className="w-9 h-9 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-all">
            <span className="material-symbols-outlined text-[22px]">notifications</span>
          </button>
          <div className="h-6 w-[1px] bg-slate-200 mx-1 hidden sm:block"></div>
          <div className="hidden sm:flex items-center gap-2">
            <span className="material-symbols-outlined text-slate-400">account_circle</span>
            <span className="text-xs font-medium text-slate-600">{user?.email}</span>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="lg:ml-[260px] pt-16 min-h-screen bg-slate-50">
        <div className="p-4 sm:p-6 max-w-7xl mx-auto">
          {children ?? <Outlet />}
        </div>
      </main>
    </div>
  );
}
