import React from 'react';

export default function EmptyState({ icon = 'inbox', title = 'Sin resultados', description }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-12 text-center shadow-sm">
      <span className="material-symbols-outlined text-[42px] text-slate-300">{icon}</span>
      <p className="mt-3 text-sm font-semibold text-slate-600">{title}</p>
      {description && <p className="mt-1 text-xs text-slate-400">{description}</p>}
    </div>
  );
}
