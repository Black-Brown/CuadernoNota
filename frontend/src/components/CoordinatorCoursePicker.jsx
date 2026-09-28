import React, { useState } from 'react';
import FilterBar from './ui/FilterBar';
import SearchInput from './ui/SearchInput';
import EmptyState from './ui/EmptyState';
import LoadingSkeleton from './ui/LoadingSkeleton';
import { selectClass } from './ui/FormField';
import { filterCoordinatorCourses, pageRows } from '../utils/coordinatorPresentation';

export default function CoordinatorCoursePicker({ sections, subjects = [], students, loading, mode, onOpen }) {
  const [year, setYear] = useState(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const years = [...new Map(sections.map(s => [String(s.academic_year_id), s.year_name])).entries()];
  const selectedYear = year ?? String(sections.find(s => s.year_active)?.academic_year_id ?? sections[0]?.academic_year_id ?? '');
  const result = pageRows(filterCoordinatorCourses(sections, selectedYear, search), page, 12);
  const icon = { reports: 'analytics', students: 'groups', catalog: 'menu_book' }[mode];
  const action = { reports: 'Abrir reporte', students: 'Ver estudiantes', catalog: 'Ver materias' }[mode];
  return <>
    <FilterBar>
      <SearchInput value={search} onChange={value => { setSearch(value); setPage(1); }} placeholder="Buscar grado, sección o tanda…" />
      <select aria-label="Año escolar de los cursos" className={`${selectClass} sm:max-w-56`} value={selectedYear} onChange={e => { setYear(e.target.value); setPage(1); }}>
        <option value="">Todos los años</option>{years.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
      </select>
    </FilterBar>
    {loading ? <LoadingSkeleton /> : !result.total ? <EmptyState icon="school" title="No hay cursos para esta selección" description="Solo se muestran las secciones de tu ámbito de supervisión." /> : <>
      <p className="mb-4 text-xs text-slate-500">{result.total} cursos en tu ámbito · Selecciona un workspace</p>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        {result.rows.map(s => <button type="button" key={s.id} onClick={() => onOpen(s)} className="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-indigo-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
          <div className="mb-4 flex w-full items-center justify-between gap-3">
            <span aria-hidden="true" className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><span className="material-symbols-outlined block text-[24px] leading-none">{icon}</span></span>
            <span className="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{s.grade_level || 'Curso'}</span>
          </div>
          <h2 className="text-base font-extrabold text-slate-950">{s.grade_name} · Sección {s.name}</h2>
          <p className="mt-2 text-xs text-slate-500">{s.shift} · {s.year_name}</p>
          <p className="my-4 text-sm text-slate-600">{mode === 'students' ? `${(students || []).filter(student => Number(student.section_id) === Number(s.id)).length} estudiantes` : `${subjects.filter(subject => Number(subject.section_id) === Number(s.id) && subject.active).length} materias activas`}</p>
          <span className="mt-auto inline-flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white">{action}<span aria-hidden="true" className="material-symbols-outlined block text-[18px] leading-none">arrow_forward</span></span>
        </button>)}
      </div>
      {result.totalPages > 1 && <nav aria-label="Paginación de cursos" className="mt-4 flex items-center justify-between gap-3 text-xs font-semibold text-slate-500"><span>Página {result.page} de {result.totalPages}</span><div className="flex gap-2"><button disabled={result.page === 1} onClick={() => setPage(result.page - 1)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 disabled:opacity-40">Anterior</button><button disabled={result.page === result.totalPages} onClick={() => setPage(result.page + 1)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 disabled:opacity-40">Siguiente</button></div></nav>}
    </>}
  </>;
}
