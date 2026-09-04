import React, { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAdminStudent, getAcademicYears, getAdminStudents, getStudentWorkspaces } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import FilterBar from '../../components/ui/FilterBar';
import SearchInput from '../../components/ui/SearchInput';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton';
import EmptyState from '../../components/ui/EmptyState';
import StudentImportDrawer from '../../components/admin/StudentImportDrawer';
import StudentActions from '../../components/admin/StudentActions';

const EMPTY_FORM = { name: '', last_name: '', enrollment_no: '' };

export default function AdminStudents() {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const location = useLocation();
  const { toast, showToast } = useToast();

  const [view, setView] = useState('list');
  const [yearId, setYearId] = useState('');
  const [search, setSearch] = useState('');
  const [active, setActive] = useState('');
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [importedCount, setImportedCount] = useState(0);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState('');

  const params = { per_page: 25, page };
  if (search) params.search = search;
  if (active !== '') params.active = active;

  const { data, isLoading } = useQuery({ queryKey: ['admin-students', params], queryFn: () => getAdminStudents(params), enabled: view === 'list' });
  const students = data?.data || [];
  const { data: years = [] } = useQuery({ queryKey: ['admin-years'], queryFn: getAcademicYears });
  useEffect(() => {
    if (!yearId && years.length) setYearId(String(years.find((year) => year.active)?.id || years[0].id));
  }, [years, yearId]);
  const workspaceParams = yearId ? { academic_year_id: yearId } : {};
  const { data: workspaceData, isLoading: workspacesLoading } = useQuery({
    queryKey: ['admin-student-workspaces', workspaceParams],
    queryFn: () => getStudentWorkspaces(workspaceParams),
    enabled: view === 'workspaces',
  });

  useEffect(() => {
    if (data?.last_page && page > data.last_page) setPage(data.last_page);
  }, [data?.last_page, page]);

  useEffect(() => {
    if (!location.state?.studentActionMessage) return;
    showToast(location.state.studentActionMessage);
    navigate(location.pathname, { replace: true, state: null });
  }, [location.state, location.pathname, navigate, showToast]);

  const createMutation = useMutation({
    mutationFn: createAdminStudent,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-students'] });
      qc.invalidateQueries({ queryKey: ['admin-student-workspaces'] });
      setDrawerOpen(false);
      setForm(EMPTY_FORM);
      showToast('Estudiante registrado y pendiente de asignación.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const openCreate = () => { setForm(EMPTY_FORM); setError(''); setDrawerOpen(true); };

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Estudiantes']}
        title="Estudiantes"
        description="Selecciona un curso para administrar a sus estudiantes o consulta el listado general."
        actions={<div className="flex flex-wrap gap-2">
          <button onClick={() => setImportOpen(true)} className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
            <span className="material-symbols-outlined text-[18px]">upload_file</span> Importar CSV
          </button>
          <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
            <span className="material-symbols-outlined text-[18px]">person_add_alt</span> Registrar estudiante
          </button>
        </div>}
      />

      <div className="mb-6 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-2 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="grid grid-cols-2 gap-1 rounded-lg bg-slate-50 p-1">
          <button type="button" onClick={() => setView('list')} className={`flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-xs font-extrabold transition-colors ${view === 'list' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900'}`}>
            <span aria-hidden="true" className="material-symbols-outlined text-[18px]">table_rows</span> Listado general
          </button>
          <button type="button" onClick={() => setView('workspaces')} className={`flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-xs font-extrabold transition-colors ${view === 'workspaces' ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900'}`}>
            <span aria-hidden="true" className="material-symbols-outlined text-[18px]">space_dashboard</span> Workspaces por curso
          </button>
        </div>
        {view === 'workspaces' && <select aria-label="Seleccionar año escolar" value={yearId} onChange={(event) => setYearId(event.target.value)} className={`${selectClass} w-full sm:w-56`}>
          {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
        </select>}
      </div>

      {view === 'list' && <FilterBar>
        <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Buscar por nombre o matrícula..." className="max-w-xs" />
        <select value={active} onChange={(e) => { setActive(e.target.value); setPage(1); }} className={`${selectClass} w-auto`}>
          <option value="">Todos los estados</option>
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </FilterBar>}

      {importedCount > 0 && <div role="status" className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
        <div><p className="font-bold">{importedCount} {importedCount === 1 ? 'estudiante registrado' : 'estudiantes registrados'} correctamente</p><p className="mt-1 text-xs">Registro completado. El siguiente paso es la asignación a una sección.</p></div>
        <Link to="/admin/student-placements" className="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-bold hover:bg-emerald-100">Asignar estudiantes <span aria-hidden="true" className="material-symbols-outlined text-[18px]">arrow_forward</span></Link>
      </div>}

      {view === 'workspaces' ? <StudentWorkspaceCards data={workspaceData} loading={workspacesLoading} onOpen={(workspaceId) => navigate(`/admin/students/workspaces/${workspaceId}${workspaceId === 'pending' ? '' : `?year=${yearId}`}`)} /> : <DataTable
        loading={isLoading}
        rows={students}
        onRowClick={(s) => navigate(`/admin/students/${s.id}`)}
        emptyIcon="school"
        emptyTitle="No hay estudiantes que coincidan con los filtros."
        columns={[
          { key: 'enrollment_no', label: 'Matrícula', render: (s) => <span className="font-mono text-xs font-bold text-slate-500">{s.enrollment_no}</span> },
          { key: 'name', label: 'Estudiante', render: (s) => <span className="font-bold text-slate-900">{s.name} {s.last_name}</span> },
          { key: 'section', label: 'Grado / Sección', render: (s) => s.section ? `${s.section.grade?.name} ${s.section.name}` : <span className="font-semibold text-amber-600">Pendiente de asignación</span> },
          { key: 'year', label: 'Año escolar', render: (s) => s.section?.academic_year?.name || '—' },
          { key: 'active', label: 'Estado', align: 'center', render: (s) => <StatusBadge tone={!s.active ? 'neutral' : s.section ? 'success' : 'warning'} label={!s.active ? 'Inactivo' : s.section ? 'Inscrito' : 'Pendiente'} /> },
          { key: 'actions', label: 'Acciones', align: 'right', render: (s) => <StudentActions student={s} onSuccess={showToast} /> },
        ]}
      />}

      {view === 'list' && data?.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-xs font-semibold text-slate-500">
          <span>Página {data.current_page} de {data.last_page} · {data.total} estudiantes</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40">Anterior</button>
            <button disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)} className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40">Siguiente</button>
          </div>
        </div>
      )}

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title="Registrar estudiante"
        description="Crea su expediente. La sección se asignará posteriormente desde Gestión académica."
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button
              onClick={() => createMutation.mutate(form)}
              disabled={createMutation.isPending || !form.name || !form.last_name || !form.enrollment_no}
              className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {createMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Registrar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}
          <FormField label="Nombre" required>
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Apellido" required>
            <input className={inputClass} value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} />
          </FormField>
          <FormField label="Número de matrícula" required>
            <input className={inputClass} value={form.enrollment_no} onChange={(e) => setForm({ ...form, enrollment_no: e.target.value })} />
          </FormField>
        </div>
      </SideDrawer>

      <StudentImportDrawer open={importOpen} onClose={() => setImportOpen(false)} onImported={(result) => {
        qc.invalidateQueries({ queryKey: ['admin-students'] });
        qc.invalidateQueries({ queryKey: ['admin-student-placements'] });
        qc.invalidateQueries({ queryKey: ['admin-student-workspaces'] });
        setImportedCount(result.imported);
        setSearch('');
        setActive('');
        setPage(1);
        setImportOpen(false);
        showToast(result.message || `${result.imported} estudiantes importados correctamente.`);
      }} />

      <Toast toast={toast} />
    </>
  );
}

function StudentWorkspaceCards({ data, loading, onOpen }) {
  if (loading) return <LoadingSkeleton />;
  const workspaces = data?.workspaces || [];
  const pending = data?.pending || {};
  const hasPending = Number(pending.students_count || 0) > 0;
  const summary = data?.summary || {};

  return <>
    <section className="mb-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <Kpi label="Cursos" value={workspaces.length} icon="school" />
      <Kpi label="Estudiantes" value={summary.students || 0} icon="groups" />
      <Kpi label="Activos" value={summary.active_students || 0} icon="person_check" />
      <Kpi label="Inactivos" value={summary.inactive_students || 0} icon="person_off" />
    </section>

    {workspaces.length === 0 && !hasPending ? <EmptyState icon="groups" title="No hay estudiantes organizados para este año escolar" description="Registra estudiantes o asígnalos a una sección para crear sus workspaces." /> : <section className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
      {workspaces.map((workspace) => <WorkspaceCard key={workspace.id} workspace={workspace} onOpen={() => onOpen(workspace.id)} />)}
      {hasPending && <article role="link" tabIndex={0} aria-label="Abrir workspace de pendientes de asignación" onClick={() => onOpen('pending')} onKeyDown={(event) => { if (event.currentTarget === event.target && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); onOpen('pending'); } }} className="cursor-pointer overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2">
        <div className="border-b border-amber-100 bg-amber-50/70 px-5 py-5">
          <div className="flex items-start justify-between gap-3"><div><span className="inline-flex rounded-md bg-amber-100 px-2 py-1 text-[9px] font-extrabold uppercase text-amber-800">Requiere asignación</span><p className="mt-4 text-[10px] font-extrabold uppercase tracking-wider text-amber-600">Workspace especial</p><h2 className="mt-1 text-xl font-extrabold text-slate-950">Pendientes de asignación</h2></div><span aria-hidden="true" className="material-symbols-outlined relative top-[3px] text-3xl text-amber-500">group_add</span></div>
          <p className="mt-2 text-xs text-slate-500">Nuevos ingresos y estudiantes reactivados sin sección actual.</p>
        </div>
        <div className="p-5"><div className="grid grid-cols-3 gap-2 border-b border-slate-100 pb-5 text-center"><Stat label="Estudiantes" value={pending.students_count} /><Stat label="Activos" value={pending.active_students_count} /><Stat label="Inactivos" value={pending.inactive_students_count} /></div><div className="mt-5 flex items-center justify-between gap-3"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Workspace de estudiantes</p><p className="mt-1 text-xs text-slate-500">Sin sección actual</p></div><button type="button" onClick={(event) => { event.stopPropagation(); onOpen('pending'); }} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-slate-800">Abrir <span aria-hidden="true" className="material-symbols-outlined text-[18px]">arrow_forward</span></button></div></div>
      </article>}
    </section>}
  </>;
}

function WorkspaceCard({ workspace, onOpen }) {
  return <article role="link" tabIndex={0} aria-label={`Abrir workspace de ${workspace.grade_name}, sección ${workspace.section_name}`} onClick={onOpen} onKeyDown={(event) => { if (event.currentTarget === event.target && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); onOpen(); } }} className="cursor-pointer overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
    <div className="border-b border-indigo-100 bg-indigo-50/70 px-5 py-5"><div className="flex items-start justify-between gap-3"><div><span className="inline-flex rounded-md bg-indigo-100 px-2 py-1 text-[9px] font-extrabold uppercase text-indigo-700">Workspace disponible</span><p className="mt-4 text-[10px] font-extrabold uppercase tracking-wider text-indigo-500">Curso / Sección</p><h2 className="mt-1 text-xl font-extrabold text-slate-950">{workspace.grade_name} · Sección {workspace.section_name}</h2></div><span aria-hidden="true" className="material-symbols-outlined relative top-[3px] text-3xl text-indigo-500">school</span></div><p className="mt-2 text-xs text-slate-500">{workspace.shift} · {workspace.academic_year_name}</p></div>
    <div className="p-5"><div className="grid grid-cols-3 gap-2 border-b border-slate-100 pb-5 text-center"><Stat label="Estudiantes" value={workspace.students_count} /><Stat label="Activos" value={workspace.active_students_count} /><Stat label="Inactivos" value={workspace.inactive_students_count} /></div><div className="mt-5 flex items-center justify-between gap-3"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Workspace del curso</p><p className="mt-1 text-xs text-slate-500">Editar, activar y gestionar matrículas</p></div><button type="button" onClick={(event) => { event.stopPropagation(); onOpen(); }} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-slate-800">Abrir <span aria-hidden="true" className="material-symbols-outlined text-[18px]">arrow_forward</span></button></div></div>
  </article>;
}

function Kpi({ label, value, icon }) {
  return <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start justify-between"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p><p className="mt-2 text-2xl font-extrabold text-slate-950">{value}</p></div><span aria-hidden="true" className="material-symbols-outlined relative top-[2px] text-slate-400">{icon}</span></div></div>;
}

function Stat({ label, value }) {
  return <div><p className="text-[9px] font-extrabold uppercase tracking-wide text-slate-400">{label}</p><p className="mt-1 text-lg font-extrabold text-slate-950">{value}</p></div>;
}
