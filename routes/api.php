<?php

use App\Infrastructure\Http\Controllers\Admin\TeacherAssignmentController;
use App\Infrastructure\Http\Controllers\Admin\StudentPlacementController;
use App\Infrastructure\Http\Controllers\Admin\AcademicCatalogController;
use App\Infrastructure\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Infrastructure\Http\Controllers\Admin\UserController as AdminUserController;
use App\Infrastructure\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Infrastructure\Http\Controllers\Admin\GradeReviewController;
use App\Infrastructure\Http\Controllers\Admin\PromotionController;
use App\Infrastructure\Http\Controllers\Admin\ReportController;
use App\Infrastructure\Http\Controllers\Admin\SystemDataController;
use App\Infrastructure\Http\Controllers\AuthController;
use App\Infrastructure\Http\Controllers\GoogleAuthController;
use App\Infrastructure\Http\Controllers\Docente\ActivityController;
use App\Infrastructure\Http\Controllers\Docente\AttendanceController;
use App\Infrastructure\Http\Controllers\Docente\DashboardController;
use App\Infrastructure\Http\Controllers\Docente\GradeController;
use App\Infrastructure\Http\Controllers\Docente\ObservationController;
use App\Infrastructure\Http\Controllers\Docente\RiskController;
use Illuminate\Support\Facades\Route;

// ── Rutas públicas ────────────────────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

// Autenticación con Google
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
Route::post('/auth/google/exchange', [GoogleAuthController::class, 'exchange']);

// ── Rutas autenticadas ────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // ── Módulo Administrativo ─────────────────────────────────────────────────
    Route::middleware(['role:admin', 'audit.admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
            Route::get('/system/reset-data/preview', [SystemDataController::class, 'preview'])->name('system.reset-data.preview');
            Route::post('/system/reset-data', [SystemDataController::class, 'reset'])->name('system.reset-data');

            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

            Route::get('/students', [AdminStudentController::class, 'index'])->name('students.index');
            Route::post('/students', [AdminStudentController::class, 'store'])->name('students.store');
            Route::post('/students/import/preview', [AdminStudentController::class, 'previewImport'])->name('students.import.preview');
            Route::post('/students/import', [AdminStudentController::class, 'import'])->name('students.import');
            Route::get('/student-placements/pending', [StudentPlacementController::class, 'index'])->name('student-placements.pending');
            Route::post('/student-placements', [StudentPlacementController::class, 'store'])->name('student-placements.store');
            Route::get('/students/{student}', [AdminStudentController::class, 'show'])->name('students.show');
            Route::patch('/students/{student}', [AdminStudentController::class, 'update'])->name('students.update');
            Route::post('/students/{student}/enrollments', [AdminStudentController::class, 'enroll'])->name('students.enroll');
            Route::post('/students/{student}/deactivate', [AdminStudentController::class, 'deactivate'])->name('students.deactivate');

            Route::get('/grade-reviews', [GradeReviewController::class, 'index'])->name('grade-reviews.index');
            Route::get('/grade-reviews/{sectionId}/{subjectId}/{periodId}', [GradeReviewController::class, 'show'])->name('grade-reviews.show');
            Route::post('/grade-reviews/decision', [GradeReviewController::class, 'decide'])->name('grade-reviews.decide');

            Route::get('/promotions/candidates', [PromotionController::class, 'candidates'])->name('promotions.candidates');
            Route::post('/promotions/bulk-decision', [PromotionController::class, 'decideMany'])->name('promotions.bulk-decision');
            Route::post('/promotions/{studentEnrollment}/decision', [PromotionController::class, 'decide'])->name('promotions.decide');

            Route::get('/reports/academic', [ReportController::class, 'academic'])->name('reports.academic');
            Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
            Route::get('/audit-logs', [ReportController::class, 'audits'])->name('audit-logs.index');
            Route::post('/backups', [ReportController::class, 'backup'])->name('backups.store');

            Route::get('/academic-years', [AcademicCatalogController::class, 'years'])->name('academic-years.index');
            Route::post('/academic-years', [AcademicCatalogController::class, 'storeYear'])->name('academic-years.store');
            Route::patch('/academic-years/{academicYear}', [AcademicCatalogController::class, 'updateYear'])->name('academic-years.update');
            Route::delete('/academic-years/{academicYear}', [AcademicCatalogController::class, 'destroyYear'])->name('academic-years.destroy');
            Route::get('/academic-years/{academicYear}/periods', [AcademicCatalogController::class, 'periods'])->name('periods.index');
            Route::post('/academic-years/{academicYear}/periods', [AcademicCatalogController::class, 'storePeriod'])->name('periods.store');
            Route::patch('/periods/{period}', [AcademicCatalogController::class, 'updatePeriod'])->name('periods.update');
            Route::delete('/periods/{period}', [AcademicCatalogController::class, 'destroyPeriod'])->name('periods.destroy');
            Route::get('/periods/{period}/activity-summary', [AcademicCatalogController::class, 'periodActivitySummary'])->name('periods.activity-summary');

            Route::get('/grades', [AcademicCatalogController::class, 'grades'])->name('grades.index');
            Route::post('/grades', [AcademicCatalogController::class, 'storeGrade'])->name('grades.store');
            Route::patch('/grades/{grade}', [AcademicCatalogController::class, 'updateGrade'])->name('grades.update');
            Route::delete('/grades/{grade}', [AcademicCatalogController::class, 'destroyGrade'])->name('grades.destroy');
            Route::get('/grades/{grade}/deletion-check', [AcademicCatalogController::class, 'gradeDeletionCheck'])->name('grades.deletion-check');
            Route::patch('/grades/{grade}/deactivate', [AcademicCatalogController::class, 'deactivateGrade'])->name('grades.deactivate');
            Route::patch('/grades/{grade}/reactivate', [AcademicCatalogController::class, 'reactivateGrade'])->name('grades.reactivate');
            Route::get('/sections', [AcademicCatalogController::class, 'sections'])->name('sections.index');
            Route::post('/sections', [AcademicCatalogController::class, 'storeSection'])->name('sections.store');
            Route::patch('/sections/{section}', [AcademicCatalogController::class, 'updateSection'])->name('sections.update');
            Route::delete('/sections/{section}', [AcademicCatalogController::class, 'destroySection'])->name('sections.destroy');

            Route::get('/subjects', [AcademicCatalogController::class, 'subjects'])->name('subjects.index');
            Route::post('/subjects', [AcademicCatalogController::class, 'storeSubject'])->name('subjects.store');
            Route::patch('/subjects/{subject}', [AcademicCatalogController::class, 'updateSubject'])->name('subjects.update');
            Route::delete('/subjects/{subject}', [AcademicCatalogController::class, 'deactivateSubject'])->name('subjects.destroy');

            Route::get('/activity-templates', [AcademicCatalogController::class, 'templates'])->name('activity-templates.index');
            Route::post('/activity-templates', [AcademicCatalogController::class, 'storeTemplate'])->name('activity-templates.store');
            Route::patch('/activity-templates/{activityTemplate}', [AcademicCatalogController::class, 'updateTemplate'])->name('activity-templates.update');
            Route::delete('/activity-templates/{activityTemplate}', [AcademicCatalogController::class, 'deactivateTemplate'])->name('activity-templates.destroy');

            Route::get(
                '/teacher-assignments/options',
                [TeacherAssignmentController::class, 'options']
            )->name('teacher-assignments.options');

            Route::get(
                '/teacher-assignments',
                [TeacherAssignmentController::class, 'index']
            )->name('teacher-assignments.index');

            Route::post(
                '/teachers',
                [TeacherAssignmentController::class, 'storeTeacher']
            )->name('teachers.store');

            Route::post(
                '/teacher-assignments',
                [TeacherAssignmentController::class, 'store']
            )->name('teacher-assignments.store');

            Route::patch(
                '/teacher-assignments/{teacherAssignment}',
                [TeacherAssignmentController::class, 'update']
            )->name('teacher-assignments.update');

            Route::delete(
                '/teacher-assignments/{teacherAssignment}',
                [TeacherAssignmentController::class, 'destroy']
            )->name('teacher-assignments.destroy');
        });

    // ── Módulo Docente ────────────────────────────────────────────────────────
    Route::middleware('role:teacher')
        ->prefix('docente')
        ->name('docente.')
        ->group(function () {
            // Calificaciones
            Route::get(
                '/courses',
                [GradeController::class, 'courses']
            )->name('courses');

            Route::get(
                '/grades/summary/{sectionId}/{subjectId}',
                [GradeController::class, 'gradebookSummary']
            )->name('grades.summary');

            Route::get(
                '/activities/{subjectId}',
                [GradeController::class, 'activitiesBySubject']
            )->name('activities.by-subject');

            Route::get(
                '/grades/activity/{activityId}/{periodId}',
                [GradeController::class, 'activityGrades']
            )->name('grades.activity');

            Route::post(
                '/grades/activity-score',
                [GradeController::class, 'store']
            )->name('grades.activity-score');

            Route::get(
                '/grades/period/{subjectId}/{periodId}',
                [GradeController::class, 'periodGrades']
            )->name('grades.period');

            Route::post(
                '/grades/submit',
                [GradeController::class, 'submit']
            )->name('grades.submit');

            Route::post(
                '/grades/recovery',
                [GradeController::class, 'recovery']
            )->name('grades.recovery');

            // Asistencia
            Route::get(
                '/attendance/{sectionId}/{date}',
                [AttendanceController::class, 'index']
            )->name('attendance.index');

            Route::post(
                '/attendance',
                [AttendanceController::class, 'store']
            )->name('attendance.store');

            Route::patch(
                '/attendance/{id}/excuse',
                [AttendanceController::class, 'updateExcuse']
            )->name('attendance.excuse');

            // Actividades
            Route::post(
                '/activities',
                [ActivityController::class, 'store']
            )->name('activities.store');

            Route::patch(
                '/activities/{id}',
                [ActivityController::class, 'update']
            )->name('activities.update');

            // Observaciones
            Route::get(
                '/observations/course/{sectionId}/{subjectId}',
                [ObservationController::class, 'course']
            )->name('observations.course');

            Route::get(
                '/observations/course/{sectionId}/{subjectId}/students/{studentId}',
                [ObservationController::class, 'student']
            )->name('observations.student-workspace');

            Route::get(
                '/observations/{studentId}',
                [ObservationController::class, 'index']
            )->name('observations.index');

            Route::post(
                '/observations',
                [ObservationController::class, 'store']
            )->name('observations.store');

            Route::patch(
                '/observations/{id}',
                [ObservationController::class, 'update']
            )->name('observations.update');

            Route::delete(
                '/observations/{id}',
                [ObservationController::class, 'destroy']
            )->name('observations.destroy');

            // Dashboard
            Route::get(
                '/dashboard',
                [DashboardController::class, 'index']
            )->name('dashboard.index');

            Route::get(
                '/dashboard/{sectionId}/{subjectId}',
                [DashboardController::class, 'bySubject']
            )->name('dashboard.by-subject');

            Route::get(
                '/current-period',
                [DashboardController::class, 'currentPeriod']
            )->name('current-period');

            Route::get(
                '/periods',
                [DashboardController::class, 'periods']
            )->name('periods');

            // Estudiantes en riesgo
            Route::get(
                '/risk',
                [RiskController::class, 'index']
            )->name('risk.index');

            Route::get(
                '/risk/{sectionId}/{subjectId}',
                [RiskController::class, 'course']
            )->name('risk.course');

            Route::get(
                '/risk/{sectionId}/{subjectId}/students/{studentId}',
                [RiskController::class, 'student']
            )->name('risk.student');
        });
});
