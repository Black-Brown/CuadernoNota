import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getAdminDashboard } from '../../api/admin.api';
import useAuthStore from '../../store/authStore';
import PageHeader from '../../components/ui/PageHeader';
import KpiCard from '../../components/ui/KpiCard';
import { KpiSkeleton } from '../../components/ui/LoadingSkeleton';

const KPIS = [
  { key: 'active_teachers', label: 'Docentes activos', icon: 'person_apron', tone: 'bg-indigo-50 text-indigo-700', helper: 'Cuentas habilitadas' },
  { key: 'active_students', label: 'Estudiantes activos', icon: 'groups', tone: 'bg-sky-50 text-sky-700', helper: 'Matrículas activas' },
  { key: 'sections', label: 'Secciones', icon: 'domain', tone: 'bg-emerald-50 text-emerald-700', helper: 'Año escolar actual' },
  { key: 'active_assignments', label: 'Asignaciones docentes', icon: 'assignment_ind', tone: 'bg-amber-50 text-amber-700', helper: 'Cursos con docente' },
];

const EXTRA_STATS = [
  { key: 'users', label: 'Usuarios registrados', icon: 'group' },
  { key: 'subjects', label: 'Materias activas', icon: 'menu_book' },
];

const PROCESS_STEPS = [
  { label: 'Configurar', icon: 'settings', desc: 'Año escolar, períodos y catálogo académico.', path: '/admin/catalog' },
  { label: 'Organizar', icon: 'account_tree', desc: 'Grados, secciones y materias.', path: '/admin/catalog' },
  { label: 'Asignar', icon: 'assignment_ind', desc: 'Profesores a cursos y materias.', path: '/admin/assignments' },
  { label: 'Supervisar', icon: 'verified', desc: 'Calificaciones y promociones.', path: '/admin/reviews' },
];

const QUICK_ACTIONS = [
  { label: 'Registrar usuario', desc: 'Docente, coordinador o admin', icon: 'person_add', path: '/admin/users' },
  { label: 'Inscribir estudiante', desc: 'Nueva matrícula académica', icon: 'person_add_alt', path: '/admin/students' },
  { label: 'Crear sección', desc: 'Grado, tanda y año escolar', icon: 'domain_add', path: '/admin/catalog' },
  { label: 'Asignar docente', desc: 'Curso, sección y materia', icon: 'assignment_ind', path: '/admin/assignments' },
  { label: 'Revisar calificaciones', desc: 'Aprobar, rechazar o reabrir', icon: 'fact_check', path: '/admin/reviews' },
];

export default function AdminDashboard() {
  const { user } = useAuthStore();
  const { data, isLoading } = useQuery({ queryKey: ['admin-dashboard'], queryFn: getAdminDashboard });
  const counts = data?.counts || {};

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Inicio']}
        title={`Bienvenido, ${user?.name?.split(' ')[0] || 'Administrador'}`}
        description="Resumen operativo del centro, configuración académica y procesos pendientes."
      />

      <section className="mb-4">
        {isLoading ? (
          <KpiSkeleton />
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            {KPIS.map((kpi) => (
              <KpiCard key={kpi.key} label={kpi.label} value={counts[kpi.key] ?? 0} icon={kpi.icon} tone={kpi.tone} helper={kpi.helper} />
            ))}
          </div>
        )}
      </section>

      <section className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {EXTRA_STATS.map((item) => (
          <div key={item.key} className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div className="mb-2 flex items-center justify-between">
              <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{item.label}</p>
              <span className="material-symbols-outlined text-[17px] text-slate-400">{item.icon}</span>
            </div>
            <p className="text-xl font-extrabold text-slate-900">{isLoading ? '...' : counts[item.key] ?? 0}</p>
          </div>
        ))}
        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
          <div className="mb-2 flex items-center justify-between">
            <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Notas pendientes</p>
            <span className="material-symbols-outlined text-[17px] text-slate-400">fact_check</span>
          </div>
          <p className="text-xl font-extrabold text-slate-900">{isLoading ? '...' : data?.pending_grades ?? 0}</p>
        </div>
        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
          <div className="mb-2 flex items-center justify-between">
            <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Alertas sin resolver</p>
            <span className="material-symbols-outlined text-[17px] text-slate-400">warning</span>
          </div>
          <p className="text-xl font-extrabold text-slate-900">{isLoading ? '...' : data?.unresolved_alerts ?? 0}</p>
        </div>
      </section>

      <section className="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div className="xl:col-span-8 rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-6 py-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h2 className="text-base font-extrabold text-slate-900">Proceso administrativo</h2>
                <p className="mt-1 text-xs text-slate-500">Flujo recomendado para mantener el ciclo académico organizado.</p>
              </div>
              <Link to="/admin/catalog" className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                Ir al catálogo
              </Link>
            </div>
          </div>
          <div className="grid grid-cols-1 gap-0 md:grid-cols-4">
            {PROCESS_STEPS.map((step, index) => (
              <Link key={step.label} to={step.path} className="border-b border-slate-100 p-5 md:border-b-0 md:border-r last:border-r-0 hover:bg-slate-50/60">
                <div className="mb-4 flex items-center justify-between">
                  <span className="material-symbols-outlined rounded-lg bg-slate-100 p-2 text-[22px] text-slate-700">{step.icon}</span>
                  <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">0{index + 1}</span>
                </div>
                <h3 className="text-sm font-extrabold text-slate-900">{step.label}</h3>
                <p className="mt-2 text-xs leading-5 text-slate-500">{step.desc}</p>
              </Link>
            ))}
          </div>
        </div>

        <div className="xl:col-span-4 rounded-xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Estado operativo</p>
          <h2 className="mt-2 text-2xl font-extrabold">{data?.pending_grades > 0 ? 'Revisión pendiente' : 'Operación estable'}</h2>
          <p className="mt-2 text-sm leading-6 text-slate-300">
            {data?.pending_grades > 0
              ? 'Hay calificaciones esperando una decisión administrativa.'
              : 'Los procesos críticos están bajo control. Continúa supervisando el ciclo escolar.'}
            {' '}Año escolar {data?.active_academic_year ? `activo: ${data.active_academic_year.name}` : 'sin configurar.'}
          </p>
          <div className="mt-6 grid grid-cols-2 gap-3">
            <div className="rounded-lg bg-white/5 p-3">
              <p className="text-[10px] font-bold uppercase text-slate-400">Notas en revisión</p>
              <p className="mt-1 text-lg font-extrabold">{data?.pending_grades ?? 0}</p>
            </div>
            <div className="rounded-lg bg-white/5 p-3">
              <p className="text-[10px] font-bold uppercase text-slate-400">Alertas abiertas</p>
              <p className="mt-1 text-lg font-extrabold">{data?.unresolved_alerts ?? 0}</p>
            </div>
          </div>
        </div>
      </section>

      <section className="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div className="xl:col-span-7 rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-6 py-4">
            <h2 className="text-base font-extrabold text-slate-900">Acciones rápidas</h2>
            <p className="mt-1 text-xs text-slate-500">Accede a las tareas administrativas frecuentes.</p>
          </div>
          <div className="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2">
            {QUICK_ACTIONS.map((action) => (
              <Link key={action.label} to={action.path} className="flex items-center gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50">
                <span className="material-symbols-outlined rounded-lg bg-indigo-50 p-2 text-[20px] text-indigo-600">{action.icon}</span>
                <div className="min-w-0">
                  <p className="text-sm font-bold text-slate-900">{action.label}</p>
                  <p className="text-xs text-slate-500">{action.desc}</p>
                </div>
              </Link>
            ))}
          </div>
        </div>

        <div className="xl:col-span-5 rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-6 py-4">
            <h2 className="text-base font-extrabold text-slate-900">Requiere atención</h2>
            <p className="mt-1 text-xs text-slate-500">Elementos que pueden bloquear el trabajo docente.</p>
          </div>
          <div className="divide-y divide-slate-100">
            <Link to="/admin/reviews" className="flex items-center justify-between px-6 py-4 hover:bg-slate-50">
              <div className="flex items-center gap-3">
                <span className="material-symbols-outlined text-[20px] text-amber-500">fact_check</span>
                <span className="text-sm font-semibold text-slate-700">Calificaciones pendientes</span>
              </div>
              <span className="text-sm font-extrabold text-slate-900">{data?.pending_grades ?? 0}</span>
            </Link>
            <Link to="/admin/audit" className="flex items-center justify-between px-6 py-4 hover:bg-slate-50">
              <div className="flex items-center gap-3">
                <span className="material-symbols-outlined text-[20px] text-red-500">warning</span>
                <span className="text-sm font-semibold text-slate-700">Alertas sin resolver</span>
              </div>
              <span className="text-sm font-extrabold text-slate-900">{data?.unresolved_alerts ?? 0}</span>
            </Link>
          </div>
        </div>
      </section>
    </>
  );
}
