import React, { useMemo } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getCourses } from '../../api/courses.api';
import { getGradebookSummary } from '../../api/grades.api';
import DashboardLayout from '../../components/DashboardLayout';

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

function scoreClass(value, emphasis = false) {
  if (value === null || value === undefined || value === '') {
    return 'text-slate-300 bg-transparent';
  }

  const base = emphasis ? 'font-extrabold' : 'font-semibold';
  return Number(value) < 70
    ? `${base} text-red-700 bg-red-50`
    : `${base} text-slate-800 bg-white`;
}

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

function GradeCell({ value, emphasis = false }) {
  return (
    <td className="p-2 text-center">
      <div className={`mx-auto w-14 h-9 rounded-lg border border-slate-100 flex items-center justify-center font-mono text-sm ${scoreClass(value, emphasis)}`}>
        {formatScore(value)}
      </div>
    </td>
  );
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
        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
          <div>
            <h2 className="text-base font-extrabold text-slate-800">Resumen anual de notas</h2>
            <p className="text-xs text-slate-400">P = período, RP = recuperación pedagógica, PC = promedio final calculado.</p>
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
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1060px] text-left border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  <th className="sticky left-0 z-20 bg-slate-50 py-4 px-5 min-w-64">Estudiante</th>
                  {[1, 2, 3, 4].map((number) => (
                    <React.Fragment key={number}>
                      <th className="py-4 px-2 text-center">P{number}</th>
                      <th className="py-4 px-2 text-center">RP{number}</th>
                    </React.Fragment>
                  ))}
                  <th className="py-4 px-2 text-center bg-slate-100">PC</th>
                  <th className="py-4 px-5 text-center">Estado</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-sm">
                {students.length === 0 ? (
                  <tr>
                    <td colSpan="11" className="py-16 text-center text-slate-400">
                      <span className="material-symbols-outlined text-[40px]">table_rows_narrow</span>
                      <p className="text-sm font-semibold mt-2">No hay calificaciones calculadas para este curso.</p>
                    </td>
                  </tr>
                ) : (
                  students.map((student) => {
                    const periodsByNumber = new Map(student.periods.map((period) => [period.period_number, period]));

                    return (
                      <tr key={student.student_id} className="hover:bg-slate-50/70 transition-colors">
                        <td className="sticky left-0 z-10 bg-white group-hover:bg-slate-50 py-3 px-5">
                          <div className="flex items-center gap-3">
                            <div className={`w-9 h-9 rounded-full flex items-center justify-center text-xs font-extrabold ${
                              student.at_risk ? 'bg-red-50 text-red-700' : 'bg-indigo-50 text-indigo-700'
                            }`}>
                              {student.student_name.slice(0, 2).toUpperCase()}
                            </div>
                            <div className="min-w-0">
                              <p className="font-bold text-slate-800 truncate">{student.student_name}</p>
                              <p className="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                {student.enrollment_no || `ID ${student.student_id}`}
                              </p>
                            </div>
                          </div>
                        </td>
                        {[1, 2, 3, 4].map((number) => {
                          const period = periodsByNumber.get(number) || {};
                          return (
                            <React.Fragment key={number}>
                              <GradeCell value={period.effective_score} />
                              <GradeCell value={period.rp_score} />
                            </React.Fragment>
                          );
                        })}
                        <GradeCell value={student.pc} emphasis />
                        <td className="py-3 px-5 text-center">
                          <span className={`inline-flex items-center justify-center px-2.5 py-1 rounded-lg border text-[10px] font-extrabold uppercase tracking-wider ${
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
