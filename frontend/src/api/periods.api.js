import api from './client';

export const getCurrentPeriod = () =>
  api.get('/docente/current-period').then((r) => r.data);

export const getPeriods = () =>
  api.get('/docente/periods').then((r) => r.data);
