import api from './client';

export const getCourseObservations = (sectionId, subjectId, periodId) =>
  api.get(`/docente/observations/course/${sectionId}/${subjectId}`, {
    params: periodId ? { period_id: periodId } : {},
  }).then((r) => r.data);

export const createObservation = (data) =>
  api.post('/docente/observations', data).then((r) => r.data);

export const updateObservation = (id, data) =>
  api.patch(`/docente/observations/${id}`, data).then((r) => r.data);

export const deleteObservation = (id) =>
  api.delete(`/docente/observations/${id}`).then((r) => r.data);
