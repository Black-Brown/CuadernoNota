function csvCell(value) {
  if (value === null || value === undefined) return '';
  const text = String(value).replace(/"/g, '""');
  return `"${text}"`;
}

function csvContent(rows) {
  return `\uFEFF${rows.map((row) => row.map(csvCell).join(',')).join('\n')}`;
}

export function downloadCsv(filename, rows) {
  if (!rows || rows.length === 0) return;

  const blob = new Blob([csvContent(rows)], {
    type: 'text/csv;charset=utf-8;',
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');

  link.href = url;
  link.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

export function safeFilename(value) {
  return String(value || 'reporte')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .toLowerCase();
}
