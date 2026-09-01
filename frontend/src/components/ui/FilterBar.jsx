import React from 'react';

export default function FilterBar({ children }) {
  return (
    <div className="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      {children}
    </div>
  );
}
