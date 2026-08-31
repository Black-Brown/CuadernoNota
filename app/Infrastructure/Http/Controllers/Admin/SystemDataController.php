<?php

namespace App\Infrastructure\Http\Controllers\Admin;

use App\Application\Admin\ResetSystemData;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SystemDataController extends Controller
{
    public function preview(Request $request, ResetSystemData $reset): JsonResponse
    {
        return $this->respond(fn () => $reset->preview($request->user()));
    }

    public function reset(Request $request, ResetSystemData $reset): JsonResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:100'],
            'preview_token' => ['required', 'string', 'max:30000'],
        ]);

        return $this->respond(fn () => $reset->execute($request->user(), $data['confirmation'], $data['preview_token'], $request->ip()));
    }

    private function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action())->header('Cache-Control', 'no-store');
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo completar la operación. No se confirmó ningún cambio. Revisa la base de datos y solicita una nueva vista previa.',
            ], 409)->header('Cache-Control', 'no-store');
        }
    }
}
