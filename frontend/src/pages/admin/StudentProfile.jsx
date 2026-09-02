import React, { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { enrollStudent, getAdminStudent, getSections } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import StudentActions from '../../components/admin/StudentActions';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton';
import EmptyState from '../../components/ui/EmptyState';
import Toast from '../../components/ui/Toast';

const STATUS_META = {
  active: { tone: 'success', label: 'Activa', icon: 'check_circle' },
  completed: { tone: 'neutral', label: 'Completada', icon: 'task_alt' },
  withdrawn: { tone: 'danger', label: 'Retirada', icon: 'cancel' },
};

const TABS = [
  ['summary', 'Resumen', 'dashboard'],
  ['grades', 'Calificaciones', 'grade'],
  ['attendance', 'Asistencia', 'fact_check'],
  ['observations', 'Observaciones', 'comment'],
  ['history', 'Historial', 'history'],
];
const ATTENDANCE_META = {
  P: { label: 'Presente', tone: 'success' }, A: { label: 'Ausente', tone: 'danger' },
  T: { label: 'Tardanza', tone: 'warning' }, E: { label: 'Excusa', tone: 'neutral' },
};
const OBSERVATION_LABELS = { academic: 'Académica', disciplinary: 'Disciplinaria', incident: 'Incidente' };

function formatDate(value) {
  if (!value) return '—';
  const date = new Date(String(value).slice(0, 10) + 'T00:00:00');
  return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('es-DO', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

function score(value) { return value === null || value === undefined ? '—' : Number(value).toFixed(1); }

export default function StudentProfile() {
  const { id } = useParams();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const { toast, showToast } = useToast();

  const [enrollOpen, setEnrollOpen] = useState(false);
  const [enrollForm, setEnrollForm] = useState({ section_id: '', enrolled_at: '' });
  const [enrollError, setEnrollError] = useState('');
  const [activeTab, setActiveTab] = useState('summary');

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

  if (isLoading) return <LoadingSkeleton />;
  if (!student) return <EmptyState icon="person_off" title="Estudiante no encontrado" />;

  const enrollments = [...(student.enrollments || [])].sort((a, b) => new Date(b.enrolled_at) - new Date(a.enrolled_at));
  const currentEnrollment = enrollments.find((e) => e.status === 'active');

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', { label: 'Estudiantes', to: '/admin/students' }, `${student.name} ${student.last_name}`]}
        title={`${student.name} ${student.last_name}`}
        description={`Matrícula ${student.enrollment_no}`}
        actions={
          <div className="flex flex-wrap items-center justify-end gap-2">
            <button
              onClick={() => {
                if (!currentEnrollment) { navigate(`/admin/student-placements?student=${student.id}`); return; }
                setEnrollForm({ section_id: '', enrolled_at: '' }); setEnrollError(''); setEnrollOpen(true);
              }}
              className="flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50"
            >
              <span className="material-symbols-outlined text-[18px]">swap_horiz</span>
              {currentEnrollment ? 'Cambiar sección' : 'Asignar sección'}
            </button>
            <StudentActions
              student={student}
              showLabels
              onSuccess={showToast}
              onDeleted={() => navigate('/admin/students', { replace: true, state: { studentActionMessage: 'Estudiante eliminado definitivamente.' } })}
            />
          </div>
        }
      />

      <section className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Estado</p>
          <div className="mt-2"><StatusBadge tone={!student.active ? 'neutral' : currentEnrollment ? 'success' : 'warning'} label={!student.active ? 'Inactivo' : currentEnrollment ? 'Inscrito' : 'Pendiente de asignación'} /></div>
          {!student.active && student.deactivation_date && (
            <p className="mt-2 text-[11px] text-slate-400">Baja: {formatDate(student.deactivation_date)} — {student.deactivation_reason}</p>
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
          <p className="mt-2 text-lg font-extrabold text-slate-900">{currentEnrollment?.section?.academic_year?.name || '—'}</p>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Inscrito desde</p>
          <p className="mt-2 text-lg font-extrabold text-slate-900">{formatDate(currentEnrollment?.enrolled_at)}</p>
        </div>
      </section>

      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div className="overflow-x-auto border-b border-slate-200 px-4 pt-2">
          <nav className="flex min-w-max gap-1" aria-label="Secciones del expediente estudiantil">
            {TABS.map(([key, label, icon]) => <button key={key} type="button" onClick={() => setActiveTab(key)} aria-current={activeTab === key ? 'page' : undefined}
              className={`flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-extrabold transition-colors ${activeTab === key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-900'}`}>
              <span aria-hidden="true" className="material-symbols-outlined text-[18px]">{icon}</span>{label}
            </button>)}
          </nav>
        </div>

        <div className="p-6">
          {activeTab === 'summary' && <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <Metric icon="monitoring" label="Promedio general" value={student.summary?.average ?? '—'} detail="Escala de 0 a 100" />
              <Metric icon="menu_book" label="Materias evaluadas" value={student.summary?.subjects ?? 0} detail={`${student.summary?.official_grades ?? 0} calificaciones oficiales`} />
              <Metric icon="event_available" label="Asistencia" value={student.summary?.attendance_percentage == null ? '—' : `${student.summary.attendance_percentage}%`} detail={`${student.summary?.attendance_total ?? 0} registros`} />
              <Metric icon="warning" label="Alertas activas" value={student.summary?.unresolved_alerts ?? 0} detail={`${student.summary?.observations ?? 0} observaciones`} />
            </div>
            <div>
              <h2 className="text-base font-extrabold text-slate-900">Alertas y seguimiento</h2>
              <p className="mt-1 text-xs text-slate-500">Situaciones académicas o de asistencia que requieren atención.</p>
              {(student.alerts || []).filter((alert) => !alert.resolved).length === 0 ? <div className="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">No hay alertas activas para este estudiante.</div> : <div className="mt-4 space-y-3">{student.alerts.filter((alert) => !alert.resolved).map((alert) => <div key={alert.id} className="rounded-xl border border-amber-200 bg-amber-50 p-4"><p className="text-sm font-extrabold text-amber-900">{alert.message}</p><p className="mt-1 text-xs text-amber-700">{formatDate(alert.created_at)}</p></div>)}</div>}
            </div>
          </div>}

          {activeTab === 'grades' && <div className="space-y-6">
            <SectionTitle title="Calificaciones por período" description="Competencias, promedio del período y estado de revisión." />
            {(student.period_grades || []).length === 0 ? <EmptyState icon="grade" title="Aún no hay calificaciones registradas" /> : <div className="overflow-x-auto rounded-xl border border-slate-200"><table className="min-w-full text-left text-sm"><thead className="bg-slate-50 text-[10px] font-extrabold uppercase text-slate-500"><tr><th className="px-4 py-3">Materia</th><th className="px-4 py-3">Período</th><th className="px-4 py-3 text-center">C1</th><th className="px-4 py-3 text-center">C2</th><th className="px-4 py-3 text-center">C3</th><th className="px-4 py-3 text-center">Promedio</th><th className="px-4 py-3">Estado</th></tr></thead><tbody className="divide-y divide-slate-100">{student.period_grades.map((grade) => <tr key={grade.id}><td className="px-4 py-3 font-bold text-slate-900">{grade.subject?.name}</td><td className="px-4 py-3 text-slate-600">{grade.period?.name} · {grade.period?.academic_year?.name}</td><td className="px-4 py-3 text-center">{score(grade.c1_score)}</td><td className="px-4 py-3 text-center">{score(grade.c2_score)}</td><td className="px-4 py-3 text-center">{score(grade.c3_score)}</td><td className="px-4 py-3 text-center font-extrabold">{score(grade.period_score)}</td><td className="px-4 py-3"><StatusBadge tone={grade.status === 'official' ? 'success' : grade.status === 'in_review' ? 'warning' : 'neutral'} label={grade.status === 'official' ? 'Oficial' : grade.status === 'in_review' ? 'En revisión' : 'Borrador'} /></td></tr>)}</tbody></table></div>}
            {(student.final_grades || []).length > 0 && <><SectionTitle title="Calificaciones finales" description="Resultado final por materia y año escolar." /><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{student.final_grades.map((grade) => <div key={grade.id} className="rounded-xl border border-slate-200 p-4"><p className="font-extrabold text-slate-900">{grade.subject?.name}</p><p className="mt-1 text-xs text-slate-500">{grade.academic_year?.name}</p><p className="mt-3 text-2xl font-extrabold text-indigo-600">{score(grade.cf)}</p></div>)}</div></>}
          </div>}

          {activeTab === 'attendance' && <div>
            <SectionTitle title="Registro de asistencia" description="Presentes, ausencias, tardanzas y excusas registradas." />
            {(student.attendances || []).length === 0 ? <EmptyState icon="fact_check" title="Aún no hay asistencia registrada" /> : <div className="mt-4 overflow-hidden rounded-xl border border-slate-200"><div className="divide-y divide-slate-100">{student.attendances.map((attendance) => { const meta = ATTENDANCE_META[attendance.code] || ATTENDANCE_META.P; return <div key={attendance.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"><div><p className="font-bold text-slate-900">{formatDate(attendance.date)}</p><p className="mt-1 text-xs text-slate-500">{attendance.section?.grade?.name} {attendance.section?.name}</p></div><div className="text-right"><StatusBadge tone={meta.tone} label={meta.label} />{attendance.excuse_reason && <p className="mt-1 text-xs text-slate-500">{attendance.excuse_reason}</p>}</div></div>; })}</div></div>}
          </div>}

          {activeTab === 'observations' && <div>
            <SectionTitle title="Observaciones docentes" description="Seguimiento académico, disciplinario e incidencias." />
            {(student.observations || []).length === 0 ? <EmptyState icon="comment" title="Aún no hay observaciones registradas" /> : <div className="mt-4 space-y-3">{student.observations.map((observation) => <article key={observation.id} className="rounded-xl border border-slate-200 p-4"><div className="flex flex-wrap items-center justify-between gap-2"><div><p className="text-sm font-extrabold text-slate-900">{OBSERVATION_LABELS[observation.type] || observation.type}</p><p className="mt-1 text-xs text-slate-500">{observation.subject?.name || 'General'} · {observation.user?.name || 'Docente'} · {formatDate(observation.date)}</p></div></div><p className="mt-3 text-sm leading-6 text-slate-700">{observation.description}</p></article>)}</div>}
          </div>}

          {activeTab === 'history' && (enrollments.length === 0 ? <EmptyState icon="history" title="Sin historial de matrículas" /> : <div><SectionTitle title="Historial de matrículas" description="Línea de tiempo de cambios de sección y estado académico." />
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
                          {enrollment.section?.grade?.name} {enrollment.section?.name} · {enrollment.section?.academic_year?.name}
                        </p>
                        <StatusBadge tone={meta.tone} label={meta.label} />
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        Desde {formatDate(enrollment.enrolled_at)} {enrollment.ended_at ? `hasta ${formatDate(enrollment.ended_at)}` : '— actual'}
                      </p>
                      {enrollment.end_reason && <p className="mt-1 text-xs text-slate-400">Motivo: {enrollment.end_reason}</p>}
                    </div>
                  </li>
                );
              })}
            </ol></div>)}
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
              {sections?.map((s) => <option key={s.id} value={s.id}>{s.grade?.name} {s.name} · {s.academic_year?.name}</option>)}
            </select>
          </FormField>
          <FormField label="Fecha de inscripción" required>
            <input type="date" className={inputClass} value={enrollForm.enrolled_at} onChange={(e) => setEnrollForm({ ...enrollForm, enrolled_at: e.target.value })} />
          </FormField>
        </div>
      </SideDrawer>

      <Toast toast={toast} />
    </>
  );
}

function Metric({ icon, label, value, detail }) {
  return <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4"><div className="flex items-start justify-between gap-3"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p><p className="mt-2 text-2xl font-extrabold text-slate-950">{value}</p><p className="mt-1 text-xs text-slate-500">{detail}</p></div><span aria-hidden="true" className="material-symbols-outlined relative top-[2px] text-indigo-500">{icon}</span></div></div>;
}

function SectionTitle({ title, description }) {
  return <div className="mb-4"><h2 className="text-base font-extrabold text-slate-900">{title}</h2><p className="mt-1 text-xs text-slate-500">{description}</p></div>;
}
