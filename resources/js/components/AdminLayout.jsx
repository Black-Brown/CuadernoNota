import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { getAdminDashboard } from '../api/admin.api';
import useAuthStore from '../store/authStore';
import AppLayout from './AppLayout';

const menuItems = [
  {
    label: 'Principal',
    items: [
      { name: 'Inicio', icon: 'dashboard', path: '/admin/dashboard' },
      { name: 'Usuarios', icon: 'manage_accounts', path: '/admin/users' },
      { name: 'Estudiantes', icon: 'school', path: '/admin/students' },
    ],
  },
  {
    label: 'Gestión académica',
    items: [
      { name: 'Catálogo académico', icon: 'account_tree', path: '/admin/catalog' },
      { name: 'Configuración institucional', icon: 'tune', path: '/admin/institutional' },
      { name: 'Asignaciones docentes', icon: 'assignment_ind', path: '/admin/assignments' },
      { name: 'Aprobación de notas', icon: 'fact_check', path: '/admin/reviews' },
      { name: 'Promoción escolar', icon: 'upgrade', path: '/admin/promotions' },
    ],
  },
  {
    label: 'Sistema',
    items: [
      { name: 'Reportes', icon: 'analytics', path: '/admin/reports' },
      { name: 'Auditoría y respaldo', icon: 'history', path: '/admin/audit' },
      { name: 'Zona peligrosa', icon: 'warning', path: '/admin/system' },
    ],
  },
];

export default function AdminLayout() {
  const { user } = useAuthStore();

  const { data } = useQuery({
    queryKey: ['admin-dashboard'],
    queryFn: getAdminDashboard,
    staleTime: 5 * 60_000,
  });

  const activeYear = data?.active_academic_year;

  const headerContent = (
    <div className="flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg select-none">
      <span className="material-symbols-outlined text-indigo-500 text-[18px]">calendar_month</span>
      <div className="flex items-center gap-1.5">
        <span className="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Año escolar activo</span>
        <span className="text-xs font-bold text-slate-800">{activeYear?.name || 'Sin configurar'}</span>
        <span className={`ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${
          activeYear ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'
        }`}>
          {activeYear ? 'Activo' : 'Pendiente'}
        </span>
      </div>
    </div>
  );

  return (
    <AppLayout
      portalName="Portal Administrativo"
      menuItems={menuItems}
      user={user}
      roleLabel="Administrador"
      searchPlaceholder="Buscar estudiantes, usuarios o cursos..."
      headerContent={headerContent}
      showPeriodSelector
    />
  );
}
