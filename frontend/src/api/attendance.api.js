import api from './client';

export const getCourseAttendance = (sectionId, subjectId, date) =>
  api.get(`/docente/attendance/${sectionId}/${subjectId}/${date}`).then((response) => response.data);

export const saveAttendance = (studentId, subjectId, date, status) =>
  api.post('/docente/attendance', { student_id: studentId, subject_id: subjectId, date, status }).then((response) => response.data);

export const excuseAttendance = (attendanceId) =>
  api.patch(`/docente/attendance/${attendanceId}/excuse`).then((response) => response.data);
