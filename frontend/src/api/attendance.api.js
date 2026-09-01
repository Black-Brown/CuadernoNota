import api from './client';

export const getSectionAttendance = (sectionId, date) =>
  api.get(`/docente/attendance/${sectionId}/${date}`).then((response) => response.data);

export const saveAttendance = (studentId, date, status) =>
  api.post('/docente/attendance', { student_id: studentId, date, status }).then((response) => response.data);

export const excuseAttendance = (attendanceId) =>
  api.patch(`/docente/attendance/${attendanceId}/excuse`).then((response) => response.data);
