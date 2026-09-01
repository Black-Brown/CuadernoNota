import React, { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import DashboardLayout from '../../components/DashboardLayout';
import { getCourseRisk } from '../../api/risk.api';
import usePeriodStore from '../../store/periodStore';
import { downloadCsv, safeFilename } from '../../utils/exportCsv';

const riskStyles = {
  high: 'bg-red-50 text-red-700 border-red-100',
  medium: 'bg-amber-50 text-amber-700 border-amber-100',
  low: 'bg-emerald-50 text-emerald-700 border-emerald-100',
};

const riskLabels = {
  high: 'Alto',
  medium: 'Medio',
  low: 'Bajo',
};

function metricColor(value, dangerBelow, warningBelow) {
  if (value === null || value === undefined) return 'text-slate-400';
  if (value < dangerBelow) return 'text-red-600';
  if (value < warningBelow) return 'text-amber-600';
  return 'text-emerald-600';
}

function progressColor(value, dangerBelow, warningBelow) {
  if (value === null || value === undefined) return 'bg-slate-300';
  if (value < dangerBelow) return 'bg-red-500';
  if (value < warningBelow) return 'bg-amber-500';
  return 'bg-emerald-500';
}

function reasonLabel(reason) {
  return {
    academic_performance: 'rendimiento',
    attendance: 'asistencia',
    competency: 'competencia',
    consecutive_absences: 'ausencias',
    active_alerts: 'alertas',
  }[reason] || reason;
}

export default function RiskCourse() {
  const { sectionId, subjectId } = useParams();
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');

  const { data, isLoading, isError } = useQuery({
    queryKey: ['courseRisk', sectionId, subjectId, selectedPeriod?.id],
    queryFn: () => getCourseRisk(sectionId, subjectId, selectedPeriod?.id),
    enabled: !!sectionId && !!subjectId && !!selectedPeriod?.id,
  });

  const course = data?.course;
  const summary = data?.summary || {};
  const students = data?.students || [];

  const filteredStudents = useMemo(() => {
    const needle = search.trim().toLowerCase();

    return students.filter((student) => {
      const matchesSearch = !needle
        || student.student_name.toLowerCase().includes(needle)
        || student.enrollment_no?.toLowerCase().includes(needle);

      const matchesFilter = filter === 'all'
        || student.risk_level === filter
        || (filter === 'attendance' && student.attendance_risk)
        || (filter === 'competency' && student.competency_risk);

      return matchesSearch && matchesFilter;
    });
  }, [students, filter, search]);

  const filters = [
    ['all', 'Todos'],
    ['high', 'Alto riesgo'],
    ['medium', 'Riesgo medio'],
    ['attendance', 'Asistencia baja'],
    ['competency', 'Competencias'],
  ];

  const handleExport = () => {
    if (!course || filteredStudents.length === 0) return;

    const rows = [
      [
        'Estudiante',
        'Matricula',
        'Nivel de riesgo',
        'Promedio',
        'Asistencia',
        'Alertas activas',
        'Riesgo academico',
        'Riesgo asistencia',
        'Riesgo competencia',
        'Senales',
        'Periodo',
        'Curso',
        'Asignatura',
      ],
      ...filteredStudents.map((student) => [
        student.display_name,
        student.enrollment_no,
        riskLabels[student.risk_level] || student.risk_level,
        student.average_grade ?? 'Sin registro',
        `${student.attendance_pct}%`,
        student.active_alerts ?? 0,
        student.academic_risk ? 'Si' : 'No',
        student.attendance_risk ? 'Si' : 'No',
        student.competency_risk ? 'Si' : 'No',
        student.risk_reasons.map(reasonLabel).join(' | '),
        selectedPeriod?.name || '',
        `${course.grade_name} ${course.section_name}`,
        course.subject_name,
      ]),
    ];

    downloadCsv(
      `estudiantes-riesgo-${safeFilename(`${course.grade_name}-${course.section_name}-${course.subject_name}-${selectedPeriod?.name}`)}.csv`,
      rows
    );
  };

  return (
    <DashboardLayout>
      <nav className="mb-5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
        <Link to="/docente/risk" className="hover:text-slate-700">Estudiantes en Riesgo</Link>
        <span className="material-symbols-outlined text-[12px]">chevron_right</span>
        <span>{course ? `${course.grade_name} ${course.section_name}` : 'Curso'}</span>
        <span className="material-symbols-outlined text-[12px]">chevron_right</span>
        <span className="text-indigo-600">{course?.subject_name || 'Asignatura'}</span>
      </nav>

      <div className="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Análisis de Riesgo</h1>
          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
            {course
              ? `${course.grade_name} ${course.section_name} · ${course.subject_name}`
              : 'Monitorea estudiantes con bajo rendimiento, asistencia crítica o competencias débiles.'}
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={handleExport}
            disabled={!course || filteredStudents.length === 0}
            className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <span className="material-symbols-outlined text-[18px]">file_download</span>
            Exportar
          </button>
          <button className="inline-flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
            <span className="material-symbols-outlined text-[18px]">mark_email_unread</span>
            Notificar coordinación
          </button>
        </div>
      </div>

      <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        {[
          ['Total en riesgo', summary.total_students_at_risk ?? 0, 'group', 'text-slate-900'],
          ['Alta prioridad', summary.high_risk_students ?? 0, 'priority_high', 'text-red-600'],
          ['Alertas asistencia', summary.attendance_alerts ?? 0, 'event_busy', 'text-amber-600'],
        ].map(([label, value, icon, color]) => (
          <div key={label} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-3 flex items-center justify-between">
              <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p>
              <span className="material-symbols-outlined text-[20px] text-slate-400">{icon}</span>
            </div>
            <p className={`font-mono text-4xl font-extrabold ${color}`}>{isLoading ? '...' : value}</p>
          </div>
        ))}
      </section>

      <section className="rounded-t-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex flex-col justify-between gap-3 lg:flex-row lg:items-center">
          <div className="flex flex-wrap gap-2">
            {filters.map(([key, label]) => (
              <button
                key={key}
                type="button"
                onClick={() => setFilter(key)}
                className={`rounded-full px-3 py-1.5 text-xs font-bold transition-colors ${
                  filter === key
                    ? 'bg-slate-950 text-white'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                }`}
              >
                {label}
              </button>
            ))}
          </div>

          <div className="relative w-full lg:w-72">
            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Filtrar por nombre o matrícula..."
              className="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-10 pr-3 text-sm outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500"
            />
          </div>
        </div>
      </section>

      <section className="overflow-hidden rounded-b-lg border-x border-b border-slate-200 bg-white shadow-sm">
        {isError ? (
          <div className="p-10 text-center text-sm font-semibold text-red-600">No se pudo cargar el análisis de riesgo.</div>
        ) : isLoading ? (
          <div className="p-10 text-center">
            <span className="material-symbols-outlined animate-spin text-[42px] text-indigo-500">progress_activity</span>
            <p className="mt-3 text-sm font-semibold text-slate-600">Cargando estudiantes...</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[900px] text-left">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                  <th className="px-6 py-4">Estudiante</th>
                  <th className="px-6 py-4">Riesgo</th>
                  <th className="px-6 py-4 text-center">Promedio</th>
                  <th className="px-6 py-4">Asistencia</th>
                  <th className="px-6 py-4">Señales</th>
                  <th className="px-6 py-4 text-center">Acciones</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-sm">
                {filteredStudents.map((student) => (
                  <tr key={student.student_id} className="transition-colors hover:bg-slate-50">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-extrabold text-slate-700">
                          {student.display_name.substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                          <p className="font-bold text-slate-900">{student.display_name}</p>
                          <p className="font-mono text-[11px] text-slate-400">{student.enrollment_no}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <span className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-extrabold ${riskStyles[student.risk_level] || riskStyles.low}`}>
                        <span className="h-1.5 w-1.5 rounded-full bg-current" />
                        {riskLabels[student.risk_level] || 'Bajo'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className={`font-mono text-base font-extrabold ${metricColor(student.average_grade, 60, 70)}`}>
                        {student.average_grade ?? '--'}
                      </span>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <span className={`w-12 font-mono text-sm font-bold ${metricColor(student.attendance_pct, 70, 80)}`}>
                          {student.attendance_pct}%
                        </span>
                        <div className="h-2 min-w-[110px] flex-1 overflow-hidden rounded-full bg-slate-100">
                          <div className={`h-full rounded-full ${progressColor(student.attendance_pct, 70, 80)}`} style={{ width: `${Math.max(4, student.attendance_pct)}%` }} />
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex flex-wrap gap-1.5">
                        {student.risk_reasons.map((reason) => (
                          <span key={reason} className="rounded bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            {reasonLabel(reason)}
                          </span>
                        ))}
                      </div>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <Link
                        to={`/docente/risk/${sectionId}/${subjectId}/students/${student.student_id}`}
                        className="inline-flex items-center justify-center rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-900"
                        title="Ver detalle"
                      >
                        <span className="material-symbols-outlined text-[21px]">open_in_new</span>
                      </Link>
                    </td>
                  </tr>
                ))}

                {filteredStudents.length === 0 && (
                  <tr>
                    <td colSpan="6" className="px-6 py-14 text-center text-sm font-semibold text-slate-400">
                      No hay estudiantes que coincidan con ese filtro.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </DashboardLayout>
  );
}
