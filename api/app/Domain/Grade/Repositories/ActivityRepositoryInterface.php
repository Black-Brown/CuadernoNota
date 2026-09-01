<?php

declare(strict_types=1);

namespace App\Domain\Grade\Repositories;

interface ActivityRepositoryInterface
{
    /**
     * Lista las actividades de una asignatura con el conteo de estudiantes
     * que ya tienen al menos una nota registrada en cada una.
     *
     * @return array [['id', 'name', 'is_base', 'active', 'score_count'], ...]
     */
    public function findBySubjectWithScoreCount(int $subjectId, ?int $sectionId = null, ?int $periodId = null): array;

    /**
     * Crea una nueva actividad adicional (is_base = false).
     *
     * @param  array $data Debe contener: name, subject_id, academic_year_id, user_id
     * @return array       La actividad creada como array plano
     */
    public function save(array $data): array;

    /**
     * Activa o desactiva una actividad (toggle del campo active).
     *
     * @return array La actividad actualizada como array plano
     */
    public function toggleActive(int $activityId): array;
}
