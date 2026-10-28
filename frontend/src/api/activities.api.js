import api from './client';

export const saveActivityWeights = (payload) => api.put('/docente/activity-weights', payload).then(r => r.data);

export const createActivity = (payload) =>
  api.post('/docente/activities', payload).then((r) => r.data);

export const updateActivity = (id, payload) =>
  api.patch(`/docente/activities/${id}`, payload).then((r) => r.data);
