import test from 'node:test';
import assert from 'node:assert/strict';
import { distributeActivityWeights, activityWeightSummary } from '../../src/utils/activityWeights.js';

test('reparto equitativo suma exactamente 100 incluso con seis actividades', () => {
  const activities = Array.from({ length: 6 }, (_, id) => ({ id, active: true }));
  const weights = distributeActivityWeights(activities);
  assert.deepEqual(activityWeightSummary(activities, weights), { total: 100, valid: true });
  assert.equal(weights[0], '16.67');
  assert.equal(weights[5], '16.66');
});
test('el total excluye inactivas y rechaza vacíos, ceros y decimales excesivos', () => {
  const activities = [{ id: 1, active: true }, { id: 2, active: true }, { id: 3, active: false }];
  assert.equal(activityWeightSummary(activities, { 1: 60, 2: 40, 3: 100 }).valid, true);
  for (const values of [{ 1: 100, 2: '' }, { 1: 100, 2: 0 }, { 1: 60, 2: 30 }, { 1: 60.001, 2: 39.999 }]) {
    assert.equal(activityWeightSummary(activities, values).valid, false);
  }
  assert.equal(activityWeightSummary([], {}).valid, false);
});
