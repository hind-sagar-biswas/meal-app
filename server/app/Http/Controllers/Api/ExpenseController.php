<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\AdjustExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Month;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    /**
     * Get paginated expenses list with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Expense::with(['user', 'contributions.user'])->orderBy('date', 'desc')->orderBy('id', 'desc');

        if ($request->filled('month_id')) {
            $query->where('month_id', $request->input('month_id'));
        } elseif ($request->filled('year') && $request->filled('month')) {
            $month = Month::where('year', $request->input('year'))->where('month', $request->input('month'))->first();
            $query->where('month_id', $month?->id ?? 0);
        }

        if ($request->has('is_grouped') && $request->input('is_grouped') !== null && $request->input('is_grouped') !== '') {
            $query->where('is_grouped', filter_var($request->input('is_grouped'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('from_date')) {
            $query->where('date', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('date', '<=', $request->input('to_date'));
        }

        $expenses = $query->paginate($perPage);

        return response()->json([
            'data' => ExpenseResource::collection($expenses->items()),
            'current_page' => $expenses->currentPage(),
            'last_page' => $expenses->lastPage(),
            'per_page' => $expenses->perPage(),
            'total' => $expenses->total(),
        ]);
    }

    /**
     * Get detailed single expense with contributors.
     */
    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['user', 'contributions.user']);

        return response()->json([
            'data' => new ExpenseResource($expense),
        ]);
    }

    /**
     * Get monthly expense summary and per-member breakdown.
     */
    public function summary(Request $request): JsonResponse
    {
        if ($request->filled('month_id')) {
            $month = Month::findOrFail($request->input('month_id'));
        } elseif ($request->filled('year') && $request->filled('month')) {
            $month = Month::where('year', $request->input('year'))
                ->where('month', $request->input('month'))
                ->firstOrFail();
        } else {
            $month = Month::ongoing();
        }

        $summary = $this->expenseService->getMonthlyExpenseSummary($month);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Create a new expense with multi-payer contributor split.
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $expense = $this->expenseService->createExpense(
                creator: $request->user(),
                data: $request->validated(),
                contributions: $request->input('contributions'),
                note: $request->input('note')
            );

            return response()->json([
                'message' => 'Expense created successfully.',
                'data' => new ExpenseResource($expense),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Create a compensating additive adjustment for an expense.
     */
    public function adjust(AdjustExpenseRequest $request, Expense $expense): JsonResponse
    {
        try {
            $adjustment = $this->expenseService->createAdjustmentExpense(
                original: $expense,
                creator: $request->user(),
                deltaAmount: (int) $request->input('amount'),
                contributions: $request->input('contributions'),
                note: $request->input('reason')
            );

            return response()->json([
                'message' => 'Expense adjustment created successfully.',
                'data' => new ExpenseResource($adjustment),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
