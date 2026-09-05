import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getCourses } from '../../api/courses.api';
import { getActivitiesBySubject, getActivityGrades, getPeriodGrades, saveActivityScore, submitGrades } from '../../api/grades.api';
import { getCurrentPeriod } from '../../api/periods.api';
import DashboardLayout from '../../components/DashboardLayout';
import usePeriodStore from '../../store/periodStore';

export default function ActivityGrades() {
  const { sectionId, subjectId, activityId } = useParams();
  const queryClient = useQueryClient();
  const selectedPeriod = usePeriodStore((state) => state.selectedPeriod);

  const [localScores, setLocalScores]   = useState([]);
  const [syncStatus, setSyncStatus]     = useState({});
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType]       = useState('success');
  const [isSavingAll, setIsSavingAll]   = useState(false);
  const pendingSaveTimersRef = useRef(new Map());
  const cellSaveChainsRef = useRef(new Map());
  const failedCellValuesRef = useRef(new Map());

  const showToast = useCallback((msg, type = 'success') => {
    setToastType(type);
    setToastMessage(msg);
    setTimeout(() => setToastMessage(''), 4000);
  }, []);

  // Resolve breadcrumb info from cached courses query
  const { data: coursesData } = useQuery({
    queryKey: ['courses'],
    queryFn: getCourses,
  });

  const currentCourse = coursesData?.courses?.find(
    (c) => String(c.section_id) === String(sectionId) && String(c.subject_id) === String(subjectId)
  );
  const gradeName   = currentCourse?.grade_name   ?? '';
  const sectionName = currentCourse?.section_name ?? '';
  const subjectName = currentCourse?.subject_name ?? 'Asignatura';

  // Fetch real current period (reuses cached value from DashboardLayout)
  const { data: periodData } = useQuery({
    queryKey: ['currentPeriod'],
    queryFn:  getCurrentPeriod,
    staleTime: 5 * 60_000,
  });
  const period = selectedPeriod || periodData?.period;
  const isPeriodOpen = period?.status === 'open';
  const saveScope = `${activityId}:${period?.id ?? 'none'}`;

  const { data: periodLockData } = useQuery({
    queryKey: ['periodGradesLock', subjectId, period?.id, sectionId],
    queryFn: () => getPeriodGrades(subjectId, period.id, sectionId),
    enabled: !!subjectId && !!period?.id && !!sectionId,
    staleTime: 30_000,
  });

  const lockedGradeStatus = periodLockData?.grades?.find((grade) =>
    ['in_review', 'official'].includes(grade.status)
  )?.status;
  const canEditGrades = isPeriodOpen && !lockedGradeStatus;

  // Resolve real activity name from the subject's activity list
  const { data: activitiesData } = useQuery({
    queryKey: ['activitiesBySubject', subjectId, sectionId, period?.id],
    queryFn:  () => getActivitiesBySubject(subjectId, sectionId, period.id),
    enabled:  !!subjectId && !!sectionId && !!period?.id,
  });

  const currentActivity = activitiesData?.activities?.find(
    (a) => String(a.id) === String(activityId)
  );
  const activityName = currentActivity?.name ?? 'Actividad';

  // Fetch grades — pass sectionId so backend returns the correct section's students
  const { data: activityGradesData } = useQuery({
    queryKey: ['activityGrades', activityId, period?.id, sectionId],
    queryFn:  () => getActivityGrades(activityId, period.id, sectionId),
    enabled:  !!activityId && !!period?.id && !!sectionId && !!currentActivity,
  });

  useEffect(() => {
    setLocalScores([]);
    setSyncStatus({});
  }, [activityId, period?.id, sectionId]);

  useEffect(() => {
    if (activityGradesData?.students) {
      setLocalScores(activityGradesData.students);
    }
  }, [activityGradesData]);

  const refreshGradeData = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['periodGrades', subjectId, period?.id, sectionId] });
    queryClient.invalidateQueries({ queryKey: ['periodGradesLock', subjectId, period?.id, sectionId] });
    queryClient.invalidateQueries({ queryKey: ['activitiesBySubject', subjectId, sectionId, period?.id] });
  }, [queryClient, subjectId, period?.id, sectionId]);

  // Serializa las escrituras de una misma celda para que una respuesta antigua
  // nunca pueda sobrescribir el valor más reciente.
  const persistCellScore = useCallback((studentId, key, value, refreshAfter = true) => {
    const cellKey = `${saveScope}:${studentId}:${key}`;
    const previousSave = cellSaveChainsRef.current.get(cellKey) ?? Promise.resolve();
    const payload = {
      activity_id: Number(activityId),
      student_id: studentId,
      competency_id: key === 'c1' ? 1 : key === 'c2' ? 2 : 3,
      period_id: period?.id,
      subject_id: Number(subjectId),
      score: value === '' || value === null || value === undefined ? null : Number(value),
    };

    setSyncStatus(prev => ({ ...prev, [cellKey]: 'sync' }));

    let trackedSave;
    trackedSave = previousSave
      .catch(() => undefined)
      .then(() => saveActivityScore(payload))
      .then((response) => {
        failedCellValuesRef.current.delete(cellKey);
        if (refreshAfter) refreshGradeData();
        if (cellSaveChainsRef.current.get(cellKey) === trackedSave) {
          setSyncStatus(prev => ({ ...prev, [cellKey]: 'cloud_done' }));
        }
        return response;
      })
      .catch((error) => {
        failedCellValuesRef.current.set(cellKey, { studentId, key, value, scope: saveScope });
        if (cellSaveChainsRef.current.get(cellKey) === trackedSave) {
          setSyncStatus(prev => ({ ...prev, [cellKey]: 'error' }));
        }
        throw error;
      })
      .finally(() => {
        if (cellSaveChainsRef.current.get(cellKey) === trackedSave) {
          cellSaveChainsRef.current.delete(cellKey);
        }
      });

    cellSaveChainsRef.current.set(cellKey, trackedSave);
    return trackedSave;
  }, [activityId, period?.id, subjectId, refreshGradeData, saveScope]);

  const reportSaveError = useCallback((error) => {
    showToast(
      error?.response?.data?.message ?? 'No fue posible guardar la calificación. Inténtalo nuevamente.',
      'error'
    );
  }, [showToast]);

  const flushPendingCellSave = useCallback((studentId, key) => {
    const cellKey = `${saveScope}:${studentId}:${key}`;
    const pending = pendingSaveTimersRef.current.get(cellKey);
    if (!pending) return Promise.resolve();

    clearTimeout(pending.timer);
    pendingSaveTimersRef.current.delete(cellKey);

    return persistCellScore(studentId, key, pending.value).catch((error) => {
      reportSaveError(error);
      throw error;
    });
  }, [persistCellScore, reportSaveError, saveScope]);

  const submitGradesMutation = useMutation({
    mutationFn: submitGrades,
    onSuccess:  () => {
      queryClient.invalidateQueries({ queryKey: ['periodGrades', subjectId, period?.id, sectionId] });
      queryClient.invalidateQueries({ queryKey: ['activitiesBySubject', subjectId, sectionId, period?.id] });
      showToast('Calificaciones enviadas y finalizadas correctamente.');
    },
    onError: (error) => {
      showToast(
        error?.response?.data?.message ?? 'No fue posible finalizar las calificaciones.',
        'error'
      );
    },
  });

  // Update immediately and persist after a short pause so typing "100" produces
  // one ordered request instead of three competing writes (1, 10 and 100).
  const handleScoreChange = (studentId, key, val) => {
    if (!canEditGrades) {
      showToast(
        lockedGradeStatus
          ? 'Estas calificaciones ya están en revisión u oficiales.'
          : 'Este período está cerrado. Solicita permiso al coordinador para modificarlo.'
      );
      return;
    }

    const numVal = val === '' ? '' : Number(val);

    setLocalScores(prev =>
      prev.map(s => s.student_id === studentId ? { ...s, [key]: numVal } : s)
    );
    const cellKey = `${saveScope}:${studentId}:${key}`;
    const previousPending = pendingSaveTimersRef.current.get(cellKey);
    if (previousPending) clearTimeout(previousPending.timer);

    setSyncStatus(prev => ({ ...prev, [cellKey]: 'sync' }));

    const timer = setTimeout(() => {
      pendingSaveTimersRef.current.delete(cellKey);
      persistCellScore(studentId, key, numVal).catch(reportSaveError);
    }, 600);

    pendingSaveTimersRef.current.set(cellKey, {
      timer,
      value: numVal,
      studentId,
      key,
      scope: saveScope,
    });
  };

  // "Guardar Progreso" and Ctrl+Enter flush pending changes and wait for the API.
  const saveAll = useCallback(async (announceSuccess = true) => {
    if (!period || !canEditGrades) return false;

    const cellsToSave = new Map();

    failedCellValuesRef.current.forEach((failed, cellKey) => {
      if (failed.scope === saveScope) cellsToSave.set(cellKey, failed);
    });

    pendingSaveTimersRef.current.forEach((pending, cellKey) => {
      if (pending.scope !== saveScope) return;
      clearTimeout(pending.timer);
      cellsToSave.set(cellKey, {
        studentId: pending.studentId,
        key: pending.key,
        value: pending.value,
      });
      pendingSaveTimersRef.current.delete(cellKey);
    });

    setIsSavingAll(true);
    cellsToSave.forEach(({ studentId, key, value }) => {
      persistCellScore(studentId, key, value, false).catch(() => undefined);
    });

    const pendingRequests = Array.from(cellSaveChainsRef.current.entries())
      .filter(([cellKey]) => cellKey.startsWith(`${saveScope}:`))
      .map(([, request]) => request);
    const uniquePendingRequests = Array.from(new Set(pendingRequests));
    if (uniquePendingRequests.length === 0) {
      setIsSavingAll(false);
      if (announceSuccess) showToast('Todo el progreso ya está guardado.');
      return true;
    }

    const results = await Promise.allSettled(uniquePendingRequests);
    setIsSavingAll(false);
    refreshGradeData();

    const failedCount = results.filter(result => result.status === 'rejected').length;
    if (failedCount > 0) {
      showToast(
        `No se pudieron guardar ${failedCount} ${failedCount === 1 ? 'calificación' : 'calificaciones'}. Revisa los campos marcados.`,
        'error'
      );
      return false;
    }

    if (announceSuccess) showToast('Progreso guardado correctamente.');
    return true;
  }, [period, canEditGrades, persistCellScore, refreshGradeData, saveScope, showToast]);

  const handleSubmitGrades = useCallback(async () => {
    if (!period || !canEditGrades) return;

    const saved = await saveAll(false);
    if (!saved) return;

    submitGradesMutation.mutate({
      subject_id: Number(subjectId),
      period_id: period.id,
      section_id: Number(sectionId),
    });
  }, [period, canEditGrades, saveAll, submitGradesMutation, subjectId, sectionId]);

  // Ctrl+Enter shortcut
  useEffect(() => {
    const handler = (e) => {
      if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        saveAll();
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [saveAll]);

  // Derived stats (live — recalculated from localScores)
  const calcTotal = (c1, c2, c3) => {
    const vals = [c1, c2, c3].filter(v => v !== '' && v !== null && v !== undefined && !isNaN(Number(v)));
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + Number(b), 0) / vals.length;
  };

  const numericTotals = localScores
    .map(s => calcTotal(s.c1, s.c2, s.c3))
    .filter(t => t !== null);

  const groupAvg    = numericTotals.length > 0
    ? (numericTotals.reduce((a, b) => a + b, 0) / numericTotals.length).toFixed(1)
    : '--';
  const gradedCount = numericTotals.length;
  const pendingCount = localScores.length - gradedCount;

  const distBuckets = [
    { label: '<70',    color: 'bg-red-400/30',     count: numericTotals.filter(t => t < 70).length,               hover: 'Insuficiente' },
    { label: '70-80',  color: 'bg-amber-400/30',   count: numericTotals.filter(t => t >= 70 && t < 80).length,    hover: 'Bajo' },
    { label: '80-90',  color: 'bg-emerald-400/30', count: numericTotals.filter(t => t >= 80 && t < 90).length,    hover: 'Bueno' },
    { label: '90-95',  color: 'bg-indigo-400/30',  count: numericTotals.filter(t => t >= 90 && t < 95).length,    hover: 'Excelente' },
    { label: '95-100', color: 'bg-indigo-500/50',  count: numericTotals.filter(t => t >= 95).length,              hover: 'Sobresaliente' },
  ];
  const distMax = Math.max(...distBuckets.map(b => b.count), 1);

  return (
    <DashboardLayout>

      {/* Toast */}
      {toastMessage && (
        <div className={`fixed top-20 right-6 z-50 text-white text-xs font-semibold px-4 py-3 rounded-xl shadow-lg border flex items-center gap-2 ${
          toastType === 'error' ? 'bg-red-700 border-red-600' : 'bg-slate-900 border-slate-700'
        }`}>
          <span className={`material-symbols-outlined text-base ${toastType === 'error' ? 'text-white' : 'text-emerald-400'}`}>
            {toastType === 'error' ? 'error' : 'check_circle'}
          </span>
          <span>{toastMessage}</span>
        </div>
      )}

      {/* Breadcrumbs + Header actions */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-6">
        <div className="space-y-1.5">
          <nav className="flex items-center gap-1.5 text-slate-400 text-[10px] font-bold tracking-wider uppercase flex-wrap">
            <Link to="/docente/courses" className="hover:text-slate-700 transition-colors">Mis Cursos</Link>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <Link to={`/docente/courses/${sectionId}/${subjectId}`} className="hover:text-slate-700 transition-colors">
              {gradeName} {sectionName}
            </Link>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <span className="text-slate-500">{subjectName}</span>
            <span className="material-symbols-outlined text-[12px]">chevron_right</span>
            <span className="text-indigo-600 font-extrabold">{activityName}</span>
          </nav>
          <h2 className="text-2xl font-bold text-slate-800 tracking-tight">
            Registro de Notas: {activityName}
          </h2>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => saveAll()}
            disabled={!canEditGrades || isSavingAll}
            className="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-xs rounded-xl shadow-sm transition-all disabled:cursor-not-allowed disabled:opacity-50 flex items-center gap-1.5"
          >
            {isSavingAll && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
            {isSavingAll ? 'Guardando...' : 'Guardar Progreso'}
          </button>
          <button
            onClick={handleSubmitGrades}
            disabled={!period || submitGradesMutation.isPending || isSavingAll || !canEditGrades}
            className="px-4 py-2 bg-slate-950 text-white hover:bg-slate-900 font-bold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span className="material-symbols-outlined text-[18px]">check_circle</span>
            Finalizar Calificación
          </button>
        </div>
      </div>

      {/* Info card + KPI */}
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">

        {/* Activity info */}
        <div className="lg:col-span-3 bg-white border border-slate-200 p-6 rounded-xl flex flex-col md:flex-row items-start md:items-center gap-6 shadow-sm">
          <div className="flex items-center gap-4 pr-6 border-r border-slate-100 shrink-0 w-full md:w-auto">
            <div className="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
              <span className="material-symbols-outlined text-[28px]">assignment</span>
            </div>
            <div>
              <p className="text-[10px] font-bold text-slate-400 tracking-wider uppercase">Actividad</p>
              <p className="font-bold text-base text-slate-800">{activityName}</p>
            </div>
          </div>
          <div className="flex-grow grid grid-cols-3 gap-4 w-full text-left">
            <div>
              <p className="text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-0.5">Tipo</p>
              <p className="font-bold text-xs text-slate-700">
                {currentActivity ? (currentActivity.is_base ? 'Base' : 'Personalizada') : '—'}
              </p>
            </div>
            <div>
              <p className="text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-0.5">Período</p>
              <p className="font-bold text-xs text-slate-700">{period ? period.name : '—'}</p>
            </div>
            <div>
              <p className="text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-0.5">Entradas Registradas</p>
              <p className="font-bold text-xs text-slate-700">
                {currentActivity?.score_count != null ? `${currentActivity.score_count} notas` : '—'}
              </p>
            </div>
          </div>
        </div>

        {/* Group average KPI */}
        <div className="bg-white border border-slate-200 p-6 rounded-xl flex flex-col justify-between shadow-sm">
          <div>
            <p className="text-[10px] font-bold text-slate-400 tracking-wider uppercase">Promedio Grupal</p>
            <p className="text-3xl font-extrabold text-slate-800 font-mono mt-0.5">{groupAvg}</p>
          </div>
          <div className="flex items-center gap-4 mt-4 text-[10px] font-bold uppercase tracking-wider">
            <span className="flex items-center gap-1 text-emerald-600">
              <span className="material-symbols-outlined text-[14px]">check_circle</span>
              {gradedCount} calificados
            </span>
            <span className="flex items-center gap-1 text-slate-400">
              <span className="material-symbols-outlined text-[14px]">pending</span>
              {pendingCount} pendientes
            </span>
          </div>
        </div>

      </div>

      {!canEditGrades && (
        <div className="mb-6 rounded-xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-800 shadow-sm">
          <div className="flex items-start gap-3">
            <span className="material-symbols-outlined text-[22px]">lock</span>
            <div>
              <p className="font-extrabold">
                {lockedGradeStatus === 'in_review'
                  ? 'Calificaciones en revisión'
                  : lockedGradeStatus === 'official'
                    ? 'Calificaciones oficiales'
                    : 'Período cerrado'}
              </p>
              <p className="mt-0.5 text-xs font-semibold leading-5">
                {lockedGradeStatus
                  ? 'Puedes consultar estas notas, pero no agregar ni modificar datos mientras coordinación las revisa o ya estén aprobadas.'
                  : 'Puedes consultar estas notas, pero no agregar ni modificar datos hasta que coordinación active el período o autorice el cambio.'}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Grade entry table */}
      <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm mb-6">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead className="bg-slate-50 border-b border-slate-200">
              <tr className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                <th className="py-4 px-6">Estudiante</th>
                <th className="py-4 px-4 w-36">Matrícula</th>
                <th className="py-4 px-4 text-center w-32">C1 Comunicativa</th>
                <th className="py-4 px-4 text-center w-36">C2 Pensamiento Lógico</th>
                <th className="py-4 px-4 text-center w-32">C3 Científica</th>
                <th className="py-4 px-4 text-center w-28 bg-slate-100/30">Total</th>
                <th className="py-4 px-6 text-center w-24">Estado</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-sm">

              {localScores.length === 0 && (
                <tr>
                  <td colSpan="7" className="py-14 text-center text-slate-400 text-xs font-semibold">
                    {period ? 'Cargando estudiantes...' : 'Esperando período activo...'}
                  </td>
                </tr>
              )}

              {localScores.map((student) => {
                const rawTotal  = calcTotal(student.c1, student.c2, student.c3);
                const totalDisp = rawTotal !== null ? rawTotal.toFixed(1) : '--';
                const isRisk    = rawTotal !== null && rawTotal < 70;
                const cellStatuses = ['c1', 'c2', 'c3'].map(
                  key => syncStatus[`${saveScope}:${student.student_id}:${key}`]
                );
                const status = cellStatuses.includes('sync')
                  ? 'sync'
                  : cellStatuses.includes('error')
                    ? 'error'
                    : cellStatuses.includes('cloud_done')
                      ? 'cloud_done'
                      : undefined;

                return (
                  <tr key={student.student_id} className="hover:bg-slate-50/50 transition-colors">
                    <td className="py-3.5 px-6">
                      <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-full bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs uppercase shrink-0">
                          {student.student_name.substring(0, 2)}
                        </div>
                        <p className="font-semibold text-slate-800 text-sm">{student.student_name}</p>
                      </div>
                    </td>

                    <td className="py-3.5 px-4">
                      <span className="font-mono text-[10px] text-slate-400">{student.enrollment_no ?? '—'}</span>
                    </td>

                    {['c1', 'c2', 'c3'].map((key) => (
                      <td key={key} className="py-3.5 px-4 text-center">
                        <input
                          type="number"
                          min="0"
                          max="100"
                          value={student[key] ?? ''}
                          onChange={(e) => handleScoreChange(student.student_id, key, e.target.value)}
                          onBlur={() => flushPendingCellSave(student.student_id, key).catch(() => undefined)}
                          disabled={!canEditGrades}
                          className={`w-full text-center font-mono py-1.5 border rounded-lg focus:ring-1 focus:outline-none text-sm transition-colors ${
                            student[key] !== null && student[key] !== '' && Number(student[key]) < 70
                              ? 'border-red-200 text-red-600 bg-red-50/30 focus:ring-red-400 focus:border-red-400'
                              : 'border-slate-200 text-emerald-600 focus:ring-slate-950 focus:border-slate-950'
                          }`}
                        />
                      </td>
                    ))}

                    <td className={`py-3.5 px-4 text-center font-mono font-bold bg-slate-50/50 ${isRisk ? 'text-red-600' : 'text-slate-800'}`}>
                      {totalDisp}
                    </td>

                    <td className="py-3.5 px-6 text-center">
                      {status === 'sync'       && <span className="material-symbols-outlined text-slate-400 animate-spin text-[18px]">sync</span>}
                      {status === 'cloud_done' && <span className="material-symbols-outlined text-emerald-500 text-[18px]">cloud_done</span>}
                      {status === 'error'      && <span className="material-symbols-outlined text-red-400 text-[18px]">error</span>}
                      {!status                 && <span className="material-symbols-outlined text-slate-300 text-[18px]">edit</span>}
                    </td>
                  </tr>
                );
              })}

            </tbody>
          </table>
        </div>
      </div>

      {/* Bottom panels */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

        {/* Distribution chart — built from live localScores */}
        <div className="bg-white border border-slate-200 p-6 rounded-xl flex flex-col gap-4 shadow-sm">
          <div className="flex items-center justify-between">
            <h3 className="font-bold text-slate-700 text-xs uppercase tracking-wider">Distribución de Calificaciones</h3>
            <span className="text-[10px] text-slate-400 font-semibold">{gradedCount} calificados</span>
          </div>

          <div className="flex items-end gap-3 h-32 w-full pt-4 border-b border-slate-100 pb-2">
            {distBuckets.map(({ label, color, count, hover }) => (
              <div
                key={label}
                className={`flex-grow ${color} rounded-t group relative cursor-default transition-all`}
                style={{ height: `${Math.max((count / distMax) * 100, count > 0 ? 8 : 3)}%` }}
              >
                {count > 0 && (
                  <div className="absolute bottom-full left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap mb-1">
                    {count} {count === 1 ? 'Estudiante' : 'Estudiantes'}
                  </div>
                )}
              </div>
            ))}
          </div>

          <div className="flex justify-between text-[10px] text-slate-400 font-bold tracking-wider uppercase">
            {distBuckets.map(b => <span key={b.label}>{b.label}</span>)}
          </div>
        </div>

        {/* Keyboard shortcuts info */}
        <div className="bg-white border border-slate-200 p-6 rounded-xl flex flex-col gap-4 shadow-sm">
          <h3 className="font-bold text-slate-700 text-xs uppercase tracking-wider">Accesos Rápidos</h3>
          <p className="text-xs text-slate-500 leading-relaxed">
            Presione{' '}
            <kbd className="px-1.5 py-0.5 border rounded bg-slate-50 text-[10px] font-bold font-mono">Ctrl</kbd>
            {' + '}
            <kbd className="px-1.5 py-0.5 border rounded bg-slate-50 text-[10px] font-bold font-mono">Enter</kbd>
            {' para guardar todos los cambios automáticamente.'}
          </p>
          <div className="flex flex-wrap gap-2 mt-2">
            {['Entrega a Tiempo', 'Excelente Creatividad', 'Requiere Refuerzo'].map((label) => (
              <span
                key={label}
                onClick={() => showToast(`Criterio "${label}" anotado.`)}
                className="px-2.5 py-1.5 bg-slate-50 rounded-lg text-[10px] font-bold border border-slate-100 cursor-pointer hover:bg-slate-950 hover:text-white transition-colors"
              >
                {label}
              </span>
            ))}
          </div>
        </div>

      </div>

    </DashboardLayout>
  );
}
