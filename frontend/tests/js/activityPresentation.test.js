import test from 'node:test';
import assert from 'node:assert/strict';
import { BASE_ACTIVITY_PRESENTATION, getActivityPresentation } from '../../resources/js/utils/activityPresentation.js';

test('six fixed activities use the icons from master even with legacy generic values', () => {
  const expected = { Proyectos: 'account_tree', Examen: 'assignment', Tareas: 'edit_note', Ensayo: 'article', 'Producción en aula': 'school', Diagnósticas: 'fact_check' };
  assert.equal(Object.keys(BASE_ACTIVITY_PRESENTATION).length, 6);
  for (const [name, icon] of Object.entries(expected)) {
    for (const storedIcon of [null, 'assignment', 'add_task']) {
      assert.equal(getActivityPresentation({ name, icon: storedIcon }).icon, icon);
    }
    assert.ok(getActivityPresentation({ name }).desc);
    assert.ok(getActivityPresentation({ name }).color);
  }
});

test('custom icons and custom activities remain supported', () => {
  assert.equal(getActivityPresentation({ name: 'Proyectos', icon: 'science' }).icon, 'science');
  assert.deepEqual(getActivityPresentation({ name: 'Presentación', icon: 'mic', description: 'Exposición oral' }), {
    icon: 'mic', color: 'bg-slate-50 text-slate-600', desc: 'Exposición oral',
  });
  assert.equal(getActivityPresentation({ name: 'Otra' }).icon, 'add_task');
});
