import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getGradeReviews } from '../../api/admin.api';
import PageHeader from '../../components/ui/PageHeader';
import FilterBar from '../../components/ui/FilterBar';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import { selectClass } from '../../components/ui/FormField';

const STATUS_OPTIONS = [
  { value: 'in_review', label: 'En revisión', tone: 'warning' },
  { value: 'official', label: 'Oficiales', tone: 'success' },
  { value: 'draft', label: 'Borrador', tone: 'neutral' },
];

export default function GradeReviews({
  loadReviews = getGradeReviews,
  queryPrefix = 'admin',
  portalName = 'Portal Administrativo',
  reviewsPath = '/admin/reviews',
  canReopen = true,
} = {}) {
  const navigate = useNavigate();
  const [status, setStatus] = useState('in_review');

  const { data, isLoading, isError } = useQuery({ queryKey: [`${queryPrefix}-grade-reviews`, status], queryFn: () => loadReviews({ status }) });
  const tone = STATUS_OPTIONS.find((s) => s.value === status)?.tone || 'neutral';

  return (
    <>
      <PageHeader
        breadcrumb={[portalName, 'Aprobación de notas']}
        title="Aprobación de calificaciones"
        description={canReopen ? 'Revisa, aprueba, rechaza o reabre calificaciones por sección, materia y período.' : 'Consulta, aprueba o devuelve calificaciones con comentarios por sección, materia y período.'}
      />

      <FilterBar>
        <select value={status} onChange={(e) => setStatus(e.target.value)} className={`${selectClass} w-auto`}>
          {STATUS_OPTIONS.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </select>
      </FilterBar>

      {isError && <p role="alert" className="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">No se pudieron cargar las calificaciones. Intenta nuevamente.</p>}
      <DataTable
        loading={isLoading}
        rows={data}
        rowKey={(row) => `${row.section_id}-${row.subject_id}-${row.period_id}`}
        emptyIcon="fact_check"
        emptyTitle="No hay calificaciones en este estado."
        onRowClick={(row) => navigate(`${reviewsPath}/${row.section_id}/${row.subject_id}/${row.period_id}`)}
        columns={[
          { key: 'grade_name', label: 'Grado' },
          { key: 'section_name', label: 'Sección' },
          { key: 'subject_name', label: 'Materia' },
          { key: 'period_name', label: 'Período' },
          { key: 'student_count', label: 'Estudiantes', align: 'center' },
          { key: 'average', label: 'Promedio', align: 'center', render: (r) => r.average != null ? Number(r.average).toFixed(1) : '—' },
          { key: 'status', label: 'Estado', align: 'center', render: () => <StatusBadge tone={tone} label={STATUS_OPTIONS.find((s) => s.value === status)?.label} /> },
        ]}
      />
    </>
  );
}
