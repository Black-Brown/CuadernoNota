import React, { useMemo, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import DashboardLayout from '../../components/DashboardLayout';
import { getCourses } from '../../api/courses.api';
import {
  createObservation,
  deleteObservation,
  getCourseObservations,
  updateObservation,
} from '../../api/observations.api';
import usePeriodStore from '../../store/periodStore';

const TYPE_META = {
  academic: {
    label: 'Académica',
    icon: 'school',
    badge: 'bg-blue-50 text-blue-700 border-blue-100',
    dot: 'bg-blue-500',
    soft: 'bg-blue-50 text-blue-700',
  },
  disciplinary: {
    label: 'Disciplinaria',
    icon: 'gavel',
    badge: 'bg-amber-50 text-amber-700 border-amber-100',
    dot: 'bg-amber-500',
    soft: 'bg-amber-50 text-amber-700',
  },
  incident: {
    label: 'Incidencia',
    icon: 'report',
    badge: 'bg-red-50 text-red-700 border-red-100',
    dot: 'bg-red-500',
    soft: 'bg-red-50 text-red-700',
  },
};

const EMPTY_FORM = {
  type: 'academic',
  date: new Date().toISOString().slice(0, 10),
  description: '',
};

function normalize(value = '') {
  return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function subjectIcon(subject = '') {
  const value = normalize(subject);
  if (value.includes('matematica')) return 'calculate';
  if (value.includes('lengua')) return 'menu_book';
  if (value.includes('social')) return 'public';
  if (value.includes('robot') || value.includes('program')) return 'memory';
  if (value.includes('ingles')) return 'translate';
  if (value.includes('fisica')) return 'sports_handball';
  return 'school';
}

export default function Observations() {
  const { sectionId, subjectId } = useParams();

  if (sectionId && subjectId) {
    return <ObservationWorkspace sectionId={sectionId} subjectId={subjectId} />;
  }

  return <ObservationCourses />;
}

function ObservationCourses() {
  const { data, isLoading } = useQuery({
    queryKey: ['courses'],
    queryFn: getCourses,
    staleTime: 5 * 60_000,
    refetchOnWindowFocus: false,
  });

  const courses = data?.courses || [];

  return (
    <DashboardLayout>
      <div className="mb-7 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <nav className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            <span>Portal Docente</span>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <span className="text-indigo-600">Observaciones</span>
          </nav>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Observaciones</h1>
          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
            Selecciona un curso para trabajar sus observaciones como un workspace cerrado por período.
          </p>
        </div>
      </div>

      {isLoading ? (
        <div className="rounded-lg border border-slate-200 bg-white p-12 text-center shadow-sm">
          <span className="material-symbols-outlined animate-spin text-[42px] text-indigo-500">progress_activity</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">Cargando cursos...</p>
        </div>
      ) : courses.length === 0 ? (
        <div className="rounded-lg border border-dashed border-slate-200 bg-white p-12 text-center text-sm font-semibold text-slate-400">
          No tienes cursos asignados.
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
          {courses.map((course) => (
            <Link
              key={`${course.section_id}-${course.subject_id}`}
              to={`/docente/observations/${course.section_id}/${course.subject_id}`}
              className="group rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md"
            >
              <div className="mb-5 flex items-start justify-between gap-4">
                <div className="flex min-w-0 gap-3">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700">
                    <span className="material-symbols-outlined text-[27px]">{subjectIcon(course.subject_name)}</span>
                  </div>
                  <div className="min-w-0">
                    <p className="truncate text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                      Workspace de observaciones
                    </p>
                    <h2 className="mt-1 text-base font-extrabold leading-snug text-slate-900">
                      {course.grade_name} {course.section_name}
                    </h2>
                    <p className="mt-1 text-sm font-semibold text-slate-500">{course.subject_name}</p>
                  </div>
                </div>
                <span className="material-symbols-outlined text-[20px] text-slate-300 group-hover:text-slate-700">arrow_forward</span>
              </div>

              <div className="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                <div>
                  <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Año</p>
                  <p className="mt-1 text-sm font-bold text-slate-800">{course.year_label || 'Activo'}</p>
                </div>
                <div>
                  <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Modo</p>
                  <p className="mt-1 text-sm font-bold text-slate-800">Por estudiante</p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}

function ObservationWorkspace({ sectionId, subjectId }) {
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);
  const [selectedStudentId, setSelectedStudentId] = useState(searchParams.get('student_id'));
  const [studentSearch, setStudentSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('all');
  const [editingObservation, setEditingObservation] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [toast, setToast] = useState('');
  const [form, setForm] = useState(EMPTY_FORM);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['courseObservations', sectionId, subjectId, selectedPeriod?.id],
    queryFn: () => getCourseObservations(sectionId, subjectId, selectedPeriod?.id),
    enabled: !!sectionId && !!subjectId && !!selectedPeriod?.id,
    staleTime: 60_000,
    refetchOnWindowFocus: false,
  });

  const course = data?.course;
  const summary = data?.summary;
  const students = data?.students || [];
  const observations = data?.observations || [];
  const canEdit = summary?.can_edit ?? false;

  const selectedStudent = useMemo(() => {
    if (students.length === 0) return null;
    const current = students.find((student) => Number(student.student_id) === Number(selectedStudentId));
    return current || students[0];
  }, [students, selectedStudentId]);

  const studentObservations = useMemo(() => {
    if (!selectedStudent) return [];
    return observations.filter((observation) => {
      const matchesStudent = Number(observation.student_id) === Number(selectedStudent.student_id);
      const matchesType = typeFilter === 'all' || observation.type === typeFilter;
      return matchesStudent && matchesType;
    });
  }, [observations, selectedStudent, typeFilter]);

  const filteredStudents = useMemo(() => {
    const value = normalize(studentSearch);
    if (!value) return students;
    return students.filter((student) => (
      normalize(student.student_name).includes(value)
      || normalize(student.display_name).includes(value)
      || normalize(student.enrollment_no).includes(value)
    ));
  }, [students, studentSearch]);

  const invalidateWorkspace = () => {
    queryClient.invalidateQueries({ queryKey: ['courseObservations', sectionId, subjectId, selectedPeriod?.id] });
  };

  const showToast = (message) => {
    setToast(message);
    setTimeout(() => setToast(''), 3500);
  };

  const resetForm = () => {
    setForm(EMPTY_FORM);
    setEditingObservation(null);
  };

  const createMutation = useMutation({
    mutationFn: createObservation,
    onSuccess: () => {
      invalidateWorkspace();
      resetForm();
      showToast('Observación registrada.');
    },
    onError: (error) => showToast(error?.response?.data?.message || 'No se pudo registrar la observación.'),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data: payload }) => updateObservation(id, payload),
    onSuccess: () => {
      invalidateWorkspace();
      resetForm();
      showToast('Observación actualizada.');
    },
    onError: (error) => showToast(error?.response?.data?.message || 'No se pudo actualizar la observación.'),
  });

  const deleteMutation = useMutation({
    mutationFn: deleteObservation,
    onSuccess: () => {
      invalidateWorkspace();
      setDeleteTarget(null);
      showToast('Observación eliminada.');
    },
    onError: (error) => showToast(error?.response?.data?.message || 'No se pudo eliminar la observación.'),
  });

  const submitObservation = () => {
    if (!selectedStudent) {
      showToast('Selecciona un estudiante.');
      return;
    }

    if (!form.date || !form.type || !form.description.trim()) {
      showToast('Completa fecha, tipo y descripción.');
      return;
    }

    if (editingObservation) {
      updateMutation.mutate({
        id: editingObservation.id,
        data: {
          date: form.date,
          type: form.type,
          description: form.description.trim(),
        },
      });
      return;
    }

    createMutation.mutate({
      student_id: Number(selectedStudent.student_id),
      section_id: Number(sectionId),
      subject_id: Number(subjectId),
      period_id: selectedPeriod?.id,
      date: form.date,
      type: form.type,
      description: form.description.trim(),
    });
  };

  const startEdit = (observation) => {
    setEditingObservation(observation);
    setForm({
      date: observation.date,
      type: observation.type,
      description: observation.description,
    });
  };

  const isPending = createMutation.isPending || updateMutation.isPending;

  return (
    <DashboardLayout>
      {toast && (
        <div className="fixed right-6 top-20 z-50 flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-3 text-xs font-semibold text-white shadow-lg">
          <span className="material-symbols-outlined text-base text-emerald-400">check_circle</span>
          {toast}
        </div>
      )}

      <nav className="mb-5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
        <Link to="/docente/observations" className="hover:text-slate-700">Observaciones</Link>
        <span className="material-symbols-outlined text-[12px]">chevron_right</span>
        <span>{course ? `${course.grade_name} ${course.section_name}` : 'Curso'}</span>
        <span className="material-symbols-outlined text-[12px]">chevron_right</span>
        <span className="text-indigo-600">{course?.subject_name || 'Workspace'}</span>
      </nav>

      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Workspace de Observaciones</h1>
          <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
            {course
              ? `${course.grade_name} ${course.section_name} · ${course.subject_name}`
              : 'Listado de estudiantes y observaciones del curso seleccionado.'}
          </p>
        </div>

        <div className="rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Período activo</p>
          <p className="mt-1 text-sm font-bold text-slate-900">{selectedPeriod?.name || 'Sin período'}</p>
        </div>
      </div>

      {isError && (
        <div className="mb-5 rounded-lg border border-red-100 bg-red-50 p-4 text-sm font-semibold text-red-700">
          No se pudo cargar este workspace de observaciones.
        </div>
      )}

      {isLoading ? (
        <div className="rounded-lg border border-slate-200 bg-white p-12 text-center shadow-sm">
          <span className="material-symbols-outlined animate-spin text-[42px] text-indigo-500">progress_activity</span>
          <p className="mt-3 text-sm font-semibold text-slate-600">Cargando estudiantes...</p>
        </div>
      ) : (
        <>
          {!canEdit && data && (
            <div className="mb-5 flex items-start gap-3 rounded-lg border border-amber-100 bg-amber-50 px-4 py-3 text-amber-800">
              <span className="material-symbols-outlined mt-0.5 text-[19px]">lock</span>
              <div>
                <p className="text-xs font-extrabold uppercase tracking-wider">Workspace bloqueado</p>
                <p className="mt-0.5 text-sm leading-5 text-amber-700">
                  Solo puedes consultar observaciones porque el período está cerrado o en revisión.
                </p>
              </div>
            </div>
          )}

          <section className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            {[
              ['Estudiantes', students.length, 'groups'],
              ['Observaciones', summary?.total_observations ?? 0, 'speaker_notes'],
              ['Con seguimiento', summary?.students_with_observations ?? 0, 'fact_check'],
              ['Incidencias', summary?.incident ?? 0, 'report'],
            ].map(([label, value, icon]) => (
              <div key={label} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div className="mb-3 flex items-center justify-between">
                  <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</p>
                  <span className="material-symbols-outlined text-[18px] text-slate-400">{icon}</span>
                </div>
                <p className="font-mono text-2xl font-extrabold text-slate-900">{value}</p>
              </div>
            ))}
          </section>

          <section className="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <aside className="xl:col-span-4">
              <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div className="border-b border-slate-100 p-4">
                  <div className="relative">
                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400">search</span>
                    <input
                      value={studentSearch}
                      onChange={(event) => setStudentSearch(event.target.value)}
                      placeholder="Buscar estudiante..."
                      className="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500"
                    />
                  </div>
                </div>

                <div className="max-h-[620px] overflow-y-auto p-2">
                  {filteredStudents.map((student) => {
                    const active = Number(selectedStudent?.student_id) === Number(student.student_id);
                    return (
                      <button
                        key={student.student_id}
                        type="button"
                        onClick={() => {
                          setSelectedStudentId(student.student_id);
                          setTypeFilter('all');
                          resetForm();
                        }}
                        className={`mb-1 flex w-full items-center justify-between rounded-lg px-3 py-3 text-left transition-all ${
                          active ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-slate-50'
                        }`}
                      >
                        <span className="flex min-w-0 items-center gap-3">
                          <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-extrabold ${
                            active ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-700'
                          }`}>
                            {student.display_name.substring(0, 2).toUpperCase()}
                          </span>
                          <span className="min-w-0">
                            <span className="block truncate text-sm font-bold">{student.display_name}</span>
                            <span className={`block text-[11px] ${active ? 'text-slate-300' : 'text-slate-400'}`}>
                              {student.enrollment_no}
                            </span>
                          </span>
                        </span>
                        <span className={`rounded-full px-2 py-0.5 text-[10px] font-extrabold ${
                          active ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500'
                        }`}>
                          {student.observation_count}
                        </span>
                      </button>
                    );
                  })}

                  {filteredStudents.length === 0 && (
                    <div className="p-8 text-center text-sm font-semibold text-slate-400">
                      No hay estudiantes con ese filtro.
                    </div>
                  )}
                </div>
              </div>
            </aside>

            <div className="xl:col-span-8">
              {!selectedStudent ? (
                <div className="rounded-lg border border-dashed border-slate-200 bg-white p-16 text-center shadow-sm">
                  <span className="material-symbols-outlined text-[42px] text-slate-300">person_search</span>
                  <p className="mt-3 text-sm font-semibold text-slate-500">Selecciona un estudiante.</p>
                </div>
              ) : (
                <div className="space-y-5">
                  <StudentHeader student={selectedStudent} />

                  <ObservationForm
                    canEdit={canEdit}
                    form={form}
                    setForm={setForm}
                    editingObservation={editingObservation}
                    onCancel={resetForm}
                    onSubmit={submitObservation}
                    isPending={isPending}
                  />

                  <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                      <div>
                        <h2 className="text-sm font-extrabold text-slate-900">Historial del estudiante</h2>
                        <p className="mt-0.5 text-xs text-slate-400">Observaciones registradas en este curso y período.</p>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <FilterChip active={typeFilter === 'all'} onClick={() => setTypeFilter('all')} label="Todos" />
                        {Object.entries(TYPE_META).map(([key, meta]) => (
                          <FilterChip key={key} active={typeFilter === key} onClick={() => setTypeFilter(key)} label={meta.label} />
                        ))}
                      </div>
                    </div>

                    <div className="p-4">
                      {studentObservations.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-slate-200 p-10 text-center">
                          <span className="material-symbols-outlined text-[36px] text-slate-300">speaker_notes_off</span>
                          <p className="mt-2 text-sm font-semibold text-slate-500">Sin observaciones para este estudiante.</p>
                        </div>
                      ) : (
                        <div className="space-y-3">
                          {studentObservations.map((observation) => (
                            <ObservationItem
                              key={observation.id}
                              observation={observation}
                              canEdit={canEdit}
                              onEdit={startEdit}
                              onDelete={setDeleteTarget}
                            />
                          ))}
                        </div>
                      )}
                    </div>
                  </div>
                </div>
              )}
            </div>
          </section>
        </>
      )}

      {deleteTarget && (
        <ConfirmDelete
          observation={deleteTarget}
          isPending={deleteMutation.isPending}
          onCancel={() => setDeleteTarget(null)}
          onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
        />
      )}
    </DashboardLayout>
  );
}

function StudentHeader({ student }) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex items-center gap-4">
        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-extrabold text-indigo-700">
          {student.display_name.substring(0, 2).toUpperCase()}
        </div>
        <div className="min-w-0">
          <p className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Estudiante seleccionado</p>
          <h2 className="mt-1 truncate text-xl font-extrabold text-slate-900">{student.display_name}</h2>
          <p className="mt-1 font-mono text-xs font-semibold text-slate-400">{student.enrollment_no}</p>
        </div>
      </div>
    </div>
  );
}

function ObservationForm({ canEdit, form, setForm, editingObservation, onCancel, onSubmit, isPending }) {
  return (
    <div className={`rounded-lg border bg-white p-5 shadow-sm ${canEdit ? 'border-slate-200' : 'border-slate-200 opacity-70'}`}>
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-extrabold text-slate-900">
            {editingObservation ? 'Editar observación' : 'Agregar observación'}
          </h2>
          <p className="mt-0.5 text-xs text-slate-400">Queda registrada solo en este curso y período.</p>
        </div>
        {editingObservation && (
          <button
            type="button"
            onClick={onCancel}
            className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
          >
            Cancelar edición
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <FormField label="Tipo">
          <select
            value={form.type}
            onChange={(event) => setForm({ ...form, type: event.target.value })}
            disabled={!canEdit}
            className={inputClass}
          >
            <option value="academic">Académica</option>
            <option value="disciplinary">Disciplinaria</option>
            <option value="incident">Incidencia</option>
          </select>
        </FormField>

        <FormField label="Fecha">
          <input
            type="date"
            value={form.date}
            onChange={(event) => setForm({ ...form, date: event.target.value })}
            disabled={!canEdit}
            className={inputClass}
          />
        </FormField>

        <div className="flex items-end">
          <button
            type="button"
            onClick={onSubmit}
            disabled={!canEdit || isPending}
            className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 text-xs font-extrabold text-white transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
            {editingObservation ? 'Actualizar' : 'Guardar'}
          </button>
        </div>
      </div>

      <div className="mt-4">
        <FormField label="Descripción">
          <textarea
            value={form.description}
            onChange={(event) => setForm({ ...form, description: event.target.value })}
            disabled={!canEdit}
            rows={4}
            placeholder="Escribe una observación clara y concreta..."
            className={`${inputClass} resize-none`}
          />
        </FormField>
      </div>
    </div>
  );
}

function ObservationItem({ observation, canEdit, onEdit, onDelete }) {
  const meta = TYPE_META[observation.type] || TYPE_META.academic;

  return (
    <article className="group rounded-lg border border-slate-200 bg-white p-4 transition-all hover:border-slate-300">
      <div className="mb-3 flex items-start justify-between gap-3">
        <div className="flex items-center gap-2">
          <span className={`flex h-8 w-8 items-center justify-center rounded-lg ${meta.soft}`}>
            <span className="material-symbols-outlined text-[18px]">{meta.icon}</span>
          </span>
          <div>
            <span className={`inline-flex rounded-full border px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider ${meta.badge}`}>
              {meta.label}
            </span>
            <p className="mt-1 text-[11px] font-semibold text-slate-400">{observation.date} · {observation.teacher_name || 'Docente'}</p>
          </div>
        </div>

        {canEdit && (
          <div className="flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
            <button type="button" onClick={() => onEdit(observation)} className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
              <span className="material-symbols-outlined text-[18px]">edit</span>
            </button>
            <button type="button" onClick={() => onDelete(observation)} className="rounded-lg p-2 text-slate-400 hover:bg-red-50 hover:text-red-600">
              <span className="material-symbols-outlined text-[18px]">delete</span>
            </button>
          </div>
        )}
      </div>
      <p className="text-sm leading-6 text-slate-600">{observation.description}</p>
    </article>
  );
}

function ConfirmDelete({ observation, isPending, onCancel, onConfirm }) {
  return (
    <>
      <div className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-[1px]" onClick={onCancel} />
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div className="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-2xl">
          <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-red-50">
            <span className="material-symbols-outlined text-[24px] text-red-600">delete</span>
          </div>
          <h3 className="mb-1 text-base font-extrabold text-slate-900">Eliminar observación</h3>
          <p className="mb-5 text-sm leading-6 text-slate-500">
            Esta observación de {observation.student_name || 'este estudiante'} será eliminada del workspace.
          </p>
          <div className="flex gap-3">
            <button type="button" onClick={onCancel} className="flex-1 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
              Cancelar
            </button>
            <button type="button" onClick={onConfirm} disabled={isPending} className="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-60">
              Eliminar
            </button>
          </div>
        </div>
      </div>
    </>
  );
}

function FilterChip({ active, onClick, label }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-lg border px-3 py-2 text-[11px] font-bold transition-all ${
        active ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
      }`}
    >
      {label}
    </button>
  );
}

function FormField({ label, children }) {
  return (
    <label className="block">
      <span className="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{label}</span>
      {children}
    </label>
  );
}

const inputClass = 'w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 outline-none transition-all focus:border-indigo-500 focus:bg-white focus:ring-1 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60';
