import React from 'react';
import { Link } from 'react-router-dom';

export default function Breadcrumb({ items = [] }) {
  return (
    <nav className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
      {items.map((item, index) => {
        const label = typeof item === 'string' ? item : item.label;
        const to = typeof item === 'string' ? null : item.to;
        const isLast = index === items.length - 1;
        return (
          <React.Fragment key={`${label}-${index}`}>
            {index > 0 && <span className="material-symbols-outlined text-[12px]">chevron_right</span>}
            {to && !isLast ? (
              <Link to={to} className="hover:text-slate-600">{label}</Link>
            ) : (
              <span className={isLast ? 'text-indigo-600' : ''}>{label}</span>
            )}
          </React.Fragment>
        );
      })}
    </nav>
  );
}
