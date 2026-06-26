import api from './client';

export const getActivitiesBySubject = (subjectId, sectionId, periodId) =>
  api.get(`/docente/activities/${subjectId}`, {
    params: {
      ...(sectionId ? { section_id: sectionId } : {}),
      ...(periodId ? { period_id: periodId } : {}),
    },
  }).then((r) => r.data);

export const getPeriodGrades = (subjectId, periodId, sectionId) =>
  api.get(`/docente/grades/period/${subjectId}/${periodId}`, {
    params: sectionId ? { section_id: sectionId } : {},
  }).then((r) => r.data);

export const getGradebookSummary = (sectionId, subjectId, academicYearId) =>
  api.get(`/docente/grades/summary/${sectionId}/${subjectId}`, {
    params: academicYearId ? { academic_year_id: academicYearId } : {},
  }).then((r) => r.data);

export const getActivityGrades = (activityId, periodId, sectionId) =>
  api.get(`/docente/grades/activity/${activityId}/${periodId}`, {
    params: sectionId ? { section_id: sectionId } : {},
  }).then((r) => r.data);

export const saveActivityScore = (payload) =>
  api.post('/docente/grades/activity-score', payload).then((r) => r.data);

export const submitGrades = (payload) =>
  api.post('/docente/grades/submit', payload).then((r) => r.data);
