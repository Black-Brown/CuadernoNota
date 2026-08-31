import React, { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { deactivateStudent, enrollStudent, getAdminStudent, getSections } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton';
import EmptyState from '../../components/ui/EmptyState';
import Toast from '../../components/ui/Toast';

const STATUS_META = {
  active: { tone: 'success', label: 'Activa', icon: 'check_circle' },
  completed: { tone: 'neutral', label: 'Completada', icon: 'task_alt' },
  withdrawn: { tone: 'danger', label: 'Retirada', icon: 'cancel' },
};

export default function StudentProfile() {
  const { id } = useParams();
  const qc = useQueryClient();
  const { toast, showToast } = useToast();

  const [enrollOpen, setEnrollOpen] = useState(false);
  const [enrollForm, setEnrollForm] = useState({ section_id: '', enrolled_at: '' });
  const [enrollError, setEnrollError] = useState('');
  const [deactivateOpen, setDeactivateOpen] = useState(false);
  const [reason, setReason] = useState('');

  const { data: student, isLoading } = useQuery({ queryKey: ['admin-student', id], queryFn: () => getAdminStudent(id) });
  const { data: sections } = useQuery({ queryKey: ['admin-sections'], queryFn: () => getSections() });

  const enrollMutation = useMutation({
    mutationFn: () => enrollStudent(id, enrollForm),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-student', id] });
      qc.invalidateQueries({ queryKey: ['admin-students'] });
      setEnrollOpen(false);
      showToast('Inscripción registrada correctamente.');
    },
    onError: (err) => setEnrollError(getErrorMessage(err)),
  });

  const deactivateMutation = useMutation({
    mutationFn: () => deactivateStudent(id, { reason }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-student', id] });
      qc.invalidateQueries({ queryKey: ['admin-students'] });
      setDeactivateOpen(false);
      setReason('');
      showToast('Estudiante dado de baja; su historial fue conservado.');
    },
    onError: (err) => showToast(getErrorMessage(err), 'error'),
  });

  if (isLoading) return <LoadingSkeleton />;
  if (!student) return <EmptyState icon="person_off" title="Estudiante no encontrado" />;

  const enrollments = [...(student.enrollments || [])].sort((a, b) => new Date(b.enrolled_at) - new Date(a.enrolled_at));
  const currentEnrollment = enrollments.find((e) => e.status === 'active') || enrollments[0];

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', { label: 'Estudiantes', to: '/admin/students' }, `${student.name} ${student.last_name}`]}
        title={`${student.name} ${student.last_name}`}
        description={`Matrícula ${student.enrollment_no}`}
        actions={
          <>
            <button
              onClick={() => { setEnrollForm({ section_id: '', enrolled_at: '' }); setEnrollError(''); setEnrollOpen(true); }}
              className="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50"
            >
              <span className="material-symbols-outlined text-[18px]">swap_horiz</span>
              Inscribir / Cambiar sección
            </button>
            {student.active && (
              <button
                onClick={() => setDeactivateOpen(true)}
                className="flex items-center gap-2 rounded-lg border border-red-200 px-4 py-2.5 text-xs font-bold text-red-600 hover:bg-red-50"
              >
                <span className="material-symbols-outlined text-[18px]">person_off</span>
                Dar de baja
              </button>
            )}
          </>
        }
      />

      <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Estado</p>
          <div className="mt-2"><StatusBadge tone={student.active ? 'success' : 'neutral'} label={student.active ? 'Activo' : 'Inactivo'} /></div>
          {!student.active && student.deactivation_date && (
            <p className="mt-2 text-[11px] text-slate-400">Baja: {student.deactivation_date} — {student.deactivation_reason}</p>
          )}
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Sección actual</p>
          <p className="mt-2 text-lg font-extrabold text-slate-900">
            {currentEnrollment ? `${currentEnrollment.section?.grade?.name} ${currentEnrollment.section?.name}` : 'Sin sección'}
          </p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Año escolar</p>
          <p className="mt-2 text-lg font-extrabold text-slate-900">{currentEnrollment?.section?.academicYear?.name || '—'}</p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Inscrito desde</p>
          <p className="mt-2 text-lg font-extrabold text-slate-900">{currentEnrollment?.enrolled_at || '—'}</p>
        </div>
      </section>

      <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-6 py-4">
          <h2 className="text-base font-extrabold text-slate-900">Historial de matrículas</h2>
          <p className="mt-1 text-xs text-slate-500">Línea de tiempo de cambios de sección y estado académico.</p>
        </div>
        <div className="p-6">
          {enrollments.length === 0 ? (
            <EmptyState icon="history" title="Sin historial de matrículas" />
          ) : (
            <ol className="space-y-0">
              {enrollments.map((enrollment, index) => {
                const meta = STATUS_META[enrollment.status] || STATUS_META.completed;
                return (
                  <li key={enrollment.id} className="relative flex gap-4 pb-8 last:pb-0">
                    {index < enrollments.length - 1 && <span className="absolute left-[15px] top-8 h-full w-px bg-slate-200" />}
                    <span className={`z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border ${meta.tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : meta.tone === 'danger' ? 'border-red-200 bg-red-50 text-red-600' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>
                      <span className="material-symbols-outlined text-[16px]">{meta.icon}</span>
                    </span>
                    <div className="min-w-0 flex-1 rounded-lg border border-slate-100 p-4">
                      <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="text-sm font-bold text-slate-900">
                          {enrollment.section?.grade?.name} {enrollment.section?.name} · {enrollment.section?.academicYear?.name}
                        </p>
                        <StatusBadge tone={meta.tone} label={meta.label} />
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        Desde {enrollment.enrolled_at} {enrollment.ended_at ? `hasta ${enrollment.ended_at}` : '— actual'}
                      </p>
                      {enrollment.end_reason && <p className="mt-1 text-xs text-slate-400">Motivo: {enrollment.end_reason}</p>}
                    </div>
                  </li>
                );
              })}
            </ol>
          )}
        </div>
      </section>

      <SideDrawer
        open={enrollOpen}
        onClose={() => setEnrollOpen(false)}
        title="Inscribir / Cambiar de sección"
        description="Se cerrará la matrícula activa actual y se abrirá una nueva."
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setEnrollOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button
              onClick={() => enrollMutation.mutate()}
              disabled={enrollMutation.isPending || !enrollForm.section_id || !enrollForm.enrolled_at}
              className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {enrollMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Confirmar
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {enrollError && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{enrollError}</div>}
          <FormField label="Sección destino" required>
            <select className={selectClass} value={enrollForm.section_id} onChange={(e) => setEnrollForm({ ...enrollForm, section_id: e.target.value })}>
              <option value="">Seleccionar sección</option>
              {sections?.map((s) => <option key={s.id} value={s.id}>{s.grade?.name} {s.name} · {s.academicYear?.name}</option>)}
            </select>
          </FormField>
          <FormField label="Fecha de inscripción" required>
            <input type="date" className={inputClass} value={enrollForm.enrolled_at} onChange={(e) => setEnrollForm({ ...enrollForm, enrolled_at: e.target.value })} />
          </FormField>
        </div>
      </SideDrawer>

      <ConfirmDialog
        open={deactivateOpen}
        onClose={() => setDeactivateOpen(false)}
        onConfirm={() => deactivateMutation.mutate()}
        loading={deactivateMutation.isPending}
        disableConfirm={!reason.trim()}
        tone="danger"
        title="Dar de baja al estudiante"
        message="Su historial académico y matrículas se conservarán intactos."
        confirmLabel="Dar de baja"
      >
        <FormField label="Motivo de la baja" required>
          <textarea rows={3} className={inputClass} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="Ej. Traslado a otro centro educativo" />
        </FormField>
      </ConfirmDialog>

      <Toast toast={toast} />
    </>
  );
}
