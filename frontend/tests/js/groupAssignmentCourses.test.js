import test from 'node:test';
import assert from 'node:assert/strict';
import { groupAssignmentCourses, groupSubjectSections } from '../../src/utils/groupAssignmentCourses.js';

test('groups courses by subject and orders their sections naturally', () => {
  const groups = groupAssignmentCourses([
    { id: 1, subject_id: 9, subject_name: 'Matemática', grade_name: '1ro', section_name: 'B', academic_year_name: '2026-2027' },
    { id: 2, subject_id: 4, subject_name: 'Lengua', grade_name: '1ro', section_name: 'A', academic_year_name: '2026-2027' },
    { id: 3, subject_id: 9, subject_name: 'Matemática', grade_name: '1ro', section_name: 'A', academic_year_name: '2026-2027' },
  ]);
  assert.deepEqual(groups.map(({ name }) => name), ['Lengua', 'Matemática']);
  assert.deepEqual(groups[1].courses.map(({ section_name }) => section_name), ['A', 'B']);
});

test('never mixes same section letters from different grades or years', () => {
  const groups = groupSubjectSections([
    { id: 1, grade_name: '1ro', section_name: 'A', academic_year_name: '2026-2027' },
    { id: 2, grade_name: '1ro', section_name: 'B', academic_year_name: '2026-2027' },
    { id: 3, grade_name: '2do', section_name: 'A', academic_year_name: '2026-2027' },
    { id: 4, grade_name: '1ro', section_name: 'A', academic_year_name: '2027-2028' },
  ]);
  assert.equal(groups.length, 3);
  assert.deepEqual(groups.find((group) => group.year === '2026-2027' && group.grade === '1ro').courses.map((course) => course.section_name), ['A', 'B']);
  assert.deepEqual(groups.find((group) => group.year === '2026-2027' && group.grade === '2do').courses.map((course) => course.section_name), ['A']);
});

test('same subject in two levels opens separate selections', () => {
  const base = { subject_id: 1, subject_name: 'Lengua', grade_name: 'Primero', academic_year_name: '2026-2027' };
  const groups = groupAssignmentCourses([{ ...base, id: 1, grade_level: 'PRIMARIA' }, { ...base, id: 2, grade_level: ' primaria ' }, { ...base, id: 3, grade_level: 'SECUNDARIA' }]);
  assert.equal(groups.length, 2);
  assert.deepEqual(groups.map(g => g.courses.length).sort(), [1, 2]);
  assert.notEqual(groups[0].key, groups[1].key);
});

test('grade and year IDs prevent mixing identically named groups', () => {
  const base = { grade_name: 'Primero', section_name: 'A', academic_year_name: '2026-2027' };
  const groups = groupSubjectSections([{ ...base, id: 1, grade_id: 1, academic_year_id: 1 }, { ...base, id: 2, grade_id: 2, academic_year_id: 1 }, { ...base, id: 3, grade_id: 1, academic_year_id: 2 }]);
  assert.equal(groups.length, 3);
});
