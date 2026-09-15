import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getAttendanceCourses, getAttendanceRecords } from '../../api/admin.api';
import DataTable from '../../components/ui/DataTable';
import { inputClass, selectClass } from '../../components/ui/FormField';
import { downloadCsv, safeFilename } from '../../utils/exportCsv';

const states = { P: 'Presente', T: 'Tardanza', A: 'Ausente', E: 'Justificada' };
const tones = { P: 'text-emerald-700 bg-emerald-50', T: 'text-sky-700 bg-sky-50', A: 'text-red-700 bg-red-50', E: 'text-amber-700 bg-amber-50' };

export default function AttendanceReportWorkspace({ yearId, yearName }) {
  const [course, setCourse] = useState(null);
  const [date, setDate] = useState('');
  const [subject, setSubject] = useState('');
  const [search, setSearch] = useState('');
  const courses = useQuery({ queryKey: ['attendance-report-courses', yearId], queryFn: () => getAttendanceCourses({ academic_year_id: yearId }), enabled: !!yearId });
  const detail = useQuery({ queryKey: ['attendance-report-records', yearId, course?.id, date], queryFn: () => getAttendanceRecords(course.id, { academic_year_id: yearId, ...(date ? { date } : {}) }), enabled: !!yearId && !!course });
  const rows = detail.data?.rows || [];
  const subjects = [...new Map(rows.map(r => [String(r.subject_id ?? 'historical'), r.subject])).entries()];
  const visible = rows.filter(r => (!subject || String(r.subject_id ?? 'historical') === subject) && `${r.last_name} ${r.name} ${r.enrollment_no}`.toLocaleLowerCase().includes(search.toLocaleLowerCase()));
  if (!course) return <section className="mb-8">
    <h2 className="mb-2 text-lg font-extrabold">Registro diario por curso</h2>
    <p className="mb-4 text-sm text-slate-500">Abre una sección para consultar la asistencia por día y materia.</p>
    {courses.isError ? <p role="alert" className="text-red-600">No se pudieron cargar los cursos. <button onClick={() => courses.refetch()}>Reintentar</button></p> : courses.isLoading ? <p>Cargando cursos…</p> : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      {courses.data?.map(c => <button key={c.id} onClick={() => { setCourse(c); setDate(''); setSubject(''); setSearch(''); }} className="rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500">
        <span className="material-symbols-outlined mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">fact_check</span>
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
    <p className="mb-4 text-sm text-slate-500">{course.shift} · {yearName}. Incluye registros históricos de estudiantes trasladados o inactivos.</p>
    <div className="mb-4 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-4">
      <label className="text-xs font-bold">Fecha<input type="date" value={date || detail.data?.date || ''} onChange={e => { setDate(e.target.value); setSubject(''); }} className={`${inputClass} mt-2`} /></label>
      <label className="text-xs font-bold">Días con registros<select value={date || detail.data?.date || ''} onChange={e => { setDate(e.target.value); setSubject(''); }} className={`${selectClass} mt-2`}><option value="">Elegir día</option>{detail.data?.dates.map(d => <option key={d} value={d}>{d}</option>)}</select></label>
      <label className="text-xs font-bold">Materia<select value={subject} onChange={e => setSubject(e.target.value)} className={`${selectClass} mt-2`}><option value="">Todas las materias</option>{subjects.map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>
      <label className="text-xs font-bold">Estudiante<input value={search} onChange={e => setSearch(e.target.value)} placeholder="Nombre o matrícula" className={`${inputClass} mt-2`} /></label>
    </div>
    {detail.isError ? <p role="alert" className="text-red-600">No se pudo cargar el registro. <button onClick={() => detail.refetch()}>Reintentar</button></p> : <>
      <div className="mb-4 flex flex-wrap items-center gap-3">{Object.entries(states).map(([code, label]) => <span key={code} className={`rounded-lg px-3 py-2 text-xs font-bold ${tones[code]}`}>{visible.filter(r => r.code === code).length} {label}</span>)}
        <button disabled={!visible.length} className="ml-auto rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold disabled:opacity-40" onClick={() => downloadCsv(`asistencia-${safeFilename(course.grade)}-${course.section}-${detail.data?.date}`, [['Año', 'Grado', 'Sección', 'Tanda', 'Fecha', 'Matrícula', 'Apellido', 'Nombre', 'Materia', 'Estado', 'Registrado por'], ...visible.map(r => [yearName, course.grade, course.section, course.shift, r.date, r.enrollment_no, r.last_name, r.name, r.subject, states[r.code] || r.code, r.teacher])])}>Exportar día CSV</button>
      </div>
      <p className="mb-3 text-xs text-slate-500">Los totales cuentan registros por materia, no estudiantes únicos. Sin registro no significa ausente.</p>
      <DataTable loading={detail.isLoading} rows={visible} emptyTitle="No hay registros para esta selección." columns={[
        { key: 'enrollment_no', label: 'Matrícula' }, { key: 'name', label: 'Estudiante', render: r => `${r.last_name}, ${r.name}` },
        { key: 'subject', label: 'Materia' }, { key: 'code', label: 'Estado', render: r => <span className={`rounded px-2 py-1 text-xs font-bold ${tones[r.code] || ''}`}>{states[r.code] || r.code}</span> }, { key: 'teacher', label: 'Registrado por' },
      ]} />
    </>}
  </section>;
}
