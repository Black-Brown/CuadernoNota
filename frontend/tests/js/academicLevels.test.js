import test from 'node:test';
import assert from 'node:assert/strict';
import { academicLevel, groupAcademicLevels } from '../../src/utils/academicLevels.js';

test('normalizes explicit levels without guessing from grade names', () => {
  assert.equal(academicLevel(' PRIMARIA '), 'Primaria');
  assert.equal(academicLevel('secundaria'), 'Secundaria');
  assert.equal(academicLevel(null), 'Sin clasificar');
  const grouped = groupAcademicLevels([{ id: 1, grade_name: '1RO SECUNDARIA' }]);
  assert.equal(grouped.find(g => g.level === 'Secundaria').rows.length, 0);
  assert.equal(grouped.find(g => g.level === 'Sin clasificar').rows.length, 1);
});

test('keeps both main workspaces visible when empty and preserves other levels', () => {
  assert.deepEqual(groupAcademicLevels([]).map(g => g.level), ['Primaria', 'Secundaria']);
  const groups = groupAcademicLevels([{ id: 1, level: 'INICIAL' }, { id: 2, grade: { level: 'PRIMARIA' } }, { id: 3, grade_level: 'Secundaria' }]);
  assert.deepEqual(groups.map(g => [g.level, g.rows.map(r => r.id)]), [['Primaria', [2]], ['Secundaria', [3]], ['Inicial', [1]]]);
});

test('custom nested assignment accessor isolates courses', () => {
  const groups = groupAcademicLevels([{ id: 1, course_offering: { section: { grade: { level: 'Primaria' } } } }], a => a.course_offering?.section?.grade?.level);
  assert.deepEqual(groups[0].rows.map(a => a.id), [1]);
  assert.equal(groups[1].rows.length, 0);
});
