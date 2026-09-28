import React, { useEffect, useState } from 'react';
import DataTable from './DataTable';
import { pageRows } from '../../utils/coordinatorPresentation';

export default function PaginatedTable({ rows = [], resetKey, ...props }) {
  const [page, setPage] = useState(1);
  useEffect(() => setPage(1), [resetKey]);
  const result = pageRows(rows, page);
  return <>
    <DataTable {...props} rows={result.rows} />
    {!props.loading && result.total > 0 && <nav aria-label="Paginación de registros" className="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs font-semibold text-slate-500">
      <span>{result.total} registros · Página {result.page} de {result.totalPages}</span>
      <div className="flex gap-2">
        <button type="button" disabled={result.page === 1} onClick={() => setPage(result.page - 1)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 disabled:opacity-40">Anterior</button>
        <button type="button" disabled={result.page === result.totalPages} onClick={() => setPage(result.page + 1)} className="rounded-lg border border-slate-200 bg-white px-3 py-2 disabled:opacity-40">Siguiente</button>
      </div>
    </nav>}
  </>;
}
