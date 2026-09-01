import React, { useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { downloadBackup, getAuditLogs } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import FilterBar from '../../components/ui/FilterBar';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import { selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';

const ACTION_OPTIONS = [
  { value: '', label: 'Todas las acciones' },
  { value: 'post', label: 'Creación (POST)' },
  { value: 'patch', label: 'Actualización (PATCH)' },
  { value: 'put', label: 'Actualización (PUT)' },
  { value: 'delete', label: 'Baja / eliminación (DELETE)' },
  { value: 'backup', label: 'Respaldo' },
  { value: 'SYSTEM_DATA_RESET', label: 'Restablecimiento de datos' },
];

const ACTION_TONE = { post: 'success', patch: 'info', put: 'info', delete: 'danger', backup: 'indigo' };

export default function Audit() {
  const { toast, showToast } = useToast();
  const [action, setAction] = useState('');
  const [page, setPage] = useState(1);
  const [confirmBackupOpen, setConfirmBackupOpen] = useState(false);

  const params = { per_page: 25, page };
  if (action) params.action = action;
  const { data, isLoading } = useQuery({ queryKey: ['admin-audit-logs', params], queryFn: () => getAuditLogs(params) });
  const logs = data?.data || [];

  const backupMutation = useMutation({
    mutationFn: downloadBackup,
    onSuccess: () => { setConfirmBackupOpen(false); showToast('Respaldo generado y descargado correctamente.'); },
    onError: (err) => { setConfirmBackupOpen(false); showToast(getErrorMessage(err), 'error'); },
  });

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Auditoría y respaldo']}
        title="Auditoría y respaldo"
        description="Consulta las acciones administrativas realizadas en el sistema y genera respaldos de la base de datos."
      />

      <section className="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <span className="material-symbols-outlined rounded-lg bg-white p-2 text-[22px] text-amber-600">database</span>
            <div>
              <h2 className="text-sm font-extrabold text-amber-900">Respaldo de la base de datos</h2>
              <p className="mt-1 max-w-xl text-xs leading-5 text-amber-800">
                El archivo generado contiene información sensible de estudiantes, usuarios y calificaciones. Guárdalo en un lugar seguro y no lo compartas fuera de la institución.
              </p>
            </div>
          </div>
          <button
            onClick={() => setConfirmBackupOpen(true)}
            disabled={backupMutation.isPending}
            className="flex shrink-0 items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
          >
            {backupMutation.isPending ? (
              <><span className="material-symbols-outlined animate-spin text-[16px]">sync</span> Generando...</>
            ) : (
              <><span className="material-symbols-outlined text-[18px]">download</span> Generar respaldo</>
            )}
          </button>
        </div>
      </section>

      <FilterBar>
        <select value={action} onChange={(e) => { setAction(e.target.value); setPage(1); }} className={`${selectClass} w-auto`}>
          {ACTION_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
      </FilterBar>

      <DataTable
        loading={isLoading}
        rows={logs}
        emptyIcon="history"
        emptyTitle="No hay registros de auditoría para este filtro."
        columns={[
          { key: 'user', label: 'Usuario', render: (l) => l.user?.name || '—' },
          { key: 'action', label: 'Acción', align: 'center', render: (l) => <StatusBadge tone={ACTION_TONE[l.action] || 'neutral'} label={l.action?.toUpperCase()} /> },
          { key: 'affected_table', label: 'Recurso', render: (l) => l.affected_table },
          { key: 'record_id', label: 'Registro', align: 'center' },
          { key: 'detail', label: 'Detalle', render: (l) => <span className="block max-w-xs truncate text-xs text-slate-500">{l.detail?.path || JSON.stringify(l.detail || {})}</span> },
          { key: 'ip', label: 'IP', render: (l) => <span className="font-mono text-xs">{l.ip}</span> },
          { key: 'created_at', label: 'Fecha', render: (l) => l.created_at ? String(l.created_at).replace('T', ' ').slice(0, 19) : '—' },
        ]}
      />

      {data?.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-xs font-semibold text-slate-500">
          <span>Página {data.current_page} de {data.last_page} · {data.total} registros</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage((p) => p - 1)} className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40">Anterior</button>
            <button disabled={page >= data.last_page} onClick={() => setPage((p) => p + 1)} className="rounded-lg border border-slate-200 px-3 py-1.5 disabled:opacity-40">Siguiente</button>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={confirmBackupOpen}
        onClose={() => setConfirmBackupOpen(false)}
        onConfirm={() => backupMutation.mutate()}
        loading={backupMutation.isPending}
        title="Generar respaldo de la base de datos"
        message="Se descargará un archivo JSON con la información completa del sistema, incluyendo datos sensibles de estudiantes y usuarios. Esta acción queda registrada en la auditoría."
        confirmLabel="Generar y descargar"
      />

      <Toast toast={toast} />
    </>
  );
}
