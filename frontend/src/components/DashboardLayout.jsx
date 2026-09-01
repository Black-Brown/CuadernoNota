import React, { useEffect, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getCurrentPeriod, getPeriods } from '../api/periods.api';
import useAuthStore from '../store/authStore';
import usePeriodStore from '../store/periodStore';
import AppLayout from './AppLayout';

const menuItems = [
  { items: [
    { name: 'Inicio', icon: 'dashboard', path: '/docente/dashboard' },
    { name: 'Mis Cursos', icon: 'school', path: '/docente/courses' },
    { name: 'Calificaciones', icon: 'grade', path: '/docente/grades' },
    { name: 'Estudiantes en Riesgo', icon: 'warning', path: '/docente/risk' },
    { name: 'Asistencia', icon: 'fact_check', path: '/docente/attendance' },
    { name: 'Observaciones', icon: 'speaker_notes', path: '/docente/observations' },
  ] },
];

export default function DashboardLayout({ children }) {
  const { user } = useAuthStore();
  const { selectedPeriodId, selectedPeriod, setSelectedPeriod } = usePeriodStore();

  const { data: periodData } = useQuery({
    queryKey: ['currentPeriod'],
    queryFn: getCurrentPeriod,
    staleTime: 5 * 60_000,
  });

  const { data: periodsData } = useQuery({
    queryKey: ['periods'],
    queryFn: getPeriods,
    staleTime: 5 * 60_000,
  });

  const periods = periodsData?.periods || [];
  const currentPeriod = periodData?.period;
  const period = useMemo(() => {
    return periods.find((item) => Number(item.id) === Number(selectedPeriodId))
      || selectedPeriod
      || currentPeriod;
  }, [periods, selectedPeriodId, selectedPeriod, currentPeriod]);

  useEffect(() => {
    if (periods.length === 0) return;

    const persistedPeriod = periods.find((item) => Number(item.id) === Number(selectedPeriodId));
    if (persistedPeriod) {
      if (selectedPeriod?.id !== persistedPeriod.id) {
        setSelectedPeriod(persistedPeriod);
      }
      return;
    }

    const defaultPeriod = currentPeriod
      ? periods.find((item) => Number(item.id) === Number(currentPeriod.id)) || currentPeriod
      : periods[0];

    if (defaultPeriod) {
      setSelectedPeriod(defaultPeriod);
    }
  }, [periods, selectedPeriodId, selectedPeriod?.id, currentPeriod, setSelectedPeriod]);

  const headerContent = (
    <div className="relative flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg select-none">
      <span className="material-symbols-outlined text-indigo-500 text-[18px]">calendar_today</span>
      {period ? (
        <>
          <select
            value={period.id}
            onChange={(event) => {
              const nextPeriod = periods.find((item) => Number(item.id) === Number(event.target.value));
              if (nextPeriod) setSelectedPeriod(nextPeriod);
            }}
            className="absolute inset-0 cursor-pointer opacity-0"
            aria-label="Seleccionar período"
          >
            {periods.map((item) => (
              <option key={item.id} value={item.id}>
                {item.name} · {item.months || 'Sin meses'}
              </option>
            ))}
          </select>
          <div className="flex items-center gap-1.5 pr-5 pointer-events-none">
            <span className="text-xs font-bold text-slate-800">{period.name}</span>
            <span className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">·</span>
            <span className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">{period.months}</span>
            <span className={`ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${
              period.status === 'open' ? 'bg-emerald-50 text-emerald-600' : period.status === 'in_review' ? 'bg-amber-50 text-amber-600' : period.status === 'upcoming' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-500'
            }`}>
              {period.status === 'open' ? 'Activo' : period.status === 'in_review' ? 'En revisión' : period.status === 'upcoming' ? 'Próximo' : period.status === 'ended' ? 'Finalizado' : 'Cerrado'}
            </span>
            <span className="material-symbols-outlined absolute right-2 text-slate-400 text-[16px]">expand_more</span>
          </div>
        </>
      ) : (
        <span className="text-xs font-semibold text-slate-400">Periodo Actual</span>
      )}
    </div>
  );

  return (
    <AppLayout
      portalName="Portal Docente"
      menuItems={menuItems}
      user={user}
      roleLabel={user?.role || 'docente'}
      searchPlaceholder="Buscar estudiantes, cursos o notas..."
      headerContent={headerContent}
      showPeriodSelector
    >
      {children}
    </AppLayout>
  );
}
