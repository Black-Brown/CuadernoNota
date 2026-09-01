export function groupAssignmentCourses(courses) {
  const groups = new Map();
  for (const course of courses) {
    const key = String(course.subject_id ?? course.subject_name);
    if (!groups.has(key)) groups.set(key, { key, name: course.subject_name, courses: [] });
    groups.get(key).courses.push(course);
  }
  return [...groups.values()].map((group) => ({
    ...group,
    courses: group.courses.sort((a, b) => (
      String(a.grade_name).localeCompare(String(b.grade_name), 'es', { numeric: true })
      || String(a.section_name).localeCompare(String(b.section_name), 'es', { numeric: true })
      || String(a.academic_year_name).localeCompare(String(b.academic_year_name), 'es')
    )),
  })).sort((a, b) => String(a.name).localeCompare(String(b.name), 'es'));
}

export function groupSubjectSections(courses) {
  const groups = new Map();
  for (const course of courses) {
    // The compound key prevents Section A from one grade/year being mixed with another.
    const key = `${course.academic_year_name}\u0000${course.grade_name}`;
    if (!groups.has(key)) groups.set(key, { key, year: course.academic_year_name, grade: course.grade_name, courses: [] });
    groups.get(key).courses.push(course);
  }
  return [...groups.values()].map((group) => ({
    ...group,
    courses: group.courses.sort((a, b) => String(a.section_name).localeCompare(String(b.section_name), 'es', { numeric: true })),
  })).sort((a, b) => String(b.year).localeCompare(String(a.year), 'es') || String(a.grade).localeCompare(String(b.grade), 'es', { numeric: true }));
}
