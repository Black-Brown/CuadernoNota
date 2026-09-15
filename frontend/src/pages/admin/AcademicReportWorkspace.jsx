import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getAcademicCourses, getAcademicRecords } from '../../api/admin.api';
import DataTable from '../../components/ui/DataTable';
import KpiCard from '../../components/ui/KpiCard';
import { inputClass, selectClass } from '../../components/ui/FormField';
import { downloadCsv, safeFilename } from '../../utils/exportCsv';

const score = value => value == null ? '—' : Number(value).toLocaleString('es-DO', { maximumFractionDigits: 2 });

export default function AcademicReportWorkspace({ yearId, yearName }) {
  const [course, setCourse] = useState(null);
  const [period, setPeriod] = useState('');
  const [subject, setSubject] = useState('');
  const [search, setSearch] = useState('');
  const courses = useQuery({ queryKey: ['academic-report-courses', yearId], queryFn: () => getAcademicCourses({ academic_year_id: yearId }), enabled: !!yearId });
  const detail = useQuery({ queryKey: ['academic-report-records', yearId, course?.id], queryFn: () => getAcademicRecords(course.id, { academic_year_id: yearId }), enabled: !!yearId && !!course });
  const rows = detail.data?.rows || [];
  const subjects = [...new Map(rows.map(r => [String(r.subject_id), r.subject])).entries()];
  const visible = rows.filter(r => (!period || String(r.period_id) === period) && (!subject || String(r.subject_id) === subject) && `${r.last_name} ${r.name} ${r.enrollment_no}`.toLocaleLowerCase().includes(search.toLocaleLowerCase()));
  const evaluated = visible.filter(r => r.effective_score != null);
  const average = evaluated.length ? score(evaluated.reduce((sum, r) => sum + Number(r.effective_score), 0) / evaluated.length) : '—';

  if (!course) return <section className="mb-8">
    <h2 className="mb-2 text-lg font-extrabold">Registro académico por curso</h2>
    <p className="mb-4 text-sm text-slate-500">Abre una sección para consultar las calificaciones oficiales por estudiante, materia y período.</p>
    {courses.isError ? <p role="alert" className="text-red-600">No se pudieron cargar los cursos. <button onClick={() => courses.refetch()}>Reintentar</button></p> : courses.isLoading ? <p>Cargando cursos…</p> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      {courses.data?.map(c => <button key={c.id} onClick={() => { setCourse(c); setPeriod(''); setSubject(''); setSearch(''); }} className="rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500">
        <span className="material-symbols-outlined mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">school</span>
        <h3 className="font-extrabold">{c.grade} · Sección {c.section}</h3>
        <p className="mt-2 text-sm text-slate-500">{c.shift} · {yearName}</p>
        <span className="mt-5 inline-flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2 text-xs font-bold text-white">Abrir registro <span className="material-symbols-outlined text-base">arrow_forward</span></span>
      </button>)}
      {!courses.data?.length && <p className="text-slate-500">No hay secciones en este año escolar.</p>}
    </div>}
  </section>;

  return <section className="mb-8">
    <button onClick={() => setCourse(null)} className="mb-4 text-sm font-bold text-indigo-600">← Volver a los cursos</button>
    <h2 className="text-xl font-extrabold">{course.grade} · Sección {course.section}</h2>
    <p className="mb-4 text-sm text-slate-500">{course.shift} · {yearName}. Solo calificaciones oficiales; incluye registros de estudiantes trasladados o inactivos.</p>
    <div className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-3">
      <label className="text-xs font-bold">Período<select value={period} onChange={e => setPeriod(e.target.value)} className={`${selectClass} mt-2`}><option value="">Todos los períodos</option>{detail.data?.periods.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select></label>
      <label className="text-xs font-bold">Materia<select value={subject} onChange={e => setSubject(e.target.value)} className={`${selectClass} mt-2`}><option value="">Todas las materias</option>{subjects.map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>
      <label className="text-xs font-bold">Estudiante<input value={search} onChange={e => setSearch(e.target.value)} placeholder="Nombre o matrícula" className={`${inputClass} mt-2`} /></label>
    </div>
    {detail.isError ? <p role="alert" className="text-red-600">No se pudo cargar el registro. <button onClick={() => detail.refetch()}>Reintentar</button></p> : <>
      <div className="mb-4 grid gap-4 md:grid-cols-3">
        <KpiCard label="Estudiantes evaluados" value={new Set(evaluated.map(r => r.student_id)).size} icon="groups" loading={detail.isLoading} />
        <KpiCard label="Promedio de notas" value={average} icon="analytics" loading={detail.isLoading} />
        <KpiCard label="Estudiantes en riesgo" value={new Set(evaluated.filter(r => Number(r.effective_score) < 70).map(r => r.student_id)).size} icon="warning" loading={detail.isLoading} />
      </div>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <p className="text-xs text-slate-500">Cada fila corresponde a una materia y período. La nota efectiva usa RP cuando existe; sin nota no significa cero.</p>
        <button disabled={!visible.length} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold disabled:opacity-40" onClick={() => downloadCsv(`academico-${safeFilename(course.grade)}-${course.section}-${safeFilename(yearName)}`, [['Año', 'Grado', 'Sección', 'Tanda', 'Matrícula', 'Apellido', 'Nombre', 'Materia', 'Período', 'C1', 'C2', 'C3', 'Nota período', 'RP', 'Nota efectiva'], ...visible.map(r => [yearName, course.grade, course.section, course.shift, r.enrollment_no, r.last_name, r.name, r.subject, r.period, r.c1_score, r.c2_score, r.c3_score, r.period_score, r.rp_score, r.effective_score])])}>Exportar selección CSV</button>
      </div>
      <DataTable loading={detail.isLoading} rows={visible} emptyTitle="No hay calificaciones oficiales para esta selección." columns={[
        { key: 'enrollment_no', label: 'Matrícula' }, { key: 'name', label: 'Estudiante', render: r => `${r.last_name}, ${r.name}` },
        { key: 'subject', label: 'Materia' }, { key: 'period', label: 'Período' },
        ...['c1_score', 'c2_score', 'c3_score', 'period_score', 'rp_score'].map((key, index) => ({ key, label: ['C1', 'C2', 'C3', 'Nota período', 'RP'][index], align: 'center', render: r => score(r[key]) })),
        { key: 'effective_score', label: 'Nota efectiva', align: 'center', render: r => <span className={r.effective_score != null && Number(r.effective_score) < 70 ? 'font-bold text-red-600' : 'font-bold'}>{score(r.effective_score)}</span> },
      ]} />
    </>}
  </section>;
}
