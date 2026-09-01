import api from './client';

export const getRiskOverview = (periodId = null) =>
  api
    .get('/docente/risk', {
      params: periodId ? { period_id: periodId } : {},
    })
    .then((r) => r.data);

export const getCourseRisk = (sectionId, subjectId, periodId = null) =>
  api
    .get(`/docente/risk/${sectionId}/${subjectId}`, {
      params: periodId ? { period_id: periodId } : {},
    })
    .then((r) => r.data);

export const getStudentRisk = (sectionId, subjectId, studentId, periodId = null) =>
  api
    .get(`/docente/risk/${sectionId}/${subjectId}/students/${studentId}`, {
      params: periodId ? { period_id: periodId } : {},
    })
    .then((r) => r.data);
