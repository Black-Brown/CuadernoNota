import React, { useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { decidePromotion, decidePromotionsBulk, getGrades, getPromotionCandidates } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';

export default function PromotionWorkspace() {
  const { sectionId } = useParams();
  const [searchParams] = useSearchParams();
  const yearId = searchParams.get('year');
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [drawerRow, setDrawerRow] = useState(null);
  const [bulkIds, setBulkIds] = useState([]);
  const [selectedIds, setSelectedIds] = useState([]);
  const [decisionStatus, setDecisionStatus] = useState('promoted');
  const [targetGradeId, setTargetGradeId] = useState('');
  const [justification, setJustification] = useState('');
  const [error, setError] = useState('');
  const params = { academic_year_id: yearId, section_id: sectionId };
  const { data: students = [], isLoading } = useQuery({
    queryKey: ['admin-promotion-candidates', params], queryFn: () => getPromotionCandidates(params), enabled: !!yearId && !!sectionId,
  });
  const { data: grades = [] } = useQuery({ queryKey: ['admin-grades'], queryFn: () => getGrades() });
  const course = students[0];
  const decided = students.filter((student) => student.decision).length;
  const eligible = students.filter((student) => student.eligible).length;
  const pending = students.length - decided;
  const promotionOpen = course?.promotion_open !== false;
  const pendingStudents = students.filter((student) => !student.decision);
  const allPendingSelected = pendingStudents.length > 0 && pendingStudents.every((student) => selectedIds.includes(student.enrollment_id));

  const suggestedGrade = (row, status) => status === 'promoted'
    ? grades.filter((grade) => grade.active !== false && grade.level === row?.grade_level && Number(grade.sort_order) > Number(row?.grade_sort_order))
      .sort((a, b) => Number(a.sort_order) - Number(b.sort_order))[0]
    : grades.find((grade) => Number(grade.id) === Number(row?.grade_id));

  const openDecision = (row) => {
    const status = row.decision?.status || (row.eligible ? 'promoted' : 'not_promoted');
    const suggested = row.decision?.target_grade || suggestedGrade(row, status);
    setBulkIds([]); setDrawerRow(row); setDecisionStatus(status); setTargetGradeId(suggested ? String(suggested.id) : '');
    setJustification(row.decision?.justification || ''); setError('');
  };
  const openBulkDecision = (status) => {
    const rows = students.filter((student) => selectedIds.includes(student.enrollment_id) && !student.decision);
    if (!rows.length) return;
    const suggested = suggestedGrade(rows[0], status);
    setBulkIds(rows.map((row) => row.enrollment_id)); setDrawerRow(rows[0]); setDecisionStatus(status);
    setTargetGradeId(suggested ? String(suggested.id) : ''); setJustification(''); setError('');
  };
  const changeDecisionStatus = (status) => {
    setDecisionStatus(status);
    const grade = suggestedGrade(drawerRow, status);
    setTargetGradeId(grade ? String(grade.id) : '');
  };
  const decideMutation = useMutation({
    mutationFn: () => {
      const payload = { status: decisionStatus, target_grade_id: Number(targetGradeId), justification: justification || undefined };
      return bulkIds.length
        ? decidePromotionsBulk({ ...payload, enrollment_ids: bulkIds, section_id: Number(sectionId) })
        : decidePromotion(drawerRow.enrollment_id, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-promotion-candidates'] });
      const count = bulkIds.length;
      setDrawerRow(null); setBulkIds([]); setSelectedIds([]);
      showToast(count ? `${count} decisiones de promoción registradas.` : 'Decisión de promoción registrada.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  return <>
    <PageHeader breadcrumb={['Portal Administrativo', { label: 'Promoción escolar', to: '/admin/promotions' }, course ? `${course.grade_name} ${course.section_name}` : 'Workspace']}
      title={course ? `${course.grade_name} ${course.section_name}` : 'Workspace de promoción'}
      description={course ? `${course.shift} · ${course.academic_year_name} · Revisa y procesa a todos los estudiantes de este curso.` : 'Cargando información del curso…'} />
    <section className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <Kpi label="Estudiantes" value={students.length} icon="groups" />
      <Kpi label="Elegibles" value={eligible} icon="verified" />
      <Kpi label="Pendientes" value={pending} icon="pending_actions" tone={pending ? 'amber' : 'slate'} />
      <Kpi label="Procesados" value={decided} icon="task_alt" />
    </section>
    {!promotionOpen && <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold leading-5 text-amber-800"><span className="font-extrabold">Promoción bloqueada:</span> {course?.promotion_block_reason}</div>}
    <div className="mb-4 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-xs leading-5 text-slate-600"><span className="font-extrabold text-slate-900">Criterio automático:</span> el estudiante aparece elegible cuando tiene todas las materias esperadas registradas y ninguna calificación final inferior a 70. La decisión final corresponde al administrador.</div>
    {pendingStudents.length > 0 && <div className="mb-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
      <div><p className="text-sm font-extrabold text-slate-900">Procesamiento por lote</p><p className="mt-1 text-xs text-slate-500">{selectedIds.length} de {pendingStudents.length} estudiantes pendientes seleccionados.</p></div>
      <div className="flex flex-wrap gap-2">
        <button type="button" onClick={() => setSelectedIds(allPendingSelected ? [] : pendingStudents.map((student) => student.enrollment_id))} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">{allPendingSelected ? 'Limpiar selección' : 'Seleccionar pendientes'}</button>
        <button type="button" onClick={() => openBulkDecision('not_promoted')} disabled={!selectedIds.length || !promotionOpen} className="rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 disabled:opacity-40">No promover</button>
        <button type="button" onClick={() => openBulkDecision('promoted')} disabled={!selectedIds.length || !promotionOpen} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-40">Promover seleccionados</button>
      </div>
    </div>}
    <DataTable loading={isLoading} rows={students} rowKey={(row) => row.enrollment_id} emptyIcon="groups" emptyTitle="Este curso no tiene estudiantes pendientes o procesados."
      columns={[
        { key: 'select', label: '', align: 'center', render: (row) => <input aria-label={`Seleccionar a ${row.student_name}`} type="checkbox" disabled={!!row.decision} checked={selectedIds.includes(row.enrollment_id)} onChange={(event) => setSelectedIds((current) => event.target.checked ? [...current, row.enrollment_id] : current.filter((id) => id !== row.enrollment_id))} className="h-4 w-4 rounded border-slate-300 text-indigo-600 disabled:opacity-30" /> },
        { key: 'student', label: 'Estudiante', render: (row) => <div><p className="font-extrabold text-slate-900">{row.student_name}</p><p className="mt-1 font-mono text-xs text-slate-500">{row.enrollment_no}</p></div> },
        { key: 'progress', label: 'Calificaciones', render: (row) => <div><p className="font-bold text-slate-900">{row.subject_count} de {row.expected_subject_count} materias</p><p className={`mt-1 text-xs ${row.failed_subjects ? 'font-bold text-red-600' : 'text-slate-500'}`}>{row.failed_subjects} reprobadas</p></div> },
        { key: 'eligible', label: 'Elegibilidad', align: 'center', render: (row) => <StatusBadge tone={row.eligible ? 'success' : 'danger'} label={row.eligible ? 'Elegible' : 'No elegible'} /> },
        { key: 'decision', label: 'Decisión', align: 'center', render: (row) => row.decision ? <StatusBadge tone={row.decision.status === 'promoted' ? 'success' : 'danger'} label={row.decision.status === 'promoted' ? 'Promovido' : 'No promovido'} /> : <StatusBadge tone="warning" label="Pendiente" /> },
        { key: 'destination', label: 'Destino', render: (row) => row.decision?.destination_section ? `${row.decision.destination_section.grade?.name} ${row.decision.destination_section.name} · ${row.decision.destination_section.academic_year?.name}` : row.decision?.target_grade ? <span className="text-amber-700">{row.decision.target_grade.name} · Pendiente de sección</span> : '—' },
        { key: 'actions', label: 'Acciones', align: 'right', render: (row) => row.decision?.placement_status === 'assigned' ? <span className="text-xs font-semibold text-slate-400">Colocado</span> : <button type="button" disabled={!promotionOpen} onClick={() => openDecision(row)} className="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-extrabold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">{row.decision ? 'Revisar decisión' : 'Registrar decisión'}</button> },
      ]} />

    <SideDrawer open={!!drawerRow} onClose={() => { setDrawerRow(null); setBulkIds([]); }} title={bulkIds.length ? `Decisión para ${bulkIds.length} estudiantes` : `Decisión — ${drawerRow?.student_name || ''}`}
      description={bulkIds.length ? 'La misma decisión se aplicará de forma atómica a todos los estudiantes seleccionados.' : `${drawerRow?.subject_count || 0} de ${drawerRow?.expected_subject_count || 0} materias · ${drawerRow?.failed_subjects || 0} reprobadas`}
      footer={<div className="flex justify-end gap-3"><button onClick={() => setDrawerRow(null)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600">Cancelar</button><button onClick={() => decideMutation.mutate()} disabled={decideMutation.isPending || !targetGradeId} className="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-50">Confirmar decisión</button></div>}>
      <div className="space-y-4">{error && <div className="rounded-lg bg-red-50 p-3 text-xs font-semibold text-red-700">{error}</div>}
        <div className="flex gap-2 rounded-lg border border-slate-200 p-1"><button type="button" onClick={() => changeDecisionStatus('promoted')} className={`flex-1 rounded-md py-2 text-xs font-bold ${decisionStatus === 'promoted' ? 'bg-emerald-600 text-white' : 'text-slate-500'}`}>Promover</button><button type="button" onClick={() => changeDecisionStatus('not_promoted')} className={`flex-1 rounded-md py-2 text-xs font-bold ${decisionStatus === 'not_promoted' ? 'bg-red-600 text-white' : 'text-slate-500'}`}>No promover</button></div>
        <FormField label="Grado de destino" required hint="La sección A, B o C se asignará posteriormente desde Gestión académica."><select className={selectClass} value={targetGradeId} onChange={(event) => setTargetGradeId(event.target.value)}><option value="">Seleccionar grado</option>{grades.filter((grade) => decisionStatus === 'not_promoted' ? Number(grade.id) === Number(drawerRow?.grade_id) : grade.level === drawerRow?.grade_level && Number(grade.sort_order) > Number(drawerRow?.grade_sort_order)).sort((a, b) => Number(a.sort_order) - Number(b.sort_order)).slice(0, 1).map((grade) => <option key={grade.id} value={grade.id}>{grade.name} · {grade.level}</option>)}</select></FormField>
        <FormField label="Justificación" hint="Recomendada cuando la decisión difiere del criterio automático."><textarea rows={4} className={inputClass} value={justification} onChange={(event) => setJustification(event.target.value)} /></FormField>
      </div>
    </SideDrawer>
    <Toast toast={toast} />
  </>;
}

function Kpi({ label, value, icon, tone = 'slate' }) {
  return <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start justify-between"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p><p className={`mt-2 text-2xl font-extrabold ${tone === 'amber' ? 'text-amber-600' : 'text-slate-950'}`}>{value}</p></div><span aria-hidden="true" className="material-symbols-outlined relative top-[2px] text-slate-400">{icon}</span></div></div>;
}
