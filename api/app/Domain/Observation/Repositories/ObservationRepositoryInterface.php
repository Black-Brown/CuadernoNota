<?php

declare(strict_types=1);

namespace App\Domain\Observation\Repositories;

interface ObservationRepositoryInterface
{
    /**
     * Devuelve todas las observaciones de un estudiante, ordenadas por fecha desc.
     *
     * @return array [['id', 'date', 'type', 'description', 'teacher_name'], ...]
     */
    public function findByStudent(int $studentId): array;

    /**
     * Devuelve las observaciones de un workspace: sección + asignatura + período.
     */
    public function findByWorkspace(int $sectionId, int $subjectId, int $periodId): array;

    /**
     * Devuelve las observaciones de un estudiante dentro de un workspace.
     */
    public function findByWorkspaceStudent(int $sectionId, int $subjectId, int $periodId, int $studentId): array;

    /**
     * Persiste una nueva observación.
     *
     * @param array $data Debe contener: student_id, user_id, date, type, description
     */
    public function save(array $data): void;

    /**
     * Actualiza una observación existente.
     */
    public function update(int $id, array $data): void;

    /**
     * Elimina una observación existente.
     */
    public function delete(int $id): void;
}
