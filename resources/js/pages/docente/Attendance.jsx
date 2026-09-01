import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import DashboardLayout from '../../components/DashboardLayout';
import { getCourses } from '../../api/courses.api';
import { getSectionAttendance, saveAttendance } from '../../api/attendance.api';
import usePeriodStore from '../../store/periodStore';

const STATUS_META = {
  present: { label: 'Presente', icon: 'check_circle', active: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
  late: { label: 'Tardanza', icon: 'schedule', active: 'border-sky-500 bg-sky-50 text-sky-700' },
  absent: { label: 'Ausente', icon: 'cancel', active: 'border-red-500 bg-red-50 text-red-700' },
  excused: { label: 'Justificada', icon: 'fact_check', active: 'border-amber-500 bg-amber-50 text-amber-700' },
};

function groupSections(courses = [], yearId = null) {
  const grouped = new Map();
  courses.filter((course) => !yearId || Number(course.academic_year_id) === Number(yearId)).forEach((course) => {
    const key = String(course.section_id);
    const current = grouped.get(key) || { ...course, subjects: [] };
    if (!current.subjects.some((item) => Number(item.id) === Number(course.subject_id))) {
      current.subjects.push({ id: course.subject_id, name: course.subject_name });
    }
    grouped.set(key, current);
  });
  return [...grouped.values()];
}

export default function Attendance() {
  const { sectionId } = useParams();
  return sectionId ? <AttendanceWorkspace sectionId={sectionId} /> : <AttendanceCourses />;
}

function AttendanceCourses() {
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);
  const { data, isLoading } = useQuery({ queryKey: ['courses'], queryFn: getCourses, staleTime: 5 * 60_000 });
  const sections = useMemo(() => groupSections(data?.courses, selectedPeriod?.academic_year_id), [data, selectedPeriod?.academic_year_id]);
  const periodOpen = selectedPeriod?.status === 'open';

  return (
    <DashboardLayout>
      <PageHeading description="Selecciona una sección para abrir su workspace de asistencia del período." />
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Período seleccionado</p><p className="mt-1 text-sm font-extrabold text-slate-900">{selectedPeriod?.name || 'Selecciona un período'}</p></div>
        <span className="rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-extrabold text-indigo-700">{sections.length} {sections.length === 1 ? 'sección' : 'secciones'}</span>
      </div>
      {isLoading ? <EmptyPanel text="Cargando secciones asignadas..." icon="progress_activity" spinning /> : sections.length === 0 ? <EmptyPanel text="No tienes secciones asignadas en este período." icon="event_busy" /> : (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
          {sections.map((section) => (
            <Link key={section.section_id} to={`/docente/attendance/${section.section_id}`} className="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md">
              <div className="border-b border-indigo-100 bg-indigo-50/70 px-5 py-5">
                <div className="flex items-start justify-between gap-4"><span className="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm"><span className="material-symbols-outlined text-[27px]">fact_check</span></span><span className={`rounded-md border px-2 py-1 text-[9px] font-extrabold uppercase tracking-wider ${periodOpen ? 'border-emerald-100 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600'}`}>{periodOpen ? 'Activa' : selectedPeriod?.status === 'upcoming' ? 'Próxima' : 'Bloqueada'}</span></div>
                <p className="mt-5 text-[10px] font-extrabold uppercase tracking-wider text-indigo-500">Workspace de asistencia</p>
              </div>
              <div className="p-5">
                <h2 className="text-lg font-extrabold text-slate-900">{section.grade_name} · Sección {section.section_name}</h2>
                <p className="mt-1 text-sm font-semibold text-slate-500">{section.year_label || 'Año escolar activo'}</p>
                <div className="mt-5 grid grid-cols-2 gap-3 border-y border-slate-100 py-4"><div><p className="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Materias</p><p className="mt-1 text-sm font-bold text-slate-800">{section.subjects.length}</p></div><div><p className="text-[9px] font-extrabold uppercase tracking-wider text-slate-400">Registro</p><p className="mt-1 text-sm font-bold text-slate-800">Diario</p></div></div>
                <div className="mt-4 flex items-center justify-between"><span className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Estudiantes de la sección</span><span className="flex items-center gap-1 text-xs font-extrabold text-indigo-600">Abrir <span className="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-1">arrow_forward</span></span></div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}

function AttendanceWorkspace({ sectionId }) {
  const queryClient = useQueryClient();
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [draft, setDraft] = useState({});
  const [message, setMessage] = useState(null);
  const { data: courseData, isLoading: loadingCourses } = useQuery({ queryKey: ['courses'], queryFn: getCourses, staleTime: 5 * 60_000 });
  const sections = useMemo(() => groupSections(courseData?.courses, selectedPeriod?.academic_year_id), [courseData, selectedPeriod?.academic_year_id]);
  const selectedSection = sections.find((section) => String(section.section_id) === String(sectionId));
  const periodOpen = selectedPeriod?.status === 'open';

  useEffect(() => {
    if (!selectedPeriod?.start_date || !selectedPeriod?.end_date) return;
    if (date < selectedPeriod.start_date || date > selectedPeriod.end_date) setDate(selectedPeriod.start_date);
  }, [selectedPeriod, date]);

  const attendance = useQuery({ queryKey: ['attendance', sectionId, date], queryFn: () => getSectionAttendance(sectionId, date), enabled: !!sectionId && !!date && !loadingCourses, retry: false });
  useEffect(() => {
    const next = {};
    (attendance.data?.records || []).forEach((record) => { next[record.student_id] = record.status || 'present'; });
    setDraft(next);
  }, [attendance.data]);

  const records = attendance.data?.records || [];
  const changed = useMemo(() => records.filter((record) => (draft[record.student_id] || 'present') !== record.status), [records, draft]);
  const totals = Object.values(draft).reduce((result, status) => ({ ...result, [status]: (result[status] || 0) + 1 }), {});
  const saveMutation = useMutation({
    mutationFn: async () => Promise.all(changed.map((record) => saveAttendance(record.student_id, date, draft[record.student_id] || 'present'))),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['attendance', sectionId, date] }); setMessage({ type: 'success', text: 'Asistencia guardada correctamente.' }); },
    onError: (error) => setMessage({ type: 'error', text: error?.response?.data?.message || 'No se pudo guardar la asistencia.' }),
  });

  return (
    <DashboardLayout>
      <Link to="/docente/attendance" className="mb-4 inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-900"><span className="material-symbols-outlined text-[17px]">arrow_back</span>Volver a las secciones</Link>
      <PageHeading description="Registra la asistencia diaria de los estudiantes de esta sección." />
      <section className="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Sección seleccionada</p><h2 className="mt-1 text-lg font-extrabold text-slate-900">{selectedSection ? `${selectedSection.grade_name} · Sección ${selectedSection.section_name}` : 'Cargando sección...'}</h2><p className="mt-1 text-xs font-semibold text-slate-500">{selectedPeriod?.name} · {selectedSection?.year_label}</p></div><label className="w-full text-xs font-extrabold uppercase tracking-wider text-slate-500 md:w-72">Fecha<input type="date" value={date} min={selectedPeriod?.start_date} max={selectedPeriod?.end_date} onChange={(event) => setDate(event.target.value)} className="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500" /></label></div></section>
      {!periodOpen && <div className="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm font-bold text-indigo-700">Este período todavía no está activo o ya finalizó. La asistencia permanece en modo consulta.</div>}
      {message && <div className={`mb-4 rounded-lg border px-4 py-3 text-sm font-bold ${message.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'}`}>{message.text}</div>}
      <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-4"><div><h2 className="font-extrabold text-slate-900">Estudiantes de la sección</h2><p className="mt-1 text-xs text-slate-500">{records.length} estudiantes · {date}</p></div><div className="flex flex-wrap gap-2 text-xs font-bold"><StatusCount tone="emerald" value={totals.present} label="presentes" /><StatusCount tone="sky" value={totals.late} label="tardanzas" /><StatusCount tone="red" value={totals.absent} label="ausentes" /><StatusCount tone="amber" value={totals.excused} label="justificadas" /></div></header>
        {attendance.isLoading ? <EmptyPanel text="Cargando estudiantes..." icon="progress_activity" spinning borderless /> : attendance.isError ? <EmptyPanel text={attendance.error?.response?.data?.message || 'No se pudo cargar la asistencia.'} icon="error" danger borderless /> : records.length === 0 ? <EmptyPanel text="No hay estudiantes activos en esta sección." icon="group_off" borderless /> : <div className="divide-y divide-slate-100">{records.map((record, index) => <div key={record.student_id} className="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"><div className="flex items-center gap-3"><span className="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-xs font-extrabold text-slate-500">{String(index + 1).padStart(2, '0')}</span><div><p className="font-bold text-slate-900">{record.student_name}</p><p className="text-xs text-slate-400">Registro diario</p></div></div><div className="grid grid-cols-2 gap-2 sm:grid-cols-4">{Object.entries(STATUS_META).map(([status, meta]) => <button key={status} type="button" onClick={() => setDraft((current) => ({ ...current, [record.student_id]: status }))} className={`flex items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-bold transition ${draft[record.student_id] === status ? meta.active : 'border-slate-200 text-slate-500 hover:bg-slate-50'}`}><span className="material-symbols-outlined text-[17px]">{meta.icon}</span>{meta.label}</button>)}</div></div>)}</div>}
        <footer className="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-4"><p className="text-xs font-semibold text-slate-500">{periodOpen ? `${changed.length} cambios pendientes` : 'Período fuera de fecha'}</p><button type="button" disabled={!periodOpen || changed.length === 0 || saveMutation.isPending} onClick={() => saveMutation.mutate()} className="rounded-lg bg-slate-950 px-5 py-3 text-sm font-extrabold text-white disabled:cursor-not-allowed disabled:opacity-40">{saveMutation.isPending ? 'Guardando...' : 'Guardar asistencia'}</button></footer>
      </section>
    </DashboardLayout>
  );
}

function PageHeading({ description }) {
  return <div className="mb-7"><nav className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400"><span>Portal Docente</span><span className="material-symbols-outlined text-[12px]">chevron_right</span><span className="text-indigo-600">Asistencia</span></nav><h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Control de asistencia</h1><p className="mt-1 text-sm text-slate-500">{description}</p></div>;
}

function StatusCount({ tone, value = 0, label }) {
  const colors = { emerald: 'bg-emerald-50 text-emerald-700', sky: 'bg-sky-50 text-sky-700', red: 'bg-red-50 text-red-700', amber: 'bg-amber-50 text-amber-700' };
  return <span className={`rounded-md px-2.5 py-1.5 ${colors[tone]}`}>{value || 0} {label}</span>;
}

function EmptyPanel({ text, icon, spinning = false, danger = false, borderless = false }) {
  return <div className={`${borderless ? '' : 'rounded-xl border border-slate-200 bg-white shadow-sm'} p-12 text-center`}><span className={`material-symbols-outlined text-[40px] ${spinning ? 'animate-spin text-indigo-500' : danger ? 'text-red-400' : 'text-slate-300'}`}>{icon}</span><p className={`mt-3 text-sm font-semibold ${danger ? 'text-red-600' : 'text-slate-500'}`}>{text}</p></div>;
}
