import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAcademicYear, deleteAcademicYear, getAcademicYears, updateAcademicYear } from '../../../api/admin.api';
import useToast from '../../../hooks/useToast';
import { getErrorMessage } from '../../../utils/apiError';
import DataTable from '../../../components/ui/DataTable';
import StatusBadge from '../../../components/ui/StatusBadge';
import SideDrawer from '../../../components/ui/SideDrawer';
import ConfirmDialog from '../../../components/ui/ConfirmDialog';
import FormField, { inputClass } from '../../../components/ui/FormField';
import Toast from '../../../components/ui/Toast';

const EMPTY_FORM = { name: '', start_date: '', end_date: '', active: false };

export default function Years() {
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState('');
  const [deleteTarget, setDeleteTarget] = useState(null);

  const { data, isLoading } = useQuery({ queryKey: ['admin-years'], queryFn: getAcademicYears });

  const openCreate = () => { setEditing(null); setForm(EMPTY_FORM); setError(''); setDrawerOpen(true); };
  const openEdit = (year) => { setEditing(year); setForm({ name: year.name, start_date: year.start_date?.slice(0, 10) || '', end_date: year.end_date?.slice(0, 10) || '', active: year.active }); setError(''); setDrawerOpen(true); };

  const saveMutation = useMutation({
    mutationFn: () => editing ? updateAcademicYear(editing.id, form) : createAcademicYear(form),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-years'] });
      qc.invalidateQueries({ queryKey: ['admin-dashboard'] });
      setDrawerOpen(false);
      showToast(editing ? 'Año escolar actualizado.' : 'Año escolar creado.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAcademicYear(deleteTarget.id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-years'] });
      setDeleteTarget(null);
      showToast('Año escolar eliminado.');
    },
    onError: (err) => { showToast(getErrorMessage(err), 'error'); setDeleteTarget(null); },
  });

  return (
    <>
      <div className="mb-4 flex justify-end">
        <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
          <span className="material-symbols-outlined text-[18px]">add</span>
          Nuevo año escolar
        </button>
      </div>

      <DataTable
        loading={isLoading}
        rows={data}
        emptyIcon="calendar_month"
        emptyTitle="No hay años escolares registrados."
        columns={[
          { key: 'name', label: 'Nombre', render: (y) => <span className="font-bold text-slate-900">{y.name}</span> },
          { key: 'start_date', label: 'Inicio' },
          { key: 'end_date', label: 'Fin' },
          { key: 'active', label: 'Estado', align: 'center', render: (y) => <StatusBadge tone={y.active ? 'success' : 'neutral'} label={y.active ? 'Activo' : 'Inactivo'} /> },
          { key: 'periods_count', label: 'Períodos', align: 'center' },
          { key: 'sections_count', label: 'Secciones', align: 'center' },
          {
            key: 'actions', label: 'Acciones', align: 'right', render: (y) => (
              <div className="flex justify-end gap-1">
                <button onClick={() => openEdit(y)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-800">
                  <span className="material-symbols-outlined text-[18px]">edit</span>
                </button>
                <button onClick={() => setDeleteTarget(y)} className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600">
                  <span className="material-symbols-outlined text-[18px]">delete</span>
                </button>
              </div>
            ),
          },
        ]}
      />

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editing ? 'Editar año escolar' : 'Nuevo año escolar'}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.name || !form.start_date || !form.end_date} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50">
              {saveMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Guardar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}
          <FormField label="Nombre" required hint="Ej. 2026-2027">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Fecha de inicio" required>
            <input type="date" className={inputClass} value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
          </FormField>
          <FormField label="Fecha de fin" required>
            <input type="date" className={inputClass} value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
          </FormField>
          <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <input type="checkbox" checked={form.active} onChange={(e) => setForm({ ...form, active: e.target.checked })} />
            Marcar como año escolar activo
          </label>
          {form.active && <p className="text-[11px] text-slate-400">Al activar este año, cualquier otro año activo pasará a inactivo automáticamente.</p>}
        </div>
      </SideDrawer>

      <ConfirmDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => deleteMutation.mutate()}
        loading={deleteMutation.isPending}
        tone="danger"
        title="Eliminar año escolar"
        message={`Se eliminará "${deleteTarget?.name}". Solo es posible si no tiene períodos ni secciones asociadas.`}
        confirmLabel="Eliminar"
      />

      <Toast toast={toast} />
    </>
  );
}
