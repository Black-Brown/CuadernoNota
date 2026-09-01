import React, { useEffect, useMemo, useRef } from 'react';
import { groupSubjectSections } from '../../utils/groupAssignmentCourses';

export default function SubjectCoursePickerDialog({ group, assignedIds, selectedIds, onChange, onClose }) {
  const dialogRef = useRef(null);
  const sectionGroups = useMemo(() => groupSubjectSections(group?.courses || []), [group]);
  const availableIds = (group?.courses || []).map((course) => Number(course.id)).filter((id) => !assignedIds.has(id));
  const selectedCount = availableIds.filter((id) => selectedIds.includes(id)).length;
  const allSelected = availableIds.length > 0 && selectedCount === availableIds.length;

  useEffect(() => {
    if (group && !dialogRef.current.open) dialogRef.current.showModal();
  }, [group]);

  if (!group) return null;
  const toggle = (id) => onChange(selectedIds.includes(id) ? selectedIds.filter((value) => value !== id) : [...selectedIds, id]);

  return (
    <dialog ref={dialogRef} onCancel={(event) => { event.preventDefault(); onClose(); }}
      aria-labelledby="subject-picker-title" className="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/55">
      <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
        <div className="flex min-w-0 items-start gap-3">
          <span aria-hidden="true" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
            <span className="material-symbols-outlined relative top-[3px] block text-[22px] leading-none text-indigo-600">menu_book</span>
          </span>
          <div><p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Seleccionar secciones</p><h2 id="subject-picker-title" className="mt-1 text-xl font-extrabold text-slate-950">{group.name}</h2><p className="mt-1 text-sm text-slate-500">Cada bloque corresponde a un grado y año escolar. Las secciones no se mezclan entre bloques.</p></div>
        </div>
        <button type="button" onClick={onClose} aria-label="Cerrar selección de secciones" className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"><span aria-hidden="true" className="material-symbols-outlined">close</span></button>
      </div>

      <div className="max-h-[calc(90dvh-180px)] space-y-5 overflow-y-auto bg-slate-50 px-6 py-5">
        {sectionGroups.map((sectionGroup) => <section key={sectionGroup.key} className="overflow-hidden rounded-xl border border-slate-200 bg-white">
          <div className="flex items-center justify-between gap-3 border-b border-slate-100 bg-white px-4 py-3">
            <div><h3 className="text-sm font-extrabold text-slate-900">{sectionGroup.grade}</h3><p className="mt-0.5 text-xs text-slate-500">Año escolar {sectionGroup.year}</p></div>
            <span className="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">{sectionGroup.courses.length} {sectionGroup.courses.length === 1 ? 'sección' : 'secciones'}</span>
          </div>
          <div className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
            {sectionGroup.courses.map((course) => {
              const id = Number(course.id); const assigned = assignedIds.has(id); const checked = selectedIds.includes(id);
              return <label key={id} className={`relative min-h-28 rounded-xl border p-4 ${assigned ? 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-60' : checked ? 'cursor-pointer border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'cursor-pointer border-slate-200 bg-white hover:border-slate-400'}`}>
                <div className="flex items-start justify-between gap-2"><span className="text-base font-extrabold text-slate-950">Sección {course.section_name}</span><input type="checkbox" disabled={assigned} checked={assigned || checked} onChange={() => toggle(id)} className="h-4 w-4 accent-indigo-600" /></div>
                <p className="mt-2 text-xs text-slate-500">{course.shift || 'Tanda no registrada'}</p>
                {assigned && <span className="mt-2 inline-flex rounded bg-emerald-50 px-2 py-1 text-[9px] font-extrabold uppercase text-emerald-700">Ya asignada</span>}
              </label>;
            })}
          </div>
        </section>)}
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-white px-6 py-4">
        <p className="text-sm font-semibold text-slate-600"><span className="font-extrabold text-indigo-700">{selectedCount}</span> seleccionadas en esta materia</p>
        <div className="flex gap-2"><button type="button" disabled={!availableIds.length} onClick={() => onChange(allSelected ? selectedIds.filter((id) => !availableIds.includes(id)) : [...new Set([...selectedIds, ...availableIds])])} className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 disabled:opacity-40">{allSelected ? 'Quitar todas' : 'Seleccionar todas'}</button><button type="button" onClick={onClose} className="rounded-lg bg-slate-950 px-4 py-2 text-xs font-bold text-white">Aplicar selección</button></div>
      </div>
    </dialog>
  );
}
