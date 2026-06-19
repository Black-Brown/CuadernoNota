<?php

namespace App\Infrastructure\Http\Controllers\Docente;

use App\Application\Observation\CreateObservation;
use App\Application\Observation\GetObservations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ObservationController extends Controller
{
    public function __construct(
        private readonly GetObservations    $getObservations,
        private readonly CreateObservation  $createObservation,
    ) {}

    public function index(int $studentId): JsonResponse
    {
        $observations = $this->getObservations->execute($studentId);

        return response()->json(['observations' => $observations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'  => 'required|integer|exists:students,id',
            'date'        => 'required|date_format:Y-m-d',
            'type'        => 'required|string|max:100',
            'description' => 'required|string',
        ]);

        $validated['user_id'] = Auth::id() ?? 1;

        $this->createObservation->execute($validated);

        return response()->json(['message' => 'Observación registrada.'], 201);
    }
}
