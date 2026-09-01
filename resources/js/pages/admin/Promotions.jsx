import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getAcademicYears, getPromotionCandidates } from '../../api/admin.api';
import PageHeader from '../../components/ui/PageHeader';
import { selectClass } from '../../components/ui/FormField';
import LoadingSkeleton from '../../components/ui/LoadingSkeleton';
import EmptyState from '../../components/ui/EmptyState';

export default function Promotions() {
  const navigate = useNavigate();
  const [yearId, setYearId] = useState('');
  const { data: years } = useQuery({ queryKey: ['admin-years'], queryFn: getAcademicYears });
  useEffect(() => {
    if (!yearId && years?.length) setYearId(String(years.find((year) => year.active)?.id || years[0].id));
  }, [years, yearId]);
  const { data: candidates = [], isLoading } = useQuery({
    queryKey: ['admin-promotion-candidates', { academic_year_id: yearId }],
    queryFn: () => getPromotionCandidates({ academic_year_id: yearId }), enabled: !!yearId,
  });
  const courses = useMemo(() => {
    const groups = new Map();
    candidates.forEach((student) => {
      if (!groups.has(student.section_id)) groups.set(student.section_id, {
        id: student.section_id, grade: student.grade_name, section: student.section_name,
        shift: student.shift, year: student.academic_year_name, students: [],
        promotionOpen: student.promotion_open, promotionBlockReason: student.promotion_block_reason,
      });
      groups.get(student.section_id).students.push(student);
    });
    return [...groups.values()].sort((a, b) => a.grade.localeCompare(b.grade, 'es', { numeric: true }) || a.section.localeCompare(b.section, 'es', { numeric: true }));
  }, [candidates]);
  const decided = candidates.filter((candidate) => candidate.decision).length;
  const eligible = candidates.filter((candidate) => candidate.eligible).length;

  return <>
    <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
      <PageHeader breadcrumb={['Portal Administrativo', 'Promoción escolar']} title="Promoción escolar"
        description="Selecciona un curso para revisar y procesar a todos sus estudiantes en un workspace organizado." />
      <select aria-label="Seleccionar año escolar" value={yearId} onChange={(event) => setYearId(event.target.value)} className={`${selectClass} mb-6 w-full sm:w-56`}>
        {years?.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
      </select>
    </div>
    <section className="mb-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <Kpi label="Cursos" value={courses.length} icon="school" />
      <Kpi label="Estudiantes" value={candidates.length} icon="groups" />
      <Kpi label="Elegibles" value={eligible} icon="verified" />
      <Kpi label="Procesados" value={decided} icon="task_alt" />
    </section>
    {isLoading ? <LoadingSkeleton /> : courses.length === 0 ? <div className="rounded-xl border border-slate-200 bg-white"><EmptyState icon="upgrade" title="No hay cursos con estudiantes para este año escolar" /></div> : <section className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
      {courses.map((course) => {
        const courseEligible = course.students.filter((student) => student.eligible).length;
        const courseDecided = course.students.filter((student) => student.decision).length;
        const pending = course.students.length - courseDecided;
        return <article key={course.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md">
          <div className="border-b border-indigo-100 bg-indigo-50/70 px-5 py-5"><div className="flex items-start justify-between gap-3"><div><span className={`inline-flex rounded-md px-2 py-1 text-[9px] font-extrabold uppercase ${course.promotionOpen ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-100 text-amber-800'}`}>{course.promotionOpen ? 'Listo para promoción' : 'Ciclo abierto'}</span><p className="mt-4 text-[10px] font-extrabold uppercase tracking-wider text-indigo-500">Curso / Sección</p><h2 className="mt-1 text-xl font-extrabold text-slate-950">{course.grade} {course.section}</h2></div><span aria-hidden="true" className="material-symbols-outlined relative top-[3px] text-3xl text-indigo-500">school</span></div><p className="mt-2 text-xs text-slate-500">{course.shift} · {course.year}</p></div>
          <div className="p-5"><div className="grid grid-cols-3 gap-2 border-b border-slate-100 pb-5 text-center"><Stat label="Estudiantes" value={course.students.length} /><Stat label="Elegibles" value={courseEligible} /><Stat label="Pendientes" value={pending} tone={pending ? 'amber' : 'emerald'} /></div>{!course.promotionOpen && <p className="mt-4 rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-800">{course.promotionBlockReason}</p>}<div className="mt-5 flex items-center justify-between gap-3"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Workspace del curso</p><p className="mt-1 text-xs text-slate-500">{courseDecided} decisiones registradas</p></div><button type="button" onClick={() => navigate(`/admin/promotions/${course.id}?year=${yearId}`)} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-slate-800">Abrir <span aria-hidden="true" className="material-symbols-outlined text-[18px]">arrow_forward</span></button></div></div>
        </article>;
      })}
    </section>}
  </>;
}

function Kpi({ label, value, icon }) {
  return <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><div className="flex items-start justify-between"><div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p><p className="mt-2 text-2xl font-extrabold text-slate-950">{value}</p></div><span aria-hidden="true" className="material-symbols-outlined relative top-[2px] text-slate-400">{icon}</span></div></div>;
}

function Stat({ label, value, tone = 'slate' }) {
  const color = tone === 'amber' ? 'text-amber-600' : tone === 'emerald' ? 'text-emerald-600' : 'text-slate-950';
  return <div><p className="text-[9px] font-extrabold uppercase tracking-wide text-slate-400">{label}</p><p className={`mt-1 text-lg font-extrabold ${color}`}>{value}</p></div>;
}
