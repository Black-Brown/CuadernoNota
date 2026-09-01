import React, { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { decideGradeReview, getGradeReviewDetail } from '../../api/admin.api';
import useToast from '../../hooks/useToast';
import { getErrorMessage } from '../../utils/apiError';
import PageHeader from '../../components/ui/PageHeader';
import DataTable from '../../components/ui/DataTable';
import StatusBadge from '../../components/ui/StatusBadge';
import ConfirmDialog from '../../components/ui/ConfirmDialog';
import FormField, { inputClass } from '../../components/ui/FormField';
import Toast from '../../components/ui/Toast';

const STATUS_META = { in_review: { tone: 'warning', label: 'En revisión' }, official: { tone: 'success', label: 'Oficial' }, draft: { tone: 'neutral', label: 'Borrador' } };

function scoreClass(value) {
  if (value === null || value === undefined) return 'text-slate-400';
  return Number(value) < 70 ? 'font-bold text-red-600' : 'font-semibold text-slate-800';
}

export default function GradeReviewDetail() {
  const { sectionId, subjectId, periodId } = useParams();
  const qc = useQueryClient();
  const { toast, showToast } = useToast();
  const [pendingAction, setPendingAction] = useState(null);
  const [comment, setComment] = useState('');

  const queryKey = ['admin-grade-review-detail', sectionId, subjectId, periodId];
  const { data, isLoading } = useQuery({ queryKey, queryFn: () => getGradeReviewDetail(sectionId, subjectId, periodId) });

  const status = data?.[0]?.status;
  const meta = STATUS_META[status] || STATUS_META.draft;
  const requiresComment = pendingAction === 'rejected' || pendingAction === 'reopened';

  const decideMutation = useMutation({
    mutationFn: () => decideGradeReview({ section_id: Number(sectionId), subject_id: Number(subjectId), period_id: Number(periodId), action: pendingAction, comment: comment || undefined }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey });
      qc.invalidateQueries({ queryKey: ['admin-grade-reviews'] });
      setPendingAction(null);
      setComment('');
      showToast('Decisión aplicada correctamente.');
    },
    onError: (err) => showToast(getErrorMessage(err), 'error'),
  });

  return (
    <>
      <PageHeader
        breadcrumb={['Portal Administrativo', { label: 'Aprobación de notas', to: '/admin/reviews' }, 'Detalle']}
        title="Detalle de calificaciones"
        description="Calificaciones por período para la sección y materia seleccionadas."
        actions={
          <div className="flex items-center gap-3">
            <StatusBadge tone={meta.tone} label={meta.label} />
            {status === 'in_review' && (
              <>
                <button onClick={() => { setPendingAction('rejected'); setComment(''); }} className="rounded-lg border border-red-200 px-4 py-2.5 text-xs font-bold text-red-600 hover:bg-red-50">Rechazar</button>
                <button onClick={() => { setPendingAction('approved'); setComment(''); }} className="rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700">Aprobar</button>
              </>
            )}
            {status === 'official' && (
              <button onClick={() => { setPendingAction('reopened'); setComment(''); }} className="rounded-lg border border-amber-200 px-4 py-2.5 text-xs font-bold text-amber-700 hover:bg-amber-50">Reabrir</button>
            )}
          </div>
        }
      />

      <DataTable
        loading={isLoading}
        rows={data}
        rowKey={(r) => r.id}
        emptyIcon="fact_check"
        emptyTitle="No hay calificaciones registradas para esta combinación."
        columns={[
          { key: 'student', label: 'Estudiante', render: (r) => <span className="font-bold text-slate-900">{r.name} {r.last_name}</span> },
          { key: 'enrollment_no', label: 'Matrícula', render: (r) => <span className="font-mono text-xs text-slate-500">{r.enrollment_no}</span> },
          { key: 'c1_score', label: 'C1', align: 'center', render: (r) => r.c1_score ?? '—' },
          { key: 'c2_score', label: 'C2', align: 'center', render: (r) => r.c2_score ?? '—' },
          { key: 'c3_score', label: 'C3', align: 'center', render: (r) => r.c3_score ?? '—' },
          { key: 'period_score', label: 'Nota del período', align: 'center', render: (r) => <span className={scoreClass(r.period_score)}>{r.period_score ?? '—'}</span> },
          { key: 'rp_score', label: 'Recuperación', align: 'center', render: (r) => <span className={scoreClass(r.rp_score)}>{r.rp_score ?? '—'}</span> },
          { key: 'status', label: 'Estado', align: 'center', render: (r) => <StatusBadge tone={STATUS_META[r.status]?.tone || 'neutral'} label={STATUS_META[r.status]?.label || r.status} /> },
        ]}
      />

      <ConfirmDialog
        open={!!pendingAction}
        onClose={() => setPendingAction(null)}
        onConfirm={() => decideMutation.mutate()}
        loading={decideMutation.isPending}
        disableConfirm={requiresComment && !comment.trim()}
        tone={pendingAction === 'rejected' ? 'danger' : 'default'}
        title={pendingAction === 'approved' ? 'Aprobar calificaciones' : pendingAction === 'rejected' ? 'Rechazar calificaciones' : 'Reabrir calificaciones'}
        message={
          pendingAction === 'approved' ? 'Las calificaciones pasarán a estado oficial.'
          : pendingAction === 'rejected' ? 'Las calificaciones volverán a borrador para que el docente las corrija.'
          : 'Las calificaciones oficiales volverán a borrador.'
        }
        confirmLabel={pendingAction === 'approved' ? 'Aprobar' : pendingAction === 'rejected' ? 'Rechazar' : 'Reabrir'}
      >
        {requiresComment && (
          <FormField label="Comentario" required hint="Obligatorio para rechazar o reabrir.">
            <textarea rows={3} className={inputClass} value={comment} onChange={(e) => setComment(e.target.value)} />
          </FormField>
        )}
      </ConfirmDialog>

      <Toast toast={toast} />
    </>
  );
}
