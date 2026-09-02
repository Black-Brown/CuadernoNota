import React, { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAdminStudent, getAdminStudents } from '../../api/admin.api';
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
import StudentImportDrawer from '../../components/admin/StudentImportDrawer';
import StudentActions from '../../components/admin/StudentActions';

const EMPTY_FORM = { name: '', last_name: '', enrollment_no: '' };

export default function AdminStudents() {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const location = useLocation();
  const { toast, showToast } = useToast();

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

  const { data, isLoading } = useQuery({ queryKey: ['admin-students', params], queryFn: () => getAdminStudents(params) });
  const students = data?.data || [];

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
        description="Inscripciones activas e historial académico del estudiantado."
        actions={<div className="flex flex-wrap gap-2">
          <button onClick={() => setImportOpen(true)} className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
            <span className="material-symbols-outlined text-[18px]">upload_file</span> Importar CSV
          </button>
          <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
            <span className="material-symbols-outlined text-[18px]">person_add_alt</span> Registrar estudiante
          </button>
        </div>}
      />

      <FilterBar>
        <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Buscar por nombre o matrícula..." className="max-w-xs" />
        <select value={active} onChange={(e) => { setActive(e.target.value); setPage(1); }} className={`${selectClass} w-auto`}>
          <option value="">Todos los estados</option>
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </FilterBar>

      {importedCount > 0 && <div role="status" className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
        <div><p className="font-bold">{importedCount} {importedCount === 1 ? 'estudiante registrado' : 'estudiantes registrados'} correctamente</p><p className="mt-1 text-xs">Registro completado. El siguiente paso es la asignación a una sección.</p></div>
        <Link to="/admin/student-placements" className="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-white px-3 py-2 text-xs font-bold hover:bg-emerald-100">Asignar estudiantes <span aria-hidden="true" className="material-symbols-outlined text-[18px]">arrow_forward</span></Link>
      </div>}

      <DataTable
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
      />

      {data?.last_page > 1 && (
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
