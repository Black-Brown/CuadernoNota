import { Navigate } from 'react-router-dom';
import useAuthStore from '../store/authStore';
import { routeForRole } from '../utils/adminAccess';

export default function ProtectedRoute({ children, allowedRoles }) {
  const { token, user } = useAuthStore();

  if (!token) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && user && !allowedRoles.includes(user.role)) {
    const fallback = routeForRole(user.role);
    return <Navigate to={fallback} replace />;
  }

  return children;
}
