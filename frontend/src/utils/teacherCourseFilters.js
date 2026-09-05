export function normalizeCourseText(value = '') {
  return String(value)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();
}

export function filterTeacherCourses(courses = [], filters = {}) {
  const search = normalizeCourseText(filters.search);
  const grade = filters.grade || '';
  const section = filters.section || '';
  const subject = filters.subject || '';

  return courses.filter((course) => {
    if (grade && course.grade_name !== grade) {
      return false;
    }

    if (section && course.section_name !== section) {
      return false;
    }

    if (subject && course.subject_name !== subject) {
      return false;
    }

    if (!search) {
      return true;
    }

    return normalizeCourseText([
      course.grade_name,
      course.section_name,
      course.subject_name,
      course.year_label,
    ].join(' ')).includes(search);
  });
}

export function getCourseFilterOptions(courses = [], selectedGrade = '') {
  const grades = [];
  const sections = [];
  const subjects = [];

  courses.forEach((course) => {
    if (course.grade_name && !grades.includes(course.grade_name)) {
      grades.push(course.grade_name);
    }

    if (
      course.section_name
      && (!selectedGrade || course.grade_name === selectedGrade)
      && !sections.includes(course.section_name)
    ) {
      sections.push(course.section_name);
    }

    if (course.subject_name && !subjects.includes(course.subject_name)) {
      subjects.push(course.subject_name);
    }
  });

  return { grades, sections, subjects };
}
