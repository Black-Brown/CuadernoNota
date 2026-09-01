import api from './client';

export const login = (credentials) =>
  api.post('/auth/login', credentials).then((r) => r.data);

export const exchangeGoogleCode = (code) =>
  api.post('/auth/google/exchange', { code }).then((r) => r.data);

export const logout = () =>
  api.post('/auth/logout').then((r) => r.data);

export const me = () =>
  api.get('/auth/me').then((r) => r.data);
