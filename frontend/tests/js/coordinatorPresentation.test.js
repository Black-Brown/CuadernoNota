import test from 'node:test';
import assert from 'node:assert/strict';
import { filterCoordinatorCourses, pageRows } from '../../src/utils/coordinatorPresentation.js';

const sections = [
  { id: 1, academic_year_id: 10, grade_name: 'Primero', name: 'A', shift: 'Matutina', year_name: '2026-2027' },
  { id: 2, academic_year_id: 10, grade_name: 'Primero', name: 'B', shift: 'Vespertina', year_name: '2026-2027' },
  { id: 3, academic_year_id: 9, grade_name: 'Primero', name: 'A', shift: 'Matutina', year_name: '2025-2026' },
];

test('cursos se filtran por año sin mezclar secciones con el mismo nombre', () => {
  assert.deepEqual(filterCoordinatorCourses(sections, '10', '').map(s => s.id), [1, 2]);
  assert.deepEqual(filterCoordinatorCourses(sections, '9', '').map(s => s.id), [3]);
  assert.equal(filterCoordinatorCourses(sections, '', '').length, 3);
});

test('búsqueda ignora mayúsculas y acentos y respeta el año', () => {
  assert.deepEqual(filterCoordinatorCourses(sections, '10', 'MÁTUTINA').map(s => s.id), [1]);
  assert.deepEqual(filterCoordinatorCourses(sections, '10', '2025'), []);
});

test('paginación muestra 20 registros sin alterar el listado para exportación', () => {
  const rows = Array.from({ length: 44 }, (_, id) => ({ id }));
  assert.equal(pageRows(rows, 1).rows.length, 20);
  assert.equal(pageRows(rows, 2).rows[0].id, 20);
  assert.equal(pageRows(rows, 3).rows.length, 4);
  assert.equal(pageRows(rows, 99).page, 3);
  assert.equal(rows.length, 44);
  assert.deepEqual(pageRows([], 3), { page: 1, totalPages: 1, rows: [], total: 0 });
});
