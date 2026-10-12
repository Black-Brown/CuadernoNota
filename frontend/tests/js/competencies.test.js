import test from 'node:test';
import assert from 'node:assert/strict';
import { COMPETENCY_KEYS, competencyId, activityAverage } from '../../src/utils/competencies.js';

test('cuatro columnas se guardan en competencias distintas, incluida C4', () => {
  assert.deepEqual(COMPETENCY_KEYS.map(competencyId), [1, 2, 3, 4]);
  assert.throws(() => competencyId('c5'));
});
test('promedio exige cuatro notas y cada una pesa 25%', () => {
  assert.equal(activityAverage(80, 100, 95, 65), 85);
  assert.equal(activityAverage(100, 100, 100, 0), 75);
  assert.equal(activityAverage(0, 0, 0, 0), 0);
  assert.equal(activityAverage('80', '90', '100', '90'), 90);
  for (const missing of [null, undefined, '', NaN, -1, 101]) {
    assert.equal(activityAverage(80, 90, 100, missing), null);
  }
  assert.equal(activityAverage(80, 90, 100), null);
});
