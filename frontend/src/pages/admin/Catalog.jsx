import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createPeriod,
  getAcademicYears,
  getAssignments,
  getPeriodActivitySummary,
  getPeriodsByYear,
  updatePeriod,
} from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import StatusBadge from '../../components/ui/StatusBadge';
import EmptyState from '../../components/ui/EmptyState';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton';
import SideDrawer from '../../components/ui/SideDrawer';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';
import Sections from './catalog/Sections';

const STATUS_META = {
  open: { tone: 'success', label: 'Abierto' },
  in_review: { tone: 'warning', label: 'En revisión' },
  closed: { tone: 'neutral', label: 'Cerrado' },
};

const EMPTY_PERIOD_FORM = { number: '', name: '', months: '', start_date: '', end_date: '', status: 'open' };

export default function Catalog() {
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [params, setParams] = useSearchParams();
  const [yearId, setYearId] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editingPeriod, setEditingPeriod] = useState(null);
  const [form, setForm] = useState(EMPTY_PERIOD_FORM);
  const [formError, setFormError] = useState('');

  const periodIdParam = params.get('period');

  const { data: years } = useQuery({ queryKey: ['admin-years'], queryFn: getAcademicYears });

  useEffect(() => {
    if (!yearId && years?.length) setYearId(String(years.find((y) => y.active)?.id || years[0].id));
  }, [years, yearId]);

  const { data: periods, isLoading: isLoadingPeriods } = useQuery({
    queryKey: ['admin-periods', yearId],
    queryFn: () => getPeriodsByYear(yearId),
    enabled: !!yearId,
  });

  const selectedPeriod = periods?.find((p) => String(p.id) === String(periodIdParam)) || null;

  const { data: assignments } = useQuery({
    queryKey: ['admin-assignments'],
    queryFn: getAssignments,
    enabled: !!selectedPeriod,
  });

  const { data: activitySummary, isLoading: isLoadingActivitySummary } = useQuery({
    queryKey: ['admin-period-activity-summary', selectedPeriod?.id],
    queryFn: () => getPeriodActivitySummary(selectedPeriod.id),
    enabled: !!selectedPeriod,
  });

  const selectPeriod = (period) => setParams({ period: String(period.id) });
  const backToPicker = () => setParams({});

  const openCreatePeriod = () => { setEditingPeriod(null); setForm(EMPTY_PERIOD_FORM); setFormError(''); setDrawerOpen(true); };
  const openEditPeriod = (period) => {
    setEditingPeriod(period);
    setForm({ number: period.number, name: period.name, months: period.months, start_date: period.start_date?.slice(0, 10) || '', end_date: period.end_date?.slice(0, 10) || '', status: period.status });
    setFormError(''); setDrawerOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: () => editingPeriod ? updatePeriod(editingPeriod.id, form) : createPeriod(yearId, form),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-periods', yearId] });
      setDrawerOpen(false);
      showToast(editingPeriod ? 'Período actualizado.' : 'Período creado.');
    },
    onError: (err) => setFormError(getErrorMessage(err)),
  });

  const yearAssignments = (assignments || []).filter(
    (a) => String(a.course_offering?.section?.academic_year?.id) === String(yearId)
  );

  const daysLeft = selectedPeriod?.end_date
    ? Math.max(0, Math.ceil((new Date(selectedPeriod.end_date) - new Date()) / (1000 * 60 * 60 * 24)))
    : null;

  return (
    <>
      <PageHeader
        breadcrumb={[
          'Portal Administrativo',
          { label: 'Catálogo académico', to: '/admin/catalog' },
          ...(selectedPeriod ? [selectedPeriod.name] : []),
        ]}
        title="Catálogo académico"
        description="Selecciona un año y un período para entrar a su workspace: secciones, asignaciones docentes y actividades quedan agrupadas y se bloquean automáticamente cuando el período se cierra."
        actions={
          <select value={yearId} onChange={(e) => { setYearId(e.target.value); backToPicker(); }} className={`${selectClass} w-auto`}>
            {years?.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
          </select>
        }
      />

      {!selectedPeriod ? (
        <>
          <div className="mb-4 flex justify-end">
            <button onClick={openCreatePeriod} disabled={!yearId} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50">
              <span className="material-symbols-outlined text-[18px]">add</span>
              Nuevo período
            </button>
          </div>

          {isLoadingPeriods ? (
            <LoadingSkeleton rows={4} />
          ) : !periods || periods.length === 0 ? (
            <EmptyState icon="date_range" title="No hay períodos registrados para este año." description="Crea el primer período para abrir su workspace." />
          ) : (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              {[...periods].sort((a, b) => a.number - b.number).map((period) => {
                const meta = STATUS_META[period.status] || STATUS_META.closed;
                return (
                  <button
                    key={period.id}
                    onClick={() => selectPeriod(period)}
                    className="group rounded-xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
                  >
                    <div className="mb-3 flex items-start justify-between">
                      <span className="material-symbols-outlined rounded-lg bg-slate-100 p-2 text-[20px] text-slate-700">date_range</span>
                      <StatusBadge tone={meta.tone} label={meta.label} />
                    </div>
                    <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Período {period.number}</p>
                    <h3 className="mt-1 text-base font-extrabold text-slate-900">{period.name}</h3>
                    <p className="mt-1 text-xs text-slate-500">{period.months}</p>
                    <p className="mt-3 text-[11px] font-semibold text-slate-400">{period.start_date} — {period.end_date}</p>
                    <div className="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                      <span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Workspace del período</span>
                      <span className="inline-flex items-center gap-1 text-xs font-bold text-slate-700 group-hover:text-indigo-600">
                        Abrir
                        <span className="material-symbols-outlined text-[16px] transition-transform group-hover:translate-x-0.5">arrow_forward</span>
                      </span>
                    </div>
                  </button>
                );
              })}
            </div>
          )}
        </>
      ) : (
        <>
          <button onClick={backToPicker} className="mb-4 flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800">
            <span className="material-symbols-outlined text-[16px]">arrow_back</span>
            Cambiar período
          </button>

          <div className="relative mb-8 flex flex-col gap-4 overflow-hidden rounded-xl bg-slate-950 p-6 text-white shadow-md md:flex-row md:items-center md:justify-between">
            <div className="relative z-10">
              <div className="mb-1 flex items-center gap-3">
                <StatusBadge tone={(STATUS_META[selectedPeriod.status] || STATUS_META.closed).tone} label={(STATUS_META[selectedPeriod.status] || STATUS_META.closed).label} />
                <h2 className="text-base font-bold">Workspace: {selectedPeriod.name}</h2>
              </div>
              <p className="max-w-xl text-xs text-slate-400">
                {selectedPeriod.status === 'open'
                  ? 'Secciones y asignaciones se pueden editar mientras este período permanezca abierto.'
                  : 'Este período está cerrado: secciones y asignaciones quedan bloqueadas hasta reabrirlo.'}
              </p>
            </div>

            <div className="relative z-10 flex items-center gap-6">
              {daysLeft !== null && (
                <div className="flex flex-col items-start md:items-end">
                  <div className="font-mono text-3xl font-extrabold text-indigo-400">{daysLeft} Días</div>
                  <div className="text-[9px] font-bold uppercase tracking-wider text-slate-400">PARA CIERRE</div>
                </div>
              )}
              <button
                onClick={() => openEditPeriod(selectedPeriod)}
                className="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800"
              >
                <span className="material-symbols-outlined text-[18px]">edit</span>
                Editar período
              </button>
            </div>
          </div>

          <section className="mb-8">
            <h3 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
              <span className="material-symbols-outlined text-[20px] text-slate-500">domain</span>
              Secciones
            </h3>
            <Sections lockedYearId={yearId} periodId={selectedPeriod.id} readOnly={selectedPeriod.status !== 'open'} />
          </section>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <section>
              <div className="mb-3 flex items-center justify-between">
                <h3 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
                  <span className="material-symbols-outlined text-[20px] text-slate-500">assignment_ind</span>
                  Asignaciones docentes
                </h3>
                <Link to="/admin/assignments" className="text-[11px] font-bold text-indigo-600 hover:text-indigo-800">Ver todas →</Link>
              </div>
              {yearAssignments.length === 0 ? (
                <EmptyState icon="assignment_ind" title="Sin docentes asignados en este año escolar." />
              ) : (
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                      <tr>
                        <th className="px-4 py-3">Docente</th>
                        <th className="px-4 py-3">Curso</th>
                        <th className="px-4 py-3 text-center">Estado</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {yearAssignments.slice(0, 8).map((a) => (
                        <tr key={a.id}>
                          <td className="px-4 py-2.5 font-bold text-slate-800">{a.teacher?.name}</td>
                          <td className="px-4 py-2.5 text-slate-500">
                            {a.course_offering?.section?.grade?.name} {a.course_offering?.section?.name} · {a.course_offering?.subject?.name}
                          </td>
                          <td className="px-4 py-2.5 text-center">
                            <StatusBadge tone={a.active ? 'success' : 'neutral'} label={a.active ? 'Activa' : 'Inactiva'} />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                  {yearAssignments.length > 8 && (
                    <p className="border-t border-slate-100 px-4 py-2 text-[10px] font-semibold text-slate-400">
                      +{yearAssignments.length - 8} asignaciones más en este año escolar.
                    </p>
                  )}
                </div>
              )}
            </section>

            <section>
              <div className="mb-3 flex items-center justify-between">
                <h3 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-slate-800">
                  <span className="material-symbols-outlined text-[20px] text-slate-500">add_task</span>
                  Actividades propagadas
                </h3>
                <Link to="/admin/institutional?tab=templates" className="text-[11px] font-bold text-indigo-600 hover:text-indigo-800">Gestionar plantillas →</Link>
              </div>
              {isLoadingActivitySummary ? (
                <LoadingSkeleton rows={3} />
              ) : !activitySummary || activitySummary.length === 0 ? (
                <EmptyState icon="add_task" title="Ninguna actividad base está activa en este período." />
              ) : (
                <div className="grid grid-cols-2 gap-3">
                  {activitySummary.map((t) => (
                    <div key={t.id} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                      <span className="material-symbols-outlined rounded-lg bg-slate-100 p-2 text-[18px] text-slate-700">{t.icon || 'add_task'}</span>
                      <p className="mt-2 text-xs font-extrabold text-slate-900">{t.name}</p>
                      <p className="text-[10px] text-slate-400">{t.course_count} {t.course_count === 1 ? 'curso' : 'cursos'} activos</p>
                    </div>
                  ))}
                </div>
              )}
            </section>
          </div>
        </>
      )}

      <SideDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editingPeriod ? 'Editar período' : 'Nuevo período'}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending || !form.number || !form.name || !form.months || !form.start_date || !form.end_date} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50">
              {saveMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Guardar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {formError && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{formError}</div>}
          <FormField label="Número" required hint="Del 1 al 4">
            <input type="number" min="1" max="4" className={inputClass} value={form.number} onChange={(e) => setForm({ ...form, number: e.target.value })} />
          </FormField>
          <FormField label="Nombre" required hint="Ej. Primer período">
            <input className={inputClass} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Meses" required hint="Ej. Ago - Oct">
            <input className={inputClass} value={form.months} onChange={(e) => setForm({ ...form, months: e.target.value })} />
          </FormField>
          <FormField label="Fecha de inicio" required>
            <input type="date" className={inputClass} value={form.start_date} onChange={(e) => setForm({ ...form, start_date: e.target.value })} />
          </FormField>
          <FormField label="Fecha de fin" required>
            <input type="date" className={inputClass} value={form.end_date} onChange={(e) => setForm({ ...form, end_date: e.target.value })} />
          </FormField>
          <FormField label="Estado" hint="Cerrar el período bloquea secciones y asignaciones dentro de su workspace.">
            <select className={selectClass} value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}>
              <option value="open">Abierto</option>
              <option value="in_review">En revisión</option>
              <option value="closed">Cerrado</option>
            </select>
          </FormField>
        </div>
      </SideDrawer>

      <Toast toast={toast} />
    </>
  );
}
