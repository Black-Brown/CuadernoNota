import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getCoordinatorAssignments } from '../api/coordinator.api';
import { updateAdminUser } from '../api/admin.api';
import SideDrawer from './ui/SideDrawer';
import FormField, { selectClass } from './ui/FormField';
import { getErrorMessage } from '../utils/apiError';

export default function CoordinatorAssignmentDrawer({ user, onClose }) {
  const qc = useQueryClient();
  const [selection, setSelection] = useState(null);
  const { data, isLoading, isError } = useQuery({ queryKey: ['coordinator-assignments', user.id], queryFn: () => getCoordinatorAssignments(user.id) });
  const selected = selection ?? data?.coordinator_level ?? '';
  const mutation = useMutation({
    mutationFn: () => updateAdminUser(user.id, { coordinator_level: selected || null }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin-users'] }); qc.invalidateQueries({ queryKey: ['coordinator-assignments', user.id] }); qc.invalidateQueries({ queryKey: ['coordinator-dashboard'] }); onClose(); },
  });
  return <SideDrawer open onClose={onClose} title="Nivel de supervisión" description={user.name} footer={
    <div className="flex justify-end">
      <button disabled={isLoading || isError || mutation.isPending} onClick={() => mutation.mutate()} className="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-50">Guardar asignación</button></div>
  }>
    <p className="mb-4 text-sm text-slate-500">Supervisará todos los grados y secciones del nivel elegido, incluyendo las nuevas secciones. Cambiar de nivel revoca el acceso al anterior.</p>
    {isLoading && <p className="text-sm text-slate-500">Cargando supervisión...</p>}
    {(isError || mutation.isError) && <p role="alert" className="mb-4 text-sm text-red-600">{mutation.error ? getErrorMessage(mutation.error) : 'No se pudo cargar la supervisión.'}</p>}
    <FormField label="Nivel educativo">
      <select className={selectClass} disabled={isLoading || isError || mutation.isPending} value={selected} onChange={e => setSelection(e.target.value)}>
        <option value="">Sin acceso a cursos</option>
        <option value="Primaria">Primaria</option>
        <option value="Secundaria">Secundaria</option>
      </select>
    </FormField>
    {!data?.coordinator_level && data?.section_ids?.length > 0 && <p className="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Tiene {data.section_ids.length} asignaciones anteriores por sección. Guardar reemplaza esas asignaciones por el nivel seleccionado; guardar sin nivel revoca todas.</p>}
    {data?.unclassified_grades?.length > 0 && <div className="mt-4 rounded-lg border border-slate-200 p-3 text-sm text-slate-600">
      <p className="font-bold">Grados fuera de Primaria y Secundaria</p>
      <p>Estos grados no se incluirán automáticamente. Revisa su nivel en Catálogo académico si corresponde.</p>
      <ul className="mt-2 list-inside list-disc">{data.unclassified_grades.map(g => <li key={g.id}>{g.name} · {g.level || 'Sin clasificación'}</li>)}</ul>
    </div>}
  </SideDrawer>;
}
