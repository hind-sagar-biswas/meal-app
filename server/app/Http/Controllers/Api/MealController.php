<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meal\SetDateRangeMealsOffRequest;
use App\Http\Requests\Meal\SetDayMealsOffRequest;
use App\Http\Requests\Meal\UpdateDayTallyRequest;
use App\Http\Requests\Meal\UpdateMealRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\MealResource;
use App\Models\AuditLog;
use App\Models\Meal;
use App\Models\Month;
use App\Models\User;
use App\Services\MealService;
use App\Services\MonthService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MealController extends Controller
{
    public function __construct(
        protected MealService $mealService,
        protected MonthService $monthService
    ) {}

    /**
     * Get logged-in user's today meal status with cutoff eligibility flags.
     */
    public function myToday(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $data = $this->mealService->getMyToday($request->user(), $date);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get aggregate meal headcount & unit summary for a date (cached 15s).
     */
    public function todaySummary(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $summary = $this->mealService->getTodaySummary($date);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get list of active members and their meal counts for a date (cached 15s).
     */
    public function todayMembers(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $members = $this->mealService->getTodayMembers($date);

        return response()->json([
            'data' => $members,
        ]);
    }

    /**
     * Combined meal dashboard breakdown for a date.
     */
    public function today(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $dashboard = $this->mealService->getTodayDashboard($date);

        return response()->json([
            'data' => $dashboard,
        ]);
    }

    /**
     * Get 31-day meal breakdown for the authenticated user for a month (cached 30s).
     */
    public function myMonth(Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request);
        $data = $this->mealService->getUserMonthMeals($request->user(), $month);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Full spreadsheet matrix (31 Day rows x 8 Member columns, cached 30s).
     */
    public function sheet(Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request);
        $data = $this->mealService->getMonthSheet($month);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get all active members' meals for a specific date.
     */
    public function byDate(Request $request, string $date): JsonResponse
    {
        try {
            $carbonDate = Carbon::parse($date)->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid date format provided.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activeUserIds = User::where('is_active', true)->pluck('id');
        $meals = Meal::where('date', $carbonDate)
            ->whereIn('user_id', $activeUserIds)
            ->with('user:id,name,email')
            ->orderBy('user_id')
            ->get();

        return response()->json([
            'date' => $carbonDate,
            'data' => MealResource::collection($meals),
        ]);
    }

    /**
     * Get audit log history for an individual meal entry.
     */
    public function history(Meal $meal): JsonResponse
    {
        $logs = AuditLog::where('auditable_type', Meal::class)
            ->where('auditable_id', $meal->id)
            ->with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json([
            'data' => AuditLogResource::collection($logs),
        ]);
    }

    /**
     * Silent one-way opt-in for breakfast before 5:00 AM.
     */
    public function optInBreakfast(Request $request, Meal $meal): JsonResponse
    {
        try {
            $updatedMeal = $this->mealService->optInBreakfast($meal, $request->user());
            $updatedMeal->load('user:id,name,email');

            return response()->json([
                'message' => 'Successfully opted in for breakfast.',
                'data' => new MealResource($updatedMeal),
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Silent one-way opt-out for lunch before 5:00 AM.
     */
    public function optOutLunch(Request $request, Meal $meal): JsonResponse
    {
        try {
            $updatedMeal = $this->mealService->optOutLunch($meal, $request->user());
            $updatedMeal->load('user:id,name,email');

            return response()->json([
                'message' => 'Successfully opted out of lunch.',
                'data' => new MealResource($updatedMeal),
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Silent one-way opt-out for dinner before 2:20 PM.
     */
    public function optOutDinner(Request $request, Meal $meal): JsonResponse
    {
        try {
            $updatedMeal = $this->mealService->optOutDinner($meal, $request->user());
            $updatedMeal->load('user:id,name,email');

            return response()->json([
                'message' => 'Successfully opted out of dinner.',
                'data' => new MealResource($updatedMeal),
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Manual edit of an individual meal entry with audit logging.
     */
    public function update(UpdateMealRequest $request, Meal $meal): JsonResponse
    {
        try {
            $updatedMeal = $this->mealService->editMeal(
                $meal,
                $request->user(),
                $request->integer('breakfast'),
                $request->integer('lunch'),
                $request->integer('dinner'),
                $request->string('note')
            );
            $updatedMeal->load('user:id,name,email');

            return response()->json([
                'message' => 'Meal entry updated successfully.',
                'data' => new MealResource($updatedMeal),
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update meal tally for all active members on a specific day.
     */
    public function dayTally(UpdateDayTallyRequest $request): JsonResponse
    {
        try {
            $count = $this->mealService->updateDayTally(
                $request->input('date'),
                $request->user(),
                $request->integer('breakfast'),
                $request->integer('lunch'),
                $request->integer('dinner'),
                $request->string('note')
            );

            return response()->json([
                'message' => 'Day meal tally updated successfully.',
                'affected_count' => $count,
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Turn all meals off for a single day for all active members.
     */
    public function dayOff(SetDayMealsOffRequest $request): JsonResponse
    {
        try {
            $count = $this->mealService->setDayMealsOff(
                $request->input('date'),
                $request->user(),
                $request->string('note')
            );

            return response()->json([
                'message' => 'Day meals turned off successfully.',
                'affected_count' => $count,
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Turn all meals off across a date range for all active members.
     */
    public function dateRangeOff(SetDateRangeMealsOffRequest $request): JsonResponse
    {
        try {
            $count = $this->mealService->setDateRangeMealsOff(
                $request->input('from_date', $request->input('start_date')),
                $request->input('to_date', $request->input('end_date')),
                $request->user(),
                $request->string('note')
            );

            return response()->json([
                'message' => 'Date range meals turned off successfully.',
                'affected_count' => $count,
            ], Response::HTTP_OK);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Resolve month from request or get current active month.
     */
    protected function resolveMonth(Request $request): Month
    {
        if ($request->has('month_id')) {
            return Month::findOrFail($request->integer('month_id'));
        }

        if ($request->has(['year', 'month'])) {
            return Month::where('year', $request->integer('year'))
                ->where('month', $request->integer('month'))
                ->firstOrFail();
        }

        return $this->monthService->getOrCreateCurrentMonth();
    }
}
