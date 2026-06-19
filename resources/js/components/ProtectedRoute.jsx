import { Navigate } from 'react-router-dom';
import useAuthStore from '../store/authStore';

const roleDefaults = {
  teacher:     '/docente/dashboard',
  coordinator: '/coordinador/dashboard',
  admin:       '/admin/dashboard',
};

export default function ProtectedRoute({ children, allowedRoles }) {
  const { token, user } = useAuthStore();

  if (!token) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && user && !allowedRoles.includes(user.role)) {
    const fallback = roleDefaults[user.role] || '/login';
    return <Navigate to={fallback} replace />;
  }

  return children;
}
