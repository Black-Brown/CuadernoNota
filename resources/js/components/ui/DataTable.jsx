import React from 'react';
import EmptyState from './EmptyState';
import LoadingSkeleton from './LoadingSkeleton';

export default function DataTable({
  columns,
  rows,
  rowKey = (row) => row.id,
  loading = false,
  emptyIcon = 'inbox',
  emptyTitle = 'Sin resultados',
  emptyDescription,
  onRowClick,
}) {
  if (loading) return <LoadingSkeleton />;
  if (!rows || rows.length === 0) {
    return <EmptyState icon={emptyIcon} title={emptyTitle} description={emptyDescription} />;
  }

  return (
    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-left text-sm">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-400">
              {columns.map((col) => (
                <th key={col.key} className={`px-5 py-3.5 whitespace-nowrap ${col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'}`}>
                  {col.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {rows.map((row, index) => (
              <tr
                key={rowKey(row, index)}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={onRowClick ? 'cursor-pointer hover:bg-slate-50/70' : ''}
              >
                {columns.map((col) => (
                  <td key={col.key} className={`px-5 py-3.5 align-middle ${col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'}`}>
                    {col.render ? col.render(row) : row[col.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
