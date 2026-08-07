<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Src\IdentityAccess\Role\Application\DTOs\RoleDTO;
use Src\IdentityAccess\Role\Application\UseCases\CreateRoleUseCase;
use Src\IdentityAccess\Role\Presentation\Requests\RoleRequest;

/**
 * Primary adapter: translates HTTP in, delegates to the Application
 * layer, translates the result back out. No business logic lives here.
 */
class RoleController extends Controller
{
    public function store(RoleRequest $request, CreateRoleUseCase $useCase): JsonResponse
    {
        $dto = RoleDTO::fromArray($request->validated());

        $role = $useCase->handle($dto);

        return response()->json([
            'id' => $role->id(),
            'name' => $role->name(),
        ], 201);
    }
}
