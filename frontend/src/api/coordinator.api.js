import api from './client';
export const getCoordinatorCatalog = () => api.get('/coordinador/catalog').then(r => r.data);
export const getCoordinatorStudents = () => api.get('/coordinador/students').then(r => r.data);
export const getCoordinatorTeachers = () => api.get('/coordinador/assignments').then(r => r.data);
export const getCoordinatorReports = (sectionId, params) => api.get(`/coordinador/reports/${sectionId}`, { params }).then(r => r.data);

export const getCoordinatorDashboard = () => api.get('/coordinador/dashboard').then(r => r.data);
export const getCoordinatorWorkspace = (sectionId, periodId) => api.get(`/coordinador/sections/${sectionId}/students`, { params: { period_id: periodId || undefined } }).then(r => r.data);
export const getCoordinatorStudent = (sectionId, studentId, periodId) => api.get(`/coordinador/sections/${sectionId}/students/${studentId}`, { params: { period_id: periodId || undefined } }).then(r => r.data);
export const getCoordinatorReviews = params => api.get('/coordinador/grade-reviews', { params }).then(r => r.data);
export const getCoordinatorReviewDetail = (section, subject, period) => api.get(`/coordinador/grade-reviews/${section}/${subject}/${period}`).then(r => r.data);
export const decideCoordinatorReview = data => api.post('/coordinador/grade-reviews/decision', data).then(r => r.data);
export const getCoordinatorAssignments = id => api.get(`/admin/users/${id}/coordinator-sections`).then(r => r.data);
export const saveCoordinatorAssignments = (id, section_ids) => api.put(`/admin/users/${id}/coordinator-sections`, { section_ids }).then(r => r.data);
