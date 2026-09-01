<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

// Domain interfaces
use App\Domain\Grade\Repositories\ActivityRepositoryInterface;
use App\Domain\Grade\Repositories\ActivityScoreRepositoryInterface;
use App\Domain\Grade\Repositories\FinalGradeRepositoryInterface;
use App\Domain\Grade\Repositories\PeriodGradeRepositoryInterface;
use App\Domain\Grade\Repositories\TeacherRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceRepositoryInterface;
use App\Domain\Observation\Repositories\ObservationRepositoryInterface;
use App\Domain\Promotion\Repositories\PromotionRepositoryInterface;

// Eloquent implementations
use App\Infrastructure\Persistence\EloquentActivityRepository;
use App\Infrastructure\Persistence\EloquentActivityScoreRepository;
use App\Infrastructure\Persistence\EloquentAttendanceRepository;
use App\Infrastructure\Persistence\EloquentFinalGradeRepository;
use App\Infrastructure\Persistence\EloquentObservationRepository;
use App\Infrastructure\Persistence\EloquentPeriodGradeRepository;
use App\Infrastructure\Persistence\EloquentPromotionRepository;
use App\Infrastructure\Persistence\EloquentTeacherRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityScoreRepositoryInterface::class, EloquentActivityScoreRepository::class);
        $this->app->bind(PeriodGradeRepositoryInterface::class,   EloquentPeriodGradeRepository::class);
        $this->app->bind(FinalGradeRepositoryInterface::class,    EloquentFinalGradeRepository::class);
        $this->app->bind(AttendanceRepositoryInterface::class,    EloquentAttendanceRepository::class);
        $this->app->bind(PromotionRepositoryInterface::class,     EloquentPromotionRepository::class);
        $this->app->bind(TeacherRepositoryInterface::class,       EloquentTeacherRepository::class);
        $this->app->bind(ActivityRepositoryInterface::class,      EloquentActivityRepository::class);
        $this->app->bind(ObservationRepositoryInterface::class,   EloquentObservationRepository::class);
    }

    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
