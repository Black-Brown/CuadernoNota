import React, { useId, useState } from 'react';
import { createPortal } from 'react-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { deactivateStudent, deleteAdminStudent, updateAdminStudent } from '../../api/admin.api';
import { getErrorMessage } from '../../utils/apiError';
import SideDrawer from '../ui/SideDrawer';
import ConfirmDialog from '../ui/ConfirmDialog';
import FormField, { inputClass } from '../ui/FormField';

const STUDENT_QUERY_KEYS = [
  'admin-students', 'admin-student', 'admin-student-placements', 'admin-dashboard',
  'admin-sections', 'admin-promotion-candidates', 'courses', 'attendance',
];

export default function StudentActions({ student, showLabels = false, onSuccess, onDeleted }) {
  const qc = useQueryClient();
  const formId = useId();
  const [action, setAction] = useState(null);
  const [form, setForm] = useState({ name: '', last_name: '', enrollment_no: '' });
  const [reason, setReason] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState('');

  const mutation = useMutation({
    mutationFn: ({ action: requestedAction, payload }) => {
      if (requestedAction === 'edit') return updateAdminStudent(student.id, payload);
      if (requestedAction === 'deactivate') return deactivateStudent(student.id, { reason: payload });
      return deleteAdminStudent(student.id, payload);
    },
    onSuccess: (result, variables) => {
      setAction(null);
      if (variables.action === 'delete') {
        qc.removeQueries({ queryKey: ['admin-student', String(student.id)] });
        onDeleted?.();
      }
      qc.invalidateQueries({ predicate: (query) => STUDENT_QUERY_KEYS.includes(query.queryKey[0]) });
      onSuccess?.(variables.action === 'edit' ? 'Datos del estudiante actualizados correctamente.' : result.message);
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const openAction = (nextAction) => {
    setForm({ name: student.name, last_name: student.last_name, enrollment_no: student.enrollment_no });
    setReason('');
    setConfirmation('');
    setError('');
    setAction(nextAction);
  };
  const close = () => { if (!mutation.isPending) setAction(null); };
  const fullName = `${student.name} ${student.last_name}`;
  const errorNotice = error && <p role="alert" className="mb-4 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>;
  const validForm = form.name.trim() && form.last_name.trim() && form.enrollment_no.trim();

  const actions = [
    { key: 'edit', label: 'Editar', icon: 'edit', color: 'text-slate-600 hover:bg-indigo-50 hover:text-indigo-600' },
    { key: 'deactivate', label: 'Desactivar', icon: 'person_off', color: 'text-slate-600 hover:bg-amber-50 hover:text-amber-700', disabled: !student.active },
    { key: 'delete', label: 'Eliminar', icon: 'delete', color: 'text-red-500 hover:bg-red-50 hover:text-red-700' },
  ];

  return (
    <div onClick={(event) => event.stopPropagation()}>
      <div role="group" aria-label={`Acciones de ${fullName}`} className="flex flex-wrap items-center justify-end gap-1.5">
        {actions.map(({ key, label, icon, color, disabled }) => <button
          key={key}
          type="button"
          aria-label={`${label} a ${fullName}`}
          title={disabled ? 'El estudiante ya está inactivo' : label}
          disabled={disabled || mutation.isPending}
          onClick={() => openAction(key)}
          className={`inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${showLabels ? 'gap-2 px-3 py-2.5 text-xs font-bold' : 'h-9 w-9'} ${color}`}
        >
          <span aria-hidden="true" className="material-symbols-outlined" style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 20, height: 20, fontSize: 20, lineHeight: 1 }}>{icon}</span>
          {showLabels && label}
        </button>)}
      </div>

      {action && createPortal(<>
        <SideDrawer
          open={action === 'edit'}
          onClose={close}
          title="Editar estudiante"
          description="Actualiza nombre, apellido y matrícula. Su sección, estado e historial no se modificarán."
          footer={<div className="flex justify-end gap-3">
            <button type="button" disabled={mutation.isPending} onClick={close} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 disabled:opacity-50">Cancelar</button>
            <button type="submit" form={formId} disabled={mutation.isPending || !validForm} className="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-50">
              {mutation.isPending ? 'Guardando…' : 'Guardar cambios'}
            </button>
          </div>}
        >
          <form id={formId} className="space-y-4" onSubmit={(event) => {
            event.preventDefault();
            if (mutation.isPending || !validForm) return;
            setError('');
            mutation.mutate({ action: 'edit', payload: Object.fromEntries(Object.entries(form).map(([key, value]) => [key, value.trim()])) });
          }}>
            {errorNotice}
            <FormField htmlFor={`${formId}-name`} label="Nombre" required>
              <input id={`${formId}-name`} required maxLength={60} autoFocus disabled={mutation.isPending} className={inputClass} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
            </FormField>
            <FormField htmlFor={`${formId}-last-name`} label="Apellido" required>
              <input id={`${formId}-last-name`} required maxLength={60} disabled={mutation.isPending} className={inputClass} value={form.last_name} onChange={(event) => setForm({ ...form, last_name: event.target.value })} />
            </FormField>
            <FormField htmlFor={`${formId}-enrollment`} label="Número de matrícula" required hint="Debe ser único para cada estudiante.">
              <input id={`${formId}-enrollment`} required maxLength={20} disabled={mutation.isPending} className={inputClass} value={form.enrollment_no} onChange={(event) => setForm({ ...form, enrollment_no: event.target.value })} />
            </FormField>
          </form>
        </SideDrawer>

        <ConfirmDialog
          open={action === 'deactivate'}
          onClose={close}
          onConfirm={() => { setError(''); mutation.mutate({ action: 'deactivate', payload: reason.trim() }); }}
          loading={mutation.isPending}
          disableConfirm={!reason.trim()}
          tone="danger"
          title="Desactivar estudiante"
          message={`Se dará de baja a ${fullName} y se retirarán sus matrículas activas. El expediente y todo su historial académico se conservarán.`}
          confirmLabel="Desactivar"
        >
          {errorNotice}
          <FormField htmlFor={`${formId}-reason`} label="Motivo de la baja" required>
            <textarea id={`${formId}-reason`} autoFocus rows={3} maxLength={200} disabled={mutation.isPending} className={inputClass} value={reason} onChange={(event) => setReason(event.target.value)} placeholder="Ej. Traslado a otro centro educativo" />
          </FormField>
        </ConfirmDialog>

        <ConfirmDialog
          open={action === 'delete'}
          onClose={close}
          onConfirm={() => { setError(''); mutation.mutate({ action: 'delete', payload: confirmation }); }}
          loading={mutation.isPending}
          disableConfirm={confirmation !== student.enrollment_no}
          tone="danger"
          title="Eliminar estudiante definitivamente"
          message={`Se eliminará el expediente de ${fullName}. Esta acción no se puede deshacer y solo está permitida si no tiene sección asignada ni historial académico.`}
          confirmLabel="Eliminar definitivamente"
        >
          {errorNotice}
          <p className="mb-4 text-xs leading-5 text-slate-500">Si tiene matrículas, notas, asistencia u otros registros, utiliza Desactivar para conservarlos.</p>
          <FormField htmlFor={`${formId}-confirmation`} label="Confirmar matrícula" required hint={`Escribe ${student.enrollment_no} exactamente para confirmar.`}>
            <input id={`${formId}-confirmation`} autoFocus autoComplete="off" disabled={mutation.isPending} className={inputClass} value={confirmation} onChange={(event) => setConfirmation(event.target.value)} />
          </FormField>
        </ConfirmDialog>
      </>, document.body)}
    </div>
  );
}
