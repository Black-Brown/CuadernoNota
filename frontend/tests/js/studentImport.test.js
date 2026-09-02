import test from 'node:test';
import assert from 'node:assert/strict';
import { STUDENT_CSV_TEMPLATE, MAX_STUDENT_CSV_BYTES, validateStudentCsvFile } from '../../src/utils/studentImport.js';

test('the template has only registration headers, a UTF-8 BOM, and no sample students', () => {
  assert.equal(STUDENT_CSV_TEMPLATE, '\uFEFFMATRICULA,NOMBRES,APELLIDOS\r\n');
});

test('validates selection, extension, emptiness and the same 5 MB limit as the API', () => {
  assert.match(validateStudentCsvFile(null), /Selecciona/);
  assert.match(validateStudentCsvFile({ name: 'listado.xlsx', size: 50 }), /extensión CSV/);
  assert.match(validateStudentCsvFile({ name: 'listado.csv', size: 0 }), /vacío/);
  assert.match(validateStudentCsvFile({ name: 'listado.csv', size: MAX_STUDENT_CSV_BYTES + 1 }), /5 MB/);
  assert.equal(validateStudentCsvFile({ name: 'listado.CSV', size: MAX_STUDENT_CSV_BYTES }), '');
  assert.equal(validateStudentCsvFile({ name: 'listado.csv', size: 50 }), '');
});
