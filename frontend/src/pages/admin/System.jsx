import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { previewSystemReset, resetSystemData } from '../../api/admin.api';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';

const CONFIRMATION = 'RESTABLECER DATOS';
const number = value => new Intl.NumberFormat('es-DO').format(value);
const dangerButton = 'rounded-lg bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40';
const secondaryButton = 'rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40';

function EntityCounts({ title, rows, danger = false }) {
  return (
    <section className={`overflow-hidden rounded-xl border ${danger ? 'border-red-200' : 'border-slate-200'}`}>
      <h3 className={`px-4 py-3 text-sm font-bold ${danger ? 'bg-red-50 text-red-900' : 'bg-slate-50 text-slate-900'}`}>{title}</h3>
      <dl className="divide-y divide-slate-100 bg-white px-4">
        {rows.map(row => (
          <div key={row.table} className="flex items-start justify-between gap-4 py-2.5 text-sm">
            <dt className="text-slate-600">{row.label}</dt>
            <dd className="font-semibold tabular-nums text-slate-900">{number(row.count)}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function System() {
  const queryClient = useQueryClient();
  const [preview, setPreview] = useState(null);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [stage, setStage] = useState(0);
  const [confirmation, setConfirmation] = useState('');
  const [backupAcknowledged, setBackupAcknowledged] = useState(false);
  const dialog = useRef(null);
  const confirmInput = useRef(null);
  const trigger = useRef(null);

  const loadPreview = useMutation({
    mutationFn: previewSystemReset,
    retry: false,
    onMutate: () => { setError(''); setPreview(null); setResult(null); setBackupAcknowledged(false); },
    onSuccess: setPreview,
    onError: err => setError(getErrorMessage(err)),
  });

  const reset = useMutation({
    mutationFn: resetSystemData,
    retry: false,
    onSuccess: data => {
      setResult(data);
      setPreview(null);
      setStage(0);
      setConfirmation('');
      // Clear cached academic lists in both portals without clearing the login session.
      queryClient.invalidateQueries();
    },
    onError: err => {
      setStage(0);
      setConfirmation('');
      setPreview(null);
      setError(err.response
        ? getErrorMessage(err)
        : 'No se recibió una respuesta. No repitas la operación: revisa Auditoría para comprobar si se completó y luego solicita un resumen nuevo.');
    },
  });

  useEffect(() => {
    if (stage && !dialog.current.open) dialog.current.showModal();
    if (!stage && dialog.current.open) {
      dialog.current.close();
      trigger.current?.focus();
    }
    if (stage === 2) confirmInput.current?.focus();
  }, [stage]);

  const close = () => { if (!reset.isPending) { setStage(0); setConfirmation(''); } };
  const submit = () => {
    if (!preview || confirmation !== CONFIRMATION || reset.isPending) return;
    if (Date.now() >= Date.parse(preview.expires_at)) {
      close();
      setPreview(null);
      setError('El resumen venció. Consulta una vista previa nueva antes de confirmar.');
      return;
    }
    setError('');
    reset.mutate({ confirmation, preview_token: preview.preview_token });
  };

  return (
    <>
      <PageHeader breadcrumb={['Portal Administrativo', 'Zona peligrosa']} title="Zona peligrosa"
        description="Operaciones sensibles sobre los datos de toda la institución, no solo del año seleccionado." />

      <section className="rounded-2xl border border-red-200 bg-white p-5 sm:p-7">
        <div className="flex items-start gap-3">
          <span aria-hidden="true" className="material-symbols-outlined rounded-xl bg-red-50 p-3 text-red-700">warning</span>
          <div>
            <h2 className="text-lg font-extrabold text-slate-950">Restablecer datos del sistema</h2>
            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Elimina los datos institucionales y académicos de todos los años. Conserva usuarios —incluidos los profesores—, roles, credenciales, años y períodos escolares, configuración técnica y auditoría.</p>
          </div>
        </div>

        <div className="my-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-900">
          <p className="font-bold">Esta operación es irreversible desde la aplicación.</p>
          <p>Antes de continuar, realiza y verifica una copia completa de la base de datos con las herramientas de tu servidor. La exportación JSON disponible en Auditoría no sustituye un respaldo completo con un procedimiento de restauración comprobado.</p>
          <p className="mt-2">Realiza el proceso sin actividad de otros usuarios y con los workers detenidos. No ejecutes migraciones durante el restablecimiento.</p>
        </div>

        {error && <p role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{error} <Link to="/admin/audit" className="font-bold underline">Ver auditoría</Link></p>}

        {!result && <button type="button" className={secondaryButton} disabled={loadPreview.isPending || reset.isPending || stage > 0}
          onClick={() => loadPreview.mutate()}>{loadPreview.isPending ? 'Revisando esquema y contando registros…' : preview ? 'Actualizar resumen' : 'Consultar datos que se eliminarán'}</button>}

        {preview && <div className="mt-6 space-y-5">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h3 className="font-bold text-slate-900">Se eliminarán {number(preview.total_to_delete)} registros</h3>
            <p className="text-xs text-slate-500">Resumen válido hasta {new Date(preview.expires_at).toLocaleTimeString('es-DO')}</p>
          </div>
          <div className="grid items-start gap-5 lg:grid-cols-2">
            <EntityCounts title="Se eliminará" rows={preview.delete} danger />
            <EntityCounts title="Se conservará" rows={preview.preserve} />
          </div>
          <p className="text-sm leading-6 text-slate-600">También se limpia el historial académico. Se conservan las competencias C1–C3 y el catálogo de actividades base, incluidas las seis fijas con sus mismos identificadores. Solo se eliminan las actividades de los cursos y sus calificaciones. No se borran tablas, vistas, relaciones ni migraciones.</p>
          <label className="flex items-start gap-3 text-sm text-slate-700">
            <input type="checkbox" className="mt-1 h-4 w-4 accent-red-700" checked={backupAcknowledged} onChange={e => setBackupAcknowledged(e.target.checked)} />
            Entiendo que esta acción afecta todos los años, es irreversible desde la aplicación y requiere un respaldo externo verificado.
          </label>
          <button ref={trigger} type="button" className={dangerButton} disabled={!backupAcknowledged || reset.isPending}
            onClick={() => { setConfirmation(''); setStage(1); }}>Restablecer datos del sistema</button>
        </div>}

        {result && <div role="status" className="mt-5 space-y-5">
          <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950">
            <h3 className="text-lg font-bold">Restablecimiento completado</h3>
            <p className="mt-2 text-sm">Los datos institucionales y académicos fueron eliminados correctamente.</p>
            <dl className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {[['Registros eliminados', result.total_deleted], ['Usuarios conservados', result.users_preserved], ['Años conservados', result.academic_years_preserved], ['Períodos conservados', result.periods_preserved]].map(([label, count]) => (
                <div key={label}><dt className="text-xs">{label}</dt><dd className="mt-1 text-2xl font-extrabold tabular-nums">{number(count)}</dd></div>
              ))}
            </dl>
          </div>
          <div className="flex flex-wrap gap-4 text-sm font-bold text-indigo-700">
            <Link to="/admin/catalog" className="underline">Configurar grados, materias y secciones</Link>
            <Link to="/admin/audit" className="underline">Consultar auditoría</Link>
          </div>
          <EntityCounts title="Detalle de registros eliminados" rows={result.deleted} />
        </div>}
      </section>

      <dialog ref={dialog} aria-labelledby="reset-dialog-title" aria-describedby="reset-dialog-description"
        onCancel={e => { e.preventDefault(); close(); }}
        className="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-red-200 bg-white p-6 shadow-2xl backdrop:bg-slate-950/60">
        <p className="text-xs font-bold uppercase tracking-wide text-red-700">Confirmación {stage || 1} de 2</p>
        <h2 id="reset-dialog-title" className="mt-2 text-lg font-extrabold text-slate-950">{stage === 2 ? 'Confirmar eliminación definitiva' : '¿Estás seguro de que deseas restablecer los datos?'}</h2>
        <p id="reset-dialog-description" className="mt-3 text-sm leading-6 text-slate-600">
          Se eliminarán {number(preview?.total_to_delete || 0)} registros académicos de todos los años. Los usuarios y años/períodos escolares se conservarán. No podrás deshacerlo desde la aplicación.
        </p>
        {stage === 2 && <div className="mt-5">
          <label htmlFor="reset-confirmation" className="block text-sm font-semibold text-slate-800">Para confirmar escribe exactamente: <span className="font-mono">{CONFIRMATION}</span></label>
          <input ref={confirmInput} id="reset-confirmation" value={confirmation} onChange={e => setConfirmation(e.target.value)}
            disabled={reset.isPending} autoComplete="off" spellCheck={false}
            className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm focus:border-red-600 focus:outline-none focus:ring-2 focus:ring-red-100" />
        </div>}
        {reset.isPending && <p role="status" className="mt-4 text-sm text-slate-600">Restableciendo y verificando integridad. No cierres esta página.</p>}
        <div className="mt-6 flex flex-wrap justify-end gap-3">
          <button type="button" className={secondaryButton} disabled={reset.isPending} onClick={close}>Cancelar</button>
          {stage === 1 ? <button type="button" className={dangerButton} onClick={() => setStage(2)}>Continuar</button>
            : <button type="button" className={dangerButton} disabled={confirmation !== CONFIRMATION || reset.isPending} onClick={submit}>Restablecer definitivamente</button>}
        </div>
      </dialog>
    </>
  );
}
