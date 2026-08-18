import React, { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import DashboardLayout from '../../components/DashboardLayout';
import { getRiskOverview } from '../../api/risk.api';
import usePeriodStore from '../../store/periodStore';

const levelMeta = {
  high: {
    label: 'Alto',
    dot: 'bg-rose-500',
    bar: 'bg-rose-500',
    chip: 'bg-rose-50 text-rose-700 border-rose-100',
    icon: 'text-rose-600 bg-rose-50',
    glow: 'group-hover:shadow-rose-100',
  },
  medium: {
    label: 'Medio',
    dot: 'bg-amber-500',
    bar: 'bg-amber-500',
    chip: 'bg-amber-50 text-amber-700 border-amber-100',
    icon: 'text-amber-600 bg-amber-50',
    glow: 'group-hover:shadow-amber-100',
  },
  low: {
    label: 'Bajo',
    dot: 'bg-emerald-500',
    bar: 'bg-emerald-500',
    chip: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    icon: 'text-emerald-600 bg-emerald-50',
    glow: 'group-hover:shadow-emerald-100',
  },
};

function subjectIcon(subject = '') {
  const normalized = subject.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  if (normalized.includes('matematica')) return 'calculate';
  if (normalized.includes('lengua')) return 'menu_book';
  if (normalized.includes('social')) return 'public';
  if (normalized.includes('robot') || normalized.includes('program')) return 'memory';
  if (normalized.includes('ingles')) return 'translate';
  if (normalized.includes('fisica')) return 'sports_handball';

  return 'school';
}

function metricValue(value, suffix = '') {
  if (value === null || value === undefined) {
    return { label: 'Sin registro', muted: true };
  }

  return { label: `${value}${suffix}`, muted: false };
}

export default function RiskOverview() {
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['riskOverview', selectedPeriod?.id],
    queryFn: () => getRiskOverview(selectedPeriod?.id),
    enabled: !!selectedPeriod?.id,
  });

  const summary = data?.summary || {};
  const courses = data?.courses || [];

  const kpis = useMemo(() => [
    {
      label: 'Estudiantes en riesgo',
      value: summary.total_students_at_risk ?? 0,
      icon: 'group',
      tone: 'bg-slate-100 text-slate-700',
      note: `${summary.courses_with_risk ?? 0} cursos con señales`,
    },
    {
      label: 'Prioridad alta',
      value: summary.high_risk_students ?? 0,
      icon: 'priority_high',
      tone: 'bg-red-50 text-red-700',
      note: 'Requieren seguimiento primero',
    },
    {
      label: 'Alertas de asistencia',
      value: summary.attendance_alerts ?? 0,
      icon: 'event_busy',
      tone: 'bg-amber-50 text-amber-700',
      note: 'Ausencias o asistencia baja',
    },
    {
      label: 'Competencias débiles',
      value: summary.competency_alerts ?? 0,
      icon: 'psychology',
      tone: 'bg-cyan-50 text-cyan-700',
      note: 'C1, C2 o C3 por debajo de 70',
    },
  ], [summary]);

  return (
    <DashboardLayout>
      <div className="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <nav className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            <span>Portal Docente</span>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <span className="text-indigo-600">Riesgo</span>
          </nav>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Estudiantes en Riesgo</h1>
          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
            Monitorea rendimiento académico, asistencia y competencias críticas en tus cursos del período seleccionado.
          </p>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Período</p>
          <div className="mt-1 flex items-center gap-2">
            <span className="material-symbols-outlined text-[18px] text-indigo-500">calendar_today</span>
            <p className="text-sm font-bold text-slate-900">{selectedPeriod?.name || 'Selecciona un período'}</p>
          </div>
        </div>
      </div>

      <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        {kpis.map((item) => (
          <div key={item.label} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-5 flex items-start justify-between">
              <div>
                <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{item.label}</p>
                <p className="mt-2 font-mono text-3xl font-extrabold text-slate-900">{isLoading ? '...' : item.value}</p>
              </div>
              <span className={`material-symbols-outlined rounded-lg p-2 text-[22px] ${item.tone}`}>{item.icon}</span>
            </div>
            <p className="text-xs font-semibold text-slate-500">{item.note}</p>
          </div>
        ))}
      </section>

      {isError && (
        <div className="rounded-lg border border-red-100 bg-red-50 p-4 text-sm font-semibold text-red-700">
          No se pudo cargar el módulo de riesgo. Intenta nuevamente.
        </div>
      )}

      {isLoading ? (
        <div className="rounded-lg border border-slate-200 bg-white p-12 text-center shadow-sm">
          <span className="material-symbols-outlined animate-spin text-[42px] text-indigo-500">progress_activity</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">Analizando cursos...</p>
        </div>
      ) : (
        <section className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
          {courses.map((course) => {
            const meta = levelMeta[course.risk_level] || levelMeta.low;
            const attendance = metricValue(course.attendance_pct, '%');
            const average = metricValue(course.avg_grade);
            const atRiskCount = Number(course.at_risk_count || 0);

            return (
              <Link
                key={`${course.section_id}-${course.subject_id}`}
                to={`/docente/risk/${course.section_id}/${course.subject_id}`}
                className={`group flex min-h-[336px] flex-col rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_10px_30px_-22px_rgba(15,23,42,0.55)] transition-all duration-200 hover:-translate-y-1 hover:border-slate-300 hover:shadow-xl ${meta.glow}`}
              >
                <div className="mb-6 flex items-start justify-between gap-4">
                  <div className="flex min-w-0 items-start gap-3">
                    <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-slate-100 shadow-sm ${meta.icon}`}>
                      <span className="material-symbols-outlined text-[25px]">{subjectIcon(course.subject_name)}</span>
                    </div>
                    <div className="min-w-0">
                      <p className="truncate text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                        {course.grade_level || 'Curso'} · {course.subject_name}
                      </p>
                      <h2 className="mt-1.5 text-lg font-extrabold leading-tight text-slate-950">
                        {course.grade_name} {course.section_name}
                      </h2>
                    </div>
                  </div>
                  <span className={`inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider shadow-sm ${meta.chip}`}>
                    <span className={`h-1.5 w-1.5 rounded-full ${meta.dot}`} />
                    {meta.label}
                  </span>
                </div>

                <div className="mb-5 rounded-2xl bg-slate-50/70 px-4 py-4">
                  <div className="flex items-end justify-between gap-4">
                    <div>
                      <p className={`text-5xl font-black leading-none tracking-tight ${atRiskCount === 0 ? 'text-emerald-600' : 'text-slate-950'}`}>
                        {atRiskCount}
                      </p>
                      <p className="mt-1.5 text-xs font-bold text-slate-500">
                        {atRiskCount === 0 ? 'sin estudiantes en riesgo' : 'estudiantes en riesgo'}
                      </p>
                    </div>
                    <div className="hidden rounded-full bg-white px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 shadow-sm sm:block">
                      Seguimiento
                    </div>
                  </div>
                </div>

                <div className="mb-5">
                  <div className="mb-2 flex items-center justify-between">
                    <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nivel de riesgo</span>
                    <span className="text-[11px] font-bold text-slate-500">{course.risk_pressure || 0}%</span>
                  </div>
                  <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div className={`h-full rounded-full ${meta.bar}`} style={{ width: `${Math.max(4, course.risk_pressure || 0)}%` }} />
                  </div>
                </div>

                <div className="mb-5 grid grid-cols-2 gap-3">
                  <div className="rounded-2xl border border-slate-100 bg-white px-3 py-3">
                    <div className="mb-2 flex items-center gap-2">
                      <span className="material-symbols-outlined text-[17px] text-slate-400">fact_check</span>
                      <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Asistencia</p>
                    </div>
                    <p className={`text-base font-extrabold ${attendance.muted ? 'text-slate-400' : 'text-slate-900'}`}>
                      {attendance.label}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-slate-100 bg-white px-3 py-3">
                    <div className="mb-2 flex items-center gap-2">
                      <span className="material-symbols-outlined text-[17px] text-slate-400">analytics</span>
                      <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Promedio</p>
                    </div>
                    <p className={`text-base font-extrabold ${average.muted ? 'text-slate-400' : 'text-slate-900'}`}>
                      {average.label}
                    </p>
                  </div>
                </div>

                <div className="mt-auto flex items-center justify-between border-t border-slate-100 pt-4">
                  <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Análisis del curso</span>
                  <span className="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition-all group-hover:bg-slate-800">
                    Ver análisis
                    <span className="material-symbols-outlined text-[16px] transition-transform group-hover:translate-x-0.5">arrow_forward</span>
                  </span>
                </div>

              </Link>
            );
          })}

          {courses.length === 0 && (
            <div className="rounded-lg border border-dashed border-slate-200 bg-white p-10 text-center text-sm font-semibold text-slate-400 md:col-span-2 xl:col-span-3">
              No hay cursos asignados para analizar en este período.
            </div>
          )}
        </section>
      )}
    </DashboardLayout>
  );
}
