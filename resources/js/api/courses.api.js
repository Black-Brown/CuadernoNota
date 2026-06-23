import api from './client';

export const getCourses = () =>
  api.get('/docente/courses').then((r) => r.data);
