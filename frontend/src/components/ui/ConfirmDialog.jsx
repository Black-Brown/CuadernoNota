import React from 'react';

export default function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  message,
  confirmLabel = 'Confirmar',
  cancelLabel = 'Cancelar',
  tone = 'default',
  loading = false,
  disableConfirm = false,
  children,
}) {
  if (!open) return null;

  const confirmClass = tone === 'danger'
    ? 'bg-red-600 hover:bg-red-700'
    : 'bg-slate-950 hover:bg-slate-800';

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div
        className="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
        style={{ animation: 'fadeInScale 0.18s cubic-bezier(.4,0,.2,1)' }}
      >
        <div className="flex items-start gap-3">
          <span className={`material-symbols-outlined rounded-lg p-2 text-[22px] ${tone === 'danger' ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-700'}`}>
            {tone === 'danger' ? 'warning' : 'help'}
          </span>
          <div className="min-w-0 flex-1">
            <h2 className="text-base font-extrabold text-slate-900">{title}</h2>
            {message && <p className="mt-1.5 text-sm leading-5 text-slate-500">{message}</p>}
          </div>
        </div>

        {children && <div className="mt-4">{children}</div>}

        <div className="mt-6 flex justify-end gap-3">
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50"
          >
            {cancelLabel}
          </button>
          <button
            type="button"
            onClick={onConfirm}
            disabled={loading || disableConfirm}
            className={`flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold text-white transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${confirmClass}`}
          >
            {loading && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
            {confirmLabel}
          </button>
        </div>
      </div>

      <style>{`
        @keyframes fadeInScale {
          from { opacity: 0; transform: scale(0.96) translateY(8px); }
          to { opacity: 1; transform: scale(1) translateY(0); }
        }
      `}</style>
    </div>
  );
}
