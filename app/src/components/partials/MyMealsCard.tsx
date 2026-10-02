import { ActivityIndicator, Alert, Pressable, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import {
    useOptInBreakfast,
    useOptOutDinner,
    useOptOutLunch,
} from '@/hooks/use-today-meals';

import { myMealsStyle } from '@/styles/home';
import { MealCutoffs, MyTodayMeal } from '@/types/meal';
import { SymbolView } from 'expo-symbols';
import { MealIcon } from '../meal-icon';

interface Props {
    meal?: MyTodayMeal;
    cutoffs?: MealCutoffs;
    onEditPress: () => void;
}

export function MyMealsCard({ meal, cutoffs, onEditPress }: Props) {
    const colors = useTheme();

    const optInBreakfastMutation = useOptInBreakfast();
    const optOutLunchMutation = useOptOutLunch();
    const optOutDinnerMutation = useOptOutDinner();

    if (!meal) return null;

    const handleBreakfastToggle = () => {
        if (meal.breakfast > 0) {
            Alert.alert(
                'Breakfast Opted-In',
                'To cancel or edit your breakfast, use "Edit with Note".'
            );
            return;
        }
        optInBreakfastMutation.mutate(meal.id);
    };

    const handleLunchToggle = () => {
        if (meal.lunch === 0) return;
        if (!cutoffs?.lunch_opt_out_allowed) {
            Alert.alert(
                'Cutoff Elapsed',
                'The 5:00 AM cutoff for lunch has passed. Please use "Edit with Note".'
            );
            return;
        }
        optOutLunchMutation.mutate(meal.id);
    };

    const handleDinnerToggle = () => {
        if (meal.dinner === 0) return;
        if (!cutoffs?.dinner_opt_out_allowed) {
            Alert.alert(
                'Cutoff Elapsed',
                'The 2:20 PM cutoff for dinner has passed. Please use "Edit with Note".'
            );
            return;
        }
        optOutDinnerMutation.mutate(meal.id);
    };

    return (
        <View
            style={[
                styles.card,
                { backgroundColor: colors.base200, borderColor: colors.cardBorder },
            ]}
        >
            <View style={styles.cardHeader}>
                <ThemedText style={styles.cardTitle}>My Meals Today</ThemedText>
                <View style={[styles.totalPill, { backgroundColor: colors.backgroundElement }]}>
                    <ThemedText style={[styles.totalText, { color: colors.accent }]}>
                        Total: {meal.total}
                    </ThemedText>
                </View>
            </View>

            <View style={styles.columnsRow}>
                {/* Breakfast */}
                <View style={[styles.mealSlot, { backgroundColor: colors.card }]}>
                    <MealIcon type="breakfast" color={colors.primary} size={28} />
                    <ThemedText style={styles.slotCount}>{meal.breakfast}</ThemedText>
                    <Pressable
                        disabled={optInBreakfastMutation.isPending || meal.breakfast > 0}
                        onPress={handleBreakfastToggle}
                        style={({ pressed }) => [
                            styles.actionButton,
                            {
                                backgroundColor:
                                    meal.breakfast > 0 ? 'transparent' : colors.primary,
                                borderColor: colors.primary,
                                opacity: pressed ? 0.7 : 1,
                            },
                        ]}
                    >
                        {optInBreakfastMutation.isPending ? (
                            <ActivityIndicator size="small" color="#FFF" />
                        ) : (
                            <ThemedText
                                style={[
                                    styles.actionText,
                                    {
                                        color:
                                            meal.breakfast > 0
                                                ? colors.textSecondary
                                                : colors.primaryForeground,
                                    },
                                ]}
                            >
                                {meal.breakfast > 0 ? 'Active' : 'Opt In'}
                            </ThemedText>
                        )}
                    </Pressable>
                </View>

                {/* Lunch */}
                <View style={[styles.mealSlot, { backgroundColor: colors.card }]}>
                    <MealIcon type="lunch" color={colors.primary} size={28} />
                    <ThemedText style={styles.slotCount}>{meal.lunch}</ThemedText>
                    <Pressable
                        disabled={
                            optOutLunchMutation.isPending ||
                            meal.lunch === 0 ||
                            !cutoffs?.lunch_opt_out_allowed
                        }
                        onPress={handleLunchToggle}
                        style={({ pressed }) => [
                            styles.actionButton,
                            {
                                backgroundColor:
                                    meal.lunch === 0 || !cutoffs?.lunch_opt_out_allowed
                                        ? 'transparent'
                                        : 'rgba(239, 68, 68, 0.12)',
                                borderColor:
                                    meal.lunch === 0 ? colors.cardBorder : colors.secondary,
                                opacity: pressed ? 0.7 : 1,
                            },
                        ]}
                    >
                        {optOutLunchMutation.isPending ? (
                            <ActivityIndicator size="small" color={colors.secondary} />
                        ) : (
                            <ThemedText
                                style={[
                                    styles.actionText,
                                    {
                                        color:
                                            meal.lunch === 0 ? colors.textSecondary : colors.secondary,
                                    },
                                ]}
                            >
                                {meal.lunch === 0 ? 'Off' : 'Cancel Meal'}
                            </ThemedText>
                        )}
                    </Pressable>
                </View>

                {/* Dinner */}
                <View style={[styles.mealSlot, { backgroundColor: colors.card }]}>
                    <MealIcon type="dinner" color={colors.primary} size={28} />
                    <ThemedText style={styles.slotCount}>{meal.dinner}</ThemedText>
                    <Pressable
                        disabled={
                            optOutDinnerMutation.isPending ||
                            meal.dinner === 0 ||
                            !cutoffs?.dinner_opt_out_allowed
                        }
                        onPress={handleDinnerToggle}
                        style={({ pressed }) => [
                            styles.actionButton,
                            {
                                backgroundColor:
                                    meal.dinner === 0 || !cutoffs?.dinner_opt_out_allowed
                                        ? 'transparent'
                                        : 'rgba(239, 68, 68, 0.12)',
                                borderColor:
                                    meal.dinner === 0 ? colors.cardBorder : colors.secondary,
                                opacity: pressed ? 0.7 : 1,
                            },
                        ]}
                    >
                        {optOutDinnerMutation.isPending ? (
                            <ActivityIndicator size="small" color={colors.secondary} />
                        ) : (
                            <ThemedText
                                style={[
                                    styles.actionText,
                                    {
                                        color:
                                            meal.dinner === 0 ? colors.textSecondary : colors.secondary,
                                    },
                                ]}
                            >
                                {meal.dinner === 0 ? 'Off' : 'Cancel Meal'}
                            </ThemedText>
                        )}
                    </Pressable>
                </View>
            </View>

            {/* Edit with Note Trigger */}
            <Pressable
                onPress={onEditPress}
                style={({ pressed }) => [
                    styles.editButton,
                    {
                        backgroundColor: colors.backgroundElement,
                        opacity: pressed ? 0.8 : 1,
                    },
                ]}
            >
                <SymbolView
                    name={{
                        ios: 'square.and.pencil',
                        android: 'edit',
                        web: 'edit',
                    }}
                    size={16}
                    tintColor={colors.baseContent}
                />
                <ThemedText style={[styles.editButtonText, { color: colors.baseContent }]}>
                    Edit My Meal for Today
                </ThemedText>
            </Pressable>
        </View>
    );
}

const styles = myMealsStyle;