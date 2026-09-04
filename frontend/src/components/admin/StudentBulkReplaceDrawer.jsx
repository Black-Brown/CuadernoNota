import React, { useEffect, useMemo, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { previewStudentBulkReplace, replaceStudentEnrollments } from '../../api/admin.api';
import { getErrorMessage } from '../../utils/apiError';
import SideDrawer from '../ui/SideDrawer';
import FormField, { inputClass } from '../ui/FormField';

export default function StudentBulkReplaceDrawer({ open, onClose, students, workspaceLabel, onApplied }) {
  const [search, setSearch] = useState('');
  const [replace, setReplace] = useState('');
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');
  const studentIds = useMemo(() => students.map((student) => Number(student.id)), [students]);
  const payload = { student_ids: studentIds, search, replace };

  useEffect(() => {
    if (!open) {
      setSearch('');
      setReplace('');
      setPreview(null);
      setError('');
    }
  }, [open]);

  const previewMutation = useMutation({
    mutationFn: previewStudentBulkReplace,
    retry: false,
    onSuccess: (data) => { setPreview(data); setError(''); },
    onError: (err) => { setPreview(null); setError(getErrorMessage(err)); },
  });
  const applyMutation = useMutation({
    mutationFn: replaceStudentEnrollments,
    retry: false,
    onSuccess: onApplied,
    onError: (err) => { setPreview(null); setError(getErrorMessage(err)); },
  });
  const busy = previewMutation.isPending || applyMutation.isPending;
  const matchedRows = preview?.rows.filter((row) => row.matched) || [];
  const canPreview = studentIds.length > 0 && search.length > 0 && !busy;
  const canApply = preview?.summary.ready > 0 && preview.summary.invalid === 0 && !busy;
  const updateValue = (setter) => (event) => {
    setter(event.target.value);
    setPreview(null);
    setError('');
  };

  return <SideDrawer
    open={open}
    onClose={() => { if (!busy) onClose(); }}
    widthClass="max-w-4xl"
    title="Buscar y reemplazar matrículas"
    description={`${students.length} estudiantes seleccionados · ${workspaceLabel}`}
    footer={<div className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-xs text-slate-500">Los cambios se aplican juntos: si uno falla, ninguno se guarda.</p>
      <div className="flex flex-wrap gap-2">
        <button type="button" disabled={busy} onClick={onClose} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 disabled:opacity-40">Cancelar</button>
        <button type="button" disabled={!canPreview} onClick={() => previewMutation.mutate(payload)} className={`flex items-center gap-2 rounded-lg border px-4 py-2.5 text-xs font-bold disabled:opacity-40 ${preview ? 'border-slate-200 text-slate-700' : 'border-slate-950 bg-slate-950 text-white'}`}>
          {previewMutation.isPending && <span aria-hidden="true" className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
          {preview ? 'Volver a validar' : 'Vista previa'}
        </button>
        {preview && <button type="button" disabled={!canApply} onClick={() => applyMutation.mutate(payload)} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-40">
          {applyMutation.isPending && <span aria-hidden="true" className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
          Aplicar {preview.summary.ready} cambios
        </button>}
      </div>
    </div>}
  >
    <div className="space-y-5">
      <section className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
        <div className="flex items-start gap-3">
          <span aria-hidden="true" className="material-symbols-outlined shrink-0 text-indigo-600">find_replace</span>
          <div>
            <h3 className="text-sm font-extrabold text-slate-900">Corrección controlada por selección</h3>
            <p className="mt-1 text-xs leading-5 text-slate-600">Busca un texto exacto dentro de la matrícula y sustitúyelo únicamente en los estudiantes seleccionados. Antes de guardar verás cada valor actual y su resultado.</p>
          </div>
        </div>
      </section>

      <div className="grid gap-4 sm:grid-cols-2">
        <FormField label="Buscar en la matrícula" required hint="Ejemplo: 2025-1SA">
          <input autoFocus maxLength={20} disabled={busy} className={inputClass} value={search} onChange={updateValue(setSearch)} placeholder="Texto incorrecto" />
        </FormField>
        <FormField label="Reemplazar por" hint="Puede quedar vacío si deseas eliminar el texto encontrado.">
          <input maxLength={20} disabled={busy} className={inputClass} value={replace} onChange={updateValue(setReplace)} placeholder="Texto correcto" />
        </FormField>
      </div>

      {error && <div role="alert" className="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</div>}

      {preview && <>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Summary label="Seleccionados" value={preview.summary.total} tone="slate" />
          <Summary label="Coincidencias" value={preview.summary.matched} tone="indigo" />
          <Summary label="Listos" value={preview.summary.ready} tone="emerald" />
          <Summary label="Con errores" value={preview.summary.invalid} tone="red" />
        </div>

        {matchedRows.length === 0 ? <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800">El texto buscado no aparece en las matrículas seleccionadas.</div> : <div className="overflow-hidden rounded-xl border border-slate-200">
          <div className="max-h-[48dvh] overflow-auto">
            <table className="min-w-full text-left text-xs">
              <thead className="sticky top-0 z-10 bg-slate-50 text-[10px] font-extrabold uppercase tracking-wide text-slate-500">
                <tr><th className="px-4 py-3">Estudiante</th><th className="px-4 py-3">Matrícula actual</th><th className="px-4 py-3">Resultado</th><th className="px-4 py-3">Validación</th></tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {matchedRows.map((row) => <tr key={row.student_id} className={row.valid ? '' : 'bg-red-50/40'}>
                  <td className="px-4 py-3 font-bold text-slate-900">{row.student_name}</td>
                  <td className="whitespace-nowrap px-4 py-3 font-mono text-slate-500">{row.current_enrollment_no}</td>
                  <td className="whitespace-nowrap px-4 py-3 font-mono font-bold text-indigo-700">{row.proposed_enrollment_no || 'Vacía'}</td>
                  <td className="min-w-48 px-4 py-3">{row.valid ? <span className="inline-flex rounded-md bg-emerald-50 px-2 py-1 font-extrabold text-emerald-700">Lista</span> : <ul className="space-y-1 text-red-700">{row.errors.map((item) => <li key={item}>• {item}</li>)}</ul>}</td>
                </tr>)}
              </tbody>
            </table>
          </div>
        </div>}
      </>}
    </div>
  </SideDrawer>;
}

function Summary({ label, value, tone }) {
  const colors = {
    slate: 'bg-slate-50 text-slate-700',
    indigo: 'bg-indigo-50 text-indigo-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    red: 'bg-red-50 text-red-700',
  };
  return <div className={`rounded-xl p-4 ${colors[tone]}`}><p className="text-[10px] font-extrabold uppercase tracking-wide opacity-70">{label}</p><p className="mt-1 text-2xl font-extrabold">{value}</p></div>;
}
