import React, { useEffect } from 'react';

export default function SideDrawer({ open, onClose, title, description, children, footer, widthClass = 'max-w-lg' }) {
  useEffect(() => {
    if (!open) return;
    const handler = (event) => { if (event.key === 'Escape') onClose(); };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex justify-end">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onClick={onClose} />
      <div
        className={`relative z-10 flex h-full w-full ${widthClass} flex-col bg-white shadow-2xl`}
        style={{ animation: 'drawerSlideIn 0.2s cubic-bezier(.4,0,.2,1)' }}
      >
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5 shrink-0">
          <div>
            <h2 className="text-lg font-extrabold tracking-tight text-slate-900">{title}</h2>
            {description && <p className="mt-1 text-xs text-slate-500">{description}</p>}
          </div>
          <button onClick={onClose} className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700">
            <span className="material-symbols-outlined text-[22px]">close</span>
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>

        {footer && <div className="shrink-0 border-t border-slate-100 px-6 py-4">{footer}</div>}
      </div>

      <style>{`
        @keyframes drawerSlideIn {
          from { transform: translateX(24px); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
        }
      `}</style>
    </div>
  );
}
