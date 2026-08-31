import test from 'node:test';
import assert from 'node:assert/strict';
import { groupAssignmentCourses, groupSubjectSections } from '../../resources/js/utils/groupAssignmentCourses.js';

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
  assert.deepEqual(groups.find((group) => group.key === '2026-2027\u00001ro').courses.map((course) => course.section_name), ['A', 'B']);
  assert.deepEqual(groups.find((group) => group.key === '2026-2027\u00002do').courses.map((course) => course.section_name), ['A']);
});
