export function distributeActivityWeights(activities) {
  const active = activities.filter(a => a.active);
  if (!active.length) return {};
  const base = Math.floor(10000 / active.length);
  const remainder = 10000 % active.length;
  return Object.fromEntries(active.map((a, index) => [a.id, ((base + (index < remainder ? 1 : 0)) / 100).toFixed(2)]));
}

export function activityWeightSummary(activities, weights) {
  const active = activities.filter(a => a.active);
  const values = active.map(a => weights[a.id]);
  const validValues = values.every(v => v !== '' && v != null && Number.isFinite(Number(v)) && Number(v) > 0 && Number(v) <= 100 && Math.abs(Number(v) * 100 - Math.round(Number(v) * 100)) < 0.000001);
  const total = values.reduce((sum, v) => sum + (Number.isFinite(Number(v)) ? Math.round(Number(v) * 100) : 0), 0) / 100;
  return { total, valid: active.length > 0 && validValues && total === 100 };
}
