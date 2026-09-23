import React from 'react';
import AppLayout from './AppLayout';
import useAuthStore from '../store/authStore';

const menuItems = [{ label: 'Supervisión académica', items: [
  { name: 'Inicio', icon: 'dashboard', path: '/coordinador/dashboard' },
  { name: 'Estudiantes', icon: 'school', path: '/coordinador/students' },
  { name: 'Catálogo académico', icon: 'account_tree', path: '/coordinador/catalog' },
  { name: 'Configuración institucional', icon: 'tune', path: '/coordinador/institutional' },
  { name: 'Asignaciones docentes', icon: 'assignment_ind', path: '/coordinador/assignments' },
  { name: 'Revisión de notas', icon: 'fact_check', path: '/coordinador/reviews' },
  { name: 'Asignación estudiantes', icon: 'group_add', path: '/coordinador/student-placements' },
  { name: 'Promoción escolar', icon: 'upgrade', path: '/coordinador/promotions' },
  { name: 'Reportes', icon: 'analytics', path: '/coordinador/reports' },
] }];

export default function CoordinatorLayout() {
  const { user } = useAuthStore();
  return <AppLayout portalName="Portal de Coordinación" roleLabel="Coordinador" user={user} menuItems={menuItems} />;
}
