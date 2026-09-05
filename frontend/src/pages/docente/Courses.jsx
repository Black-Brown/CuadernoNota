import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getCourses } from '../../api/courses.api';
import { getDashboardSummary } from '../../api/dashboard.api';
import DashboardLayout from '../../components/DashboardLayout';
import SearchInput from '../../components/ui/SearchInput';
import usePeriodStore from '../../store/periodStore';
import {
  filterTeacherCourses,
  getCourseFilterOptions,
  normalizeCourseText,
} from '../../utils/teacherCourseFilters';

const SUBJECT_META = [
  {
    match: ['matematica', 'matemática', 'math'],
    icon: 'calculate',
    label: 'Matemática',
    tone: 'bg-indigo-50 text-indigo-700 border-indigo-100',
    description: 'Razonamiento lógico, resolución de problemas y análisis numérico.',
  },
  {
    match: ['lengua', 'espanola', 'española', 'literatura'],
    icon: 'menu_book',
    label: 'Lengua Española',
    tone: 'bg-rose-50 text-rose-700 border-rose-100',
    description: 'Lectura, escritura, comunicación oral y comprensión de textos.',
  },
  {
    match: ['sociales', 'social'],
    icon: 'public',
    label: 'Ciencias Sociales',
    tone: 'bg-amber-50 text-amber-700 border-amber-100',
    description: 'Historia, ciudadanía, geografía y pensamiento social.',
  },
  {
    match: ['robotica', 'robótica', 'programacion', 'programación'],
    icon: 'memory',
    label: 'Robótica y Programación',
    tone: 'bg-cyan-50 text-cyan-700 border-cyan-100',
    description: 'Diseño computacional, tecnología, lógica y proyectos aplicados.',
  },
  {
    match: ['ingles', 'inglés', 'english'],
    icon: 'translate',
    label: 'Inglés',
    tone: 'bg-violet-50 text-violet-700 border-violet-100',
    description: 'Comprensión, producción oral, vocabulario y comunicación bilingüe.',
  },
  {
    match: ['educacion fisica', 'educación física', 'fisica', 'física'],
    icon: 'sports_handball',
    label: 'Educación Física',
    tone: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    description: 'Movimiento, bienestar, disciplina deportiva y hábitos saludables.',
  },
];

function subjectMeta(subjectName = '') {
  const normalized = normalizeCourseText(subjectName);
  return SUBJECT_META.find((item) => item.match.some((term) => normalized.includes(normalizeCourseText(term)))) || {
    icon: 'school',
    label: subjectName || 'Asignatura',
    tone: 'bg-slate-50 text-slate-700 border-slate-100',
    description: 'Planificación, seguimiento académico y evaluación por competencias.',
  };
}

function statusMeta(status) {
  return status === 'active'
    ? { label: 'Activo', className: 'bg-emerald-50 text-emerald-700 border-emerald-100' }
    : { label: 'Inactivo', className: 'bg-slate-100 text-slate-600 border-slate-200' };
}

function uniqueStudentTotal(courses) {
  return [...new Map(courses.map((course) => [
    Number(course.section_id),
    Number(course.students_count || 0),
  ])).values()].reduce((total, count) => total + count, 0);
}

export default function Courses() {
  const [search, setSearch] = useState('');
  const [gradeFilter, setGradeFilter] = useState('');
  const [sectionFilter, setSectionFilter] = useState('');
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);
  const { data, isLoading } = useQuery({
    queryKey: ['courses'],
    queryFn: getCourses,
  });

  const { data: summaryData } = useQuery({
    queryKey: ['dashboardSummary', selectedPeriod?.id],
    queryFn: () => getDashboardSummary(selectedPeriod?.id),
  });

  const rawCourses = data?.courses || [];
  const activeCourses = summaryData?.active_courses ?? rawCourses.filter((course) => course.status === 'active').length;
  const totalStudents = summaryData?.total_students ?? uniqueStudentTotal(rawCourses);
  const avgGrade = summaryData?.avg_grade ?? null;
  const attendanceAvg = summaryData?.attendance_avg ?? null;
  const { grades: gradeOptions, sections: sectionOptions } = useMemo(
    () => getCourseFilterOptions(rawCourses, gradeFilter),
    [rawCourses, gradeFilter],
  );
  const visibleCourses = useMemo(
    () => filterTeacherCourses(rawCourses, {
      search,
      grade: gradeFilter,
      section: sectionFilter,
    }),
    [rawCourses, search, gradeFilter, sectionFilter],
  );
  const hasFilters = Boolean(search.trim() || gradeFilter || sectionFilter);

  function clearFilters() {
    setSearch('');
    setGradeFilter('');
    setSectionFilter('');
  }

  return (
    <DashboardLayout>
      <div className="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <nav className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            <span>Portal Docente</span>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <span className="text-indigo-600">Cursos</span>
          </nav>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Mis Cursos</h1>
          <p className="mt-1 max-w-2xl text-sm text-slate-500">
            Accede a cada workspace para gestionar actividades, notas, asistencia y seguimiento del período seleccionado.
          </p>
        </div>

        <div className="grid min-w-full grid-cols-2 gap-3 sm:min-w-[520px] sm:grid-cols-4">
          {[
            ['Cursos', activeCourses ?? 0, 'school'],
            ['Estudiantes', totalStudents ?? 0, 'groups'],
            ['Promedio', avgGrade ?? '--', 'analytics'],
            ['Asistencia', attendanceAvg != null ? `${attendanceAvg}%` : '--', 'fact_check'],
          ].map(([label, value, icon]) => (
            <div key={label} className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
              <div className="mb-2 flex items-center justify-between">
                <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p>
                <span className="material-symbols-outlined text-[17px] text-slate-400">{icon}</span>
              </div>
              <p className="text-xl font-extrabold text-slate-900">{value}</p>
            </div>
          ))}
        </div>
      </div>

      {!isLoading && rawCourses.length > 0 && (
        <section className="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Filtros de cursos">
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(280px,2fr)_minmax(180px,1fr)_minmax(180px,1fr)]">
            <div>
              <label className="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                Buscar curso
              </label>
              <SearchInput
                value={search}
                onChange={setSearch}
                placeholder="Buscar por materia, grado, sección o año..."
              />
            </div>

            <label className="block">
              <span className="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                Grado
              </span>
              <select
                value={gradeFilter}
                onChange={(event) => {
                  setGradeFilter(event.target.value);
                  setSectionFilter('');
                }}
                className="h-[38px] w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500"
              >
                <option value="">Todos los grados</option>
                {gradeOptions.map((grade) => (
                  <option key={grade} value={grade}>{grade}</option>
                ))}
              </select>
            </label>

            <label className="block">
              <span className="mb-1.5 block text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                Sección
              </span>
              <select
                value={sectionFilter}
                onChange={(event) => setSectionFilter(event.target.value)}
                className="h-[38px] w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-700 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500"
              >
                <option value="">Todas las secciones</option>
                {sectionOptions.map((section) => (
                  <option key={section} value={section}>Sección {section}</option>
                ))}
              </select>
            </label>
          </div>

          <div className="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-xs font-semibold text-slate-500" aria-live="polite">
              {visibleCourses.length} de {rawCourses.length} {rawCourses.length === 1 ? 'curso' : 'cursos'}
            </p>
            <button
              type="button"
              onClick={clearFilters}
              disabled={!hasFilters}
              className="inline-flex items-center gap-1 self-start text-xs font-bold text-indigo-600 transition-colors hover:text-indigo-800 disabled:cursor-not-allowed disabled:text-slate-300 sm:self-auto"
            >
              <span className="material-symbols-outlined text-[16px]">filter_alt_off</span>
              Limpiar filtros
            </button>
          </div>
        </section>
      )}

      {isLoading ? (
        <div className="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm">
          <span className="material-symbols-outlined text-[42px] text-indigo-500 animate-spin">progress_activity</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">Cargando cursos asignados...</p>
        </div>
      ) : rawCourses.length === 0 ? (
        <div className="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm">
          <span className="material-symbols-outlined text-[42px] text-slate-300">school_off</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">No tienes cursos asignados.</p>
        </div>
      ) : visibleCourses.length === 0 ? (
        <div className="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm">
          <span className="material-symbols-outlined text-[42px] text-slate-300">search_off</span>
          <p className="mt-3 text-sm font-semibold text-slate-700">No encontramos cursos con esos filtros.</p>
          <p className="mt-1 text-xs text-slate-500">Prueba otra búsqueda o limpia la selección actual.</p>
          <button
            type="button"
            onClick={clearFilters}
            className="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-xs font-bold text-white transition-colors hover:bg-slate-800"
          >
            Limpiar filtros
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
          {visibleCourses.map((course) => {
            const meta = subjectMeta(course.subject_name);
            const status = statusMeta(course.status);

            return (
              <Link
                key={`${course.academic_year_id}-${course.section_id}-${course.subject_id}`}
                to={`/docente/courses/${course.section_id}/${course.subject_id}`}
                className="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
              >
                <div className={`relative border-b ${meta.tone}`}>
                  <div className="flex h-32 items-center justify-between px-6">
                    <div>
                      <span className={`inline-flex rounded-md border px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider ${status.className}`}>
                        {status.label}
                      </span>
                      <p className="mt-3 text-xs font-bold uppercase tracking-wider opacity-70">{meta.label}</p>
                    </div>
                    <span className="material-symbols-outlined text-[72px] opacity-90 transition-transform group-hover:scale-105">
                      {meta.icon}
                    </span>
                  </div>
                </div>

                <div className="p-5">
                  <div className="mb-4 flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h2 className="text-base font-extrabold leading-snug text-slate-900">
                        {course.grade_name} {course.section_name} - {course.subject_name}
                      </h2>
                      <p className="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{meta.description}</p>
                    </div>
                    <span className="material-symbols-outlined shrink-0 text-[20px] text-slate-300">more_vert</span>
                  </div>

                  <div className="mb-4 grid grid-cols-3 gap-2 border-y border-slate-100 py-4 text-center">
                    <div>
                      <p className="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Estudiantes</p>
                      <p className="mt-1 text-sm font-bold text-slate-800">{course.students_count ?? 0}</p>
                    </div>
                    <div>
                      <p className="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Año</p>
                      <p className="mt-1 text-sm font-bold text-slate-800">{course.year_label || 'Activo'}</p>
                    </div>
                    <div>
                      <p className="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Estado</p>
                      <p className="mt-1 text-sm font-bold text-slate-800">{status.label}</p>
                    </div>
                  </div>

                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                      Workspace del curso
                    </span>
                    <span className="inline-flex items-center gap-1.5 rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white transition-colors group-hover:bg-slate-800">
                      Abrir
                      <span className="material-symbols-outlined text-[16px] transition-transform group-hover:translate-x-0.5">
                        arrow_forward
                      </span>
                    </span>
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </DashboardLayout>
  );
}
