export const ROLE_ROUTES = Object.freeze({
  teacher: '/docente/dashboard',
  coordinator: '/coordinador/reports',
  admin: '/admin/dashboard',
});

export const ADMIN_CREATABLE_ROLES = Object.freeze({
  teacher: 'Docente',
  admin: 'Administrador',
  coordinator: 'Coordinador',
});

export const ROLE_LABELS = Object.freeze({
  ...ADMIN_CREATABLE_ROLES,
  coordinator: 'Coordinador',
});

export function routeForRole(role) {
  return ROLE_ROUTES[role] || '/login';
}
