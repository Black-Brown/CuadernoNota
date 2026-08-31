import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createActivityTemplate, deactivateActivityTemplate, getActivityTemplates, updateActivityTemplate } from '../../../api/admin.api';
import useToast from '../../../hooks/useToast';
import { getErrorMessage } from '../../../utils/apiError';
import StatusBadge from '../../../components/ui/StatusBadge';
import EmptyState from '../../../components/ui/EmptyState';
import LoadingSkeleton from '../../../components/ui/LoadingSkeleton';
import SideDrawer from '../../../components/ui/SideDrawer';
import ConfirmDialog from '../../../components/ui/ConfirmDialog';
import FormField, { inputClass } from '../../../components/ui/FormField';
import Toast from '../../../components/ui/Toast';
import { getActivityPresentation } from '../../../utils/activityPresentation';

const EMPTY_FORM = { name: '', icon: 'add_task', active: true };

export default function ActivityTemplates() {
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [error, setError] = useState('');
  const [deactivateTarget, setDeactivateTarget] = useState(null);

  const { data, isLoading } = useQuery({ queryKey: ['admin-templates'], queryFn: getActivityTemplates });

  const openCreate = () => { setEditing(null); setForm(EMPTY_FORM); setError(''); setDrawerOpen(true); };
  const openEdit = (template) => { setEditing(template); setForm({ name: template.name, icon: getActivityPresentation(template).icon, active: template.active }); setError(''); setDrawerOpen(true); };

  const saveMutation = useMutation({
    mutationFn: () => editing ? updateActivityTemplate(editing.id, form) : createActivityTemplate(form),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-templates'] });
      setDrawerOpen(false);
      showToast(editing ? 'Actividad base actualizada.' : 'Actividad base creada.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const deactivateMutation = useMutation({
    mutationFn: () => deactivateActivityTemplate(deactivateTarget.id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-templates'] });
      setDeactivateTarget(null);
      showToast('Actividad base desactivada; el historial fue conservado.');
    },
    onError: (err) => { showToast(getErrorMessage(err), 'error'); setDeactivateTarget(null); },
  });

  return (
    <>
      <p className="mb-4 text-sm text-slate-600">Las seis actividades base fijas se conservan siempre, incluso al restablecer el sistema. No se pueden renombrar, desactivar ni eliminar.</p>
      <div className="mb-4 flex justify-end">
        <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
          <span className="material-symbols-outlined text-[18px]">add</span>
          Nueva actividad base
        </button>
      </div>

      {isLoading ? (
        <LoadingSkeleton rows={4} />
      ) : !data || data.length === 0 ? (
        <EmptyState icon="add_task" title="No hay actividades base registradas." />
      ) : (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
          {data.map((template) => {
            const meta = getActivityPresentation(template);
            return (
            <div key={template.id} className={`group flex min-h-[200px] flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 text-left transition-all hover:border-slate-800 hover:shadow-md ${!template.active ? 'opacity-60' : ''}`}>
              <div className="mb-4 flex items-start justify-between gap-3">
                <div aria-hidden="true" className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${meta.color}`}>
                  <span className="material-symbols-outlined text-[24px] leading-none">{meta.icon}</span>
                </div>
                <StatusBadge tone={template.active ? 'success' : 'neutral'} label={template.active ? 'Activa' : 'Inactiva'} />
              </div>
              <p className="text-sm font-extrabold text-slate-900">{template.name}</p>
              <p className="mt-2 text-xs leading-5 text-slate-500">{meta.desc}</p>
              {template.is_fixed && <p className="mt-1 text-xs font-bold text-indigo-700">Base fija · Protegida</p>}
              <p className="mt-1 text-[11px] text-slate-400">{template.course_activities_count ?? 0} usos</p>
              <div className="mt-4 flex gap-3 border-t border-slate-100 pt-3">
                <button onClick={() => openEdit(template)} className="flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800">
                  <span className="material-symbols-outlined text-[15px]">edit</span> Editar
                </button>
                {template.active && !template.is_fixed && (
                  <button onClick={() => setDeactivateTarget(template)} className="flex items-center gap-1 text-xs font-bold text-red-500 hover:text-red-700">
                    <span className="material-symbols-outlined text-[15px]">block</span> Desactivar
                  </button>
                )}
              </div>
            </div>
            );
          })}
        </div>
      )}

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editing ? 'Editar actividad base' : 'Nueva actividad base'}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.name} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50">
              {saveMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Guardar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}
          <FormField label="Nombre" required hint="Ej. Proyectos, Examen, Tareas">
            <input className={inputClass} disabled={editing?.is_fixed} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Ícono" hint="Nombre de un Material Symbol, ej. assignment">
            <input className={inputClass} value={form.icon} onChange={(e) => setForm({ ...form, icon: e.target.value })} />
          </FormField>
          <div className="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div aria-hidden="true" className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${getActivityPresentation(form).color}`}>
              <span className="material-symbols-outlined text-[24px] leading-none">{getActivityPresentation(form).icon}</span>
            </div>
            <p className="text-xs text-slate-500">Vista previa del ícono en el catálogo.</p>
          </div>
          {editing && !editing.is_fixed && (
            <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
              <input type="checkbox" checked={form.active} onChange={(e) => setForm({ ...form, active: e.target.checked })} />
              Actividad activa
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
        title="Desactivar actividad base"
        message={`"${deactivateTarget?.name}" dejará de proponerse a los docentes. El historial se conserva.`}
        confirmLabel="Desactivar"
      />

      <Toast toast={toast} />
    </>
  );
}
