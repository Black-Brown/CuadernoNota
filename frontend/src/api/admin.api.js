import api from './client';

// ── Dashboard ────────────────────────────────────────────────────────────────
export const getAdminDashboard = () => api.get('/admin/dashboard').then(r => r.data);

// No automatic retry: a lost response does not mean the transaction failed.
export const previewSystemReset = () => api.get('/admin/system/reset-data/preview').then(r => r.data);
export const resetSystemData = data => api.post('/admin/system/reset-data', data).then(r => r.data);

// ── Usuarios ─────────────────────────────────────────────────────────────────
export const getAdminUsers = (params = {}) => api.get('/admin/users', { params }).then(r => r.data);
export const createAdminUser = data => api.post('/admin/users', data).then(r => r.data);
export const updateAdminUser = (id, data) => api.patch(`/admin/users/${id}`, data).then(r => r.data);
export const deactivateAdminUser = id => api.delete(`/admin/users/${id}`).then(r => r.data);

// ── Estudiantes ──────────────────────────────────────────────────────────────
export const getAdminStudents = (params = {}) => api.get('/admin/students', { params }).then(r => r.data);
export const getAdminStudent = id => api.get(`/admin/students/${id}`).then(r => r.data);
export const createAdminStudent = data => api.post('/admin/students', data).then(r => r.data);
export const updateAdminStudent = (id, data) => api.patch(`/admin/students/${id}`, data).then(r => r.data);
export const enrollStudent = (id, data) => api.post(`/admin/students/${id}/enrollments`, data).then(r => r.data);
export const deactivateStudent = (id, data) => api.post(`/admin/students/${id}/deactivate`, data).then(r => r.data);
const studentCsvForm = file => { const data = new FormData(); data.append('file', file); return data; };
export const previewStudentImport = file => api.post('/admin/students/import/preview', studentCsvForm(file)).then(r => r.data);
export const importStudentsCsv = file => api.post('/admin/students/import', studentCsvForm(file)).then(r => r.data);
export const getPendingStudentPlacements = (params = {}) => api.get('/admin/student-placements/pending', { params }).then(r => r.data);
export const assignStudentsToSection = data => api.post('/admin/student-placements', data).then(r => r.data);

// ── Años escolares ───────────────────────────────────────────────────────────
export const getAcademicYears = () => api.get('/admin/academic-years').then(r => r.data);
export const createAcademicYear = data => api.post('/admin/academic-years', data).then(r => r.data);
export const updateAcademicYear = (id, data) => api.patch(`/admin/academic-years/${id}`, data).then(r => r.data);
export const deleteAcademicYear = id => api.delete(`/admin/academic-years/${id}`).then(r => r.data);

// ── Períodos ─────────────────────────────────────────────────────────────────
export const getPeriodsByYear = academicYearId => api.get(`/admin/academic-years/${academicYearId}/periods`).then(r => r.data);
export const createPeriod = (academicYearId, data) => api.post(`/admin/academic-years/${academicYearId}/periods`, data).then(r => r.data);
export const updatePeriod = (id, data) => api.patch(`/admin/periods/${id}`, data).then(r => r.data);
export const deletePeriod = id => api.delete(`/admin/periods/${id}`).then(r => r.data);
export const getPeriodActivitySummary = periodId => api.get(`/admin/periods/${periodId}/activity-summary`).then(r => r.data);

// ── Grados ───────────────────────────────────────────────────────────────────
export const getGrades = (params = {}) => api.get('/admin/grades', { params }).then(r => r.data);
export const createGrade = data => api.post('/admin/grades', data).then(r => r.data);
export const updateGrade = (id, data) => api.patch(`/admin/grades/${id}`, data).then(r => r.data);
export const deleteGrade = id => api.delete(`/admin/grades/${id}`).then(r => r.data);
export const getGradeDeletionCheck = id => api.get(`/admin/grades/${id}/deletion-check`).then(r => r.data);
export const deactivateGrade = id => api.patch(`/admin/grades/${id}/deactivate`).then(r => r.data);
export const reactivateGrade = id => api.patch(`/admin/grades/${id}/reactivate`).then(r => r.data);

// ── Secciones ────────────────────────────────────────────────────────────────
export const getSections = (params = {}) => api.get('/admin/sections', { params }).then(r => r.data);
export const createSection = data => api.post('/admin/sections', data).then(r => r.data);
export const updateSection = (id, data) => api.patch(`/admin/sections/${id}`, data).then(r => r.data);
export const deleteSection = (id, periodId) => api.delete(`/admin/sections/${id}`, { params: periodId ? { period_id: periodId } : {} }).then(r => r.data);

// ── Materias ─────────────────────────────────────────────────────────────────
export const getSubjects = () => api.get('/admin/subjects').then(r => r.data);
export const createSubject = data => api.post('/admin/subjects', data).then(r => r.data);
export const updateSubject = (id, data) => api.patch(`/admin/subjects/${id}`, data).then(r => r.data);
export const deactivateSubject = id => api.delete(`/admin/subjects/${id}`).then(r => r.data);

// ── Actividades base ─────────────────────────────────────────────────────────
export const getActivityTemplates = () => api.get('/admin/activity-templates').then(r => r.data);
export const createActivityTemplate = data => api.post('/admin/activity-templates', data).then(r => r.data);
export const updateActivityTemplate = (id, data) => api.patch(`/admin/activity-templates/${id}`, data).then(r => r.data);
export const deactivateActivityTemplate = id => api.delete(`/admin/activity-templates/${id}`).then(r => r.data);

// ── Asignaciones docentes ────────────────────────────────────────────────────
export const getAssignmentOptions = () => api.get('/admin/teacher-assignments/options').then(r => r.data);
export const getAssignments = () => api.get('/admin/teacher-assignments').then(r => r.data);
export const createTeacher = data => api.post('/admin/teachers', data).then(r => r.data);
export const createAssignment = data => api.post('/admin/teacher-assignments', data).then(r => r.data);
export const createAssignments = (teacherId, courseOfferingIds) => api.post('/admin/teacher-assignments', {
  teacher_id: Number(teacherId), course_offering_ids: courseOfferingIds.map(Number),
}).then(r => r.data);
export const updateAssignment = (id, data) => api.patch(`/admin/teacher-assignments/${id}`, data).then(r => r.data);
export const deactivateAssignment = id => updateAssignment(id, { active: false });
export const deleteAssignment = id => api.delete(`/admin/teacher-assignments/${id}`).then(r => r.data);

// ── Aprobación de calificaciones ─────────────────────────────────────────────
export const getGradeReviews = (params = {}) => api.get('/admin/grade-reviews', { params }).then(r => r.data);
export const getGradeReviewDetail = (sectionId, subjectId, periodId) =>
  api.get(`/admin/grade-reviews/${sectionId}/${subjectId}/${periodId}`).then(r => r.data);
export const decideGradeReview = data => api.post('/admin/grade-reviews/decision', data).then(r => r.data);

// ── Promoción escolar ────────────────────────────────────────────────────────
export const getPromotionCandidates = params => api.get('/admin/promotions/candidates', { params }).then(r => r.data);
export const decidePromotion = (studentEnrollmentId, data) =>
  api.post(`/admin/promotions/${studentEnrollmentId}/decision`, data).then(r => r.data);
export const decidePromotionsBulk = data => api.post('/admin/promotions/bulk-decision', data).then(r => r.data);

// ── Reportes ─────────────────────────────────────────────────────────────────
export const getAcademicReport = (params = {}) => api.get('/admin/reports/academic', { params }).then(r => r.data);
export const getAttendanceReport = (params = {}) => api.get('/admin/reports/attendance', { params }).then(r => r.data);

// ── Auditoría y respaldo ─────────────────────────────────────────────────────
export const getAuditLogs = (params = {}) => api.get('/admin/audit-logs', { params }).then(r => r.data);

export async function downloadBackup() {
  const response = await api.post('/admin/backups', {}, { responseType: 'blob' });
  const disposition = response.headers['content-disposition'] || '';
  const match = disposition.match(/filename="?([^"]+)"?/);
  const filename = match ? match[1] : `cuaderno-nota-backup-${Date.now()}.json`;

  const url = window.URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}
