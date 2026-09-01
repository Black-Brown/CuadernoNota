import React from 'react';

export default function KpiCard({ label, value, icon, helper, tone = 'bg-indigo-50 text-indigo-700', loading = false }) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="mb-5 flex items-start justify-between">
        <div>
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p>
          <p className="mt-2 text-3xl font-extrabold text-slate-900">{loading ? '...' : value}</p>
        </div>
        {icon && <span className={`material-symbols-outlined rounded-lg p-2 text-[22px] ${tone}`}>{icon}</span>}
      </div>
      {helper && <p className="text-xs font-semibold text-slate-500">{helper}</p>}
    </div>
  );
}
