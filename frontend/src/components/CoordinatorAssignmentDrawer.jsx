import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getCoordinatorAssignments, saveCoordinatorAssignments } from '../api/coordinator.api';
import SideDrawer from './ui/SideDrawer';
import SearchInput from './ui/SearchInput';
import { getErrorMessage } from '../utils/apiError';

export default function CoordinatorAssignmentDrawer({ user, onClose }) {
  const qc = useQueryClient();
  const [selection, setSelection] = useState(null);
  const [search, setSearch] = useState('');
  const { data, isLoading, isError } = useQuery({ queryKey: ['coordinator-assignments', user.id], queryFn: () => getCoordinatorAssignments(user.id) });
  const selected = selection ?? data?.section_ids ?? [];
  const mutation = useMutation({
    mutationFn: () => saveCoordinatorAssignments(user.id, selected),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['coordinator-assignments', user.id] }); qc.invalidateQueries({ queryKey: ['coordinator-dashboard'] }); onClose(); },
  });
  const label = section => `${section.year_name} · ${section.grade_name} · Sección ${section.name} · ${section.shift}`;
  return <SideDrawer open onClose={onClose} title="Secciones de supervisión" description={user.name} footer={
    <div className="flex items-center justify-between gap-3"><span className="text-xs text-slate-500">{selected.length} seleccionadas</span>
      <button disabled={isLoading || isError || mutation.isPending} onClick={() => mutation.mutate()} className="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-50">Guardar asignación</button></div>
  }>
    <p className="mb-4 text-sm text-slate-500">El coordinador podrá consultar y revisar las materias de estas secciones. Sin selección no tendrá acceso a ningún curso.</p>
    <SearchInput value={search} onChange={setSearch} placeholder="Buscar año, grado o sección..." />
    {isLoading && <p className="mt-4 text-sm text-slate-500">Cargando secciones...</p>}
    {(isError || mutation.isError) && <p role="alert" className="mt-4 text-sm text-red-600">{mutation.error ? getErrorMessage(mutation.error) : 'No se pudieron cargar las secciones.'}</p>}
    <div className="mt-4 space-y-2">{data?.sections.filter(s => label(s).toLocaleLowerCase().includes(search.toLocaleLowerCase())).map(section =>
      <label key={section.id} className="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
        <input type="checkbox" checked={selected.includes(section.id)} onChange={e => setSelection(e.target.checked ? [...selected, section.id] : selected.filter(id => id !== section.id))} />
        {label(section)}
      </label>)}</div>
  </SideDrawer>;
}
