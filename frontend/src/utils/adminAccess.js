export const ROLE_ROUTES = Object.freeze({
  teacher: '/docente/dashboard',
  coordinator: '/modulo-coordinador-proximamente',
  admin: '/admin/dashboard',
});

export const ADMIN_CREATABLE_ROLES = Object.freeze({
  teacher: 'Docente',
  admin: 'Administrador',
});

export const ROLE_LABELS = Object.freeze({
  ...ADMIN_CREATABLE_ROLES,
  coordinator: 'Coordinador · Próximamente',
});

export function routeForRole(role) {
  return ROLE_ROUTES[role] || '/login';
}
