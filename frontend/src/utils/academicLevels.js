export function academicLevel(value) {
  const text = String(value ?? '').trim();
  return ({ primaria: 'Primaria', secundaria: 'Secundaria', inicial: 'Inicial' })[text.toLocaleLowerCase('es')] || text || 'Sin clasificar';
}

export function groupAcademicLevels(items, getLevel = item => item.grade_level ?? item.level ?? item.grade?.level) {
  const groups = new Map([['Primaria', []], ['Secundaria', []]]);
  for (const item of items || []) {
    const level = academicLevel(getLevel(item));
    if (!groups.has(level)) groups.set(level, []);
    groups.get(level).push(item);
  }
  return [...groups].map(([level, rows]) => ({ level, rows }));
}
