import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getDashboardSummary } from '../../api/dashboard.api';
import { getCourses } from '../../api/courses.api';
import useAuthStore from '../../store/authStore';
import DashboardLayout from '../../components/DashboardLayout';
import usePeriodStore from '../../store/periodStore';

const processSteps = [
  { label: 'Planificar', icon: 'event_note', desc: 'Define actividades por curso y período.' },
  { label: 'Evaluar', icon: 'edit_square', desc: 'Registra C1, C2 y C3 por actividad.' },
  { label: 'Recuperar', icon: 'published_with_changes', desc: 'Aplica RP si el período lo requiere.' },
  { label: 'Enviar', icon: 'task_alt', desc: 'Manda el workspace a revisión.' },
];

const subjectIcons = {
  matematica: 'calculate',
  lengua: 'menu_book',
  ingles: 'translate',
  ciencias: 'science',
  sociales: 'public',
};

function iconForSubject(subject = '') {
  const normalized = subject.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const key = Object.keys(subjectIcons).find((item) => normalized.includes(item));
  return key ? subjectIcons[key] : 'school';
}

function formatValue(value, suffix = '') {
  if (value === null || value === undefined || value === '') return '--';
  return `${value}${suffix}`;
}

export default function DocenteDashboard() {
  const { user } = useAuthStore();
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);

  const { data, isLoading } = useQuery({
    queryKey: ['dashboardSummary'],
    queryFn: getDashboardSummary,
  });

  const { data: coursesData } = useQuery({
    queryKey: ['courses'],
    queryFn: getCourses,
    staleTime: 5 * 60_000,
  });

  const courses = coursesData?.courses || [];
  const activeStudents = data?.total_students ?? 0;
  const attendanceAvg = data?.attendance_avg ?? null;
  const avgGrade = data?.avg_grade ?? null;
  const activeCourses = data?.active_courses ?? courses.length;
  const atRiskEstimate = avgGrade !== null && avgGrade < 75 ? 'Alto' : 'Controlado';

  const kpis = [
    {
      label: 'Cursos asignados',
      value: activeCourses,
      icon: 'school',
      helper: 'Workspaces activos',
      tone: 'bg-indigo-50 text-indigo-700',
    },
    {
      label: 'Estudiantes',
      value: activeStudents,
      icon: 'groups',
      helper: 'En secciones asignadas',
      tone: 'bg-sky-50 text-sky-700',
    },
    {
      label: 'Promedio general',
      value: formatValue(avgGrade),
      icon: 'analytics',
      helper: 'Escala 0-100',
      tone: 'bg-emerald-50 text-emerald-700',
    },
    {
      label: 'Asistencia',
      value: formatValue(attendanceAvg, attendanceAvg !== null ? '%' : ''),
      icon: 'fact_check',
      helper: 'Promedio de grupos',
      tone: 'bg-amber-50 text-amber-700',
    },
  ];

  return (
    <DashboardLayout>
      <div className="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <nav className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            <span>Portal Docente</span>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <span className="text-indigo-600">Inicio</span>
          </nav>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">
            Bienvenido, {user?.name || 'Docente'}
          </h1>
          <p className="mt-1 max-w-2xl text-sm text-slate-500">
            Resumen operativo de tus cursos, procesos de evaluación y seguimiento académico del período.
          </p>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Período seleccionado</p>
          <div className="mt-1 flex items-center gap-2">
            <span className="material-symbols-outlined text-[18px] text-indigo-500">calendar_today</span>
            <p className="text-sm font-bold text-slate-900">{selectedPeriod?.name || 'Actual'}</p>
            {selectedPeriod?.status && (
              <span className={`rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wider ${
                selectedPeriod.status === 'open' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'
              }`}>
                {selectedPeriod.status === 'open' ? 'Activo' : 'Cerrado'}
              </span>
            )}
          </div>
        </div>
      </div>

      <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {kpis.map((item) => (
          <div key={item.label} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-5 flex items-start justify-between">
              <div>
                <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{item.label}</p>
                <p className="mt-2 text-3xl font-extrabold text-slate-900">{isLoading ? '...' : item.value}</p>
              </div>
              <span className={`material-symbols-outlined rounded-lg p-2 text-[22px] ${item.tone}`}>{item.icon}</span>
            </div>
            <p className="text-xs font-semibold text-slate-500">{item.helper}</p>
          </div>
        ))}
      </section>

      <section className="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div className="xl:col-span-8 rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-6 py-4">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h2 className="text-base font-extrabold text-slate-900">Proceso del docente</h2>
                <p className="mt-1 text-xs text-slate-500">Flujo recomendado para cerrar calificaciones sin mezclar períodos.</p>
              </div>
              <Link to="/docente/courses" className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                Ver cursos
              </Link>
            </div>
          </div>
          <div className="grid grid-cols-1 gap-0 md:grid-cols-4">
            {processSteps.map((step, index) => (
              <div key={step.label} className="border-b border-slate-100 p-5 md:border-b-0 md:border-r last:border-r-0">
                <div className="mb-4 flex items-center justify-between">
                  <span className="material-symbols-outlined rounded-lg bg-slate-100 p-2 text-[22px] text-slate-700">{step.icon}</span>
                  <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">0{index + 1}</span>
                </div>
                <h3 className="text-sm font-extrabold text-slate-900">{step.label}</h3>
                <p className="mt-2 text-xs leading-5 text-slate-500">{step.desc}</p>
              </div>
            ))}
          </div>
        </div>

        <div className="xl:col-span-4 rounded-xl border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Estado operativo</p>
          <h2 className="mt-2 text-2xl font-extrabold">Riesgo {atRiskEstimate}</h2>
          <p className="mt-2 text-sm leading-6 text-slate-300">
            Usa los workspaces para registrar actividades, revisar candidatos a RP y enviar períodos a revisión cuando estén listos.
          </p>
          <div className="mt-6 grid grid-cols-2 gap-3">
            <div className="rounded-lg bg-white/5 p-3">
              <p className="text-[10px] font-bold uppercase text-slate-400">Revisión</p>
              <p className="mt-1 text-lg font-extrabold">Por curso</p>
            </div>
            <div className="rounded-lg bg-white/5 p-3">
              <p className="text-[10px] font-bold uppercase text-slate-400">Bloqueos</p>
              <p className="mt-1 text-lg font-extrabold">Automáticos</p>
            </div>
          </div>
        </div>
      </section>

      <section className="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div className="xl:col-span-7 rounded-xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 px-6 py-4">
            <h2 className="text-base font-extrabold text-slate-900">Cursos recientes</h2>
            <p className="mt-1 text-xs text-slate-500">Acceso rápido a tus primeros workspaces asignados.</p>
          </div>
          <div className="divide-y divide-slate-100">
            {(courses.length > 0 ? courses.slice(0, 5) : []).map((course) => (
              <Link
                key={`${course.section_id}-${course.subject_id}`}
                to={`/docente/courses/${course.section_id}/${course.subject_id}`}
                className="flex items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50"
              >
                <div className="flex min-w-0 items-center gap-3">
                  <span className="material-symbols-outlined rounded-lg bg-slate-100 p-2 text-[22px] text-slate-700">
                    {iconForSubject(course.subject_name)}
                  </span>
                  <div className="min-w-0">
                    <p className="truncate text-sm font-bold text-slate-900">
                      {course.grade_name} {course.section_name} - {course.subject_name}
                    </p>
                    <p className="text-xs font-semibold text-slate-400">{course.year_label || 'Año activo'}</p>
                  </div>
                </div>
                <span className="material-symbols-outlined text-[18px] text-slate-300">arrow_forward</span>
              </Link>
            ))}
            {courses.length === 0 && (
              <div className="px-6 py-10 text-center text-sm font-semibold text-slate-400">
                No hay cursos asignados todavía.
              </div>
            )}
          </div>
        </div>

        <div className="xl:col-span-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-base font-extrabold text-slate-900">Lectura rápida</h2>
          <div className="mt-5 space-y-4">
            {[
              ['Períodos cerrados', 'Bloquean edición para proteger datos en revisión.', 'lock'],
              ['En revisión', 'El docente espera validación de coordinación.', 'rule'],
              ['Recuperación RP', 'Debe registrarse antes de enviar a revisión.', 'published_with_changes'],
            ].map(([title, desc, icon]) => (
              <div key={title} className="flex gap-3 rounded-lg border border-slate-100 p-3">
                <span className="material-symbols-outlined text-[22px] text-slate-500">{icon}</span>
                <div>
                  <p className="text-sm font-bold text-slate-800">{title}</p>
                  <p className="mt-1 text-xs leading-5 text-slate-500">{desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </DashboardLayout>
  );
}
