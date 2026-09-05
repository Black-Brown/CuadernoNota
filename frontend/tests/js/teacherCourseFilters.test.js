import test from 'node:test';
import assert from 'node:assert/strict';
import {
  filterTeacherCourses,
  getCourseFilterOptions,
} from '../../src/utils/teacherCourseFilters.js';

const courses = [
  {
    grade_name: '1RO SECUNDARIA',
    section_name: 'A',
    subject_name: 'Ciencias Sociales',
    year_label: '2026-2027',
  },
  {
    grade_name: '1RO SECUNDARIA',
    section_name: 'B',
    subject_name: 'Lengua Española',
    year_label: '2026-2027',
  },
  {
    grade_name: '2DO SECUNDARIA',
    section_name: 'A',
    subject_name: 'Matemática',
    year_label: '2026-2027',
  },
];

test('busca cursos sin depender de mayúsculas ni acentos', () => {
  const result = filterTeacherCourses(courses, { search: 'espanola' });

  assert.deepEqual(result, [courses[1]]);
});

test('combina grado y sección sin mezclar secciones de otros grados', () => {
  const result = filterTeacherCourses(courses, {
    grade: '1RO SECUNDARIA',
    section: 'A',
  });

  assert.deepEqual(result, [courses[0]]);
});

test('limita las opciones de sección al grado seleccionado', () => {
  assert.deepEqual(getCourseFilterOptions(courses, '1RO SECUNDARIA'), {
    grades: ['1RO SECUNDARIA', '2DO SECUNDARIA'],
    sections: ['A', 'B'],
  });
  assert.deepEqual(getCourseFilterOptions(courses, '2DO SECUNDARIA').sections, ['A']);
});
