import React, { useMemo } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getCourses } from '../../api/courses.api';
import { getGradebookSummary } from '../../api/grades.api';
import DashboardLayout from '../../components/DashboardLayout';
import { downloadCsv, safeFilename } from '../../utils/exportCsv';

const statusLabels = {
  pending: 'Pendiente',
  draft: 'Borrador',
  in_review: 'En revisión',
  official: 'Oficial',
};

const statusStyles = {
  pending: 'bg-slate-100 text-slate-600 border-slate-200',
  draft: 'bg-amber-50 text-amber-700 border-amber-100',
  in_review: 'bg-sky-50 text-sky-700 border-sky-100',
  official: 'bg-emerald-50 text-emerald-700 border-emerald-100',
};

function formatScore(value) {
  if (value === null || value === undefined || value === '') return '-';
  const number = Number(value);
  if (Number.isNaN(number)) return '-';
  return Number.isInteger(number) ? String(number) : number.toFixed(1);
}

const competencies = [
  {
    key: 'c1_score',
    shortLabel: 'C1',
    label: 'Comunicativa',
    icon: 'forum',
    iconTone: 'bg-sky-50 text-sky-700 border-sky-100',
  },
  {
    key: 'c2_score',
    shortLabel: 'C2',
    label: 'Pensamiento Logico, Creativo y Critico',
    secondary: 'Resolucion de Problemas',
    icon: 'psychology',
    iconTone: 'bg-violet-50 text-violet-700 border-violet-100',
  },
  {
    key: 'c3_score',
    shortLabel: 'C3',
    label: 'Cientifica y Tecnologica',
    secondary: 'Ambiental y de la Salud',
    icon: 'science',
    iconTone: 'bg-emerald-50 text-emerald-700 border-emerald-100',
  },
];

function CourseSelection({ courses, isLoading }) {
  const totalCourses = courses.length;
  const uniqueSections = new Set(courses.map((course) => course.section_id)).size;
  const uniqueSubjects = new Set(courses.map((course) => course.subject_id)).size;

  return (
    <>
      <div className="mb-8 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
          <nav className="flex items-center gap-1.5 text-slate-400 text-[10px] font-bold tracking-wider uppercase mb-2">
            <span>Portal Docente</span>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <span className="text-indigo-600 font-extrabold">Calificaciones</span>
          </nav>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Calificaciones</h1>
          <p className="text-sm text-slate-500 mt-1 max-w-2xl">
            Selecciona un curso para consultar las notas calculadas por período, recuperaciones y calificación final.
          </p>
        </div>

        <div className="grid grid-cols-3 gap-3 min-w-full sm:min-w-[420px]">
          <div className="bg-white border border-slate-200 rounded-xl px-4 py-3 shadow-sm">
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Cursos</p>
            <p className="text-2xl font-extrabold text-slate-800">{totalCourses}</p>
          </div>
          <div className="bg-white border border-slate-200 rounded-xl px-4 py-3 shadow-sm">
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Secciones</p>
            <p className="text-2xl font-extrabold text-slate-800">{uniqueSections}</p>
          </div>
          <div className="bg-white border border-slate-200 rounded-xl px-4 py-3 shadow-sm">
            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Materias</p>
            <p className="text-2xl font-extrabold text-slate-800">{uniqueSubjects}</p>
          </div>
        </div>
      </div>

      {isLoading ? (
        <div className="bg-white border border-slate-200 rounded-xl p-12 text-center shadow-sm">
          <span className="material-symbols-outlined text-[42px] text-indigo-500 animate-spin">progress_activity</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">Cargando cursos...</p>
        </div>
      ) : courses.length === 0 ? (
        <div className="bg-white border border-slate-200 rounded-xl p-12 text-center shadow-sm">
          <span className="material-symbols-outlined text-[42px] text-slate-300">school_off</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">No tienes cursos asignados para calificaciones.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
          {courses.map((course) => (
            <Link
              key={`${course.section_id}-${course.subject_id}`}
              to={`/docente/grades/${course.section_id}/${course.subject_id}`}
              className="group bg-white border border-slate-200 rounded-xl shadow-sm hover:border-slate-800 hover:shadow-md transition-all overflow-hidden"
            >
              <div className="p-5">
                <div className="flex items-start justify-between gap-4 mb-5">
                  <div className="min-w-0">
                    <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                      {course.year_label || 'Año escolar'}
                    </p>
                    <h3 className="text-lg font-extrabold text-slate-900 leading-snug group-hover:text-indigo-600 transition-colors">
                      {course.grade_name} {course.section_name}
                    </h3>
                    <p className="text-sm font-semibold text-slate-500 mt-1 truncate">{course.subject_name}</p>
                  </div>
                  <div className="w-11 h-11 rounded-xl bg-slate-950 text-white flex items-center justify-center shrink-0">
                    <span className="material-symbols-outlined text-[24px]">grading</span>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-2 border-t border-slate-100 pt-4">
                  <div>
                    <p className="text-[9px] font-bold uppercase tracking-wider text-slate-400">Track</p>
                    <p className="text-xs font-bold text-slate-700">Boletín</p>
                  </div>
                  <div>
                    <p className="text-[9px] font-bold uppercase tracking-wider text-slate-400">Escala</p>
                    <p className="text-xs font-bold text-slate-700">0-100</p>
                  </div>
                  <div>
                    <p className="text-[9px] font-bold uppercase tracking-wider text-slate-400">Mínima</p>
                    <p className="text-xs font-bold text-slate-700">70</p>
                  </div>
                </div>
              </div>

              <div className="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <span className="text-xs font-bold text-slate-500">Ver calificaciones</span>
                <span className="material-symbols-outlined text-[18px] text-slate-400 group-hover:text-slate-800 group-hover:translate-x-1 transition-all">
                  arrow_forward
                </span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}

function ReportCardCell({ value, emphasis = false }) {
  const hasScore = value !== null && value !== undefined && value !== '';
  const isLow = hasScore && Number(value) < 70;

  return (
    <td className={`border-b border-r border-slate-100 px-1 py-2 text-center align-middle font-mono text-[12px] leading-none ${
      isLow
        ? 'bg-rose-50 text-rose-700 font-extrabold'
        : hasScore
          ? emphasis
            ? 'bg-slate-900 text-white font-extrabold'
            : 'bg-white text-slate-800 font-bold'
          : emphasis
            ? 'bg-slate-50 text-slate-300'
            : 'bg-slate-50/60 text-slate-300'
    }`}>
      {formatScore(value) === '-' ? '' : formatScore(value)}
    </td>
  );
}

function competencyAverage(periods, scoreKey) {
  const values = periods
    .map((period) => period?.[scoreKey])
    .filter((value) => value !== null && value !== undefined && value !== '')
    .map(Number)
    .filter((value) => !Number.isNaN(value));

  return values.length === 4
    ? Math.round(values.reduce((total, value) => total + value, 0) / values.length)
    : null;
}

function GradebookView({ course }) {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['gradebookSummary', course.section_id, course.subject_id, course.academic_year_id],
    queryFn: () => getGradebookSummary(course.section_id, course.subject_id, course.academic_year_id),
    enabled: !!course,
  });

  const periods = data?.periods || [];
  const students = data?.students || [];
  const summary = data?.summary || {};
  const courseData = data?.course || course;
  const handleExport = () => {
    if (!courseData || students.length === 0) return;

    const headers = [
      'Estudiante',
      'Matricula',
      ...competencies.flatMap((competency) => (
        [1, 2, 3, 4].flatMap((number) => [
          `${competency.shortLabel} P${number}`,
          `${competency.shortLabel} RP${number}`,
        ])
      )),
      'Final C1',
      'Final C2',
      'Final C3',
      'Estado',
    ];

    const rows = [
      [`Calificaciones - ${courseData.grade_name} ${courseData.section_name} - ${courseData.subject_name}`],
      ['Periodo', courseData.year_label || 'Año escolar activo'],
      [],
      headers,
      ...students.map((student) => {
        const periodsByNumber = new Map(student.periods.map((period) => [period.period_number, period]));
        const orderedPeriods = [1, 2, 3, 4].map((number) => periodsByNumber.get(number) || {});
        const finalCompetencies = competencies.map((competency) => competencyAverage(orderedPeriods, competency.key));

        return [
          student.student_name,
          student.enrollment_no || `ID ${student.student_id}`,
          ...competencies.flatMap((competency) => (
            orderedPeriods.flatMap((period) => [
              formatScore(period[competency.key]),
              formatScore(period.rp_score),
            ])
          )),
          ...finalCompetencies.map(formatScore),
          statusLabels[student.status] || student.status,
        ];
      }),
    ];

    downloadCsv(
      `calificaciones-${safeFilename(`${courseData.grade_name}-${courseData.section_name}-${courseData.subject_name}`)}.csv`,
      rows
    );
  };

  return (
    <>
      <div className="mb-6">
        <nav className="flex items-center gap-1.5 text-slate-400 text-[10px] font-bold tracking-wider uppercase mb-2">
          <Link to="/docente/grades" className="hover:text-slate-700 transition-colors">Calificaciones</Link>
          <span className="material-symbols-outlined text-[12px]">chevron_right</span>
          <span className="text-indigo-600 font-extrabold">
            {courseData.grade_name} {courseData.section_name}
          </span>
        </nav>

        <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-slate-900 tracking-tight">{courseData.subject_name}</h1>
            <p className="text-sm text-slate-500 mt-1">
              {courseData.grade_name} {courseData.section_name} · {courseData.year_label || 'Año escolar activo'}
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Link
              to="/docente/grades"
              className="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-colors"
            >
              <span className="material-symbols-outlined text-[18px]">arrow_back</span>
              Cursos
            </Link>
            <button
              type="button"
              onClick={handleExport}
              disabled={students.length === 0 || isLoading}
              className="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-sm transition-colors"
            >
              <span className="material-symbols-outlined text-[18px]">ios_share</span>
              Exportar
            </button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Estudiantes</p>
          <p className="text-3xl font-extrabold text-slate-800 mt-1">{summary.total_students ?? 0}</p>
        </div>
        <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Promedio</p>
          <p className="text-3xl font-extrabold text-slate-800 mt-1">{formatScore(summary.class_average)}</p>
        </div>
        <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">En riesgo</p>
          <p className="text-3xl font-extrabold text-red-600 mt-1">{summary.at_risk ?? 0}</p>
        </div>
        <div className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
          <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pendientes</p>
          <p className="text-3xl font-extrabold text-amber-600 mt-1">{summary.pending ?? 0}</p>
        </div>
      </div>

      <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        <div className="px-5 py-4 border-b border-slate-100 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="material-symbols-outlined text-[20px] text-indigo-500">view_week</span>
              <h2 className="text-base font-extrabold text-slate-800">Resumen anual de notas</h2>
            </div>
            <p className="text-xs text-slate-400 mt-1">P = período, RP = recuperación pedagógica, C1/C2/C3 = calificación final por competencia.</p>
          </div>
          <div className="flex flex-wrap gap-2">
            {competencies.map((competency) => (
              <span
                key={competency.key}
                className={`inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider ${competency.iconTone}`}
              >
                <span className="material-symbols-outlined text-[14px]">{competency.icon}</span>
                {competency.shortLabel}
              </span>
            ))}
          </div>
          {isLoading && (
            <span className="flex items-center gap-2 text-[10px] font-bold text-indigo-600 uppercase tracking-wider">
              <span className="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
              Cargando
            </span>
          )}
        </div>

        {isError ? (
          <div className="p-12 text-center text-red-600">
            <span className="material-symbols-outlined text-[42px]">error</span>
            <p className="text-sm font-bold mt-2">{error?.response?.data?.message || 'No se pudieron cargar las calificaciones.'}</p>
          </div>
        ) : isLoading ? (
          <div className="p-12 text-center text-slate-400">
            <span className="material-symbols-outlined text-[42px] animate-spin">progress_activity</span>
            <p className="text-sm font-semibold mt-2">Preparando tabla de calificaciones...</p>
          </div>
        ) : (
          <div className="bg-slate-50/70 p-3">
            <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="w-full min-w-[1320px] table-fixed border-separate border-spacing-0 text-left">
              <colgroup>
                <col className="w-64" />
                {competencies.map((competency) => (
                  <React.Fragment key={competency.key}>
                    {[1, 2, 3, 4].map((number) => (
                      <React.Fragment key={`${competency.key}-${number}`}>
                        <col className="w-10" />
                        <col className="w-10" />
                      </React.Fragment>
                    ))}
                  </React.Fragment>
                ))}
                <col className="w-12" />
                <col className="w-12" />
                <col className="w-12" />
                <col className="w-28" />
              </colgroup>
              <thead>
                <tr className="text-[11px] font-bold text-slate-900">
                  <th rowSpan="2" className="sticky left-0 z-30 border-b border-r border-slate-200 bg-white px-3 py-3 align-middle shadow-[8px_0_14px_-18px_rgba(15,23,42,0.6)]">
                    <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Estudiante</span>
                  </th>
                  {competencies.map((competency) => (
                    <th key={competency.key} colSpan="8" className="border-b border-r border-slate-200 bg-white px-3 py-3 align-top">
                      <div className="flex items-start gap-2">
                        <span className={`mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border ${competency.iconTone}`}>
                          <span className="material-symbols-outlined text-[18px]">{competency.icon}</span>
                        </span>
                        <div className="min-w-0">
                          <div className="flex items-center gap-1.5">
                            <span className="text-[10px] font-extrabold text-slate-400">{competency.shortLabel}</span>
                            <p className="leading-tight text-slate-900">{competency.label}</p>
                          </div>
                          {competency.secondary && <p className="mt-0.5 leading-tight text-slate-500">{competency.secondary}</p>}
                        </div>
                      </div>
                    </th>
                  ))}
                  <th colSpan="3" className="border-b border-r border-slate-200 bg-slate-100 px-3 py-3 text-center text-[10px] leading-tight text-slate-700">
                    Calificacion final por competencia
                  </th>
                  <th rowSpan="2" className="border-b border-slate-200 bg-white px-2 py-3 text-center">
                    Estado
                  </th>
                </tr>
                <tr className="bg-slate-50 text-[12px] font-extrabold text-slate-600">
                  {competencies.map((competency) => (
                    <React.Fragment key={`${competency.key}-periods`}>
                      {[1, 2, 3, 4].map((number) => (
                        <React.Fragment key={`${competency.key}-period-${number}`}>
                          <th className="border-b border-r border-slate-200 px-1 py-2 text-center">P{number}</th>
                          <th className="border-b border-r border-slate-200 bg-white px-1 py-2 text-center text-slate-400">RP</th>
                        </React.Fragment>
                      ))}
                    </React.Fragment>
                  ))}
                  <th className="border-b border-r border-slate-200 bg-slate-100 px-1 py-2 text-center text-slate-700">C1</th>
                  <th className="border-b border-r border-slate-200 bg-slate-100 px-1 py-2 text-center text-slate-700">C2</th>
                  <th className="border-b border-r border-slate-200 bg-slate-100 px-1 py-2 text-center text-slate-700">C3</th>
                </tr>
              </thead>
              <tbody className="text-sm">
                {students.length === 0 ? (
                  <tr>
                    <td colSpan="29" className="py-16 text-center text-slate-400">
                      <span className="material-symbols-outlined text-[40px]">table_rows_narrow</span>
                      <p className="text-sm font-semibold mt-2">No hay calificaciones calculadas para este curso.</p>
                    </td>
                  </tr>
                ) : (
                  students.map((student) => {
                    const periodsByNumber = new Map(student.periods.map((period) => [period.period_number, period]));
                    const orderedPeriods = [1, 2, 3, 4].map((number) => periodsByNumber.get(number) || {});
                    const finalCompetencies = competencies.map((competency) => competencyAverage(orderedPeriods, competency.key));

                    return (
                      <tr key={student.student_id} className="group hover:bg-slate-50">
                        <td className="sticky left-0 z-20 border-b border-r border-slate-100 bg-white px-3 py-2 shadow-[8px_0_14px_-18px_rgba(15,23,42,0.6)] group-hover:bg-slate-50">
                          <div className="min-w-0">
                            <p className="truncate text-xs font-bold text-slate-900">{student.student_name}</p>
                            <p className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                              {student.enrollment_no || `ID ${student.student_id}`}
                            </p>
                          </div>
                        </td>
                        {competencies.map((competency) => (
                          <React.Fragment key={`${student.student_id}-${competency.key}`}>
                            {orderedPeriods.map((period, index) => (
                              <React.Fragment key={`${competency.key}-${index + 1}`}>
                                <ReportCardCell value={period[competency.key]} />
                                <ReportCardCell value={period.rp_score} />
                              </React.Fragment>
                            ))}
                          </React.Fragment>
                        ))}
                        {finalCompetencies.map((score, index) => (
                          <ReportCardCell key={`final-${index + 1}`} value={score} emphasis />
                        ))}
                        <td className="border-b border-slate-100 bg-white px-2 py-2 text-center group-hover:bg-slate-50">
                          <span className={`inline-flex items-center justify-center rounded border px-2 py-1 text-[9px] font-extrabold uppercase tracking-wider ${
                            statusStyles[student.status] || statusStyles.pending
                          }`}>
                            {statusLabels[student.status] || student.status}
                          </span>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

export default function Grades() {
  const { sectionId, subjectId } = useParams();

  const { data, isLoading } = useQuery({
    queryKey: ['courses'],
    queryFn: getCourses,
    staleTime: 5 * 60_000,
  });

  const courses = data?.courses || [];
  const selectedCourse = useMemo(() => {
    if (!sectionId || !subjectId) return null;
    return courses.find(
      (course) =>
        String(course.section_id) === String(sectionId) &&
        String(course.subject_id) === String(subjectId)
    );
  }, [courses, sectionId, subjectId]);

  return (
    <DashboardLayout>
      {sectionId && subjectId && selectedCourse ? (
        <GradebookView course={selectedCourse} />
      ) : sectionId && subjectId && isLoading ? (
        <div className="bg-white border border-slate-200 rounded-xl p-12 text-center shadow-sm">
          <span className="material-symbols-outlined text-[42px] text-indigo-500 animate-spin">progress_activity</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">Validando curso...</p>
        </div>
      ) : (
        <CourseSelection courses={courses} isLoading={isLoading} />
      )}
    </DashboardLayout>
  );
}
