import React from 'react';
import { Link } from 'react-router-dom';
import DashboardLayout from '../../components/DashboardLayout';

export default function ModuleComingSoon({
  title = 'Modulo en produccion',
  icon = 'construction',
  description = 'Esta pagina todavia no esta activa. El equipo esta preparando este modulo para una proxima version.',
}) {
  return (
    <DashboardLayout>
      <div className="min-h-[calc(100vh-8rem)] flex items-center justify-center">
        <section className="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
          <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-xl border border-amber-100 bg-amber-50 text-amber-600">
            <span className="material-symbols-outlined text-[30px]">{icon}</span>
          </div>

          <p className="mb-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
            Pagina no activa todavia
          </p>
          <h1 className="text-2xl font-extrabold tracking-tight text-slate-900">{title}</h1>
          <p className="mx-auto mt-3 max-w-lg text-sm leading-6 text-slate-500">{description}</p>

          <div className="mt-7 flex flex-col items-center justify-center gap-2 sm:flex-row">
            <Link
              to="/docente/dashboard"
              className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition-colors hover:bg-slate-800"
            >
              <span className="material-symbols-outlined text-[18px]">dashboard</span>
              Ir al inicio
            </Link>
            <Link
              to="/docente/courses"
              className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition-colors hover:bg-slate-50"
            >
              <span className="material-symbols-outlined text-[18px]">school</span>
              Ver cursos
            </Link>
          </div>
        </section>
      </div>
    </DashboardLayout>
  );
}
