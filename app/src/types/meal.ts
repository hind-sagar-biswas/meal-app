export interface MealCutoffs {
    breakfast_cutoff: string | null;
    lunch_cutoff: string;
    dinner_cutoff: string;
    breakfast_opt_in_allowed: boolean;
    lunch_opt_out_allowed: boolean;
    dinner_opt_out_allowed: boolean;
}

export interface MyTodayMeal {
    id: number;
    user_id: number;
    date: string;
    breakfast: number;
    lunch: number;
    dinner: number;
    total: number;
    has_logged: boolean;
    is_editable: boolean;
}

export interface MyTodayResponse {
    data: {
        server_time: string;
        date: string;
        is_month_closed: boolean;
        cutoffs: MealCutoffs;
        meal: MyTodayMeal;
    };
}

export interface MealSummarySlot {
    headcount: number;
    units: number;
}

export interface TodaySummaryResponse {
    data: {
        date: string;
        breakfast: MealSummarySlot;
        lunch: MealSummarySlot;
        dinner: MealSummarySlot;
        total_units: number;
    };
}

export interface RoommateTodayMeal {
    meal_id: number;
    user_id: number;
    name: string;
    breakfast: number;
    lunch: number;
    dinner: number;
    total: number;
    has_logged: boolean;
}

export interface TodayMembersResponse {
    data: RoommateTodayMeal[];
}

export interface MealMutationPayload {
    mealId: number;
    breakfast?: number;
    lunch?: number;
    dinner?: number;
    note?: string;
}