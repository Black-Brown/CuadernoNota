import React, { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { decidePromotion, getAcademicYears, getPromotionCandidates, getSections } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import FilterBar from '../../components/ui/FilterBar';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import SideDrawer from '../../components/ui/SideDrawer';
import FormField, { inputClass, selectClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';

export default function Promotions() {
  const qc = useQueryClient();
  const { toast, showToast } = useToast();

  const [yearId, setYearId] = useState('');
  const [sectionId, setSectionId] = useState('');
  const [drawerRow, setDrawerRow] = useState(null);
  const [decisionStatus, setDecisionStatus] = useState('promoted');
  const [destinationSectionId, setDestinationSectionId] = useState('');
  const [justification, setJustification] = useState('');
  const [error, setError] = useState('');

  const { data: years } = useQuery({ queryKey: ['admin-years'], queryFn: getAcademicYears });
  const { data: sections } = useQuery({ queryKey: ['admin-sections'], queryFn: () => getSections() });

  useEffect(() => {
    if (!yearId && years?.length) setYearId(String(years.find((y) => y.active)?.id || years[0].id));
  }, [years, yearId]);

  const sectionsForYear = (sections || []).filter((s) => String(s.academicYear?.id) === String(yearId));

  const params = { academic_year_id: yearId };
  if (sectionId) params.section_id = sectionId;
  const { data, isLoading } = useQuery({ queryKey: ['admin-promotion-candidates', params], queryFn: () => getPromotionCandidates(params), enabled: !!yearId });

  const destinationOptions = (sections || []).filter((s) => String(s.academicYear?.id) !== String(yearId));

  const openDecision = (row) => {
    setDrawerRow(row); setDecisionStatus(row.eligible ? 'promoted' : 'not_promoted');
    setDestinationSectionId(''); setJustification(''); setError('');
  };

  const decideMutation = useMutation({
    mutationFn: () => decidePromotion(drawerRow.enrollment_id, {
      status: decisionStatus,
      destination_section_id: decisionStatus === 'promoted' ? destinationSectionId : null,
      justification: justification || undefined,
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-promotion-candidates'] });
      setDrawerRow(null);
      showToast('Decisión de promoción registrada.');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', 'Promoción escolar']}
        title="Promoción escolar"
        description="Evalúa candidatos y registra su decisión de promoción o su sección de destino."
      />

      <FilterBar>
        <select value={yearId} onChange={(e) => { setYearId(e.target.value); setSectionId(''); }} className={`${selectClass} w-auto`}>
          {years?.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
        </select>
        <select value={sectionId} onChange={(e) => setSectionId(e.target.value)} className={`${selectClass} w-auto`}>
          <option value="">Todas las secciones</option>
          {sectionsForYear.map((s) => <option key={s.id} value={s.id}>{s.grade?.name} {s.name}</option>)}
        </select>
      </FilterBar>

      <DataTable
        loading={isLoading}
        rows={data}
        rowKey={(r) => r.enrollment_id}
        emptyIcon="upgrade"
        emptyTitle="No hay candidatos para los filtros seleccionados."
        columns={[
          { key: 'student_name', label: 'Estudiante', render: (r) => <span className="font-bold text-slate-900">{r.student_name}</span> },
          { key: 'grade_name', label: 'Grado' },
          { key: 'subject_count', label: 'Materias registradas', align: 'center' },
          { key: 'expected_subject_count', label: 'Materias esperadas', align: 'center' },
          { key: 'failed_subjects', label: 'Materias reprobadas', align: 'center', render: (r) => <span className={r.failed_subjects > 0 ? 'font-bold text-red-600' : ''}>{r.failed_subjects}</span> },
          { key: 'eligible', label: 'Elegibilidad', align: 'center', render: (r) => <StatusBadge tone={r.eligible ? 'success' : 'danger'} label={r.eligible ? 'Elegible' : 'No elegible'} /> },
          {
            key: 'actions', label: 'Acciones', align: 'right', render: (r) => (
              <button onClick={() => openDecision(r)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-slate-50">Decidir</button>
            ),
          },
        ]}
      />

      <SideDrawer
        open={!!drawerRow}
        onClose={() => setDrawerRow(null)}
        title={`Decisión de promoción — ${drawerRow?.student_name || ''}`}
        description={`${drawerRow?.grade_name || ''} · ${drawerRow?.failed_subjects || 0} materia(s) reprobada(s)`}
        footer={
          <div className="flex justify-end gap-3">
            <button onClick={() => setDrawerRow(null)} className="rounded-lg border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
            <button
              onClick={() => decideMutation.mutate()}
              disabled={decideMutation.isPending || (decisionStatus === 'promoted' && !destinationSectionId)}
              className="flex items-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-50"
            >
              {decideMutation.isPending && <span className="material-symbols-outlined animate-spin text-[16px]">sync</span>}
              Confirmar decisión
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {error && <div className="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{error}</div>}

          <div className="flex gap-2 rounded-lg border border-slate-200 p-1">
            <button onClick={() => setDecisionStatus('promoted')} className={`flex-1 rounded-md py-1.5 text-xs font-bold ${decisionStatus === 'promoted' ? 'bg-emerald-600 text-white' : 'text-slate-500'}`}>Promover</button>
            <button onClick={() => setDecisionStatus('not_promoted')} className={`flex-1 rounded-md py-1.5 text-xs font-bold ${decisionStatus === 'not_promoted' ? 'bg-red-600 text-white' : 'text-slate-500'}`}>No promover</button>
          </div>

          {decisionStatus === 'promoted' && (
            <FormField label="Sección destino" required hint="Debe pertenecer a otro año escolar.">
              <select className={selectClass} value={destinationSectionId} onChange={(e) => setDestinationSectionId(e.target.value)}>
                <option value="">Seleccionar sección</option>
                {destinationOptions.map((s) => <option key={s.id} value={s.id}>{s.grade?.name} {s.name} · {s.academicYear?.name}</option>)}
              </select>
            </FormField>
          )}

          <FormField label="Justificación" hint="Opcional. Recomendado si el estudiante no cumple todos los criterios.">
            <textarea rows={3} className={inputClass} value={justification} onChange={(e) => setJustification(e.target.value)} />
          </FormField>
        </div>
      </SideDrawer>

      <Toast toast={toast} />
    </>
  );
}
