import React, { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAssignments, createTeacher, deactivateAssignment, deleteAssignment, getAssignmentOptions, getAssignments, updateAssignment } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';
import { groupAssignmentCourses } from '../../utils/groupAssignmentCourses';
import SubjectCoursePickerDialog from '../../components/admin/SubjectCoursePickerDialog';

function uniqueBy(list, key) {
  return [...new Set(list.map((item) => item[key]).filter(Boolean))];
}

export default function Assignments() {
  const qc = useQueryClient();
  const { toast, showToast } = useToast();

  const [filterYear, setFilterYear] = useState('');
  const [filterGrade, setFilterGrade] = useState('');
  const [filterSection, setFilterSection] = useState('');
  const [filterSubject, setFilterSubject] = useState('');
  const [filterStatus, setFilterStatus] = useState('active');

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [teacherMode, setTeacherMode] = useState('existing');
  const [teacherId, setTeacherId] = useState('');
  const [newTeacher, setNewTeacher] = useState({ name: '', email: '', password: '' });
  const [courseOfferingIds, setCourseOfferingIds] = useState([]);
  const [courseSearch, setCourseSearch] = useState('');
  const [drawerYear, setDrawerYear] = useState('');
  const [drawerGrade, setDrawerGrade] = useState('');
  const [openSubjectKey, setOpenSubjectKey] = useState(null);
  const [error, setError] = useState('');
  const [toggleTarget, setToggleTarget] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const { data: options } = useQuery({ queryKey: ['admin-assignment-options'], queryFn: getAssignmentOptions });
  const { data: assignments, isLoading, error: assignmentsError } = useQuery({ queryKey: ['admin-assignments'], queryFn: getAssignments });

  const courses = options?.courses || [];
  const teachers = (options?.teachers || []).filter((t) => t.active);

  const years = useMemo(() => uniqueBy(courses, 'academic_year_name'), [courses]);
  const gradesList = useMemo(() => uniqueBy(courses, 'grade_name'), [courses]);
  const sectionsList = useMemo(() => uniqueBy(courses, 'section_name'), [courses]);
  const subjectsList = useMemo(() => uniqueBy(courses, 'subject_name'), [courses]);
  const assignedCourseIds = useMemo(() => new Set((assignments || [])
    .filter((a) => a.active && String(a.teacher_id) === String(teacherId))
    .map((a) => Number(a.course_offering_id))), [assignments, teacherId]);
  const selectableCourses = useMemo(() => courses.filter((course) => {
    if (drawerYear && course.academic_year_name !== drawerYear) return false;
    if (drawerGrade && course.grade_name !== drawerGrade) return false;
    const text = `${course.grade_name} ${course.section_name} ${course.subject_name} ${course.academic_year_name}`.toLocaleLowerCase('es');
    return !courseSearch.trim() || text.includes(courseSearch.trim().toLocaleLowerCase('es'));
  }), [courses, drawerYear, drawerGrade, courseSearch]);
  const subjectGroups = useMemo(() => groupAssignmentCourses(selectableCourses), [selectableCourses]);
  const openSubject = subjectGroups.find((group) => group.key === openSubjectKey) || null;
  const statusCounts = useMemo(() => ({
    active: (assignments || []).filter((assignment) => assignment.active).length,
    inactive: (assignments || []).filter((assignment) => !assignment.active).length,
    all: (assignments || []).length,
  }), [assignments]);

  const filteredRows = (assignments || []).filter((a) => {
    const co = a.course_offering;
    if (filterStatus === 'active' && !a.active) return false;
    if (filterStatus === 'inactive' && a.active) return false;
    if (filterYear && co?.section?.academic_year?.name !== filterYear) return false;
    if (filterGrade && co?.section?.grade?.name !== filterGrade) return false;
    if (filterSection && co?.section?.name !== filterSection) return false;
    if (filterSubject && co?.subject?.name !== filterSubject) return false;
    return true;
  });

  const openCreate = () => {
    setTeacherMode('existing'); setTeacherId(''); setNewTeacher({ name: '', email: '', password: '' });
    setCourseOfferingIds([]); setCourseSearch(''); setDrawerYear(''); setDrawerGrade(''); setOpenSubjectKey(null); setError(''); setDrawerOpen(true);
  };

  const assignMutation = useMutation({
    mutationFn: async () => {
      let finalTeacherId = teacherId;
      if (teacherMode === 'new') {
        const created = await createTeacher(newTeacher);
        finalTeacherId = created.id;
      }
      return createAssignments(finalTeacherId, courseOfferingIds);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-assignments'] });
      qc.invalidateQueries({ queryKey: ['admin-assignment-options'] });
      setDrawerOpen(false);
      showToast(`${courseOfferingIds.length} ${courseOfferingIds.length === 1 ? 'curso asignado' : 'cursos asignados'} correctamente.`);
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id, active }) => active ? updateAssignment(id, { active: true }) : deactivateAssignment(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-assignments'] });
      qc.invalidateQueries({ queryKey: ['admin-dashboard'] });
      setToggleTarget(null);
      showToast('Estado de la asignación actualizado.');
    },
    onError: (err) => showToast(getErrorMessage(err), 'error'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => deleteAssignment(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-assignments'] });
      qc.invalidateQueries({ queryKey: ['admin-assignment-options'] });
      qc.invalidateQueries({ queryKey: ['admin-dashboard'] });
      setDeleteTarget(null);
      showToast('Asignación docente eliminada correctamente.');
    },
    onError: (err) => showToast(getErrorMessage(err), 'error'),
  });

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Asignaciones docentes']}
        title="Asignaciones docentes"
        description="Vincula profesores a los cursos y materias disponibles del ciclo escolar."
        actions={
          <button onClick={openCreate} className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">
            <span className="material-symbols-outlined text-[18px]">assignment_ind</span>
            Asignar docente
          </button>
        }
      />

      <div className="mb-5 space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <p className="text-sm font-extrabold text-slate-900">Estado de las asignaciones</p>
            <p className="mt-0.5 text-xs text-slate-500">Las asignaciones activas se muestran primero por defecto.</p>
          </div>
          <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="group" aria-label="Filtrar asignaciones por estado">
            {[
              ['active', 'Activas', statusCounts.active],
              ['inactive', 'Inactivas', statusCounts.inactive],
              ['all', 'Todas', statusCounts.all],
            ].map(([value, label, count]) => (
              <button key={value} type="button" onClick={() => setFilterStatus(value)} aria-pressed={filterStatus === value}
                className={`rounded-md px-3 py-2 text-xs font-extrabold transition-colors ${filterStatus === value ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:bg-white hover:text-slate-900'}`}>
                {label} <span className={`ml-1 rounded-full px-1.5 py-0.5 text-[10px] ${filterStatus === value ? 'bg-white/15 text-white' : 'bg-slate-200 text-slate-600'}`}>{count}</span>
              </button>
            ))}
          </div>
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <select aria-label="Filtrar por año escolar" value={filterYear} onChange={(e) => setFilterYear(e.target.value)} className={selectClass}>
            <option value="">Todos los años</option>
            {years.map((y) => <option key={y} value={y}>{y}</option>)}
          </select>
          <select aria-label="Filtrar por grado" value={filterGrade} onChange={(e) => setFilterGrade(e.target.value)} className={selectClass}>
            <option value="">Todos los grados</option>
            {gradesList.map((g) => <option key={g} value={g}>{g}</option>)}
          </select>
          <select aria-label="Filtrar por sección" value={filterSection} onChange={(e) => setFilterSection(e.target.value)} className={selectClass}>
            <option value="">Todas las secciones</option>
            {sectionsList.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
          <select aria-label="Filtrar por materia" value={filterSubject} onChange={(e) => setFilterSubject(e.target.value)} className={selectClass}>
            <option value="">Todas las materias</option>
            {subjectsList.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
      </div>

      {assignmentsError && <p role="alert" className="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">{getErrorMessage(assignmentsError)}</p>}

      <DataTable
        loading={isLoading}
        rows={filteredRows}
        emptyIcon="assignment_ind"
        emptyTitle={filterStatus === 'active' ? 'No hay asignaciones activas que coincidan con los filtros.' : filterStatus === 'inactive' ? 'No hay asignaciones inactivas que coincidan con los filtros.' : 'No hay asignaciones que coincidan con los filtros.'}
        columns={[
          { key: 'teacher', label: 'Docente', render: (a) => <div><p className="font-bold text-slate-900">{a.teacher?.name || 'Docente no disponible'}</p><p className="mt-1 text-xs text-slate-500">{a.teacher?.email}</p></div> },
          { key: 'course', label: 'Curso / Materia', render: (a) => {
            const course = a.course_offering;
            return course ? <div>
              <p className="font-semibold text-slate-900">{course.section?.grade?.name || 'Grado no disponible'} · Sección {course.section?.name || '—'}</p>
              <p className="mt-1 text-sm text-slate-600">{course.subject?.name || 'Materia no disponible'}</p>
              <p className="mt-1 text-xs text-slate-500">{course.section?.shift || 'Tanda no registrada'}</p>
            </div> : <span className="text-amber-700">Curso no disponible</span>;
          } },
          { key: 'year', label: 'Año escolar', render: (a) => a.course_offering?.section?.academic_year?.name || '—' },
          { key: 'active', label: 'Estado', align: 'center', render: (a) => <StatusBadge tone={a.active ? 'success' : 'neutral'} label={a.active ? 'Activa' : 'Inactiva'} /> },
          { key: 'assigned_by', label: 'Asignado por', render: (a) => a.assigned_by?.name || 'No registrado' },
          { key: 'assigned_at', label: 'Fecha', render: (a) => a.assigned_at ? String(a.assigned_at).slice(0, 10) : '—' },
          {
            key: 'actions', label: 'Acciones', align: 'right', render: (a) => (
              <div className="flex justify-end gap-1">
                <button
                  type="button"
                  title={a.active ? 'Desactivar asignación' : 'Reactivar asignación'}
                  aria-label={`${a.active ? 'Desactivar' : 'Reactivar'} asignación de ${a.teacher?.name || 'docente'}`}
                  disabled={toggleMutation.isPending}
                  onClick={() => setToggleTarget(a)}
                  className={`flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-40 ${a.active ? 'hover:bg-red-50 hover:text-red-600' : 'hover:bg-emerald-50 hover:text-emerald-600'}`}
                >
                  <span aria-hidden="true" className="material-symbols-outlined text-[18px] leading-none">{a.active ? 'block' : 'restart_alt'}</span>
                </button>
                <button
                  type="button"
                  title="Eliminar asignación"
                  aria-label={`Eliminar asignación de ${a.teacher?.name || 'docente'}`}
                  disabled={deleteMutation.isPending}
                  onClick={() => setDeleteTarget(a)}
                  className="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-40"
                >
                  <span aria-hidden="true" className="material-symbols-outlined text-[18px] leading-none">delete</span>
                </button>
              </div>
            ),
          },
        ]}
      />

      <SideDrawer
        open={drawerOpen}
        onClose={() => { if (openSubjectKey) setOpenSubjectKey(null); else setDrawerOpen(false); }}
        title="Asignar docente"
        description="Selecciona un profesor y asígnale uno o varios cursos en una sola operación."
        widthClass="max-w-2xl"
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerOpen(false)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button
              onClick={() => assignMutation.mutate()}
              disabled={assignMutation.isPending || courseOfferingIds.length === 0 || (teacherMode === 'existing' ? !teacherId : !newTeacher.name || !newTeacher.email)}
              className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {assignMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Asignar {courseOfferingIds.length || ''} {courseOfferingIds.length === 1 ? 'curso' : courseOfferingIds.length > 1 ? 'cursos' : ''}
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}

          <div className="flex gap-2 rounded-lg border border-slate-200 p-1">
            <button onClick={() => setTeacherMode('existing')} className={`flex-1 rounded-md py-1.5 text-xs font-bold ${teacherMode === 'existing' ? 'bg-slate-950 text-white' : 'text-slate-500'}`}>Docente existente</button>
            <button onClick={() => setTeacherMode('new')} className={`flex-1 rounded-md py-1.5 text-xs font-bold ${teacherMode === 'new' ? 'bg-slate-950 text-white' : 'text-slate-500'}`}>Nuevo docente</button>
          </div>

          {teacherMode === 'existing' ? (
            <FormField label="Profesor" required>
              <select className={selectClass} value={teacherId} onChange={(e) => setTeacherId(e.target.value)}>
                <option value="">Seleccionar profesor</option>
                {teachers.map((t) => <option key={t.id} value={t.id}>{t.name} · {t.email}</option>)}
              </select>
            </FormField>
          ) : (
            <>
              <FormField label="Nombre" required>
                <input className={inputClass} value={newTeacher.name} onChange={(e) => setNewTeacher({ ...newTeacher, name: e.target.value })} />
              </FormField>
              <FormField label="Correo" required>
                <input type="email" className={inputClass} value={newTeacher.email} onChange={(e) => setNewTeacher({ ...newTeacher, email: e.target.value })} />
              </FormField>
              <FormField label="Contraseña" hint="Si se omite, se genera una aleatoria.">
                <input type="password" className={inputClass} value={newTeacher.password} onChange={(e) => setNewTeacher({ ...newTeacher, password: e.target.value })} />
              </FormField>
            </>
          )}

          <div className="space-y-3 border-t border-slate-200 pt-4">
            <div className="flex items-center justify-between gap-3">
              <div><h3 className="text-sm font-extrabold text-slate-900">Cursos</h3><p className="text-xs text-slate-500">Marca todas las materias y grupos que impartirá.</p></div>
              <span className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-extrabold text-indigo-700">{courseOfferingIds.length} seleccionados</span>
            </div>
            <input aria-label="Buscar cursos" className={inputClass} placeholder="Buscar materia, grado o sección…" value={courseSearch} onChange={(e) => setCourseSearch(e.target.value)} />
            <div className="grid grid-cols-2 gap-2">
              <select aria-label="Filtrar cursos por año" className={selectClass} value={drawerYear} onChange={(e) => setDrawerYear(e.target.value)}>
                <option value="">Todos los años</option>{years.map((year) => <option key={year} value={year}>{year}</option>)}
              </select>
              <select aria-label="Filtrar cursos por grado" className={selectClass} value={drawerGrade} onChange={(e) => setDrawerGrade(e.target.value)}>
                <option value="">Todos los grados</option>{gradesList.map((grade) => <option key={grade} value={grade}>{grade}</option>)}
              </select>
            </div>
            <div className="flex items-center justify-between border-y border-slate-100 py-2">
              <span className="text-xs text-slate-500">{subjectGroups.length} materias · {selectableCourses.length} cursos</span>
              <button type="button" disabled={!courseOfferingIds.length} onClick={() => setCourseOfferingIds([])} className="text-xs font-bold text-slate-500 disabled:opacity-40">Limpiar selección</button>
            </div>
            <div className="max-h-[46dvh] space-y-3 overflow-y-auto pr-1">
              {subjectGroups.length === 0 ? <p className="rounded-lg border border-dashed border-slate-200 p-5 text-center text-sm text-slate-500">No hay materias para estos filtros.</p> : subjectGroups.map((group) => {
                const availableIds = group.courses.map((course) => Number(course.id)).filter((id) => !assignedCourseIds.has(id));
                const selectedCount = availableIds.filter((id) => courseOfferingIds.includes(id)).length;
                return <button type="button" key={group.key} onClick={() => setOpenSubjectKey(group.key)} className={`flex w-full items-center gap-3 rounded-xl border bg-white p-3 text-left transition-all hover:border-slate-400 hover:shadow-sm ${selectedCount ? 'border-indigo-400 ring-1 ring-indigo-100' : 'border-slate-200'}`}>
                      <span aria-hidden="true" className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50">
                        <span className="material-symbols-outlined relative top-[3px] block text-[22px] leading-none text-indigo-600">menu_book</span>
                      </span>
                      <span className="min-w-0 flex-1"><span className="block truncate text-sm font-extrabold text-slate-900">{group.name}</span><span className="mt-0.5 block text-[11px] text-slate-500">{group.courses.length} {group.courses.length === 1 ? 'curso' : 'cursos'}{selectedCount ? ` · ${selectedCount} seleccionados` : ''}</span></span>
                      <span aria-hidden="true" className="flex h-10 w-10 shrink-0 items-center justify-center">
                        <span className="material-symbols-outlined relative top-[3px] block text-[22px] leading-none text-slate-400">open_in_new</span>
                      </span>
                    </button>;
              })}
            </div>
          </div>
        </div>
      </SideDrawer>

      <SubjectCoursePickerDialog group={openSubject} assignedIds={assignedCourseIds} selectedIds={courseOfferingIds}
        onChange={setCourseOfferingIds} onClose={() => setOpenSubjectKey(null)} />

      <ConfirmDialog
        open={!!toggleTarget}
        onClose={() => { if (!toggleMutation.isPending) setToggleTarget(null); }}
        onConfirm={() => toggleMutation.mutate({ id: toggleTarget.id, active: !toggleTarget.active })}
        loading={toggleMutation.isPending}
        title={toggleTarget?.active ? 'Desactivar asignación docente' : 'Reactivar asignación docente'}
        tone={toggleTarget?.active ? 'danger' : 'default'}
        confirmLabel={toggleTarget?.active ? 'Desactivar asignación' : 'Reactivar asignación'}
        message={`${toggleTarget?.teacher?.name || 'Docente'} · ${toggleTarget?.course_offering?.subject?.name || 'Materia'} · ${toggleTarget?.course_offering?.section?.grade?.name || 'Grado'} ${toggleTarget?.course_offering?.section?.name || ''}. ${toggleTarget?.active ? 'El docente dejará de tener acceso mediante esta asignación. El curso y las calificaciones se conservan.' : 'Se volverá a activar el acceso a este curso mediante la asignación.'}`}
      />
      <ConfirmDialog
        open={!!deleteTarget}
        onClose={() => { if (!deleteMutation.isPending) setDeleteTarget(null); }}
        onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
        loading={deleteMutation.isPending}
        title="Eliminar asignación docente"
        tone="danger"
        confirmLabel="Eliminar asignación"
        message={`${deleteTarget?.teacher?.name || 'Docente'} · ${deleteTarget?.course_offering?.subject?.name || 'Materia'} · ${deleteTarget?.course_offering?.section?.grade?.name || 'Grado'} ${deleteTarget?.course_offering?.section?.name || ''}. Se eliminará el vínculo del docente con este curso. El curso, las actividades y las calificaciones se conservarán.`}
      />
      <Toast toast={toast} />
    </>
  );
}
