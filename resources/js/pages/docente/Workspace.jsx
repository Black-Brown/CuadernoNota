import React, { useCallback, useMemo, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { getCourses } from '../../api/courses.api';
import { getSubjectDashboard } from '../../api/dashboard.api';
import { getPeriodGrades, getActivitiesBySubject, submitGrades } from '../../api/grades.api';
import { getCurrentPeriod } from '../../api/periods.api';
import DashboardLayout from '../../components/DashboardLayout';
import ManageActivitiesModal from '../../components/ManageActivitiesModal';
import usePeriodStore from '../../store/periodStore';
import { downloadCsv, safeFilename } from '../../utils/exportCsv';

export default function Workspace() {
  const { sectionId, subjectId } = useParams();
  const queryClient = useQueryClient();
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);

  const [showGradesTable, setShowGradesTable] = useState(false);
  const [selectedStudent, setSelectedStudent] = useState(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [showActivitiesModal, setShowActivitiesModal] = useState(false);

  const [drawerForm, setDrawerForm] = useState({
    c1: '',
    c2: '',
    c3: '',
    comment: '',
  });

  const { data: coursesData } = useQuery({
    queryKey: ['courses'],
    queryFn: getCourses,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  });

  const currentCourse = useMemo(() => {
    return coursesData?.courses?.find(
      (c) =>
        String(c.section_id) === String(sectionId) &&
        String(c.subject_id) === String(subjectId)
    );
  }, [coursesData, sectionId, subjectId]);

  const gradeName = currentCourse ? currentCourse.grade_name : '1ro';
  const sectionName = currentCourse ? currentCourse.section_name : 'Secundaria A';
  const subjectName = currentCourse ? currentCourse.subject_name : 'Matemática';

  const { data: subjectStats } = useQuery({
    queryKey: ['subjectDashboard', sectionId, subjectId],
    queryFn: () => getSubjectDashboard(sectionId, subjectId),
    enabled: !!sectionId && !!subjectId,
    staleTime: 3 * 60 * 1000,
    refetchOnWindowFocus: false,
  });

  const { data: periodData, isLoading: isLoadingPeriod } = useQuery({
    queryKey: ['currentPeriod'],
    queryFn: getCurrentPeriod,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  });

  const period = selectedPeriod || periodData?.period;
  const isPeriodOpen = period?.status === 'open';

  const { data: periodLockData } = useQuery({
    queryKey: ['periodGradesLock', subjectId, period?.id, sectionId],
    queryFn: () => getPeriodGrades(subjectId, period.id, sectionId),
    enabled: !!subjectId && !!period?.id && !!sectionId,
    staleTime: 30_000,
    refetchOnWindowFocus: false,
  });

  const lockedGradeStatus = periodLockData?.grades?.find((grade) =>
    ['in_review', 'official'].includes(grade.status)
  )?.status;
  const canEditWorkspace = isPeriodOpen && !lockedGradeStatus;
  const hasCalculatedGrades = (periodLockData?.grades || []).length > 0;

  const submitGradesMutation = useMutation({
    mutationFn: submitGrades,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['periodGradesLock', subjectId, period?.id, sectionId] });
      queryClient.invalidateQueries({ queryKey: ['periodGrades', subjectId, period?.id, sectionId] });
      queryClient.invalidateQueries({ queryKey: ['activitiesBySubject', subjectId, sectionId, period?.id] });
      showToast('Calificaciones enviadas a revisión correctamente.');
    },
    onError: (error) => {
      showToast(error?.response?.data?.message || 'No se pudieron enviar las calificaciones a revisión.');
    },
  });

  const { data: activitiesData, isLoading: isLoadingActivities } = useQuery({
    queryKey: ['activitiesBySubject', subjectId, sectionId, period?.id],
    queryFn: () => getActivitiesBySubject(subjectId, sectionId, period.id),
    enabled: !!subjectId && !!sectionId && !!period?.id,
    staleTime: 3 * 60 * 1000,
    refetchOnWindowFocus: false,
  });

  const realActivities = activitiesData?.activities || [];

  const {
    data: periodGradesData,
    isLoading: isLoadingGrades,
    isFetching: isFetchingGrades,
    isError: isGradesError,
    error: gradesError,
  } = useQuery({
    queryKey: ['periodGrades', subjectId, period?.id, sectionId],
    queryFn: () => getPeriodGrades(subjectId, period.id, sectionId),
    enabled: showGradesTable && !!subjectId && !!period?.id && !!sectionId,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
    retry: 1,
  });

  const prefetchPeriodGrades = useCallback(() => {
    if (!subjectId || !period?.id) return;

    queryClient.prefetchQuery({
      queryKey: ['periodGrades', subjectId, period.id, sectionId],
      queryFn: () => getPeriodGrades(subjectId, period.id, sectionId),
      staleTime: 5 * 60 * 1000,
    });
  }, [queryClient, subjectId, period?.id, sectionId]);

  const activityMeta = {
    Proyectos: {
      icon: 'account_tree',
      desc: 'Evaluación basada en competencias colaborativas.',
    },
    Examen: {
      icon: 'assignment',
      desc: 'Pruebas sumativas periódicas.',
    },
    Tareas: {
      icon: 'edit_note',
      desc: 'Seguimiento de trabajo autónomo.',
    },
    Ensayo: {
      icon: 'article',
      desc: 'Análisis crítico y redacción científica.',
    },
    'Producción en aula': {
      icon: 'school',
      desc: 'Evidencias de trabajo diario y participación.',
    },
    Diagnósticas: {
      icon: 'fact_check',
      desc: 'Evaluación inicial de conocimientos previos.',
    },
  };

  const groupAvg = subjectStats?.group_avg ?? 80.5;
  const atRiskCount = subjectStats?.at_risk_count ?? 12;
  const attendancePct = subjectStats?.attendance_pct ?? 96.8;

  const studentsList = periodGradesData?.grades || [];

  const handleToggleGradesTable = () => {
    setShowGradesTable((prev) => !prev);
  };

  const handleOpenDrawer = (student) => {
    setSelectedStudent(student);

    setDrawerForm({
      c1: student.c1_score ?? '',
      c2: student.c2_score ?? '',
      c3: student.c3_score ?? '',
      comment: '',
    });

    setIsDrawerOpen(true);
  };

  const showToast = (msg) => {
    setToastMessage(msg);

    setTimeout(() => {
      setToastMessage('');
    }, 4000);
  };

  const handleSaveGrades = () => {
    setIsDrawerOpen(false);

    if (selectedStudent) {
      showToast(`Calificaciones de ${selectedStudent.student_name} actualizadas correctamente.`);
    }
  };

  const handleExportReport = () => {
    const baseName = safeFilename(`${gradeName}-${sectionName}-${subjectName}-${period?.name || 'periodo'}`);

    if (studentsList.length > 0) {
      downloadCsv(`reporte-workspace-${baseName}.csv`, [
        [`Reporte Workspace - ${subjectName}`],
        ['Curso', `${gradeName} ${sectionName}`],
        ['Periodo', period?.name || ''],
        [],
        ['Estudiante', 'C1', 'C2', 'C3', 'Nota periodo', 'RP', 'Nota final', 'Estado'],
        ...studentsList.map((student) => [
          student.student_name,
          student.c1_score ?? '',
          student.c2_score ?? '',
          student.c3_score ?? '',
          student.period_score ?? '',
          student.rp_score ?? '',
          student.effective_score ?? '',
          student.status ?? '',
        ]),
      ]);
      return;
    }

    downloadCsv(`reporte-workspace-${baseName}.csv`, [
      [`Reporte Workspace - ${subjectName}`],
      ['Curso', `${gradeName} ${sectionName}`],
      ['Periodo', period?.name || ''],
      ['Promedio general', groupAvg],
      ['Riesgo academico', atRiskCount],
      ['Asistencia', `${attendancePct}%`],
      [],
      ['Actividades'],
      ['Nombre', 'Tipo', 'Estado', 'Notas registradas', 'Descripcion'],
      ...realActivities.map((activity) => [
        activity.name,
        activity.type || '',
        activity.active ? 'Activa' : 'Inactiva',
        activity.score_count ?? 0,
        activity.description || '',
      ]),
    ]);
  };

  const renderGradesContent = () => {
    if (isLoadingGrades || isLoadingPeriod) {
      return (
        <div className="bg-white border border-slate-200 rounded-xl p-10 text-center shadow-sm">
          <div className="flex flex-col items-center gap-3 text-slate-400">
            <span className="material-symbols-outlined text-[42px] animate-spin">
              progress_activity
            </span>

            <p className="text-sm font-semibold text-slate-600">
              Cargando nota completa...
            </p>

            <p className="text-xs text-slate-400 max-w-md">
              Estamos buscando las calificaciones del período actual sin recargar la página.
            </p>
          </div>
        </div>
      );
    }

    if (isGradesError) {
      return (
        <div className="bg-white border border-red-100 rounded-xl p-10 text-center shadow-sm">
          <div className="flex flex-col items-center gap-3 text-red-500">
            <span className="material-symbols-outlined text-[42px]">
              error
            </span>

            <p className="text-sm font-bold">
              No se pudo cargar la nota completa.
            </p>

            <p className="text-xs text-slate-500 max-w-md">
              {gradesError?.message || 'Revisa el endpoint de calificaciones o intenta nuevamente.'}
            </p>

            <button
              type="button"
              onClick={() =>
                queryClient.invalidateQueries({
                  queryKey: ['periodGrades', subjectId, period?.id, sectionId],
                })
              }
              className="mt-2 px-4 py-2 bg-slate-950 text-white rounded-xl text-xs font-bold hover:bg-slate-900 transition-all"
            >
              Reintentar
            </button>
          </div>
        </div>
      );
    }

    return (
      <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
        {isFetchingGrades && (
          <div className="px-6 py-2 bg-indigo-50 border-b border-indigo-100 text-[10px] font-bold text-indigo-600 uppercase tracking-wider flex items-center gap-2">
            <span className="material-symbols-outlined text-[14px] animate-spin">
              progress_activity
            </span>
            Actualizando notas...
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                <th className="py-4 px-6">Estudiante</th>
                <th className="py-4 px-6 text-center">C1 Comunicativa</th>
                <th className="py-4 px-6 text-center">C2 Pensamiento Lógico</th>
                <th className="py-4 px-6 text-center">C3 Científica</th>
                <th className="py-4 px-6 text-center">Nota Periodo</th>
                <th className="py-4 px-6 text-center">Recup.</th>
                <th className="py-4 px-6 text-center">Nota Final</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-slate-100 text-sm">
              {studentsList.length === 0 ? (
                <tr>
                  <td colSpan="8" className="py-16 text-center">
                    <div className="flex flex-col items-center gap-3 text-slate-400">
                      <span className="material-symbols-outlined text-[40px] text-slate-300">
                        group_off
                      </span>

                      <p className="text-sm font-semibold">
                        {!period
                          ? 'Esperando período activo...'
                          : 'No hay calificaciones registradas para este período.'}
                      </p>

                      <p className="text-xs">
                        {period
                          ? 'Las notas aparecerán aquí una vez que se registren desde las actividades.'
                          : ''}
                      </p>
                    </div>
                  </td>
                </tr>
              ) : (
                studentsList.map((student) => {
                  const isRisk = Number(student.effective_score) < 70;

                  return (
                    <tr
                      key={student.student_id}
                      className={`hover:bg-slate-50/50 transition-colors ${
                        isRisk ? 'bg-red-50/10' : ''
                      }`}
                    >
                      <td className="py-3.5 px-6 font-semibold text-slate-800">
                        {student.student_name}

                        {isRisk && (
                          <span className="ml-2 inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-red-50 text-red-600 rounded text-[9px] font-bold border border-red-100">
                            <span className="material-symbols-outlined text-[10px]">
                              warning
                            </span>
                            Riesgo
                          </span>
                        )}
                      </td>

                      <td className="py-3.5 px-6 text-center font-mono text-slate-600">
                        {student.c1_score ?? '-'}
                      </td>

                      <td className="py-3.5 px-6 text-center font-mono text-slate-600">
                        {student.c2_score ?? '-'}
                      </td>

                      <td className="py-3.5 px-6 text-center font-mono text-slate-600">
                        {student.c3_score ?? '-'}
                      </td>

                      <td className="py-3.5 px-6 text-center font-mono font-bold text-slate-700">
                        {student.period_score ?? '-'}
                      </td>

                      <td className="py-3.5 px-6 text-center font-mono text-amber-600">
                        {student.rp_score ?? '-'}
                      </td>

                      <td
                        className={`py-3.5 px-6 text-center font-mono font-extrabold ${
                          isRisk ? 'text-red-600' : 'text-slate-800'
                        }`}
                      >
                        {student.effective_score ?? '-'}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    );
  };

  return (
    <>
      <DashboardLayout>
        {toastMessage && (
          <div className="fixed top-20 right-6 z-50 bg-slate-900 text-white text-xs font-semibold px-4 py-3 rounded-xl shadow-lg border border-slate-700 flex items-center gap-2 animate-in fade-in slide-in-from-top-4 duration-300">
            <span className="material-symbols-outlined text-emerald-400 text-base">
              check_circle
            </span>

            <span>{toastMessage}</span>
          </div>
        )}

        <div className="space-y-2 mb-6">
          <nav className="flex items-center gap-1.5 text-slate-400 text-[10px] font-bold tracking-wider uppercase">
            <Link
              to="/docente/courses"
              className="hover:text-slate-700 transition-colors"
            >
              MIS CURSOS
            </Link>

            <span className="material-symbols-outlined text-[12px]">
              chevron_right
            </span>

            <span>
              {gradeName.toUpperCase()} {sectionName.toUpperCase()}
            </span>

            <span className="material-symbols-outlined text-[12px]">
              chevron_right
            </span>

            <span className="text-indigo-600 font-extrabold">
              {subjectName.toUpperCase()}
            </span>
          </nav>

          <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
            <div>
              <h1 className="text-2xl font-bold text-slate-800 tracking-tight">
                Workspace de {subjectName}
              </h1>

              <p className="text-xs text-slate-500">
                Planificación, actividades y evaluación formativa.
              </p>
            </div>

            <div className="flex gap-2">
              <button
                type="button"
                onMouseEnter={prefetchPeriodGrades}
                onFocus={prefetchPeriodGrades}
                onClick={handleToggleGradesTable}
                disabled={!period && !isLoadingPeriod}
                className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed ${
                  showGradesTable
                    ? 'bg-indigo-50 border border-indigo-200 text-indigo-700'
                    : 'bg-slate-950 text-white hover:bg-slate-900'
                }`}
              >
                <span className="material-symbols-outlined text-[18px]">
                  table_chart
                </span>

                {showGradesTable ? 'Ver Categorías de Actividades' : 'Ver Nota Completa'}
              </button>

              <button
                type="button"
                onClick={() => submitGradesMutation.mutate({
                  subject_id: Number(subjectId),
                  section_id: Number(sectionId),
                  period_id: period.id,
                })}
                disabled={!canEditWorkspace || !period?.id || !hasCalculatedGrades || submitGradesMutation.isPending}
                title={
                  !hasCalculatedGrades
                    ? 'No hay notas calculadas para enviar'
                    : !canEditWorkspace
                      ? 'Este workspace no permite modificaciones'
                      : 'Enviar calificaciones a revisión'
                }
                className="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl text-xs font-semibold text-slate-700 transition-all shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
              >
                <span className={`material-symbols-outlined text-[18px] ${submitGradesMutation.isPending ? 'animate-spin' : ''}`}>
                  {submitGradesMutation.isPending ? 'sync' : 'task_alt'}
                </span>

                Enviar a revisión
              </button>

              <button
                type="button"
                onClick={handleExportReport}
                className="flex items-center gap-2 px-4 py-2.5 border border-slate-200 bg-white hover:bg-slate-50 rounded-xl text-xs font-semibold text-slate-700 transition-all shadow-sm"
              >
                <span className="material-symbols-outlined text-[18px]">
                  ios_share
                </span>

                Exportar Reporte
              </button>
            </div>
          </div>
        </div>

        <div className="bg-slate-950 text-white rounded-xl p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden group shadow-md mb-8">
          <div className="relative z-10">
            <div className="flex items-center gap-3 mb-1">
              <span
                className={`text-[9px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider ${
                  period?.status === 'open'
                    ? 'bg-emerald-500 text-white'
                    : period?.status === 'in_review'
                    ? 'bg-amber-400 text-amber-900'
                    : 'bg-slate-600 text-slate-200'
                }`}
              >
                {period?.status === 'open'
                  ? 'Activo'
                  : period?.status === 'in_review'
                  ? 'En Revisión'
                  : period
                  ? 'Cerrado'
                  : 'Cargando...'}
              </span>

              <h2 className="text-base font-bold">
                Periodo seleccionado: {period ? period.name : '—'}
              </h2>
            </div>

            <p className="text-xs text-slate-400 max-w-xl">
              {period?.end_date ? (
                <>
                  El cierre de calificaciones está programado para el{' '}
                  <span className="font-bold text-white">
                    {new Date(period.end_date).toLocaleDateString('es-ES', {
                      day: 'numeric',
                      month: 'long',
                    })}
                  </span>
                  . Asegúrese de sincronizar todos los registros antes de la fecha límite.
                </>
              ) : (
                'Asegúrese de sincronizar todos los registros antes del cierre del período.'
              )}
            </p>
          </div>

          <div className="relative z-10 flex flex-col items-start md:items-end">
            {period?.end_date ? (
              (() => {
                const daysLeft = Math.max(
                  0,
                  Math.ceil(
                    (new Date(period.end_date) - new Date()) / (1000 * 60 * 60 * 24)
                  )
                );

                return (
                  <>
                    <div className="text-3xl font-extrabold text-indigo-400 font-mono">
                      {daysLeft} Días
                    </div>

                    <div className="text-[9px] font-bold text-slate-400 tracking-wider uppercase">
                      PARA CIERRE
                    </div>
                  </>
                );
              })()
            ) : (
              <div className="text-3xl font-extrabold text-slate-500 font-mono">
                —
              </div>
            )}
          </div>

          <div className="absolute inset-0 bg-gradient-to-r from-transparent to-indigo-500/5 pointer-events-none" />
        </div>

        {!showGradesTable ? (
          <section className="space-y-6">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                <span className="material-symbols-outlined text-[20px] text-slate-500">
                  menu_book
                </span>
                Actividades de Aprendizaje
              </h3>

              <button
                type="button"
                onClick={() => canEditWorkspace && setShowActivitiesModal(true)}
                disabled={!canEditWorkspace}
                title={!canEditWorkspace ? 'Este workspace no permite modificaciones' : 'Gestionar actividades'}
                className="text-[10px] font-bold text-slate-400 hover:text-slate-800 transition-colors uppercase tracking-wider flex items-center gap-1 disabled:cursor-not-allowed disabled:opacity-50"
              >
                GESTIONAR ACTIVIDADES

                <span className="material-symbols-outlined text-[14px]">
                  settings
                </span>
              </button>
            </div>

            {isLoadingActivities ? (
              <div className="bg-white border border-slate-200 rounded-xl p-10 text-center shadow-sm">
                <div className="flex flex-col items-center gap-3 text-slate-400">
                  <span className="material-symbols-outlined text-[42px] animate-spin">
                    progress_activity
                  </span>

                  <p className="text-sm font-semibold text-slate-600">
                    Cargando actividades...
                  </p>
                </div>
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {realActivities.filter((act) => act.active).map((act, idx) => {
                  const meta = activityMeta[act.name] ?? {
                    icon: act.icon || 'assignment',
                    desc: act.description || 'Actividad de evaluación.',
                  };

                  const isFirst = idx === 0;

                  return (
                    <Link
                      key={act.id}
                      to={`/docente/courses/${sectionId}/${subjectId}/activity/${act.id}`}
                      className={`group bg-white p-5 rounded-xl hover:shadow-md transition-all cursor-pointer flex flex-col justify-between min-h-[160px] text-left ${
                        isFirst
                          ? 'border border-slate-800'
                          : 'border border-slate-200 hover:border-slate-800'
                      }`}
                    >
                      <div>
                        <div className="flex justify-between items-start mb-4">
                          <div
                            className={`w-10 h-10 flex items-center justify-center rounded-lg ${
                              isFirst
                                ? 'bg-indigo-50 text-indigo-600'
                                : 'bg-slate-50 text-slate-600'
                            }`}
                          >
                            <span className="material-symbols-outlined text-[24px]">
                              {meta.icon}
                            </span>
                          </div>

                          {act.score_count != null && (
                            <span className="text-[9px] font-bold bg-slate-100 text-slate-600 px-2 py-0.5 rounded uppercase">
                              {act.score_count} {act.score_count === 1 ? 'NOTA' : 'NOTAS'}
                            </span>
                          )}
                        </div>

                        <h4 className="font-bold text-sm text-slate-800 mb-1">
                          {act.name}
                        </h4>

                        <p className="text-xs text-slate-400 leading-normal">
                          {meta.desc}
                        </p>
                      </div>

                      <div className="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
                        <span
                          className={`text-[9px] font-bold uppercase tracking-wider ${
                            act.active ? 'text-emerald-500' : 'text-slate-400'
                          }`}
                        >
                          {act.active ? 'ACTIVO' : 'INACTIVO'}
                        </span>

                        <span
                          className={`material-symbols-outlined group-hover:translate-x-1 transition-all ${
                            isFirst
                              ? 'text-slate-800'
                              : 'text-slate-400 group-hover:text-slate-800'
                          }`}
                        >
                          arrow_forward
                        </span>
                      </div>
                    </Link>
                  );
                })}

                <button
                  type="button"
                  onClick={() => canEditWorkspace && setShowActivitiesModal(true)}
                  disabled={!canEditWorkspace}
                  className="group border-2 border-dashed border-slate-200 hover:border-slate-800 hover:bg-slate-50 p-5 rounded-xl transition-all flex flex-col items-center justify-center gap-3 min-h-[160px] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:border-slate-200 disabled:hover:bg-transparent"
                >
                  <div className="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center group-hover:bg-slate-900 group-hover:text-white transition-all">
                    <span className="material-symbols-outlined text-base">
                      settings
                    </span>
                  </div>

                  <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500 group-hover:text-slate-800">
                    GESTIONAR ACTIVIDADES
                  </span>
                </button>
              </div>
            )}
          </section>
        ) : (
          <section className="space-y-4">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                <span className="material-symbols-outlined text-[20px] text-slate-500">
                  groups
                </span>

                Nota Completa{period ? ` (${period.name})` : ''}
              </h3>

              <span className="text-[10px] font-bold text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full uppercase tracking-wider">
                {studentsList.length} Estudiantes
              </span>
            </div>

            {renderGradesContent()}
          </section>
        )}

        <section className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
          <div className="bg-white border border-slate-200 p-5 rounded-xl flex items-center gap-4 shadow-sm hover:border-slate-800 transition-colors">
            <div className="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center text-slate-800">
              <span className="material-symbols-outlined text-[24px]">
                analytics
              </span>
            </div>

            <div>
              <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Promedio General
              </p>

              <p className="text-xl font-bold font-mono text-slate-800">
                {groupAvg}
              </p>
            </div>
          </div>

          <div className="bg-white border border-slate-200 p-5 rounded-xl flex items-center gap-4 shadow-sm hover:border-slate-800 transition-colors">
            <div className="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center text-red-600">
              <span className="material-symbols-outlined text-[24px]">
                error
              </span>
            </div>

            <div>
              <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Riesgo Académico
              </p>

              <p className="text-xl font-bold font-mono text-red-600">
                {atRiskCount}%
              </p>
            </div>
          </div>

          <div className="bg-white border border-slate-200 p-5 rounded-xl flex items-center gap-4 shadow-sm hover:border-slate-800 transition-colors">
            <div className="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600">
              <span className="material-symbols-outlined text-[24px]">
                trending_up
              </span>
            </div>

            <div>
              <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Progreso Periodo
              </p>

              <p className="text-xl font-bold font-mono text-emerald-600">
                +4.2%
              </p>
            </div>
          </div>

          <div className="bg-white border border-slate-200 p-5 rounded-xl flex items-center gap-4 shadow-sm hover:border-slate-800 transition-colors">
            <div className="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-600">
              <span className="material-symbols-outlined text-[24px]">
                check_circle
              </span>
            </div>

            <div>
              <p className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Asistencia
              </p>

              <p className="text-xl font-bold font-mono text-slate-800">
                {attendancePct}%
              </p>
            </div>
          </div>
        </section>

        {isDrawerOpen && (
          <div
            className="fixed inset-0 bg-slate-900/40 backdrop-blur-[1px] z-[60] transition-opacity duration-300"
            onClick={() => setIsDrawerOpen(false)}
          />
        )}

        <div
          className={`fixed right-0 top-0 h-full w-[400px] bg-white z-[70] shadow-2xl flex flex-col transition-transform duration-300 ${
            isDrawerOpen ? 'translate-x-0' : 'translate-x-full'
          }`}
        >
          <div className="p-6 border-b border-slate-100 flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-800">
              Editar Calificaciones
            </h2>

            <button
              type="button"
              className="material-symbols-outlined p-1.5 hover:bg-slate-100 rounded-full transition-colors text-slate-400"
              onClick={() => setIsDrawerOpen(false)}
            >
              close
            </button>
          </div>

          {selectedStudent && (
            <div className="flex-grow overflow-y-auto p-6 space-y-6">
              <div className="flex items-center gap-4 p-4 bg-slate-50 border border-slate-200/50 rounded-xl">
                <div className="w-12 h-12 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm shrink-0 uppercase">
                  {selectedStudent.student_name?.substring(0, 2)}
                </div>

                <div>
                  <h3 className="font-bold text-sm text-slate-800 leading-tight">
                    {selectedStudent.student_name}
                  </h3>

                  <p className="text-[10px] font-semibold text-slate-400 mt-0.5">
                    Matrícula: #{20230000 + Number(selectedStudent.student_id)}
                  </p>

                  {Number(selectedStudent.effective_score) < 70 && (
                    <div className="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-600 rounded text-[9px] font-bold border border-red-100">
                      <span className="material-symbols-outlined text-[11px]">
                        warning
                      </span>

                      RIESGO ACADÉMICO
                    </div>
                  )}
                </div>
              </div>

              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                      C1 COMUNICATIVA
                    </label>

                    <input
                      className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-mono text-sm text-slate-800 focus:bg-white focus:ring-1 focus:ring-slate-950 focus:border-slate-950 outline-none transition-all"
                      type="number"
                      min="0"
                      max="100"
                      value={drawerForm.c1}
                      onChange={(e) =>
                        setDrawerForm({
                          ...drawerForm,
                          c1: e.target.value,
                        })
                      }
                    />
                  </div>

                  <div>
                    <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                      C2 PENS. LÓGICO
                    </label>

                    <input
                      className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-mono text-sm text-slate-800 focus:bg-white focus:ring-1 focus:ring-slate-950 focus:border-slate-950 outline-none transition-all"
                      type="number"
                      min="0"
                      max="100"
                      value={drawerForm.c2}
                      onChange={(e) =>
                        setDrawerForm({
                          ...drawerForm,
                          c2: e.target.value,
                        })
                      }
                    />
                  </div>
                </div>

                <div>
                  <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                    C3 CIENTÍFICA
                  </label>

                  <input
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 font-mono text-sm text-slate-800 focus:bg-white focus:ring-1 focus:ring-slate-950 focus:border-slate-950 outline-none transition-all"
                    type="number"
                    min="0"
                    max="100"
                    value={drawerForm.c3}
                    onChange={(e) =>
                      setDrawerForm({
                        ...drawerForm,
                        c3: e.target.value,
                      })
                    }
                  />
                </div>

                <div className="pt-4 border-t border-slate-100">
                  <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                    COMENTARIO DEL DOCENTE
                  </label>

                  <textarea
                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:bg-white focus:ring-1 focus:ring-slate-950 focus:border-slate-950 outline-none transition-all placeholder:italic"
                    placeholder="Escriba las razones de la calificación o recomendaciones para el estudiante..."
                    rows="4"
                    value={drawerForm.comment}
                    onChange={(e) =>
                      setDrawerForm({
                        ...drawerForm,
                        comment: e.target.value,
                      })
                    }
                  />
                </div>
              </div>

              <div className="p-4 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                <p className="text-[10px] text-slate-400 leading-normal italic">
                  <span className="font-bold text-slate-600">Historial:</span>{' '}
                  Última edición hace 2 días por el docente. Los cambios en el sistema son auditables y visibles para la coordinación académica.
                </p>
              </div>
            </div>
          )}

          <div className="p-6 bg-slate-50 border-t border-slate-100 flex gap-4">
            <button
              type="button"
              className="flex-1 px-4 py-2.5 border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold transition-all shadow-sm"
              onClick={() => setIsDrawerOpen(false)}
            >
              Cancelar
            </button>

            <button
              type="button"
              className="flex-1 px-4 py-2.5 bg-slate-950 hover:bg-slate-900 text-white rounded-xl text-xs font-bold transition-all shadow-sm"
              onClick={handleSaveGrades}
            >
              Guardar Cambios
            </button>
          </div>
        </div>
      </DashboardLayout>

      {showActivitiesModal && (
        <ManageActivitiesModal
          subjectId={subjectId}
          sectionId={sectionId}
          periodId={period?.id}
          periodName={period?.name}
          periodStatus={period?.status}
          lockedGradeStatus={lockedGradeStatus}
          onClose={() => setShowActivitiesModal(false)}
        />
      )}
    </>
  );
}
