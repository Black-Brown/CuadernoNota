import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Login from './pages/auth/Login';
import DocenteDashboard from './pages/docente/Dashboard';
import Courses from './pages/docente/Courses';
import Workspace from './pages/docente/Workspace';
import ActivityGrades from './pages/docente/ActivityGrades.jsx';
import Grades from './pages/docente/Grades.jsx';
import Observations from './pages/docente/Observations.jsx';
import ModuleComingSoon from './pages/docente/ModuleComingSoon.jsx';
import RiskOverview from './pages/docente/RiskOverview.jsx';
import RiskCourse from './pages/docente/RiskCourse.jsx';
import RiskStudent from './pages/docente/RiskStudent.jsx';
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
                  <Route path="risk" element={<RiskOverview />} />
                  <Route path="risk/:sectionId/:subjectId" element={<RiskCourse />} />
                  <Route path="risk/:sectionId/:subjectId/students/:studentId" element={<RiskStudent />} />
                  <Route path="courses/:sectionId/:subjectId" element={<Workspace />} />
                  <Route path="courses/:sectionId/:subjectId/activity/:activityId" element={<ActivityGrades />} />
                  <Route
                    path="attendance"
                    element={
                      <ModuleComingSoon
                        title="Asistencia en produccion"
                        icon="fact_check"
                        description="El registro general de asistencia aun no esta activo desde esta pantalla. Por ahora, continua trabajando desde tus cursos asignados."
                      />
                    }
                  />
                  <Route path="observations" element={<Observations />} />
                  <Route path="observations/:sectionId/:subjectId" element={<Observations />} />
                  <Route path="*" element={<ModuleComingSoon title="Pagina en produccion" />} />
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
