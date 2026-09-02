// Manual UI regression fixture: /tests/fixtures/student-crud.html in the Vite dev server.
// All requests use this in-memory adapter; no account, database or live API is accessed.
import React from 'react';
import { createRoot } from 'react-dom/client';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import api from '../../src/api/client';
import Students from '../../src/pages/admin/Students';
import StudentProfile from '../../src/pages/admin/StudentProfile';
import validCsv from './student-import-valid.csv?raw';
import errorsCsv from './student-import-errors.csv?raw';
import legacyCsv from './student-import-legacy.csv?raw';
import '../../src/app.css';

const initialStudents = () => [
  { id: 1, name: 'Ana', last_name: 'Prueba', enrollment_no: 'PRUEBA-001', active: true, section: null, enrollments: [] },
  { id: 2, name: 'Luis', last_name: 'Prueba', enrollment_no: 'PRUEBA-002', active: false, section: null, enrollments: [] },
  { id: 3, name: 'Eva', last_name: 'Prueba', enrollment_no: 'PRUEBA-003', active: true,
    section: { name: 'A', grade: { name: '1RO SECUNDARIA' }, academic_year: { name: '2026-2027' } },
    enrollments: [{ id: 1, status: 'active', enrolled_at: '2026-09-01', section: { name: 'A', grade: { name: '1RO SECUNDARIA' }, academic_year: { name: '2026-2027' } } }] },
];
let students = initialStudents();
// Test-only alternative for browser automation environments without file-chooser support.
// Dispatches the same input change event using only the adjacent, synthetic CSV fixtures.
function selectSample(name, contents) {
  const input = document.querySelector('input[aria-label="Archivo CSV de estudiantes"]');
  if (!input || input.disabled) return;
  const transfer = new DataTransfer();
  transfer.items.add(new File([contents], name, { type: 'text/csv' }));
  input.files = transfer.files;
  input.dispatchEvent(new Event('change', { bubbles: true }));
}
const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
api.defaults.adapter = async (config) => {
  const respond = (data) => ({ data: structuredClone(data), status: 200, statusText: 'OK', headers: {}, config });
  const invalid = (field, message) => { throw { response: { status: 422, data: { message, errors: { [field]: [message] } } } }; };
  if (config.url === '/admin/sections') return respond([]);
  if (config.url.startsWith('/admin/students/import') && config.method === 'post') {
    // Deliberately minimal CSV mock for the adjacent fixtures. Real parsing is tested in PHP.
    const file = config.data.get('file');
    const [header, ...lines] = (await file.text()).trim().split(/\r?\n/);
    const headers = header.split(',');
    const numbers = lines.map((line) => line.split(',')[0]);
    const rows = lines.map((line, index) => {
      const [enrollment_no, name, last_name] = line.split(',');
      const errors = [];
      if (!name) errors.push('Nombres es obligatorio.');
      if (!last_name) errors.push('Apellidos es obligatorio.');
      if (students.some((student) => student.enrollment_no === enrollment_no)) errors.push('La matrícula ya existe en el sistema.');
      if (numbers.filter((number) => number === enrollment_no).length > 1) errors.push('La matrícula está repetida dentro del archivo.');
      return { row_number: index + 2, data: { enrollment_no, name, last_name }, errors, valid: errors.length === 0 };
    });
    const valid = rows.filter((row) => row.valid).length;
    if (config.url.endsWith('/preview')) return respond({ rows, summary: { total: rows.length, valid, invalid: rows.length - valid }, ignored_columns: headers.slice(3) });
    if (valid !== rows.length) invalid('file', 'El archivo contiene errores. No se guardó ningún estudiante. Vuelve a validar el archivo.');
    rows.forEach((row) => students.push({ id: Math.max(...students.map((student) => student.id), 0) + 1, ...row.data, active: true, section: null, enrollments: [] }));
    return respond({ message: `${rows.length} estudiantes registrados y pendientes de asignación de sección.`, imported: rows.length, pending_placement: rows.length });
  }
  if (config.url === '/admin/students' && config.method === 'get') {
    const { search = '', active } = config.params || {};
    const data = students.filter((student) => `${student.name} ${student.last_name} ${student.enrollment_no}`.toLowerCase().includes(search.toLowerCase())
      && (active === undefined || student.active === (String(active) === '1')));
    return respond({ data, current_page: 1, last_page: 1, total: data.length });
  }
  const match = config.url.match(/^\/admin\/students\/(\d+)(\/deactivate)?$/);
  if (!match) throw new Error(`Unexpected fixture request: ${config.method} ${config.url}`);
  const student = students.find((item) => item.id === Number(match[1]));
  if (!student) throw new Error('Fixture student not found');
  const body = typeof config.data === 'string' ? JSON.parse(config.data) : config.data || {};
  if (config.method === 'get') return respond(student);
  if (config.method === 'patch') {
    if (students.some((item) => item.id !== student.id && item.enrollment_no === body.enrollment_no)) invalid('enrollment_no', 'La matrícula ya existe en el sistema.');
    Object.assign(student, body);
    return respond(student);
  }
  if (config.method === 'delete') {
    if (body.confirmation !== student.enrollment_no) invalid('confirmation', 'Confirma la matrícula exacta.');
    if (student.enrollments.length) invalid('student', 'Este estudiante tiene historial académico. Utiliza Desactivar para conservarlo.');
    students = students.filter((item) => item.id !== student.id);
    return respond({ message: 'Estudiante eliminado definitivamente.' });
  }
  if (match[2] && config.method === 'post') {
    student.active = false;
    student.enrollments.forEach((enrollment) => { enrollment.status = 'withdrawn'; });
    return respond({ message: 'Estudiante dado de baja; su historial fue conservado.' });
  }
  throw new Error(`Unexpected fixture method: ${config.method}`);
};

createRoot(document.getElementById('root')).render(
  <QueryClientProvider client={queryClient}>
    <MemoryRouter initialEntries={['/admin/students']}>
      <div className="min-h-screen bg-slate-50 p-6">
        <aside className="mb-6 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
          <span>Prueba aislada: datos ficticios en memoria. Recargar restaura todo.</span>
          <button onClick={() => { students = initialStudents(); queryClient.invalidateQueries(); }} className="font-bold">Restablecer datos de prueba</button>
        </aside>
        <aside className="fixed bottom-3 left-3 z-[60] flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
          <strong>CSV sintéticos (solo prueba)</strong>
          <button onClick={() => selectSample('valido.csv', validCsv)}>Cargar ejemplo válido</button>
          <button onClick={() => selectSample('errores.csv', errorsCsv)}>Cargar ejemplo con errores</button>
          <button onClick={() => selectSample('antiguo.csv', legacyCsv)}>Cargar ejemplo antiguo</button>
        </aside>
        <Routes>
          <Route path="/admin/students" element={<Students />} />
          <Route path="/admin/students/:id" element={<StudentProfile />} />
        </Routes>
      </div>
    </MemoryRouter>
  </QueryClientProvider>,
);
