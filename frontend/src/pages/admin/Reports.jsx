import React, { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getAcademicReport, getAcademicYears, getAttendanceReport } from '../../api/admin.api';
import { downloadCsv, safeFilename } from '../../utils/exportCsv';
import PageHeader from '../../components/ui/PageHeader';
import KpiCard from '../../components/ui/KpiCard';
import DataTable from '../../components/ui/DataTable';
import { selectClass } from '../../components/ui/FormField';

const TABS = [
  { key: 'academic', label: 'Académico', icon: 'school' },
  { key: 'attendance', label: 'Asistencia', icon: 'fact_check' },
];

export default function Reports() {
  const [tab, setTab] = useState('academic');
  const [yearId, setYearId] = useState('');

  const { data: years } = useQuery({ queryKey: ['admin-years'], queryFn: getAcademicYears });
  useEffect(() => {
    if (!yearId && years?.length) setYearId(String(years.find((y) => y.active)?.id || years[0].id));
  }, [years, yearId]);

  const academic = useQuery({ queryKey: ['admin-report-academic', yearId], queryFn: () => getAcademicReport({ academic_year_id: yearId }), enabled: !!yearId && tab === 'academic' });
  const attendance = useQuery({ queryKey: ['admin-report-attendance', yearId], queryFn: () => getAttendanceReport({ academic_year_id: yearId }), enabled: !!yearId && tab === 'attendance' });

  const academicRows = academic.data?.rows || [];
  const attendanceRows = attendance.data || [];

  const academicSummary = academic.data?.summary || {};
  const evaluatedStudents = Number(academicSummary.evaluated_students || 0);
  const atRiskStudents = Number(academicSummary.at_risk_students || 0);
  const academicAvg = academicSummary.overall_average != null ? Number(academicSummary.overall_average).toFixed(1) : '—';

  const attendanceTotals = attendanceRows.reduce((acc, r) => ({
    records: acc.records + Number(r.records || 0),
    present: acc.present + Number(r.present || 0),
    absent: acc.absent + Number(r.absent || 0),
    late: acc.late + Number(r.late || 0),
    excused: acc.excused + Number(r.excused || 0),
  }), { records: 0, present: 0, absent: 0, late: 0, excused: 0 });
  const attendanceRate = attendanceTotals.records ? ((attendanceTotals.present / attendanceTotals.records) * 100).toFixed(1) : '—';

  const yearName = years?.find((y) => String(y.id) === String(yearId))?.name || 'reporte';

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Reportes']}
        title="Reportes"
        description="Consulta el rendimiento académico y la asistencia del centro por año escolar."
        actions={
          <select value={yearId} onChange={(e) => setYearId(e.target.value)} className={`${selectClass} w-auto`}>
            {years?.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
          </select>
        }
      />

      <div className="mb-6 flex gap-1 border-b border-slate-200">
        {TABS.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-bold transition-colors ${tab === t.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
          >
            <span className="material-symbols-outlined text-[18px]">{t.icon}</span>
            {t.label}
          </button>
        ))}
      </div>

      {tab === 'academic' ? (
        <>
          <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <KpiCard label="Estudiantes evaluados" value={evaluatedStudents} icon="groups" tone="bg-sky-50 text-sky-700" loading={academic.isLoading} />
            <KpiCard label="Promedio general" value={academicAvg} icon="analytics" tone="bg-emerald-50 text-emerald-700" loading={academic.isLoading} />
            <KpiCard label="Estudiantes en riesgo" value={atRiskStudents} icon="warning" tone="bg-red-50 text-red-700" loading={academic.isLoading} />
          </section>

          <div className="mb-3 flex justify-end">
            <button
              onClick={() => downloadCsv(`reporte-academico-${safeFilename(yearName)}`, [
                ['Grado', 'Sección', 'Materia', 'Período', 'Estudiantes', 'Promedio', 'En riesgo'],
                ...academicRows.map((r) => [r.grade, r.section, r.subject, r.period, r.students, r.average, r.at_risk]),
              ])}
              disabled={academicRows.length === 0}
              className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
            >
              <span className="material-symbols-outlined text-[16px]">download</span>
              Exportar CSV
            </button>
          </div>

          <DataTable
            loading={academic.isLoading}
            rows={academicRows}
            rowKey={(r, i) => `${r.grade}-${r.section}-${r.subject}-${r.period}-${i}`}
            emptyIcon="school"
            emptyTitle="No hay calificaciones oficiales para este año."
            columns={[
              { key: 'grade', label: 'Grado' },
              { key: 'section', label: 'Sección' },
              { key: 'subject', label: 'Materia' },
              { key: 'period', label: 'Período' },
              { key: 'students', label: 'Estudiantes', align: 'center' },
              { key: 'average', label: 'Promedio', align: 'center' },
              { key: 'at_risk', label: 'En riesgo', align: 'center', render: (r) => <span className={r.at_risk > 0 ? 'font-bold text-red-600' : ''}>{r.at_risk}</span> },
            ]}
          />
        </>
      ) : (
        <>
          <section className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
            <KpiCard label="Registros" value={attendanceTotals.records} icon="fact_check" tone="bg-indigo-50 text-indigo-700" loading={attendance.isLoading} />
            <KpiCard label="Asistieron (P + T)" value={attendanceTotals.present} icon="check_circle" tone="bg-emerald-50 text-emerald-700" loading={attendance.isLoading} />
            <KpiCard label="Ausentes" value={attendanceTotals.absent} icon="cancel" tone="bg-red-50 text-red-700" loading={attendance.isLoading} />
            <KpiCard label="Tasa de asistencia" value={`${attendanceRate}%`} icon="trending_up" tone="bg-sky-50 text-sky-700" loading={attendance.isLoading} />
          </section>

          <div className="mb-3 flex justify-end">
            <button
              onClick={() => downloadCsv(`reporte-asistencia-${safeFilename(yearName)}`, [
                ['Grado', 'Sección', 'Registros', 'Asistieron (P + T)', 'Ausentes', 'Tardanzas', 'Excusas'],
                ...attendanceRows.map((r) => [r.grade, r.section, r.records, r.present, r.absent, r.late, r.excused]),
              ])}
              disabled={attendanceRows.length === 0}
              className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
            >
              <span className="material-symbols-outlined text-[16px]">download</span>
              Exportar CSV
            </button>
          </div>

          <DataTable
            loading={attendance.isLoading}
            rows={attendanceRows}
            rowKey={(r, i) => `${r.grade}-${r.section}-${i}`}
            emptyIcon="fact_check"
            emptyTitle="No hay registros de asistencia para este año."
            columns={[
              { key: 'grade', label: 'Grado' },
              { key: 'section', label: 'Sección' },
              { key: 'records', label: 'Registros', align: 'center' },
              { key: 'present', label: 'Asistieron', align: 'center' },
              { key: 'absent', label: 'Ausentes', align: 'center' },
              { key: 'late', label: 'Tardanzas', align: 'center' },
              { key: 'excused', label: 'Excusas', align: 'center' },
            ]}
          />
        </>
      )}
    </>
  );
}
