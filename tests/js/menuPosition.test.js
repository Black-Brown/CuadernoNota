import test from 'node:test';
import assert from 'node:assert/strict';
import { menuPosition } from '../../resources/js/utils/menuPosition.js';

test('opens below a single row independently of table height', () => {
  assert.deepEqual(menuPosition({ top: 100, bottom: 132, right: 600 }, { width: 176, height: 190 }, { width: 800, height: 600 }), { top: 136, left: 424 });
});
test('opens above when there is insufficient room below', () => {
  assert.deepEqual(menuPosition({ top: 550, bottom: 582, right: 600 }, { width: 176, height: 190 }, { width: 800, height: 600 }), { top: 356, left: 424 });
});
test('stays inside the viewport near horizontal edges', () => {
  const menu = { width: 176, height: 190 };
  const viewport = { width: 320, height: 400 };
  assert.equal(menuPosition({ top: 20, bottom: 52, right: 20 }, menu, viewport).left, 8);
  assert.equal(menuPosition({ top: 20, bottom: 52, right: 500 }, menu, viewport).left, 136);
});
