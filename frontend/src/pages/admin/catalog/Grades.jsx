import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createGrade,
  deactivateGrade,
  deleteGrade,
  getGradeDeletionCheck,
  getGrades,
  reactivateGrade,
  updateGrade,
} from '../../../api/admin.api';
import useToast from '../../../hooks/useToast';
import { getErrorMessage } from '../../../utils/apiError';
import DataTable from '../../../components/ui/DataTable';
import SideDrawer from '../../../components/ui/SideDrawer';
import ConfirmDialog from '../../../components/ui/ConfirmDialog';
import ActionsMenu from '../../../components/ui/ActionsMenu';
import StatusBadge from '../../../components/ui/StatusBadge';
import FilterBar from '../../../components/ui/FilterBar';
import FormField, { inputClass } from '../../../components/ui/FormField';
import Toast from '../../../components/ui/Toast';

const EMPTY_FORM = { name: '', level: '', sort_order: '' };

const STATUS_TABS = [
  { key: 'active', label: 'Activos' },
  { key: 'inactive', label: 'Inactivos' },
  { key: 'all', label: 'Todos' },
];

export default function Grades() {
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [statusFilter, setStatusFilter] = useState('active');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState('');
  const [manageTarget, setManageTarget] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [blockedTarget, setBlockedTarget] = useState(null);
  const [deactivateTarget, setDeactivateTarget] = useState(null);
  const [reactivateTarget, setReactivateTarget] = useState(null);

  const { data, isLoading } = useQuery({
    queryKey: ['admin-grades', statusFilter],
    queryFn: () => getGrades({ status: statusFilter }),
  });

  const openCreate = () => { setEditing(null); setForm(EMPTY_FORM); setError(''); setDrawerOpen(true); };
  const openEdit = (grade) => { setEditing(grade); setForm({ name: grade.name, level: grade.level, sort_order: grade.sort_order }); setError(''); setDrawerOpen(true); };

  const invalidateGrades = () => qc.invalidateQueries({ queryKey: ['admin-grades'] });

  const saveMutation = useMutation({
    mutationFn: () => editing ? updateGrade(editing.id, form) : createGrade(form),
    onSuccess: () => {
      invalidateGrades();
      setDrawerOpen(false);
      showToast(editing ? 'Grado actualizado.' : 'Grado creado.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const checkMutation = useMutation({
    mutationFn: (grade) => getGradeDeletionCheck(grade.id).then((result) => ({ grade, result })),
    onSuccess: ({ grade, result }) => {
      if (result.can_delete) setDeleteTarget(grade);
      else setBlockedTarget({ grade, relations: result.relations });
    },
    onError: (err) => showToast(getErrorMessage(err), 'error'),
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteGrade(deleteTarget.id),
    onSuccess: () => {
      invalidateGrades();
      setDeleteTarget(null);
      showToast('El grado se eliminó correctamente.');
    },
    onError: (err) => { showToast(getErrorMessage(err), 'error'); setDeleteTarget(null); },
  });

  const deactivateMutation = useMutation({
    mutationFn: (id) => deactivateGrade(id),
    onSuccess: () => {
      invalidateGrades();
      setBlockedTarget(null);
      setDeactivateTarget(null);
      showToast('El grado se desactivó correctamente.');
    },
    onError: (err) => { showToast(getErrorMessage(err), 'error'); setBlockedTarget(null); setDeactivateTarget(null); },
  });

  const reactivateMutation = useMutation({
    mutationFn: (id) => reactivateGrade(id),
    onSuccess: () => {
      invalidateGrades();
      setReactivateTarget(null);
      showToast('El grado se reactivó correctamente.');
    },
    onError: (err) => { showToast(getErrorMessage(err), 'error'); setReactivateTarget(null); },
  });

  return (
    <>
      <div className="mb-4 flex justify-end">
        <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
          <span className="material-symbols-outlined text-[18px]">add</span>
          Nuevo grado
        </button>
      </div>

      <FilterBar>
        {STATUS_TABS.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setStatusFilter(tab.key)}
            className={`rounded-lg px-3 py-1.5 text-xs font-bold transition-colors ${
              statusFilter === tab.key ? 'bg-slate-950 text-white' : 'text-slate-500 hover:bg-slate-100'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </FilterBar>

      <DataTable
        loading={isLoading}
        rows={data}
        emptyIcon="stairs"
        emptyTitle="No hay grados registrados."
        columns={[
          { key: 'name', label: 'Nombre', render: (g) => <span className="font-bold text-slate-900">{g.name}</span> },
          { key: 'level', label: 'Nivel' },
          { key: 'sort_order', label: 'Orden', align: 'center' },
          { key: 'sections_count', label: 'Secciones', align: 'center' },
          { key: 'subjects_count', label: 'Materias', align: 'center' },
          { key: 'active', label: 'Estado', align: 'center', render: (g) => <StatusBadge tone={g.active ? 'success' : 'neutral'} label={g.active ? 'Activo' : 'Inactivo'} /> },
          {
            key: 'actions', label: 'Acciones', align: 'right', render: (g) => (
              <div className="flex justify-end">
                <ActionsMenu
                  items={[
                    { label: 'Editar', icon: 'edit', onClick: () => openEdit(g) },
                    { label: 'Administrar', icon: 'visibility', onClick: () => setManageTarget(g) },
                    { label: 'Secciones A/B/C', icon: 'domain', onClick: () => navigate(`/admin/institutional?tab=sections&grade=${g.id}`) },
                    g.active
                      ? { label: 'Desactivar', icon: 'block', onClick: () => setDeactivateTarget(g) }
                      : { label: 'Reactivar', icon: 'restart_alt', onClick: () => setReactivateTarget(g) },
                    { label: 'Eliminar', icon: 'delete', danger: true, disabled: checkMutation.isPending, onClick: () => checkMutation.mutate(g) },
                  ]}
                />
              </div>
            ),
          },
        ]}
      />

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editing ? 'Editar grado' : 'Nuevo grado'}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.name || !form.level || !form.sort_order} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50">
              {saveMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Guardar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}
          <FormField label="Nombre" required hint="Ej. 5to Grado">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Nivel" required hint="Ej. Primaria, Secundaria">
            <input className={inputClass} value={form.level} onChange={(e) => setForm({ ...form, level: e.target.value })} />
          </FormField>
          <FormField label="Orden" required hint="Determina el orden de visualización">
            <input type="number" min="1" max="127" className={inputClass} value={form.sort_order} onChange={(e) => setForm({ ...form, sort_order: e.target.value })} />
          </FormField>
        </div>
      </SideDrawer>

      <SideDrawer
        open={!!manageTarget}
        onClose={() => setManageTarget(null)}
        title={`Administrar ${manageTarget?.name || ''}`}
        description="Información asociada a este grado."
      >
        {manageTarget && (
          <div className="space-y-6">
            <section>
              <h3 className="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Información del grado</h3>
              <dl className="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 p-3 text-sm">
                <div><dt className="text-[10px] font-bold uppercase text-slate-400">Nombre</dt><dd className="font-semibold text-slate-800">{manageTarget.name}</dd></div>
                <div><dt className="text-[10px] font-bold uppercase text-slate-400">Nivel</dt><dd className="font-semibold text-slate-800">{manageTarget.level}</dd></div>
                <div><dt className="text-[10px] font-bold uppercase text-slate-400">Orden</dt><dd className="font-semibold text-slate-800">{manageTarget.sort_order}</dd></div>
                <div>
                  <dt className="text-[10px] font-bold uppercase text-slate-400">Estado</dt>
                  <dd><StatusBadge tone={manageTarget.active ? 'success' : 'neutral'} label={manageTarget.active ? 'Activo' : 'Inactivo'} /></dd>
                </div>
              </dl>
            </section>

            <section>
              <h3 className="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Secciones ({manageTarget.sections?.length || 0})</h3>
              {manageTarget.sections?.length ? (
                <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                  {manageTarget.sections.map((s) => (
                    <li key={s.id} className="flex items-center justify-between px-3 py-2 text-sm">
                      <span className="font-semibold text-slate-800">Sección {s.name}</span>
                      <span className="text-xs text-slate-500">{s.shift} · {s.academicYear?.name}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-400">Sin secciones asociadas.</p>
              )}
            </section>

            <section>
              <h3 className="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Materias ({manageTarget.subjects?.length || 0})</h3>
              {manageTarget.subjects?.length ? (
                <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200">
                  {manageTarget.subjects.map((s) => (
                    <li key={s.id} className="flex items-center justify-between px-3 py-2 text-sm">
                      <span className="font-semibold text-slate-800">{s.name}</span>
                      <StatusBadge tone={s.active ? 'success' : 'neutral'} label={s.active ? 'Activa' : 'Inactiva'} />
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-400">Sin materias asociadas.</p>
              )}
            </section>
          </div>
        )}
      </SideDrawer>

      <ConfirmDialog
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => deleteMutation.mutate()}
        loading={deleteMutation.isPending}
        tone="danger"
        title="Eliminar grado"
        message={`¿Estás seguro de que deseas eliminar "${deleteTarget?.name}"? Este grado no tiene información asociada y puede eliminarse de forma segura.`}
        confirmLabel="Eliminar grado"
      />

      <ConfirmDialog
        open={!!blockedTarget}
        onClose={() => setBlockedTarget(null)}
        onConfirm={() => deactivateMutation.mutate(blockedTarget.grade.id)}
        loading={deactivateMutation.isPending}
        title="No se puede eliminar este grado"
        message={`"${blockedTarget?.grade?.name}" tiene información asociada que debe conservarse. Para conservar esta información puedes desactivar el grado.`}
        confirmLabel="Desactivar grado"
      >
        {blockedTarget && (
          <dl className="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
            {blockedTarget.relations.sections > 0 && (
              <div><dt className="text-[10px] font-bold uppercase text-slate-400">Secciones</dt><dd className="font-extrabold text-slate-800">{blockedTarget.relations.sections}</dd></div>
            )}
            {blockedTarget.relations.subjects > 0 && (
              <div><dt className="text-[10px] font-bold uppercase text-slate-400">Materias</dt><dd className="font-extrabold text-slate-800">{blockedTarget.relations.subjects}</dd></div>
            )}
          </dl>
        )}
      </ConfirmDialog>

      <ConfirmDialog
        open={!!deactivateTarget}
        onClose={() => setDeactivateTarget(null)}
        onConfirm={() => deactivateMutation.mutate(deactivateTarget.id)}
        loading={deactivateMutation.isPending}
        title="Desactivar grado"
        message="Puedes desactivar este grado para que deje de utilizarse en nuevas operaciones, conservando toda su información histórica."
        confirmLabel="Desactivar grado"
      />

      <ConfirmDialog
        open={!!reactivateTarget}
        onClose={() => setReactivateTarget(null)}
        onConfirm={() => reactivateMutation.mutate(reactivateTarget.id)}
        loading={reactivateMutation.isPending}
        title={`¿Reactivar ${reactivateTarget?.name || ''}?`}
        message="El grado volverá a estar disponible para nuevas operaciones."
        confirmLabel="Reactivar"
      />

      <Toast toast={toast} />
    </>
  );
}
