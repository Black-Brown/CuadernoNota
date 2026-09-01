import React, { useEffect, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { importStudentsCsv, previewStudentImport } from '../../api/admin.api';
import { getErrorMessage } from '../../utils/apiError';
import SideDrawer from '../ui/SideDrawer';

export default function StudentImportDrawer({ open, onClose, onImported }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!open) { setFile(null); setPreview(null); setError(''); }
  }, [open]);

  const previewMutation = useMutation({
    mutationFn: () => previewStudentImport(file),
    onSuccess: (data) => { setPreview(data); setError(''); },
    onError: (err) => { setPreview(null); setError(getErrorMessage(err)); },
  });
  const importMutation = useMutation({
    mutationFn: () => importStudentsCsv(file),
    onSuccess: (data) => onImported(data),
    onError: (err) => setError(getErrorMessage(err)),
  });
  const busy = previewMutation.isPending || importMutation.isPending;
  const selectFile = (event) => {
    setFile(event.target.files?.[0] || null);
    setPreview(null);
    setError('');
  };

  return <SideDrawer open={open} onClose={() => { if (!busy) onClose(); }} widthClass="max-w-4xl"
    title="Importar estudiantes" description="Valida el listado antes de crear estudiantes y matrículas académicas."
    footer={<div className="flex flex-wrap items-center justify-between gap-3">
      <p className="text-xs text-slate-500">{preview ? `${preview.summary.valid} de ${preview.summary.total} registros listos` : 'Ningún registro se guardará sin confirmación.'}</p>
      <div className="flex gap-2">
        <button type="button" disabled={busy} onClick={onClose} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 disabled:opacity-40">Cancelar</button>
        {!preview && <button type="button" disabled={!file || busy} onClick={() => previewMutation.mutate()} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-40">
          {previewMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>} Validar archivo
        </button>}
        {preview && <button type="button" disabled={preview.summary.invalid > 0 || busy} onClick={() => importMutation.mutate()} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white disabled:opacity-40">
          {importMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>} Importar {preview.summary.valid} estudiantes
        </button>}
      </div>
    </div>}>
    <div className="space-y-5">
      <section className="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
        <div className="flex gap-3"><span aria-hidden="true" className="material-symbols-outlined relative top-[2px] text-indigo-600">info</span><div>
          <h3 className="text-sm font-extrabold text-slate-900">Estructura esperada</h3>
          <p className="mt-1 text-xs leading-5 text-slate-600">MATRICULA, NOMBRES, APELLIDOS, ANO_ESCOLAR, GRADO, SECCION, TANDA y FECHA_INSCRIPCION. NOMBRE_TUTOR es opcional.</p>
          <p className="mt-1 text-xs text-slate-500">La sección debe existir exactamente en el catálogo académico. La fecha debe usar AAAA-MM-DD.</p>
        </div></div>
      </section>

      <label className="block cursor-pointer rounded-xl border-2 border-dashed border-slate-200 bg-white p-6 text-center transition-colors hover:border-indigo-300 hover:bg-indigo-50/30">
        <span aria-hidden="true" className="material-symbols-outlined text-3xl text-indigo-600">upload_file</span>
        <span className="mt-2 block text-sm font-extrabold text-slate-900">{file?.name || 'Seleccionar archivo CSV'}</span>
        <span className="mt-1 block text-xs text-slate-500">Máximo 5 MB</span>
        <input type="file" accept=".csv,text/csv" onChange={selectFile} className="sr-only" />
      </label>

      {error && <div role="alert" className="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</div>}

      {preview && <>
        <div className="grid grid-cols-3 gap-3">
          <Summary label="Total" value={preview.summary.total} tone="slate" />
          <Summary label="Listos" value={preview.summary.valid} tone="emerald" />
          <Summary label="Con errores" value={preview.summary.invalid} tone="red" />
        </div>
        <div className="overflow-hidden rounded-xl border border-slate-200">
          <div className="max-h-[48dvh] overflow-auto">
            <table className="min-w-full text-left text-xs">
              <thead className="sticky top-0 z-10 bg-slate-50 text-[10px] font-extrabold uppercase tracking-wide text-slate-500">
                <tr><th className="px-4 py-3">Fila</th><th className="px-4 py-3">Matrícula</th><th className="px-4 py-3">Estudiante</th><th className="px-4 py-3">Destino</th><th className="px-4 py-3">Validación</th></tr>
              </thead>
              <tbody className="divide-y divide-slate-100 bg-white">
                {preview.rows.map((row) => <tr key={row.row_number} className={row.valid ? '' : 'bg-red-50/40'}>
                  <td className="px-4 py-3 font-mono text-slate-400">{row.row_number}</td>
                  <td className="whitespace-nowrap px-4 py-3 font-mono font-bold text-slate-700">{row.data.enrollment_no || '—'}</td>
                  <td className="px-4 py-3"><p className="font-bold text-slate-900">{row.data.name} {row.data.last_name}</p>{row.warnings?.map((warning) => <p key={warning} className="mt-1 text-amber-700">{warning}</p>)}</td>
                  <td className="min-w-56 px-4 py-3 text-slate-600">{row.section_label || `${row.data.grade} · Sección ${row.data.section}`}</td>
                  <td className="min-w-60 px-4 py-3">{row.valid ? <span className="inline-flex rounded-md bg-emerald-50 px-2 py-1 font-extrabold text-emerald-700">Lista</span> : <ul className="space-y-1 text-red-700">{row.errors.map((item) => <li key={item}>• {item}</li>)}</ul>}</td>
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
