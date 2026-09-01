import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Login from './pages/auth/Login';
import DocenteDashboard from './pages/docente/Dashboard';
import Courses from './pages/docente/Courses';
import Workspace from './pages/docente/Workspace';
import ActivityGrades from './pages/docente/ActivityGrades.jsx';
import Grades from './pages/docente/Grades.jsx';
import Observations from './pages/docente/Observations.jsx';
import Attendance from './pages/docente/Attendance.jsx';
import ModuleComingSoon from './pages/docente/ModuleComingSoon.jsx';
import RiskOverview from './pages/docente/RiskOverview.jsx';
import RiskCourse from './pages/docente/RiskCourse.jsx';
import RiskStudent from './pages/docente/RiskStudent.jsx';
import ProtectedRoute from './components/ProtectedRoute';
import AdminLayout from './components/AdminLayout.jsx';
import AdminDashboard from './pages/admin/Dashboard.jsx';
import AdminUsers from './pages/admin/Users.jsx';
import AdminStudents from './pages/admin/Students.jsx';
import AdminStudentProfile from './pages/admin/StudentProfile.jsx';
import AdminCatalog from './pages/admin/Catalog.jsx';
import AdminInstitutional from './pages/admin/Institutional.jsx';
import AdminAssignments from './pages/admin/Assignments.jsx';
import AdminGradeReviews from './pages/admin/GradeReviews.jsx';
import AdminGradeReviewDetail from './pages/admin/GradeReviewDetail.jsx';
import AdminPromotions from './pages/admin/Promotions.jsx';
import AdminPromotionWorkspace from './pages/admin/PromotionWorkspace.jsx';
import AdminStudentPlacements from './pages/admin/StudentPlacements.jsx';
import AdminReports from './pages/admin/Reports.jsx';
import AdminAudit from './pages/admin/Audit.jsx';
import AdminSystem from './pages/admin/System.jsx';

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
          <Route
            path="/modulo-coordinador-proximamente"
            element={<ProtectedRoute allowedRoles={['coordinator']}><ModuleComingSoon title="Portal de coordinación próximamente" icon="admin_panel_settings" description="Tu cuenta está activa, pero el módulo de coordinación todavía no forma parte de esta beta." /></ProtectedRoute>}
          />

          <Route path="/admin" element={<ProtectedRoute allowedRoles={['admin']}><AdminLayout /></ProtectedRoute>}>
            <Route index element={<Navigate to="dashboard" replace />} />
            <Route path="dashboard" element={<AdminDashboard />} />
            <Route path="users" element={<AdminUsers />} />
            <Route path="students" element={<AdminStudents />} />
            <Route path="students/:id" element={<AdminStudentProfile />} />
            <Route path="catalog" element={<AdminCatalog />} />
            <Route path="institutional" element={<AdminInstitutional />} />
            <Route path="assignments" element={<AdminAssignments />} />
            <Route path="reviews" element={<AdminGradeReviews />} />
            <Route path="reviews/:sectionId/:subjectId/:periodId" element={<AdminGradeReviewDetail />} />
            <Route path="promotions" element={<AdminPromotions />} />
            <Route path="promotions/:sectionId" element={<AdminPromotionWorkspace />} />
            <Route path="student-placements" element={<AdminStudentPlacements />} />
            <Route path="reports" element={<AdminReports />} />
            <Route path="audit" element={<AdminAudit />} />
            <Route path="system" element={<AdminSystem />} />
          </Route>

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
                  <Route path="attendance" element={<Attendance />} />
                  <Route path="attendance/:sectionId" element={<Attendance />} />
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
