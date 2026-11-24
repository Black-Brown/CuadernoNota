import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { bulkDeleteStudents } from '../../api/admin.api';
import { getErrorMessage } from '../../utils/apiError';
import ConfirmDialog from '../ui/ConfirmDialog';
import FormField, { inputClass } from '../ui/FormField';

export default function StudentBulkDelete({ students, onDeleted }) {
  const qc = useQueryClient();
  const [selection, setSelection] = useState([]);
  const [confirmation, setConfirmation] = useState('');
  const mutation = useMutation({
    mutationFn: bulkDeleteStudents,
    onSuccess: (result) => {
      setSelection([]);
      qc.invalidateQueries({ predicate: (query) => ['admin-students', 'admin-student', 'admin-student-workspaces', 'admin-student-placements', 'admin-dashboard'].includes(query.queryKey[0]) });
      onDeleted(result);
    },
  });
  return <>
    <button type="button" disabled={!students.length} onClick={() => { setSelection([...students]); setConfirmation(''); mutation.reset(); }} className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-100 disabled:opacity-40">
      <span aria-hidden="true" className="material-symbols-outlined text-[17px]">delete</span> Eliminar seleccionados ({students.length})
    </button>
    {selection.length > 0 && createPortal(<ConfirmDialog open onClose={() => { if (!mutation.isPending) setSelection([]); }}
      title={`Eliminar ${selection.length} estudiantes`} tone="danger" confirmLabel={`Eliminar ${selection.length} estudiantes`}
      message="Esta acción elimina definitivamente los expedientes seleccionados. Solo se permite si no tienen sección asignada ni historial académico. Si alguno no cumple, no se eliminará ninguno."
      loading={mutation.isPending} disableConfirm={confirmation !== 'ELIMINAR'}
      onConfirm={() => mutation.mutate({ student_ids: selection.map(student => student.id), confirmation })}>
      <ul className="mb-4 max-h-36 overflow-y-auto text-xs text-slate-600">{selection.map(student => <li key={student.id}>{student.enrollment_no} · {student.name} {student.last_name}</li>)}</ul>
      {mutation.isError && <p role="alert" className="mb-3 text-sm text-red-600">{getErrorMessage(mutation.error)}</p>}
      <FormField htmlFor="bulk-delete-confirmation" label="Escribe ELIMINAR para confirmar" required>
        <input id="bulk-delete-confirmation" autoFocus autoComplete="off" className={inputClass} disabled={mutation.isPending} value={confirmation} onChange={event => setConfirmation(event.target.value)} />
      </FormField>
    </ConfirmDialog>, document.body)}
  </>;
}
