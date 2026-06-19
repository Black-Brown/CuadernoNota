import api from './client';

export const getDashboardSummary = () =>
  api.get('/docente/dashboard').then((r) => r.data);

export const getSubjectDashboard = (sectionId, subjectId) =>
  api.get(`/docente/dashboard/${sectionId}/${subjectId}`).then((r) => r.data);
