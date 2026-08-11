<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Src\AcademicRisk\RiskBoard\Application\UseCases\EvaluateRiskBoardUseCase;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskBoard;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskItem;

final class RiskBoardController extends Controller
{
    use AuthorizesRequests;

    public function index(EvaluateRiskBoardUseCase $useCase): JsonResponse
    {
        $this->authorize('viewAny', RiskBoard::class);

        $board = $useCase->handle();

        return response()->json([
            'total' => $board->total(),
            'data' => array_map(
                static fn (RiskItem $item): array => [
                    'key' => $item->fingerprint(),
                    'type' => $item->type->value,
                    'level' => $item->level()->value,
                    'subjectId' => $item->subjectId,
                    'subjectCode' => $item->subjectCode,
                    'subjectName' => $item->subjectName,
                    'term' => $item->term,
                    'measuredValue' => $item->measuredValue,
                    'thresholdValue' => $item->thresholdValue,
                ],
                $board->all(),
            ),
        ]);
    }
}
