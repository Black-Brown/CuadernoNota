import React from 'react';

const TONES = {
  success: 'bg-emerald-50 text-emerald-700 border-emerald-100',
  warning: 'bg-amber-50 text-amber-700 border-amber-100',
  danger: 'bg-red-50 text-red-700 border-red-100',
  info: 'bg-sky-50 text-sky-700 border-sky-100',
  indigo: 'bg-indigo-50 text-indigo-700 border-indigo-100',
  neutral: 'bg-slate-100 text-slate-600 border-slate-200',
};

export default function StatusBadge({ tone = 'neutral', label }) {
  return (
    <span className={`inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider ${TONES[tone] || TONES.neutral}`}>
      {label}
    </span>
  );
}
