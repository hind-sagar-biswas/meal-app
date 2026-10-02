import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import { Platform } from 'react-native';

import { apiRequest } from '@/lib/api-client';
import {
    MyTodayResponse,
    TodayMembersResponse,
    TodaySummaryResponse,
} from '@/types/meal';

// Query Keys
export const mealKeys = {
    all: ['meals'] as const,
    myToday: () => [...mealKeys.all, 'my-today'] as const,
    todaySummary: () => [...mealKeys.all, 'today-summary'] as const,
    todayMembers: () => [...mealKeys.all, 'today-members'] as const,
};

// 1. Fetch My Today Data
export function useMyToday() {
    return useQuery({
        queryKey: mealKeys.myToday(),
        queryFn: () => apiRequest<MyTodayResponse>('/meals/my-today'),
        staleTime: 10 * 1000,
    });
}

// 2. Fetch Today Summary (15s micro-cache on server)
export function useTodaySummary() {
    return useQuery({
        queryKey: mealKeys.todaySummary(),
        queryFn: () => apiRequest<TodaySummaryResponse>('/meals/today-summary'),
        staleTime: 15 * 1000,
    });
}

// 3. Fetch Today Members (15s micro-cache on server)
export function useTodayMembers() {
    return useQuery({
        queryKey: mealKeys.todayMembers(),
        queryFn: () => apiRequest<TodayMembersResponse>('/meals/today-members'),
        staleTime: 15 * 1000,
    });
}

// 4. Breakfast Opt-in Mutation (Optimistic)
export function useOptInBreakfast() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (mealId: number) =>
            apiRequest(`/meals/${mealId}/opt-in-breakfast`, { method: 'POST' }),
        onMutate: async () => {
            if (Platform.OS !== 'web') {
                Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
            }

            await queryClient.cancelQueries({ queryKey: mealKeys.myToday() });
            const previousData = queryClient.getQueryData<MyTodayResponse>(mealKeys.myToday());

            if (previousData) {
                queryClient.setQueryData<MyTodayResponse>(mealKeys.myToday(), {
                    ...previousData,
                    data: {
                        ...previousData.data,
                        meal: {
                            ...previousData.data.meal,
                            breakfast: 1,
                            total: previousData.data.meal.total + 1,
                        },
                    },
                });
            }

            return { previousData };
        },
        onError: (_err, _variables, context) => {
            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            }
            if (context?.previousData) {
                queryClient.setQueryData(mealKeys.myToday(), context.previousData);
            }
        },
        onSettled: () => {
            queryClient.invalidateQueries({ queryKey: mealKeys.all });
        },
    });
}

// 5. Lunch Opt-out Mutation (Optimistic)
export function useOptOutLunch() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (mealId: number) =>
            apiRequest(`/meals/${mealId}/opt-out-lunch`, { method: 'POST' }),
        onMutate: async () => {
            if (Platform.OS !== 'web') {
                Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
            }

            await queryClient.cancelQueries({ queryKey: mealKeys.myToday() });
            const previousData = queryClient.getQueryData<MyTodayResponse>(mealKeys.myToday());

            if (previousData) {
                const currentLunch = previousData.data.meal.lunch;
                queryClient.setQueryData<MyTodayResponse>(mealKeys.myToday(), {
                    ...previousData,
                    data: {
                        ...previousData.data,
                        meal: {
                            ...previousData.data.meal,
                            lunch: 0,
                            total: Math.max(0, previousData.data.meal.total - currentLunch),
                        },
                    },
                });
            }

            return { previousData };
        },
        onError: (_err, _variables, context) => {
            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            }
            if (context?.previousData) {
                queryClient.setQueryData(mealKeys.myToday(), context.previousData);
            }
        },
        onSettled: () => {
            queryClient.invalidateQueries({ queryKey: mealKeys.all });
        },
    });
}

// 6. Dinner Opt-out Mutation (Optimistic)
export function useOptOutDinner() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (mealId: number) =>
            apiRequest(`/meals/${mealId}/opt-out-dinner`, { method: 'POST' }),
        onMutate: async () => {
            if (Platform.OS !== 'web') {
                Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
            }

            await queryClient.cancelQueries({ queryKey: mealKeys.myToday() });
            const previousData = queryClient.getQueryData<MyTodayResponse>(mealKeys.myToday());

            if (previousData) {
                const currentDinner = previousData.data.meal.dinner;
                queryClient.setQueryData<MyTodayResponse>(mealKeys.myToday(), {
                    ...previousData,
                    data: {
                        ...previousData.data,
                        meal: {
                            ...previousData.data.meal,
                            dinner: 0,
                            total: Math.max(0, previousData.data.meal.total - currentDinner),
                        },
                    },
                });
            }

            return { previousData };
        },
        onError: (_err, _variables, context) => {
            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            }
            if (context?.previousData) {
                queryClient.setQueryData(mealKeys.myToday(), context.previousData);
            }
        },
        onSettled: () => {
            queryClient.invalidateQueries({ queryKey: mealKeys.all });
        },
    });
}