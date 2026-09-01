import api from './client';

export const createActivity = (payload) =>
  api.post('/docente/activities', payload).then((r) => r.data);

export const updateActivity = (id, payload) =>
  api.patch(`/docente/activities/${id}`, payload).then((r) => r.data);
