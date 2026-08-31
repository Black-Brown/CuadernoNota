import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAdminStudent, getAdminStudents, getSections } from '../../api/admin.api';
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

const EMPTY_FORM = { name: '', last_name: '', enrollment_no: '', section_id: '', enrolled_at: '' };

export default function AdminStudents() {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const { toast, showToast } = useToast();

  const [search, setSearch] = useState('');
  const [sectionId, setSectionId] = useState('');
  const [active, setActive] = useState('');
  const [page, setPage] = useState(1);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState('');

  const params = { per_page: 25, page };
  if (search) params.search = search;
  if (sectionId) params.section_id = sectionId;
  if (active !== '') params.active = active;

  const { data, isLoading } = useQuery({ queryKey: ['admin-students', params], queryFn: () => getAdminStudents(params) });
  const { data: sections } = useQuery({ queryKey: ['admin-sections'], queryFn: () => getSections() });
  const students = data?.data || [];

  const createMutation = useMutation({
    mutationFn: createAdminStudent,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-students'] });
      setDrawerOpen(false);
      setForm(EMPTY_FORM);
      showToast('Estudiante inscrito correctamente.');
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
        actions={
          <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
            <span className="material-symbols-outlined text-[18px]">person_add_alt</span>
            Inscribir estudiante
          </button>
        }
      />

      <FilterBar>
        <SearchInput value={search} onChange={(v) => { setSearch(v); setPage(1); }} placeholder="Buscar por nombre o matrícula..." className="max-w-xs" />
        <select value={sectionId} onChange={(e) => { setSectionId(e.target.value); setPage(1); }} className={`${selectClass} w-auto`}>
          <option value="">Todas las secciones</option>
          {sections?.map((s) => <option key={s.id} value={s.id}>{s.grade?.name} {s.name} · {s.academicYear?.name}</option>)}
        </select>
        <select value={active} onChange={(e) => { setActive(e.target.value); setPage(1); }} className={`${selectClass} w-auto`}>
          <option value="">Todos los estados</option>
          <option value="1">Activo</option>
          <option value="0">Inactivo</option>
        </select>
      </FilterBar>

      <DataTable
        loading={isLoading}
        rows={students}
        onRowClick={(s) => navigate(`/admin/students/${s.id}`)}
        emptyIcon="school"
        emptyTitle="No hay estudiantes que coincidan con los filtros."
        columns={[
          { key: 'enrollment_no', label: 'Matrícula', render: (s) => <span className="font-mono text-xs font-bold text-slate-500">{s.enrollment_no}</span> },
          { key: 'name', label: 'Estudiante', render: (s) => <span className="font-bold text-slate-900">{s.name} {s.last_name}</span> },
          { key: 'section', label: 'Grado / Sección', render: (s) => s.section ? `${s.section.grade?.name} ${s.section.name}` : '—' },
          { key: 'year', label: 'Año escolar', render: (s) => s.section?.academicYear?.name || '—' },
          { key: 'active', label: 'Estado', align: 'center', render: (s) => <StatusBadge tone={s.active ? 'success' : 'neutral'} label={s.active ? 'Activo' : 'Inactivo'} /> },
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
        title="Inscribir estudiante"
        description="Registra una nueva matrícula académica."
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button
              onClick={() => createMutation.mutate(form)}
              disabled={createMutation.isPending || !form.name || !form.last_name || !form.enrollment_no || !form.section_id || !form.enrolled_at}
              className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {createMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Inscribir
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
          <FormField label="Sección" required>
            <select className={selectClass} value={form.section_id} onChange={(e) => setForm({ ...form, section_id: e.target.value })}>
              <option value="">Seleccionar sección</option>
              {sections?.map((s) => <option key={s.id} value={s.id}>{s.grade?.name} {s.name} · {s.academicYear?.name}</option>)}
            </select>
          </FormField>
          <FormField label="Fecha de inscripción" required>
            <input type="date" className={inputClass} value={form.enrolled_at} onChange={(e) => setForm({ ...form, enrolled_at: e.target.value })} />
          </FormField>
        </div>
      </SideDrawer>

      <Toast toast={toast} />
    </>
  );
}
