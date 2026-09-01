export function getErrorMessage(error, fallback = 'Ocurrió un error. Intenta nuevamente.') {
  const errors = error?.response?.data?.errors;
  if (errors) {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first.length > 0) return first[0];
  }
  return error?.response?.data?.message || fallback;
}
