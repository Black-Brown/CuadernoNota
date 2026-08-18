<?php

use App\Infrastructure\Http\Controllers\AuthController;
use App\Infrastructure\Http\Controllers\Docente\ActivityController;
use App\Infrastructure\Http\Controllers\Docente\AttendanceController;
use App\Infrastructure\Http\Controllers\Docente\DashboardController;
use App\Infrastructure\Http\Controllers\Docente\GradeController;
use App\Infrastructure\Http\Controllers\Docente\ObservationController;
use App\Infrastructure\Http\Controllers\Docente\RiskController;
use Illuminate\Support\Facades\Route;

// ── Rutas públicas ────────────────────────────────────────────────────────────
Route::post('/auth/login',           [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

// ── Rutas autenticadas ────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // ── Módulo Docente ────────────────────────────────────────────────────────
    Route::middleware('role:teacher')->prefix('docente')->name('docente.')->group(function () {
        // Grades
        Route::get('/courses', [GradeController::class, 'courses'])->name('courses');
        Route::get('/grades/summary/{sectionId}/{subjectId}', [GradeController::class, 'gradebookSummary'])->name('grades.summary');
        Route::get('/activities/{subjectId}', [GradeController::class, 'activitiesBySubject'])->name('activities.by-subject');
        Route::get('/grades/activity/{activityId}/{periodId}', [GradeController::class, 'activityGrades'])->name('grades.activity');
        Route::post('/grades/activity-score', [GradeController::class, 'store'])->name('grades.activity-score');
        Route::get('/grades/period/{subjectId}/{periodId}', [GradeController::class, 'periodGrades'])->name('grades.period');
        Route::post('/grades/submit', [GradeController::class, 'submit'])->name('grades.submit');
        Route::post('/grades/recovery', [GradeController::class, 'recovery'])->name('grades.recovery');

        // Attendance
        Route::get('/attendance/{sectionId}/{date}', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::patch('/attendance/{id}/excuse', [AttendanceController::class, 'updateExcuse'])->name('attendance.excuse');

        // Activities (gestión)
        Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
        Route::patch('/activities/{id}', [ActivityController::class, 'update'])->name('activities.update');

        // Observations
        Route::get('/observations/course/{sectionId}/{subjectId}', [ObservationController::class, 'course'])->name('observations.course');
        Route::get('/observations/course/{sectionId}/{subjectId}/students/{studentId}', [ObservationController::class, 'student'])->name('observations.student-workspace');
        Route::get('/observations/{studentId}', [ObservationController::class, 'index'])->name('observations.index');
        Route::post('/observations', [ObservationController::class, 'store'])->name('observations.store');
        Route::patch('/observations/{id}', [ObservationController::class, 'update'])->name('observations.update');
        Route::delete('/observations/{id}', [ObservationController::class, 'destroy'])->name('observations.destroy');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/{sectionId}/{subjectId}', [DashboardController::class, 'bySubject'])->name('dashboard.by-subject');
        Route::get('/current-period', [DashboardController::class, 'currentPeriod'])->name('current-period');
        Route::get('/periods',        [DashboardController::class, 'periods'])->name('periods');

        // Students at Risk
        Route::get('/risk', [RiskController::class, 'index'])->name('risk.index');
        Route::get('/risk/{sectionId}/{subjectId}', [RiskController::class, 'course'])->name('risk.course');
        Route::get('/risk/{sectionId}/{subjectId}/students/{studentId}', [RiskController::class, 'student'])->name('risk.student');
    });
});
