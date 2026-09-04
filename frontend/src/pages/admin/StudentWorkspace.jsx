import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { getAdminStudents, getStudentWorkspaces } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import PageHeader from '../../components/ui/PageHeader';
import FilterBar from '../../components/ui/FilterBar';
import SearchInput from '../../components/ui/SearchInput';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import StudentActions from '../../components/admin/StudentActions';
import StudentBulkReplaceDrawer from '../../components/admin/StudentBulkReplaceDrawer';
import { selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';

export default function StudentWorkspace() {
  const { workspaceId } = useParams();
  const [searchParams] = useSearchParams();
  const yearId = searchParams.get('year') || '';
  const pending = workspaceId === 'pending';
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [search, setSearch] = useState('');
  const [active, setActive] = useState('');
  const [selectedIds, setSelectedIds] = useState([]);
  const [bulkOpen, setBulkOpen] = useState(false);

  const workspaceParams = yearId ? { academic_year_id: yearId } : {};
  const { data: workspaceData } = useQuery({
    queryKey: ['admin-student-workspaces', workspaceParams],
    queryFn: () => getStudentWorkspaces(workspaceParams),
  });
  const workspace = pending
    ? null
    : workspaceData?.workspaces?.find((item) => Number(item.id) === Number(workspaceId));
  const params = {
    per_page: 1000,
    ...(pending ? { pending: 1 } : { section_id: workspaceId, ...(yearId ? { academic_year_id: yearId } : {}) }),
    ...(search ? { search } : {}),
    ...(active !== '' ? { active } : {}),
  };
  const { data, isLoading } = useQuery({
    queryKey: ['admin-students', 'workspace', params],
    queryFn: () => getAdminStudents(params),
  });
  const students = data?.data || [];

  useEffect(() => {
    const visibleIds = new Set(students.map((student) => Number(student.id)));
    setSelectedIds((current) => current.filter((id) => visibleIds.has(id)));
  }, [data]);

  const selectedStudents = useMemo(
    () => students.filter((student) => selectedIds.includes(Number(student.id))),
    [students, selectedIds],
  );
  const allVisibleSelected = students.length > 0 && students.every((student) => selectedIds.includes(Number(student.id)));
  const title = pending
    ? 'Pendientes de asignación'
    : workspace ? `${workspace.grade_name} · Sección ${workspace.section_name}` : 'Workspace del curso';
  const description = pending
    ? 'Expedientes sin una sección actual. Revisa, corrige y prepara a los estudiantes para su asignación.'
    : workspace ? `${workspace.shift} · ${workspace.academic_year_name} · Administra a todos los estudiantes de este curso.` : 'Cargando información del curso…';
  const totals = pending ? workspaceData?.pending : workspace;
  const workspaceLabel = pending ? 'Pendientes de asignación' : title;

  const toggleAll = () => {
    setSelectedIds(allVisibleSelected ? [] : students.map((student) => Number(student.id)));
  };

  return <>
    <PageHeader
      breadcrumb={['Portal Administrativo', { label: 'Estudiantes', to: '/admin/students' }, title]}
      title={title}
      description={description}
      actions={<button type="button" onClick={() => navigate('/admin/student-placements')} className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
        <span aria-hidden="true" className="material-symbols-outlined text-[18px]">group_add</span> Asignar estudiantes
      </button>}
    />

    <section className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <Kpi label="Estudiantes" value={totals?.students_count ?? data?.total ?? 0} icon="groups" />
      <Kpi label="Activos" value={totals?.active_students_count ?? students.filter((student) => student.active).length} icon="person_check" tone="emerald" />
      <Kpi label="Inactivos" value={totals?.inactive_students_count ?? students.filter((student) => !student.active).length} icon="person_off" tone="slate" />
      <Kpi label="Seleccionados" value={selectedIds.length} icon="select_check_box" tone="indigo" />
    </section>

    <FilterBar>
      <SearchInput value={search} onChange={setSearch} placeholder="Buscar por nombre o matrícula..." className="max-w-sm" />
      <select aria-label="Filtrar por estado" value={active} onChange={(event) => setActive(event.target.value)} className={`${selectClass} w-auto`}>
        <option value="">Todos los estados</option>
        <option value="1">Activos</option>
        <option value="0">Inactivos</option>
      </select>
    </FilterBar>

    {students.length > 0 && <div className="mb-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p className="text-sm font-extrabold text-slate-900">Acciones por lote</p>
        <p className="mt-1 text-xs text-slate-500">{selectedIds.length} de {students.length} estudiantes visibles seleccionados.</p>
      </div>
      <div className="flex flex-wrap gap-2">
        <button type="button" onClick={toggleAll} className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">{allVisibleSelected ? 'Limpiar selección' : 'Seleccionar visibles'}</button>
        <button type="button" disabled={!selectedIds.length} onClick={() => setBulkOpen(true)} className="flex items-center gap-2 rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-40">
          <span aria-hidden="true" className="material-symbols-outlined text-[17px]">find_replace</span> Buscar y reemplazar matrículas
        </button>
      </div>
    </div>}

    <DataTable
      loading={isLoading}
      rows={students}
      onRowClick={(student) => navigate(`/admin/students/${student.id}`)}
      emptyIcon="groups"
      emptyTitle="No hay estudiantes que coincidan con los filtros de este workspace."
      columns={[
        { key: 'select', label: '', align: 'center', render: (student) => <input aria-label={`Seleccionar a ${student.name} ${student.last_name}`} type="checkbox" checked={selectedIds.includes(Number(student.id))} onClick={(event) => event.stopPropagation()} onChange={(event) => setSelectedIds((current) => event.target.checked ? [...current, Number(student.id)] : current.filter((id) => id !== Number(student.id)))} className="h-4 w-4 rounded border-slate-300 text-indigo-600" /> },
        { key: 'enrollment_no', label: 'Matrícula', render: (student) => <span className="font-mono text-xs font-bold text-slate-500">{student.enrollment_no}</span> },
        { key: 'student', label: 'Estudiante', render: (student) => <div><p className="font-extrabold text-slate-900">{student.name} {student.last_name}</p><p className="mt-1 text-xs text-slate-500">{student.section ? `${student.section.grade?.name} · Sección ${student.section.name}` : 'Sin sección actual'}</p></div> },
        { key: 'year', label: 'Año escolar', render: (student) => student.section?.academic_year?.name || '—' },
        { key: 'active', label: 'Estado', align: 'center', render: (student) => <StatusBadge tone={!student.active ? 'neutral' : student.section ? 'success' : 'warning'} label={!student.active ? 'Inactivo' : student.section ? 'Inscrito' : 'Pendiente'} /> },
        { key: 'actions', label: 'Acciones', align: 'right', render: (student) => <StudentActions student={student} onSuccess={showToast} /> },
      ]}
    />

    <StudentBulkReplaceDrawer
      open={bulkOpen}
      onClose={() => setBulkOpen(false)}
      students={selectedStudents}
      workspaceLabel={workspaceLabel}
      onApplied={(result) => {
        qc.invalidateQueries({ queryKey: ['admin-students'] });
        qc.invalidateQueries({ queryKey: ['admin-student-workspaces'] });
        setBulkOpen(false);
        setSelectedIds([]);
        showToast(result.message);
      }}
    />
    <Toast toast={toast} />
  </>;
}

function Kpi({ label, value, icon, tone = 'slate' }) {
  const colors = { slate: 'text-slate-950', emerald: 'text-emerald-600', indigo: 'text-indigo-600' };
  return <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start justify-between"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p><p className={`mt-2 text-2xl font-extrabold ${colors[tone]}`}>{value}</p></div><span aria-hidden="true" className="material-symbols-outlined relative top-[2px] text-slate-400">{icon}</span></div></div>;
}
