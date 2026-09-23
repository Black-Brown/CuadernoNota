import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getCoordinatorDashboard } from '../../api/coordinator.api';
import PageHeader from '../../components/ui/PageHeader';
import KpiCard from '../../components/ui/KpiCard';
import DataTable from '../../components/ui/DataTable';

export default function CoordinatorDashboard() {
  const navigate = useNavigate();
  const { data, isLoading, isError, refetch } = useQuery({ queryKey: ['coordinator-dashboard'], queryFn: getCoordinatorDashboard });
  return <>
    <PageHeader breadcrumb={['Portal de Coordinación', 'Inicio']} title="Supervisión académica"
      description={`Secciones bajo tu supervisión · ${data?.active_academic_year?.name ?? 'Sin año escolar activo'}`}
      actions={<Link to="/coordinador/reviews" className="rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800">Revisar calificaciones</Link>} />
    {isError ? <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">No se pudo cargar la supervisión. <button onClick={() => refetch()} className="font-bold underline">Reintentar</button></div> : <>
      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <KpiCard label="Secciones asignadas" value={data?.counts.sections ?? '—'} icon="school" loading={isLoading} />
        <KpiCard label="Estudiantes activos" value={data?.counts.students ?? '—'} icon="groups" loading={isLoading} />
        <KpiCard label="Cursos pendientes de revisión" value={data?.counts.pending_reviews ?? '—'} icon="fact_check" loading={isLoading} />
      </div>
      <DataTable rows={data?.sections} loading={isLoading} emptyTitle="Sin secciones asignadas en este año" emptyIcon="school"
        onRowClick={section => navigate(`/coordinador/sections/${section.id}`)}
        columns={[{ key: 'grade_name', label: 'Grado' }, { key: 'name', label: 'Sección' }, { key: 'shift', label: 'Tanda' }, { key: 'students_count', label: 'Estudiantes activos', align: 'center' }]} />
    </>}
  </>;
}
