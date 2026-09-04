import test from 'node:test';
import assert from 'node:assert/strict';
import {
  ADMIN_CREATABLE_ROLES,
  ROLE_LABELS,
  routeForRole,
} from '../../src/utils/adminAccess.js';

test('cada rol entra en una ruta existente o informativa', () => {
  assert.equal(routeForRole('admin'), '/admin/dashboard');
  assert.equal(routeForRole('teacher'), '/docente/dashboard');
  assert.equal(routeForRole('coordinator'), '/modulo-coordinador-proximamente');
  assert.equal(routeForRole('unknown'), '/login');
});

test('la beta solo permite crear docentes y administradores', () => {
  assert.deepEqual(Object.keys(ADMIN_CREATABLE_ROLES), ['teacher', 'admin']);
  assert.equal(ROLE_LABELS.coordinator, 'Coordinador · Próximamente');
});
