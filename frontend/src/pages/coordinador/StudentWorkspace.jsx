import React, { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getCoordinatorWorkspace, getCoordinatorStudent } from '../../api/coordinator.api';
import PageHeader from '../../components/ui/PageHeader';
import FilterBar from '../../components/ui/FilterBar';
import SearchInput from '../../components/ui/SearchInput';
import DataTable from '../../components/ui/DataTable';
import KpiCard from '../../components/ui/KpiCard';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import { selectClass } from '../../components/ui/FormField';

const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const states = { draft: 'Borrador', in_review: 'En revisión', official: 'Oficial' };
const attendanceLabels = { P: 'Presente', T: 'Tardanza', A: 'Ausente', E: 'Justificada' };

export default function StudentWorkspace() {
  const { sectionId } = useParams();
  const [periodId, setPeriodId] = useState('');
  const [search, setSearch] = useState('');
  const [riskOnly, setRiskOnly] = useState(false);
  const [selectedStudent, setSelectedStudent] = useState(null);
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['coordinator-workspace', sectionId, periodId],
    queryFn: () => getCoordinatorWorkspace(sectionId, periodId),
  });
  const rows = (data?.students ?? []).filter(s => normalize(`${s.last_name} ${s.name} ${s.enrollment_no}`).includes(normalize(search)) && (!riskOnly || s.at_risk));
  return <>
    <PageHeader breadcrumb={['Portal de Coordinación', { label: 'Inicio', to: '/coordinador/dashboard' }, 'Estudiantes']}
      title={data ? `${data.section.grade_name} · Sección ${data.section.name}` : 'Seguimiento del curso'}
      description={data ? `${data.section.year_name} · ${data.section.shift}. Consulta académica por período.` : 'Estudiantes de la sección asignada.'} />
    {isError ? <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">No se pudo cargar el curso o no tienes acceso. <button onClick={() => refetch()} className="underline">Reintentar</button></p> : <>
      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <KpiCard label="Estudiantes activos" value={data?.students.length ?? '—'} loading={isLoading} icon="groups" />
        <KpiCard label="Con notas registradas" value={data?.students.filter(s => s.evaluated_subjects > 0).length ?? '—'} loading={isLoading} icon="grade" />
        <KpiCard label="En riesgo académico" value={data?.students.filter(s => s.at_risk).length ?? '—'} loading={isLoading} icon="warning" helper="Al menos una materia por debajo de 70, considerando RP." />
      </div>
      <FilterBar>
        <SearchInput value={search} onChange={setSearch} placeholder="Buscar estudiante o matrícula..." />
        <select aria-label="Período" className={selectClass} value={periodId || data?.period?.id || ''} onChange={e => { setPeriodId(e.target.value); setSelectedStudent(null); }}>
          {!data?.periods.length && <option value="">Sin períodos configurados</option>}
          {data?.periods.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
        </select>
        <label className="flex shrink-0 items-center gap-2 text-sm text-slate-600"><input type="checkbox" checked={riskOnly} onChange={e => setRiskOnly(e.target.checked)} />Solo en riesgo</label>
      </FilterBar>
      <DataTable rows={rows} loading={isLoading} onRowClick={setSelectedStudent} emptyTitle="No hay estudiantes que coincidan" columns={[
        { key: 'name', label: 'Estudiante', render: s => `${s.last_name}, ${s.name}` },
        { key: 'enrollment_no', label: 'Matrícula' },
        { key: 'evaluated_subjects', label: 'Materias evaluadas', align: 'center' },
        { key: 'average', label: 'Promedio registrado', align: 'center', render: s => s.average ?? '—' },
        { key: 'risk', label: 'Seguimiento', render: s => <StatusBadge tone={s.at_risk ? 'danger' : 'neutral'} label={s.at_risk ? 'En riesgo' : s.evaluated_subjects ? 'Sin materias reprobadas' : 'Sin notas'} /> },
        { key: 'open', label: 'Ficha', render: s => <button onClick={e => { e.stopPropagation(); setSelectedStudent(s); }} className="font-bold text-indigo-600">Abrir</button> },
      ]} />
    </>}
    {selectedStudent && <StudentDetail key={`${sectionId}-${selectedStudent.id}-${data?.period?.id}`} sectionId={sectionId} student={selectedStudent} periodId={data?.period?.id} onClose={() => setSelectedStudent(null)} />}
  </>;
}

function StudentDetail({ sectionId, student, periodId, onClose }) {
  const [tab, setTab] = useState('grades');
  const { data, isLoading, isError } = useQuery({
    queryKey: ['coordinator-student', sectionId, student.id, periodId],
    queryFn: () => getCoordinatorStudent(sectionId, student.id, periodId),
  });
  return <SideDrawer open onClose={onClose} title={`${student.last_name}, ${student.name}`} description={`${student.enrollment_no} · ${data?.period?.name ?? 'Sin período'}`} widthClass="max-w-3xl">
    {isError ? <p role="alert" className="text-sm text-red-600">No se pudo cargar la ficha o el estudiante ya no pertenece a tu sección.</p> : <>
      <div className="mb-5 flex flex-wrap gap-2">{[['grades', 'Calificaciones'], ['attendance', 'Asistencia'], ['observations', 'Observaciones']].map(([key, label]) =>
        <button key={key} onClick={() => setTab(key)} aria-pressed={tab === key} className={`rounded-lg px-4 py-2 text-xs font-bold ${tab === key ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'}`}>{label}</button>)}</div>
      {tab === 'grades' && <DataTable rows={data?.grades} loading={isLoading} emptyTitle="Sin notas en este período" columns={[
        { key: 'subject_name', label: 'Materia' },
        ...['c1_score', 'c2_score', 'c3_score'].map((key, index) => ({ key, label: `C${index + 1}`, render: r => r[key] ?? '—' })),
        { key: 'period_score', label: 'Período', render: r => r.period_score ?? '—' },
        { key: 'rp_score', label: 'RP', render: r => r.rp_score ?? '—' },
        { key: 'status', label: 'Estado', render: r => states[r.status] ?? r.status },
      ]} />}
      {tab === 'attendance' && <>
        <p className="mb-4 text-sm text-slate-600">Asistencia: {data?.summary.attendance_percentage == null ? 'Sin registros' : `${data.summary.attendance_percentage}%`} · {data?.summary.attendance_records ?? 0} registros por materia. Las tardanzas cuentan como asistencia.</p>
        <DataTable rows={data?.attendance} loading={isLoading} emptyTitle="Sin asistencia en este período" columns={[
          { key: 'date', label: 'Fecha' }, { key: 'subject_name', label: 'Materia', render: r => r.subject_name ?? 'Registro histórico' },
          { key: 'code', label: 'Estado', render: r => attendanceLabels[r.code] ?? r.code },
        ]} />
      </>}
      {tab === 'observations' && <DataTable rows={data?.observations} loading={isLoading} emptyTitle="Sin observaciones en este período" columns={[
        { key: 'date', label: 'Fecha' }, { key: 'author_name', label: 'Docente' },
        { key: 'type', label: 'Tipo', render: r => ({ academic: 'Académica', disciplinary: 'Disciplinaria', incident: 'Incidencia' })[r.type] ?? r.type },
        { key: 'description', label: 'Observación' },
      ]} />}
    </>}
  </SideDrawer>;
}
