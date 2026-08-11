<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Src\Academic\Teacher\Application\UseCases\ListTeachersUseCase;
use Src\Academic\Teacher\Domain\Entities\Teacher;

final class TeacherController extends Controller
{
    use AuthorizesRequests;

    public function index(ListTeachersUseCase $useCase): JsonResponse
    {
        $this->authorize('viewAny', Teacher::class);

        $teachers = array_map(
            static fn (Teacher $teacher): array => [
                'id' => $teacher->id(),
                'identityCard' => $teacher->identityCard(),
                'name' => $teacher->name(),
                'referenceWorkload' => $teacher->referenceWorkload(),
            ],
            $useCase->all(),
        );

        return response()->json(['data' => $teachers]);
    }
}
