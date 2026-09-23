import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/client';
import { getCoordinatorCatalog, getCoordinatorStudents, getCoordinatorTeachers, getCoordinatorReports } from '../../api/coordinator.api';
import PageHeader from '../../components/ui/PageHeader';
import DataTable from '../../components/ui/DataTable';
import FilterBar from '../../components/ui/FilterBar';
import SearchInput from '../../components/ui/SearchInput';
import SideDrawer from '../../components/ui/SideDrawer';
import { inputClass, selectClass } from '../../components/ui/FormField';
import { downloadCsv } from '../../utils/exportCsv';

const titles = { students: 'Estudiantes', catalog: 'Catálogo académico', institutional: 'Configuración institucional', assignments: 'Asignaciones docentes', reports: 'Reportes', promotions: 'Promoción escolar', 'student-placements': 'Asignación estudiantes' };
const courseLabel = c => `${c.grade_name} · Sección ${c.name} · ${c.shift} · ${c.year_name}`;
const basic = [{ key: 'grade_name', label: 'Grado' }, { key: 'section_name', label: 'Sección' }, { key: 'shift', label: 'Tanda' }, { key: 'year_name', label: 'Año escolar' }];

export default function Management({ mode }) {
  const [search, setSearch] = useState('');
  const [sectionId, setSectionId] = useState('');
  const [selected, setSelected] = useState(null);
  const [tab, setTab] = useState('academic');
  const [period, setPeriod] = useState('');
  const [date, setDate] = useState('');
  const catalog = useQuery({ queryKey: ['coordinator-catalog'], queryFn: getCoordinatorCatalog });
  const listing = useQuery({ queryKey: ['coordinator-management', mode], queryFn: mode === 'students' ? getCoordinatorStudents : getCoordinatorTeachers, enabled: ['students', 'assignments'].includes(mode) });
  const report = useQuery({ queryKey: ['coordinator-reports', sectionId, period, date], queryFn: () => getCoordinatorReports(sectionId, { period_id: period || undefined, date: date || undefined }), enabled: mode === 'reports' && !!sectionId });
  const extra = useQuery({ queryKey: ['coordinator-management-extra', mode, sectionId], queryFn: () => api.get(mode === 'promotions' ? `/coordinador/promotions/${sectionId}` : '/coordinador/student-placements').then(r => r.data), enabled: mode === 'student-placements' || (mode === 'promotions' && !!sectionId) });
  const sections = catalog.data?.sections || [];
  const error = catalog.isError || listing.isError || report.isError || extra.isError;
  const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  let rows = [];
  let columns = [];
  if (mode === 'promotions') {
    rows = extra.data || [];
    columns = [{ key: 'student_name', label: 'Estudiante' }, { key: 'enrollment_no', label: 'Matrícula' }, { key: 'subject_count', label: 'Materias evaluadas' }, { key: 'missing_subject_count', label: 'Materias pendientes' }, { key: 'eligible', label: 'Elegibilidad', render: r => r.eligible ? 'Elegible' : 'No elegible' }, { key: 'promotion_block_reason', label: 'Estado del proceso' }];
  } else if (mode === 'student-placements') {
    rows = extra.data || [];
    columns = [{ key: 'name', label: 'Estudiante', render: r => `${r.last_name}, ${r.name}` }, { key: 'enrollment_no', label: 'Matrícula' }, { key: 'grade_name', label: 'Grado de procedencia' }, { key: 'section_name', label: 'Sección de procedencia' }];
  }
  if (mode === 'students') {
    rows = (listing.data || []).filter(r => !sectionId || String(r.section_id) === sectionId);
    columns = [{ key: 'enrollment_no', label: 'Matrícula' }, { key: 'name', label: 'Estudiante', render: r => `${r.last_name}, ${r.name}` }, ...basic,
      { key: 'active', label: 'Estado', render: r => r.active ? 'Activo' : 'Inactivo' },
      { key: 'actions', label: 'Acciones', render: r => <div className="flex gap-3"><button onClick={() => setSelected(r)} className="font-bold text-indigo-600">Editar</button>{r.active && <Link to={`/coordinador/sections/${r.section_id}`} className="font-bold text-indigo-600">Seguimiento</Link>}</div> }];
  } else if (mode === 'assignments') {
    rows = listing.data || [];
    columns = [{ key: 'teacher_name', label: 'Docente' }, { key: 'subject_name', label: 'Materia' }, ...basic, { key: 'active', label: 'Estado', render: r => r.active ? 'Activa' : 'Inactiva' }];
  } else if (mode === 'catalog') {
    rows = sections.map(s => ({ ...s, section_name: s.name, subjects: (catalog.data?.subjects || []).filter(c => c.section_id === s.id).map(c => c.name).join(', ') }));
    columns = [...basic, { key: 'subjects', label: 'Materias' }];
  } else if (mode === 'institutional') {
    rows = (catalog.data?.periods || []).map(p => ({ ...p, year: sections.find(s => s.academic_year_id === p.academic_year_id)?.year_name }));
    columns = [{ key: 'year', label: 'Año escolar' }, { key: 'name', label: 'Período' }, { key: 'start_date', label: 'Inicio' }, { key: 'end_date', label: 'Fin' }, { key: 'status', label: 'Estado', render: r => ({ open: 'Abierto', closed: 'Cerrado' }[r.status] || r.status) }];
  } else if (mode === 'reports' && sectionId) {
    rows = tab === 'academic' ? report.data?.grades || [] : report.data?.attendance || [];
    columns = [{ key: 'enrollment_no', label: 'Matrícula' }, { key: 'name', label: 'Estudiante', render: r => `${r.last_name}, ${r.name}` }, { key: 'subject', label: 'Materia', render: r => r.subject || 'Sin materia (histórico)' },
      ...(tab === 'academic' ? [{ key: 'period', label: 'Período' }, { key: 'period_score', label: 'Nota período', render: r => r.period_score ?? '—' }, { key: 'rp_score', label: 'RP', render: r => r.rp_score ?? '—' }]
        : [{ key: 'date', label: 'Fecha' }, { key: 'code', label: 'Estado', render: r => ({ P: 'Presente', T: 'Tardanza', A: 'Ausente', E: 'Justificada' }[r.code] || r.code) }])];
  }
  rows = rows.filter(r => normalize(Object.values(r).join(' ')).includes(normalize(search)));
  return <>
    <PageHeader breadcrumb={['Portal de Coordinación', titles[mode]]} title={titles[mode]} description="Únicamente información de tus grados y secciones asignados." />
    {['promotions', 'student-placements'].includes(mode) && <p className="mb-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Consulta de seguimiento. Las decisiones de promoción y la colocación siguen en el administrador en esta primera etapa; no se muestran estudiantes sin procedencia asignada.</p>}
    {['catalog', 'institutional', 'assignments'].includes(mode) && <p className="mb-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500">Consulta de tu ámbito. Los cambios globales y la administración de asignaciones permanecen a cargo del administrador.</p>}
    {error ? <p role="alert" className="text-red-600">No se pudieron cargar los datos. <button onClick={() => { catalog.refetch(); if (['students', 'assignments'].includes(mode)) listing.refetch(); if (sectionId && mode === 'reports') report.refetch(); }}>Reintentar</button></p> : <>
      {mode === 'reports' && !sectionId ? <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {catalog.isLoading && <p>Cargando cursos…</p>}
        {sections.map(s => <button key={s.id} onClick={() => setSectionId(String(s.id))} className="rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm hover:border-indigo-400"><span className="material-symbols-outlined mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">analytics</span><h2 className="font-extrabold">{s.grade_name} · Sección {s.name}</h2><p className="mt-2 text-sm text-slate-500">{s.shift} · {s.year_name}</p><span className="mt-4 inline-block rounded-lg bg-slate-950 px-4 py-2 text-xs font-bold text-white">Abrir reporte →</span></button>)}
        {!catalog.isLoading && !sections.length && <p>No tienes secciones asignadas.</p>}
      </div> : <>
        {mode === 'reports' && <><button className="mb-4 font-bold text-indigo-600" onClick={() => { setSectionId(''); setDate(''); setPeriod(''); setSearch(''); }}>← Volver a los cursos</button><h2 className="mb-4 font-extrabold">{courseLabel(sections.find(s => String(s.id) === sectionId) || {})}</h2><div className="mb-4 flex gap-3">{[['academic', 'Académico'], ['attendance', 'Asistencia']].map(([key, label]) => <button key={key} onClick={() => setTab(key)} className={`rounded-lg px-4 py-2 text-sm font-bold ${tab === key ? 'bg-slate-950 text-white' : 'bg-white text-slate-600'}`}>{label}</button>)}</div><p className="mb-4 text-xs text-slate-500">Calificaciones oficiales. Sin registro no significa cero ni ausencia.</p></>}
        <FilterBar><SearchInput value={search} onChange={setSearch} placeholder="Buscar…" />
          {mode === 'promotions' && <select aria-label="Curso" className={selectClass} value={sectionId} onChange={e => setSectionId(e.target.value)}><option value="">Seleccionar curso</option>{sections.map(s => <option key={s.id} value={s.id}>{courseLabel(s)}</option>)}</select>}
          {mode === 'students' && <select aria-label="Curso" className={selectClass} value={sectionId} onChange={e => setSectionId(e.target.value)}><option value="">Todos mis cursos</option>{sections.map(s => <option key={s.id} value={s.id}>{courseLabel(s)}</option>)}</select>}
          {mode === 'reports' && (tab === 'academic' ? <select aria-label="Período" className={selectClass} value={period} onChange={e => setPeriod(e.target.value)}><option value="">Todos los períodos</option>{report.data?.periods.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select> : <input aria-label="Fecha" type="date" className={inputClass} value={date || report.data?.date || ''} onChange={e => setDate(e.target.value)} />)}
        </FilterBar>
        {mode === 'reports' && <button disabled={!rows.length} onClick={() => downloadCsv(`reporte-coordinador-${tab}`, [columns.map(c => c.label), ...rows.map(r => columns.map(c => c.render ? c.render(r) : r[c.key]))])} className="mb-3 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold disabled:opacity-40">Exportar CSV</button>}
        <DataTable rows={rows} rowKey={r => r.id ?? r.enrollment_id} columns={columns} loading={catalog.isLoading || listing.isLoading || report.isLoading || extra.isLoading} emptyTitle="No hay registros en tu ámbito para esta selección." />
      </>}
    </>}
    {selected && <EditStudent student={selected} onClose={() => setSelected(null)} />}
  </>;
}

function EditStudent({ student, onClose }) {
  const client = useQueryClient();
  const [form, setForm] = useState({ name: student.name, last_name: student.last_name, enrollment_no: student.enrollment_no });
  const mutation = useMutation({ mutationFn: () => api.patch(`/coordinador/students/${student.id}`, form), onSuccess: () => { client.invalidateQueries({ queryKey: ['coordinator-management'] }); client.invalidateQueries({ queryKey: ['coordinator-workspace'] }); onClose(); } });
  return <SideDrawer open onClose={onClose} title="Editar estudiante" description="No se modifica la sección, matrícula académica ni estado." footer={<button disabled={mutation.isPending} onClick={() => mutation.mutate()} className="rounded-lg bg-slate-950 px-4 py-2 text-sm font-bold text-white disabled:opacity-40">{mutation.isPending ? 'Guardando…' : 'Guardar cambios'}</button>}>
    {Object.entries({ name: 'Nombre', last_name: 'Apellido', enrollment_no: 'Número de matrícula' }).map(([key, label]) => <label key={key} className="mb-4 block text-sm font-bold">{label}<input className={`${inputClass} mt-2`} value={form[key]} onChange={e => setForm({ ...form, [key]: e.target.value })} /></label>)}
    {mutation.isError && <p role="alert" className="text-sm text-red-600">{mutation.error.response?.data?.message || 'No se pudo guardar.'}</p>}
  </SideDrawer>;
}
