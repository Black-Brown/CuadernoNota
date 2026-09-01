import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createSubject, deactivateSubject, getGrades, getSubjects, updateSubject } from '../../../api/admin.api';
import useToast from '../../../hooks/useToast';
import { getErrorMessage } from '../../../utils/apiError';
import DataTable from '../../../components/ui/DataTable';
import StatusBadge from '../../../components/ui/StatusBadge';
import SideDrawer from '../../../components/ui/SideDrawer';
import ConfirmDialog from '../../../components/ui/ConfirmDialog';
import FormField, { inputClass } from '../../../components/ui/FormField';
import Toast from '../../../components/ui/Toast';

const EMPTY_FORM = { name: '', code: '', active: true, grade_ids: [] };

export default function Subjects() {
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState('');
  const [deactivateTarget, setDeactivateTarget] = useState(null);

  const { data, isLoading } = useQuery({ queryKey: ['admin-subjects'], queryFn: getSubjects });
  const { data: grades } = useQuery({ queryKey: ['admin-grades', 'all'], queryFn: () => getGrades({ status: 'all' }) });
  const selectableGrades = grades?.filter((g) => g.active || form.grade_ids.includes(g.id));

  const openCreate = () => { setEditing(null); setForm(EMPTY_FORM); setError(''); setDrawerOpen(true); };
  const openEdit = (subject) => { setEditing(subject); setForm({ name: subject.name, code: subject.code, active: subject.active, grade_ids: subject.grades?.map((g) => g.id) || [] }); setError(''); setDrawerOpen(true); };

  const toggleGrade = (id) => setForm((prev) => ({
    ...prev,
    grade_ids: prev.grade_ids.includes(id) ? prev.grade_ids.filter((g) => g !== id) : [...prev.grade_ids, id],
  }));

  const saveMutation = useMutation({
    mutationFn: () => editing ? updateSubject(editing.id, form) : createSubject(form),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-subjects'] });
      setDrawerOpen(false);
      showToast(editing ? 'Materia actualizada.' : 'Materia creada.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const deactivateMutation = useMutation({
    mutationFn: () => deactivateSubject(deactivateTarget.id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-subjects'] });
      setDeactivateTarget(null);
      showToast('Materia desactivada; el historial fue conservado.');
    },
    onError: (err) => { showToast(getErrorMessage(err), 'error'); setDeactivateTarget(null); },
  });

  return (
    <>
      <div className="mb-4 flex justify-end">
        <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
          <span className="material-symbols-outlined text-[18px]">add</span>
          Nueva materia
        </button>
      </div>

      <DataTable
        loading={isLoading}
        rows={data}
        emptyIcon="menu_book"
        emptyTitle="No hay materias registradas."
        columns={[
          { key: 'name', label: 'Nombre', render: (s) => <span className="font-bold text-slate-900">{s.name}</span> },
          { key: 'code', label: 'Código', render: (s) => <span className="font-mono text-xs">{s.code}</span> },
          { key: 'grades', label: 'Grados', render: (s) => (
            <div className="flex flex-wrap gap-1">
              {s.grades?.map((g) => <StatusBadge key={g.id} tone="indigo" label={g.name} />)}
            </div>
          ) },
          { key: 'course_offerings_count', label: 'Cursos', align: 'center' },
          { key: 'active', label: 'Estado', align: 'center', render: (s) => <StatusBadge tone={s.active ? 'success' : 'neutral'} label={s.active ? 'Activa' : 'Inactiva'} /> },
          {
            key: 'actions', label: 'Acciones', align: 'right', render: (s) => (
              <div className="flex justify-end gap-1">
                <button onClick={() => openEdit(s)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-800">
                  <span className="material-symbols-outlined text-[18px]">edit</span>
                </button>
                <button onClick={() => setDeactivateTarget(s)} disabled={!s.active} className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30">
                  <span className="material-symbols-outlined text-[18px]">block</span>
                </button>
              </div>
            ),
          },
        ]}
      />

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editing ? 'Editar materia' : 'Nueva materia'}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.name || !form.code || form.grade_ids.length === 0} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50">
              {saveMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Guardar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}
          <FormField label="Nombre" required>
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Código" required hint="Ej. MAT, LEN">
            <input className={inputClass} value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} />
          </FormField>
          <FormField label="Grados en los que se imparte" required>
            <div className="grid grid-cols-2 gap-2 rounded-lg border border-slate-200 p-3">
              {selectableGrades?.map((g) => (
                <label key={g.id} className="flex items-center gap-2 text-sm text-slate-700">
                  <input type="checkbox" checked={form.grade_ids.includes(g.id)} onChange={() => toggleGrade(g.id)} />
                  {g.name}
                </label>
              ))}
            </div>
          </FormField>
          {editing && (
            <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
              <input type="checkbox" checked={form.active} onChange={(e) => setForm({ ...form, active: e.target.checked })} />
              Materia activa
            </label>
          )}
        </div>
      </SideDrawer>

      <ConfirmDialog
        open={!!deactivateTarget}
        onClose={() => setDeactivateTarget(null)}
        onConfirm={() => deactivateMutation.mutate()}
        loading={deactivateMutation.isPending}
        tone="danger"
        title="Desactivar materia"
        message={`"${deactivateTarget?.name}" y sus cursos asociados se desactivarán. El historial se conserva.`}
        confirmLabel="Desactivar"
      />

      <Toast toast={toast} />
    </>
  );
}
