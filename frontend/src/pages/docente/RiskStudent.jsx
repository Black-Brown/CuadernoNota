import React from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import DashboardLayout from '../../components/DashboardLayout';
import { getStudentRisk } from '../../api/risk.api';
import usePeriodStore from '../../store/periodStore';
import { downloadCsv, safeFilename } from '../../utils/exportCsv';

const levelLabel = {
  high: 'Alto Riesgo',
  medium: 'Riesgo Medio',
  low: 'Riesgo Bajo',
};

const levelClass = {
  high: 'bg-red-50 text-red-700 border-red-100',
  medium: 'bg-amber-50 text-amber-700 border-amber-100',
  low: 'bg-emerald-50 text-emerald-700 border-emerald-100',
};

function scoreClass(score) {
  if (score === null || score === undefined) return 'text-slate-400';
  if (score < 60) return 'text-red-600';
  if (score < 70) return 'text-amber-600';
  return 'text-slate-900';
}

function RingChart({ academic, attendance, competency, level }) {
  const radius = 42;
  const circumference = 2 * Math.PI * radius;
  const academicOffset = circumference - ((academic ?? 0) / 100) * circumference;
  const attendanceOffset = circumference - ((attendance ?? 0) / 100) * circumference;
  const competencyOffset = circumference - ((competency ?? 0) / 100) * circumference;

  return (
    <div className="relative flex h-48 w-48 items-center justify-center">
      <svg viewBox="0 0 112 112" className="h-full w-full -rotate-90">
        <circle cx="56" cy="56" r={radius} fill="none" stroke="#e2e8f0" strokeWidth="10" />
        <circle cx="56" cy="56" r={radius} fill="none" stroke="#ef4444" strokeDasharray={circumference} strokeDashoffset={academicOffset} strokeLinecap="round" strokeWidth="10" />
        <circle cx="56" cy="56" r={radius - 13} fill="none" stroke="#f59e0b" strokeDasharray={2 * Math.PI * (radius - 13)} strokeDashoffset={attendanceOffset * ((radius - 13) / radius)} strokeLinecap="round" strokeWidth="10" />
        <circle cx="56" cy="56" r={radius - 26} fill="none" stroke="#10b981" strokeDasharray={2 * Math.PI * (radius - 26)} strokeDashoffset={competencyOffset * ((radius - 26) / radius)} strokeLinecap="round" strokeWidth="10" />
      </svg>
      <div className="absolute text-center">
        <p className="text-lg font-extrabold text-slate-900">{levelLabel[level] || 'Riesgo'}</p>
        <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">prioridad</p>
      </div>
    </div>
  );
}

function ActionLink({ action }) {
  const className = 'mb-2 flex w-full items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-left text-sm font-bold text-slate-700 transition-colors hover:border-slate-200 hover:bg-white hover:text-slate-950';
  const content = (
    <>
      <span className="flex min-w-0 items-center gap-3">
        <span className="material-symbols-outlined text-[20px] text-slate-500">{action.icon}</span>
        <span className="min-w-0">
          <span className="block truncate">{action.label}</span>
          <span className="mt-0.5 block truncate text-[10px] font-semibold text-slate-400">{action.helper}</span>
        </span>
      </span>
      <span className="material-symbols-outlined text-[18px] text-slate-300">chevron_right</span>
    </>
  );

  if (action.to) {
    return (
      <Link to={action.to} className={className}>
        {content}
      </Link>
    );
  }

  return (
    <a href={action.href} className={className}>
      {content}
    </a>
  );
}

export default function RiskStudent() {
  const { sectionId, subjectId, studentId } = useParams();
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['studentRisk', sectionId, subjectId, studentId, selectedPeriod?.id],
    queryFn: () => getStudentRisk(sectionId, subjectId, studentId, selectedPeriod?.id),
    enabled: !!sectionId && !!subjectId && !!studentId && !!selectedPeriod?.id,
  });

  const student = data?.student;
  const course = data?.course;
  const performance = data?.activity_performance || [];
  const competencies = data?.competency_summary || [];
  const alerts = data?.alert_timeline || [];
  const observations = data?.recent_observations || [];
  const distribution = data?.risk_distribution || {};
  const coordinatorSubject = student
    ? encodeURIComponent(`Seguimiento de riesgo - ${student.display_name}`)
    : '';
  const coordinatorBody = student && course
    ? encodeURIComponent(
      [
        `Estudiante: ${student.display_name}`,
        `Matrícula: ${student.enrollment_no}`,
        `Curso: ${course.grade_name} ${course.section_name} - ${course.subject_name}`,
        `Período: ${selectedPeriod?.name || 'No especificado'}`,
        `Nivel de riesgo: ${levelLabel[student.risk_level] || student.risk_level}`,
        `Promedio actual: ${student.average_grade ?? 'Sin registro'}`,
        `Asistencia: ${student.attendance_pct}%`,
        `Competencia crítica: ${student.critical_competency?.toUpperCase() || 'Sin registro'}`,
        '',
        'Favor revisar este caso para seguimiento de coordinación.',
      ].join('\n')
    )
    : '';
  const actionItems = student ? [
    {
      label: 'Agregar observación',
      icon: 'add_comment',
      helper: 'Abrir workspace del curso',
      to: `/docente/observations/${sectionId}/${subjectId}?student_id=${student.student_id}`,
    },
    {
      label: 'Notificar coordinación',
      icon: 'mail',
      helper: 'Preparar mensaje contextual',
      href: `mailto:?subject=${coordinatorSubject}&body=${coordinatorBody}`,
    },
    {
      label: 'Abrir calificaciones',
      icon: 'grade',
      helper: 'Ver resumen del curso',
      to: `/docente/grades/${sectionId}/${subjectId}`,
    },
  ] : [];
  const handleExport = () => {
    if (!student || !course) return;

    const rows = [
      ['Reporte de estudiante en riesgo'],
      ['Estudiante', student.display_name],
      ['Matricula', student.enrollment_no],
      ['Curso', `${course.grade_name} ${course.section_name}`],
      ['Asignatura', course.subject_name],
      ['Periodo', selectedPeriod?.name || ''],
      ['Nivel de riesgo', levelLabel[student.risk_level] || student.risk_level],
      ['Promedio actual', student.average_grade ?? 'Sin registro'],
      ['Asistencia', `${student.attendance_pct}%`],
      ['Competencia critica', student.critical_competency?.toUpperCase() || 'Sin registro'],
      ['Alertas activas', student.active_alerts],
      [],
      ['Competencias'],
      ['Codigo', 'Competencia', 'Promedio', 'Estado', 'Actividad mas debil'],
      ...competencies.map((competency) => [
        competency.code,
        competency.name,
        competency.average ?? 'Sin registro',
        competency.status,
        competency.weakest_activity
          ? `${competency.weakest_activity.activity_name} (${competency.weakest_activity.score})`
          : '',
      ]),
      [],
      ['Actividades'],
      ['Actividad', 'C1', 'C2', 'C3'],
      ...performance.map((activity) => [
        activity.activity_name,
        activity.c1 ?? '',
        activity.c2 ?? '',
        activity.c3 ?? '',
      ]),
      [],
      ['Alertas'],
      ['Fecha', 'Tipo', 'Mensaje', 'Resuelta'],
      ...alerts.map((alert) => [
        alert.date?.slice(0, 10) || '',
        alert.type,
        alert.message,
        alert.resolved ? 'Si' : 'No',
      ]),
      [],
      ['Observaciones'],
      ['Fecha', 'Descripcion', 'Autor'],
      ...observations.map((observation) => [
        observation.date,
        observation.description,
        observation.author_name || 'Docente',
      ]),
    ];

    downloadCsv(
      `reporte-riesgo-${safeFilename(`${student.display_name}-${course.subject_name}-${selectedPeriod?.name}`)}.csv`,
      rows
    );
  };

  return (
    <DashboardLayout>
      <nav className="mb-5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
        <Link to="/docente/risk" className="hover:text-slate-700">Riesgo</Link>
        <span className="material-symbols-outlined text-[12px]">chevron_right</span>
        <Link to={`/docente/risk/${sectionId}/${subjectId}`} className="hover:text-slate-700">
          {course ? `${course.grade_name} ${course.section_name}` : 'Curso'}
        </Link>
        <span className="material-symbols-outlined text-[12px]">chevron_right</span>
        <span className="text-indigo-600">{student?.display_name || 'Estudiante'}</span>
      </nav>

      {isLoading ? (
        <div className="rounded-lg border border-slate-200 bg-white p-12 text-center shadow-sm">
          <span className="material-symbols-outlined animate-spin text-[42px] text-indigo-500">progress_activity</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">Cargando expediente de riesgo...</p>
        </div>
      ) : isError || !student ? (
        <div className="rounded-lg border border-red-100 bg-red-50 p-8 text-center text-sm font-semibold text-red-700">
          No se pudo cargar el detalle del estudiante.
        </div>
      ) : (
        <>
          <div className="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <div className="mb-2 flex items-center gap-3">
                <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">{student.display_name}</h1>
                <span className={`rounded-full border px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider ${levelClass[student.risk_level] || levelClass.low}`}>
                  {levelLabel[student.risk_level] || 'Riesgo'}
                </span>
              </div>
              <p className="text-sm leading-6 text-slate-500">
                {course?.grade_name} {course?.section_name} · {course?.subject_name} · Matrícula {student.enrollment_no}
              </p>
            </div>

            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={handleExport}
                className="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50"
              >
                Exportar reporte
              </button>
              <button className="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800">
                Asignar recuperación
              </button>
            </div>
          </div>

          <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
            {[
              ['Promedio actual', student.average_grade ?? '--', '/100', scoreClass(student.average_grade)],
              ['Asistencia', `${student.attendance_pct}%`, '', scoreClass(student.attendance_pct < 80 ? 60 : 80)],
              ['Competencia crítica', student.critical_competency?.toUpperCase() || '--', '', 'text-slate-900'],
              ['Alertas activas', student.active_alerts, '', 'text-slate-900'],
            ].map(([label, value, suffix, color]) => (
              <div key={label} className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <p className="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p>
                <div className="flex items-baseline gap-2">
                  <span className={`font-mono text-3xl font-extrabold ${color}`}>{value}</span>
                  {suffix && <span className="text-xs font-bold text-slate-400">{suffix}</span>}
                </div>
              </div>
            ))}
          </section>

          <section className="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <div className="space-y-6 xl:col-span-8">
              <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                <TimelineCard title="Línea de alertas" icon="history" items={alerts} empty="No hay alertas registradas." />
                <ObservationCard observations={observations} />
              </div>

              <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-100 bg-slate-50 px-5 py-4">
                  <h2 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-700">Análisis por competencia</h2>
                </div>

                <div className="p-5">
                  <p className="mb-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Matriz de actividades</p>
                  <div className="mb-6 overflow-x-auto">
                    <table className="w-full min-w-[560px] text-left">
                      <thead>
                        <tr className="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                          <th className="px-4 py-3">Actividad</th>
                          <th className="px-4 py-3 text-center">C1</th>
                          <th className="px-4 py-3 text-center">C2</th>
                          <th className="px-4 py-3 text-center">C3</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 text-sm">
                        {performance.map((activity) => (
                          <tr key={activity.activity_id} className="hover:bg-slate-50">
                            <td className="px-4 py-3 font-bold text-slate-800">{activity.activity_name}</td>
                            {['c1', 'c2', 'c3'].map((key) => (
                              <td key={key} className={`px-4 py-3 text-center font-mono font-bold ${scoreClass(activity[key])}`}>
                                {activity[key] ?? '--'}
                              </td>
                            ))}
                          </tr>
                        ))}

                        {performance.length === 0 && (
                          <tr>
                            <td colSpan="4" className="px-4 py-8 text-center text-sm font-semibold text-slate-400">
                              No hay actividades calificadas para este período.
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>

                  <p className="mb-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Resumen de competencias</p>
                  <div className="overflow-x-auto">
                    <table className="w-full min-w-[620px] text-left">
                      <thead>
                        <tr className="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                          <th className="px-4 py-3">Competencia</th>
                          <th className="px-4 py-3 text-center">Promedio</th>
                          <th className="px-4 py-3 text-center">Estado</th>
                          <th className="px-4 py-3">Actividad más débil</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100 text-sm">
                        {competencies.map((competency) => (
                          <tr key={competency.code} className={competency.status === 'risk' ? 'bg-red-50/40' : 'hover:bg-slate-50'}>
                            <td className={`px-4 py-3 font-bold ${competency.status === 'risk' ? 'text-red-700' : 'text-slate-800'}`}>
                              {competency.code} · {competency.name}
                            </td>
                            <td className={`px-4 py-3 text-center font-mono font-extrabold ${scoreClass(competency.average)}`}>
                              {competency.average ?? '--'}
                            </td>
                            <td className="px-4 py-3 text-center">
                              <span className={`inline-block h-2.5 w-2.5 rounded-full ${competency.status === 'risk' ? 'bg-red-500' : 'bg-emerald-500'}`} />
                            </td>
                            <td className="px-4 py-3 text-slate-600">
                              {competency.weakest_activity
                                ? `${competency.weakest_activity.activity_name} (${competency.weakest_activity.score})`
                                : '--'}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <aside className="space-y-6 xl:col-span-4">
              <div className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="mb-6 text-[11px] font-extrabold uppercase tracking-wider text-slate-700">Distribución del riesgo</h2>
                <div className="flex justify-center">
                  <RingChart
                    academic={distribution.academic_health}
                    attendance={distribution.attendance_health}
                    competency={distribution.competency_health}
                    level={student.risk_level}
                  />
                </div>
                <div className="mt-5 space-y-3 text-sm">
                  {[
                    ['Salud académica', distribution.academic_health, 'bg-red-500'],
                    ['Asistencia', distribution.attendance_health, 'bg-amber-500'],
                    ['Competencias', distribution.competency_health, 'bg-emerald-500'],
                  ].map(([label, value, dot]) => (
                    <div key={label} className="flex items-center justify-between">
                      <span className="flex items-center gap-2 text-slate-600">
                        <span className={`h-2 w-2 rounded-full ${dot}`} />
                        {label}
                      </span>
                      <span className="font-mono font-bold text-slate-900">{value ?? '--'}%</span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="rounded-lg border border-slate-200 bg-slate-950 p-6 text-white shadow-sm">
                <div className="mb-4 flex items-center gap-2">
                  <span className="material-symbols-outlined text-[20px] text-indigo-300">tips_and_updates</span>
                  <h2 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-300">Lectura docente</h2>
                </div>
                <p className="text-sm leading-6 text-slate-300">
                  Prioriza una observación académica, revisa la competencia crítica y asigna recuperación pedagógica antes de enviar el período a revisión.
                </p>
              </div>

              <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="mb-3 text-[11px] font-extrabold uppercase tracking-wider text-slate-700">Acciones</h2>
                {actionItems.map((action) => (
                  <ActionLink key={action.label} action={action} />
                ))}
              </div>
            </aside>
          </section>
        </>
      )}
    </DashboardLayout>
  );
}

function TimelineCard({ title, icon, items, empty }) {
  return (
    <div className="flex h-80 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
      <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-4">
        <h2 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-700">{title}</h2>
        <span className="material-symbols-outlined text-[18px] text-slate-400">{icon}</span>
      </div>
      <div className="flex-1 overflow-y-auto p-5">
        {items.length === 0 ? (
          <p className="text-sm font-semibold text-slate-400">{empty}</p>
        ) : (
          <div className="relative space-y-5 before:absolute before:bottom-1 before:left-[7px] before:top-1 before:w-px before:bg-slate-200">
            {items.map((item) => (
              <div key={item.id} className="relative pl-7">
                <span className={`absolute left-0 top-1.5 h-3.5 w-3.5 rounded-full border-2 border-white ${item.resolved ? 'bg-emerald-500' : 'bg-red-500'}`} />
                <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{item.date?.slice(0, 10) || 'Sin fecha'}</p>
                <p className="mt-1 text-sm font-bold text-slate-900">{item.message}</p>
                <p className="text-xs text-slate-400">{item.type}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

function ObservationCard({ observations }) {
  return (
    <div className="flex h-80 flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
      <div className="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-5 py-4">
        <h2 className="text-[11px] font-extrabold uppercase tracking-wider text-slate-700">Observaciones recientes</h2>
        <span className="material-symbols-outlined text-[18px] text-slate-400">notes</span>
      </div>
      <div className="flex-1 overflow-y-auto p-5">
        {observations.length === 0 ? (
          <p className="text-sm font-semibold text-slate-400">No hay observaciones registradas.</p>
        ) : (
          <div className="relative space-y-5 before:absolute before:bottom-1 before:left-[7px] before:top-1 before:w-px before:bg-slate-200">
            {observations.map((observation) => (
              <div key={observation.id} className="relative pl-7">
                <span className="absolute left-0 top-1.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-slate-500" />
                <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{observation.date}</p>
                <p className="mt-1 text-sm font-bold text-slate-900">{observation.description}</p>
                <p className="text-xs text-slate-400">{observation.author_name || 'Docente'}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
