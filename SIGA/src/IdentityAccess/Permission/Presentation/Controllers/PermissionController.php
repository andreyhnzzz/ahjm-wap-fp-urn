<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Src\IdentityAccess\Permission\Application\DTOs\PermissionDTO;
use Src\IdentityAccess\Permission\Application\UseCases\CreatePermissionUseCase;
use Src\IdentityAccess\Permission\Presentation\Requests\PermissionRequest;

/**
 * Primary adapter: translates HTTP in, delegates to the Application
 * layer, translates the result back out. No business logic lives here.
 */
class PermissionController extends Controller
{
    public function store(PermissionRequest $request, CreatePermissionUseCase $useCase): JsonResponse
    {
        $dto = PermissionDTO::fromArray($request->validated());

        $permission = $useCase->handle($dto);

        return response()->json([
            'id' => $permission->id(),
            'name' => $permission->name(),
        ], 201);
    }
}
