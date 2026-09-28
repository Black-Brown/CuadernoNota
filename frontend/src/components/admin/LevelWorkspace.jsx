import React, { useState } from 'react';
import { groupAcademicLevels } from '../../utils/academicLevels';

// Pure visual organization: authorization remains exclusively in the API.
export default function LevelWorkspace({ items = [], getLevel, children, unit = 'cursos', loading = false, enabled = true }) {
  const [selected, setSelected] = useState(null);
  if (!enabled) return children(items);
  const groups = groupAcademicLevels(items, getLevel);
  const current = groups.find(group => group.level === selected);
  if (selected) return <section className="space-y-4">
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h2 className="text-lg font-extrabold text-slate-950">{selected}</h2>
      <button type="button" onClick={() => setSelected(null)} className="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700">Volver a niveles</button>
    </div>
    {children(current?.rows || [])}
  </section>;
  return <section aria-label="Workspaces por nivel educativo" className="grid gap-4 sm:grid-cols-2">
    {groups.map(({ level, rows }) => <button type="button" key={level} onClick={() => setSelected(level)} className="rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-colors hover:border-indigo-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
      <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Workspace del nivel</span>
      <span className="mt-2 block text-xl font-extrabold text-slate-950">{level}</span>
      <span className="mt-2 block text-sm text-slate-500">{loading ? 'Cargando…' : `${rows.length} ${unit}`}</span>
      <span className="mt-5 inline-flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2 text-xs font-bold text-white">Abrir <span aria-hidden="true" className="material-symbols-outlined text-[18px]">arrow_forward</span></span>
    </button>)}
  </section>;
}
