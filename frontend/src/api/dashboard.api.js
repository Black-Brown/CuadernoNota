import api from './client';

export const getDashboardSummary = (periodId = null) =>
  api.get('/docente/dashboard', { params: periodId ? { period_id: periodId } : {} }).then((r) => r.data);

export const getSubjectDashboard = (sectionId, subjectId, periodId = null) =>
  api.get(`/docente/dashboard/${sectionId}/${subjectId}`, {
    params: periodId ? { period_id: periodId } : {},
  }).then((r) => r.data);
