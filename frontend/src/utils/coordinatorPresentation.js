export const normalizeSearch = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');

export function filterCoordinatorCourses(sections, year, search) {
  const term = normalizeSearch(search).trim();
  return sections.filter(s => (!year || String(s.academic_year_id) === String(year)) && normalizeSearch(`${s.grade_name} ${s.name} ${s.shift} ${s.year_name}`).includes(term));
}

export function pageRows(rows, requestedPage, size = 20) {
  const totalPages = Math.max(1, Math.ceil(rows.length / size));
  const page = Math.min(totalPages, Math.max(1, requestedPage));
  return { page, totalPages, rows: rows.slice((page - 1) * size, page * size), total: rows.length };
}
