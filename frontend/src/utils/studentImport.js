export const STUDENT_CSV_TEMPLATE = '\uFEFFMATRICULA,NOMBRES,APELLIDOS\r\n';
export const MAX_STUDENT_CSV_BYTES = 5 * 1024 * 1024;

export function validateStudentCsvFile(file) {
  if (!file) return 'Selecciona un archivo CSV.';
  if (!file.name.toLowerCase().endsWith('.csv')) return 'Selecciona un archivo con extensión CSV.';
  if (file.size === 0) return 'El archivo CSV está vacío.';
  if (file.size > MAX_STUDENT_CSV_BYTES) return 'El archivo CSV no puede superar los 5 MB.';
  return '';
}
