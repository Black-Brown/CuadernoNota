import React from 'react';

export default function FormField({ label, htmlFor, error, required, hint, children }) {
  return (
    <div className="space-y-1.5">
      {label && (
        <label htmlFor={htmlFor} className="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          {label} {required && <span className="text-red-500">*</span>}
        </label>
      )}
      {children}
      {hint && !error && <p className="text-[11px] text-slate-400">{hint}</p>}
      {error && <p className="text-[11px] font-semibold text-red-600">{error}</p>}
    </div>
  );
}

export const inputClass = 'w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors disabled:bg-slate-50 disabled:text-slate-400';
export const selectClass = `${inputClass} bg-white appearance-none`;
