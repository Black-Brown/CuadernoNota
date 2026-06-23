import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Login from './pages/auth/Login';
import DocenteDashboard from './pages/docente/Dashboard';
import Courses from './pages/docente/Courses';
import Workspace from './pages/docente/Workspace';
import ActivityGrades from './pages/docente/ActivityGrades.jsx';
import Grades from './pages/docente/Grades.jsx';
import ProtectedRoute from './components/ProtectedRoute';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: 1, staleTime: 30_000 },
  },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          {/* Ruta raíz → login */}
          <Route path="/" element={<Navigate to="/login" replace />} />

          {/* Pública */}
          <Route path="/login" element={<Login />} />

          {/* Protegidas: Docente */}
          <Route
            path="/docente/*"
            element={
              <ProtectedRoute allowedRoles={['teacher']}>
                <Routes>
                  <Route path="dashboard" element={<DocenteDashboard />} />
                  <Route path="courses" element={<Courses />} />
                  <Route path="grades" element={<Grades />} />
                  <Route path="grades/:sectionId/:subjectId" element={<Grades />} />
                  <Route path="courses/:sectionId/:subjectId" element={<Workspace />} />
                  <Route path="courses/:sectionId/:subjectId/activity/:activityId" element={<ActivityGrades />} />
                </Routes>
              </ProtectedRoute>
            }
          />

          {/* Fallback */}
          <Route path="*" element={<Navigate to="/login" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
