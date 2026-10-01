<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Month\UpdateBreakfastPriceRequest;
use App\Http\Resources\MonthResource;
use App\Http\Resources\MonthResultResource;
use App\Models\Month;
use App\Services\MonthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonthController extends Controller
{
    public function __construct(
        protected MonthService $monthService
    ) {}

    /**
     * Get list of all months.
     */
    public function index(): JsonResponse
    {
        $months = Month::with('closedBy:id,name')->orderBy('year', 'desc')->orderBy('month', 'desc')->get();

        return response()->json([
            'data' => MonthResource::collection($months),
        ]);
    }

    /**
     * Get or create the ongoing active month.
     */
    public function current(): JsonResponse
    {
        $month = $this->monthService->getOrCreateCurrentMonth();
        $month->load('closedBy:id,name');

        return response()->json([
            'data' => new MonthResource($month),
        ]);
    }

    /**
     * Get real-time live estimation calculation for a month.
     */
    public function liveSummary(Month $month): JsonResponse
    {
        $summary = $this->monthService->getLiveSummary($month);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get snapshot results of a closed month.
     */
    public function results(Month $month): JsonResponse
    {
        if (! $month->is_closed) {
            return response()->json([
                'message' => 'Month is not closed yet.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->monthService->getClosedMonthResult($month);

        if (! $result) {
            return response()->json([
                'message' => 'Results not found for this month.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => new MonthResultResource($result),
        ]);
    }

    /**
     * Close a month and snapshot final balances.
     */
    public function close(Request $request, Month $month): JsonResponse
    {
        try {
            $result = $this->monthService->closeMonth($month, $request->user());

            return response()->json([
                'message' => 'Month closed successfully.',
                'data' => new MonthResultResource($result),
            ], Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Reopen a closed month within the 6-hour grace window.
     */
    public function reopen(Request $request, Month $month): JsonResponse
    {
        try {
            $reopenedMonth = $this->monthService->reopenMonth($month, $request->user());

            return response()->json([
                'message' => 'Month reopened successfully.',
                'data' => new MonthResource($reopenedMonth),
            ], Response::HTTP_OK);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Set or update the unit breakfast price for an open month.
     */
    public function updateBreakfastPrice(UpdateBreakfastPriceRequest $request, Month $month): JsonResponse
    {
        try {
            $updatedMonth = $this->monthService->setBreakfastPrice(
                $month,
                (int) $request->input('breakfast_price'),
                $request->user()
            );

            return response()->json([
                'message' => 'Breakfast price updated successfully.',
                'data' => new MonthResource($updatedMonth),
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
