import React from 'react';

export default function Toast({ toast }) {
  if (!toast) return null;
  const isError = toast.tone === 'error';
  return (
    <div className={`fixed bottom-6 right-6 z-[70] flex items-center gap-2 px-4 py-3 rounded-xl shadow-xl text-xs font-semibold text-white ${isError ? 'bg-red-600' : 'bg-slate-900'}`}>
      <span className="material-symbols-outlined text-base">{isError ? 'error' : 'check_circle'}</span>
      {toast.message}
    </div>
  );
}
