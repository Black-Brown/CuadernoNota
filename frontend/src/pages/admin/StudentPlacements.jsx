import React, { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { assignStudentsToSection, getPendingStudentPlacements, getSections } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import SearchInput from '../../components/ui/SearchInput';
import SideDrawer from '../../components/ui/SideDrawer';
import StatusBadge from '../../components/ui/StatusBadge';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton';
import EmptyState from '../../components/ui/EmptyState';
import Toast from '../../components/ui/Toast';

const today = () => new Date().toISOString().slice(0, 10);

export default function StudentPlacements() {
  const [searchParams] = useSearchParams();
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [search, setSearch] = useState('');
  const [selectedIds, setSelectedIds] = useState(() => {
    const studentId = Number(searchParams.get('student'));
    return studentId ? [studentId] : [];
  });
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [sectionId, setSectionId] = useState('');
  const [enrolledAt, setEnrolledAt] = useState(today());
  const [error, setError] = useState('');
  const { data: students = [], isLoading } = useQuery({ queryKey: ['admin-student-placements'], queryFn: () => getPendingStudentPlacements() });
  const { data: sections = [] } = useQuery({ queryKey: ['admin-sections'], queryFn: () => getSections() });
  const visible = useMemo(() => students.filter((student) => `${student.name} ${student.last_name} ${student.enrollment_no} ${student.target_grade_name || ''}`.toLocaleLowerCase('es').includes(search.trim().toLocaleLowerCase('es'))), [students, search]);
  const selected = students.filter((student) => selectedIds.includes(student.id));
  const targetGradeIds = [...new Set(selected.map((student) => student.target_grade_id).filter(Boolean).map(Number))];
  const compatibleSections = sections.filter((section) => targetGradeIds.length === 0 || (targetGradeIds.length === 1 && Number(section.grade_id) === targetGradeIds[0]));
  const allVisibleSelected = visible.length > 0 && visible.every((student) => selectedIds.includes(student.id));
  const assignMutation = useMutation({
    mutationFn: () => assignStudentsToSection({ student_ids: selectedIds, section_id: Number(sectionId), enrolled_at: enrolledAt }),
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: ['admin-student-placements'] }); qc.invalidateQueries({ queryKey: ['admin-students'] });
      setSelectedIds([]); setDrawerOpen(false); showToast(data.message);
    },
    onError: (err) => setError(getErrorMessage(err)),
  });
  const openAssignment = () => { setSectionId(''); setEnrolledAt(today()); setError(''); setDrawerOpen(true); };

  return <>
    <PageHeader breadcrumb={['Portal Administrativo', 'Asignación de estudiantes']} title="Asignación de estudiantes"
      description="Ubica nuevos ingresos y estudiantes promovidos en una sección real del año escolar correspondiente."
      actions={<button type="button" disabled={!selectedIds.length} onClick={openAssignment} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-extrabold text-white disabled:opacity-40"><span aria-hidden="true" className="material-symbols-outlined text-[18px]">group_add</span>Asignar {selectedIds.length || ''}</button>} />
    <section className="mb-5 grid gap-3 sm:grid-cols-3"><Kpi label="Pendientes" value={students.length} icon="pending_actions" /><Kpi label="Nuevos ingresos" value={students.filter((student) => student.placement_reason === 'Nuevo ingreso').length} icon="person_add" /><Kpi label="Por promoción" value={students.filter((student) => student.placement_reason !== 'Nuevo ingreso').length} icon="upgrade" /></section>
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><SearchInput value={search} onChange={setSearch} placeholder="Buscar estudiante o matrícula…" className="max-w-sm" /><button type="button" disabled={!visible.length} onClick={() => setSelectedIds(allVisibleSelected ? selectedIds.filter((id) => !visible.some((student) => student.id === id)) : [...new Set([...selectedIds, ...visible.map((student) => student.id)])])} className="text-xs font-extrabold text-indigo-600 disabled:opacity-40">{allVisibleSelected ? 'Quitar visibles' : 'Seleccionar visibles'}</button></div>
    {isLoading ? <LoadingSkeleton /> : visible.length === 0 ? <div className="rounded-xl border border-slate-200 bg-white"><EmptyState icon="how_to_reg" title="No hay estudiantes pendientes de asignación" /></div> : <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div className="divide-y divide-slate-100">{visible.map((student) => { const checked = selectedIds.includes(student.id); return <label key={student.id} className={`flex cursor-pointer flex-wrap items-center gap-4 px-5 py-4 transition-colors ${checked ? 'bg-indigo-50/60' : 'hover:bg-slate-50'}`}><input type="checkbox" checked={checked} onChange={() => setSelectedIds(checked ? selectedIds.filter((id) => id !== student.id) : [...selectedIds, student.id])} className="h-4 w-4 accent-indigo-600" /><div className="min-w-48 flex-1"><p className="font-extrabold text-slate-900">{student.name} {student.last_name}</p><p className="mt-1 font-mono text-xs text-slate-500">{student.enrollment_no}</p></div><div className="min-w-48 flex-1"><p className="text-xs font-bold text-slate-700">{student.origin || 'Sin matrícula anterior'}</p><p className="mt-1 text-xs text-slate-500">Destino: {student.target_grade_name || 'Por definir'}</p></div><StatusBadge tone={student.placement_reason === 'Nuevo ingreso' ? 'neutral' : 'warning'} label={student.placement_reason} /></label>; })}</div></div>}

    <SideDrawer open={drawerOpen} onClose={() => { if (!assignMutation.isPending) setDrawerOpen(false); }} title={`Asignar ${selectedIds.length} estudiante(s)`} description="La operación creará una matrícula activa para cada estudiante seleccionado."
      footer={<div className="flex justify-end gap-3"><button type="button" onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600">Cancelar</button><button type="button" onClick={() => assignMutation.mutate()} disabled={assignMutation.isPending || !sectionId || !enrolledAt || targetGradeIds.length > 1} className="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-40">Confirmar asignación</button></div>}>
      <div className="space-y-4">{error && <div className="rounded-lg bg-red-50 p-3 text-xs font-semibold text-red-700">{error}</div>}{targetGradeIds.length > 1 && <div className="rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-800">Seleccionaste estudiantes con grados de destino diferentes. Asígnalos en grupos separados.</div>}<div className="rounded-xl border border-slate-200 bg-slate-50 p-4"><p className="text-xs font-extrabold text-slate-900">{selectedIds.length} estudiantes seleccionados</p><p className="mt-1 text-xs text-slate-500">{targetGradeIds.length === 1 ? `Solo se mostrarán secciones de ${selected.find((student) => Number(student.target_grade_id) === targetGradeIds[0])?.target_grade_name}.` : 'Para nuevos ingresos puedes elegir cualquier sección.'}</p></div>
        <FormField label="Sección destino" required><select className={selectClass} value={sectionId} onChange={(event) => setSectionId(event.target.value)}><option value="">Seleccionar año, grado y sección</option>{compatibleSections.map((section) => <option key={section.id} value={section.id}>{section.academic_year?.name} · {section.grade?.name} {section.name} · {section.shift}</option>)}</select></FormField>
        <FormField label="Fecha de inscripción" required><input type="date" className={inputClass} value={enrolledAt} onChange={(event) => setEnrolledAt(event.target.value)} /></FormField>
      </div>
    </SideDrawer><Toast toast={toast} />
  </>;
}

function Kpi({ label, value, icon }) { return <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex justify-between"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p><p className="mt-2 text-2xl font-extrabold text-slate-950">{value}</p></div><span aria-hidden="true" className="material-symbols-outlined relative top-[2px] text-slate-400">{icon}</span></div></div>; }
