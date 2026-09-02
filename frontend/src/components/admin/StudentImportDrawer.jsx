import React, { useEffect, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { importStudentsCsv, previewStudentImport } from '../../api/admin.api';
import { getErrorMessage } from '../../utils/apiError';
import { STUDENT_CSV_TEMPLATE, validateStudentCsvFile } from '../../utils/studentImport';
import SideDrawer from '../ui/SideDrawer';

export default function StudentImportDrawer({ open, onClose, onImported }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [validatedFile, setValidatedFile] = useState(null);
  const [error, setError] = useState('');
  const [onlyErrors, setOnlyErrors] = useState(false);

  useEffect(() => {
    if (!open) { setFile(null); setPreview(null); setValidatedFile(null); setError(''); setOnlyErrors(false); }
  }, [open]);

  const previewMutation = useMutation({
    mutationFn: previewStudentImport,
    retry: false,
    onSuccess: (data, uploadedFile) => { setPreview(data); setValidatedFile(uploadedFile); setOnlyErrors(false); setError(''); },
    onError: (err) => { setPreview(null); setValidatedFile(null); setError(getErrorMessage(err)); },
  });
  const importMutation = useMutation({
    mutationFn: importStudentsCsv,
    retry: false,
    onSuccess: (data) => onImported(data),
    onError: (err) => { setValidatedFile(null); setError(getErrorMessage(err)); },
  });
  const busy = previewMutation.isPending || importMutation.isPending;
  const canImport = preview?.summary.valid > 0 && preview.summary.invalid === 0 && validatedFile === file;
  const visibleRows = preview?.rows.filter((row) => !onlyErrors || !row.valid) || [];
  const selectFile = (event) => {
    if (busy) return;
    const selected = event.target.files?.[0];
    if (!selected) return;
    const validationError = validateStudentCsvFile(selected);
    setFile(validationError ? null : selected);
    setPreview(null);
    setValidatedFile(null);
    setOnlyErrors(false);
    setError(validationError);
    event.target.value = '';
  };

  return <SideDrawer open={open} onClose={() => { if (!busy) onClose(); }} widthClass="max-w-4xl"
    title="Agregar estudiantes masivamente" description="Registra sus expedientes. La sección se asignará después desde Gestión académica."
    footer={<div className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-xs text-slate-500">{preview ? `${preview.summary.valid} de ${preview.summary.total} registros listos` : 'Ningún registro se guardará sin confirmación.'}</p>
      <div className="flex flex-wrap gap-2">
        <button type="button" disabled={busy} onClick={onClose} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 disabled:opacity-40">Cancelar</button>
        <button type="button" disabled={!file || busy} onClick={() => previewMutation.mutate(file)} className={`flex items-center gap-2 rounded-lg border px-4 py-2.5 text-xs font-bold disabled:opacity-40 ${preview ? 'border-slate-200 text-slate-700' : 'border-slate-950 bg-slate-950 text-white'}`}>
          {previewMutation.isPending && <span aria-hidden="true" className="material-symbols-outlined animate-spin text-[16px]">sync</span>} {preview ? 'Volver a validar' : 'Validar archivo'}
        </button>
        {preview && <button type="button" disabled={!canImport || busy} onClick={() => importMutation.mutate(file)} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-40">
          {importMutation.isPending && <span aria-hidden="true" className="material-symbols-outlined animate-spin text-[16px]">sync</span>} Registrar {preview.summary.valid} {preview.summary.valid === 1 ? 'estudiante' : 'estudiantes'}
        </button>}
      </div>
    </div>}>
    <div className="space-y-5">
      <section className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
        <div className="flex items-start gap-3"><span aria-hidden="true" className="material-symbols-outlined shrink-0 text-indigo-600">info</span><div>
          <h3 className="text-sm font-extrabold text-slate-900">Solo necesitas tres columnas</h3>
          <p className="mt-1 font-mono text-xs font-semibold leading-5 text-slate-700">MATRICULA, NOMBRES, APELLIDOS</p>
          <p className="mt-1 text-xs leading-5 text-slate-600">También se acepta NOMBRE y APELLIDO, en cualquier orden. No necesitas año escolar, grado, sección ni fecha.</p>
          <p className="mt-1 text-xs leading-5 text-slate-600">Todos quedarán pendientes de asignación. No se modificarán estudiantes existentes.</p>
        </div></div>
      </section>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="max-w-lg text-xs leading-5 text-slate-500">Guarda como CSV UTF-8, separado por comas o punto y coma. En Excel, usa formato Texto para conservar los ceros iniciales de la matrícula.</p>
        <a href={`data:text/csv;charset=utf-8,${encodeURIComponent(STUDENT_CSV_TEMPLATE)}`} download="plantilla_estudiantes.csv" className="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-indigo-600 hover:bg-indigo-50">
          <span aria-hidden="true" className="material-symbols-outlined text-[18px]">download</span> Descargar plantilla
        </a>
      </div>

      <label className={`block rounded-xl border-2 border-dashed border-slate-200 bg-white p-6 text-center transition-colors focus-within:ring-2 focus-within:ring-indigo-500 ${busy ? 'cursor-wait opacity-60' : 'cursor-pointer hover:border-indigo-300 hover:bg-indigo-50/30'}`}>
        <span aria-hidden="true" className="material-symbols-outlined text-3xl text-indigo-600">upload_file</span>
        <span className="mt-2 block text-sm font-extrabold text-slate-900">{file?.name || 'Seleccionar archivo CSV'}</span>
        <span className="mt-1 block text-xs text-slate-500">Máximo 5 MB · 1,000 estudiantes por carga</span>
        <input aria-label="Archivo CSV de estudiantes" type="file" accept=".csv,text/csv" onChange={selectFile} disabled={busy} className="sr-only" />
      </label>

      {error && <div role="alert" className="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</div>}

      {preview && <>
        {preview.ignored_columns?.length > 0 && <div role="status" className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">
          <p className="font-bold">Estas columnas no se usarán en el registro:</p>
          <p className="mt-1 break-words">{preview.ignored_columns.join(', ')}</p>
          <p className="mt-1">Esta carga solo registra nombre, apellido y matrícula; no asigna secciones ni guarda otros datos adicionales.</p>
        </div>}
        <div className="grid grid-cols-3 gap-3">
          <Summary label="Total" value={preview.summary.total} tone="slate" />
          <Summary label="Listos" value={preview.summary.valid} tone="emerald" />
          <Summary label="Con errores" value={preview.summary.invalid} tone="red" />
        </div>
        <div className="flex flex-wrap items-center justify-between gap-3 text-xs">
          <p className="text-slate-600">{preview.summary.invalid > 0 ? 'Corrige los errores y vuelve a subir el archivo. No se guardarán filas parcialmente.' : 'Revisa los nombres y matrículas antes de confirmar el registro.'}</p>
          {preview.summary.invalid > 0 && <label className="flex items-center gap-2 font-semibold text-red-700"><input type="checkbox" checked={onlyErrors} onChange={(event) => setOnlyErrors(event.target.checked)} className="accent-indigo-600" /> Ver solo errores</label>}
        </div>
        <div className="overflow-hidden rounded-xl border border-slate-200">
          <div className="max-h-[48dvh] overflow-auto">
            <table className="min-w-full text-left text-xs">
              <thead className="sticky top-0 z-10 bg-slate-50 text-[10px] font-extrabold uppercase tracking-wide text-slate-500">
                <tr><th className="px-4 py-3">Fila</th><th className="px-4 py-3">Matrícula</th><th className="px-4 py-3">Nombres</th><th className="px-4 py-3">Apellidos</th><th className="px-4 py-3">Validación</th></tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {visibleRows.map((row) => <tr key={row.row_number} className={row.valid ? '' : 'bg-red-50/40'}>
                  <td className="px-4 py-3 font-mono text-slate-400">{row.row_number}</td>
                  <td className="whitespace-nowrap px-4 py-3 font-mono font-bold text-slate-700">{row.data.enrollment_no || '—'}</td>
                  <td className="px-4 py-3 font-bold text-slate-900">{row.data.name || '—'}</td>
                  <td className="px-4 py-3 font-bold text-slate-900">{row.data.last_name || '—'}</td>
                  <td className="min-w-48 px-4 py-3">{row.valid ? <span className="inline-flex rounded-md bg-emerald-50 px-2 py-1 font-extrabold text-emerald-700">Lista para registrar</span> : <ul className="space-y-1 text-red-700">{row.errors.map((item) => <li key={item}>• {item}</li>)}</ul>}</td>
                </tr>)}
              </tbody>
            </table>
          </div>
        </div>
      </>}
    </div>
  </SideDrawer>;
}

function Summary({ label, value, tone }) {
  const colors = { slate: 'bg-slate-50 text-slate-700', emerald: 'bg-emerald-50 text-emerald-700', red: 'bg-red-50 text-red-700' };
  return <div className={`rounded-xl p-4 ${colors[tone]}`}><p className="text-[10px] font-extrabold uppercase tracking-wide opacity-70">{label}</p><p className="mt-1 text-2xl font-extrabold">{value}</p></div>;
}
