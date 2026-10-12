export const COMPETENCY_KEYS = Object.freeze(['c1', 'c2', 'c3', 'c4']);

export function competencyId(key) {
  const index = COMPETENCY_KEYS.indexOf(key);
  if (index === -1) throw new Error('Competencia desconocida');
  return index + 1;
}

// An incomplete activity is pending, never averaged as three scores or filled with zero.
export function activityAverage(...scores) {
  if (scores.length !== 4 || scores.some(value => value === '' || value == null || !Number.isFinite(Number(value)) || Number(value) < 0 || Number(value) > 100)) return null;
  return scores.reduce((sum, value) => sum + Number(value), 0) / 4;
}
