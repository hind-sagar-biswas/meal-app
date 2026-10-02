import { StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { TodaySummaryResponse } from '@/types/meal';
import { todayTallyStyle } from '@/styles/home';

interface Props {
    summary?: TodaySummaryResponse['data'];
}

export function TodayTallyCard({ summary }: Props) {
    const colors = useTheme();

    if (!summary) return null;

    return (
        <View
            style={[
                styles.card,
                { backgroundColor: colors.base200, borderColor: colors.cardBorder },
            ]}
        >
            <View style={styles.header}>
                <ThemedText style={styles.title}>Today's Mess Tally</ThemedText>
                <ThemedText style={[styles.totalUnits, { color: colors.primary }]}>
                    {summary.total_units} Units Total
                </ThemedText>
            </View>

            <View style={styles.slotsRow}>
                <View style={styles.slot}>
                    <ThemedText style={styles.slotName}>Breakfast</ThemedText>
                    <ThemedText style={styles.headcount}>
                        {summary.breakfast.headcount}
                    </ThemedText>
                    <ThemedText style={[styles.units, { color: colors.textSecondary }]}>
                        {summary.breakfast.units} units
                    </ThemedText>
                </View>

                <View style={styles.slot}>
                    <ThemedText style={styles.slotName}>Lunch</ThemedText>
                    <ThemedText style={styles.headcount}>
                        {summary.lunch.headcount}
                    </ThemedText>
                    <ThemedText style={[styles.units, { color: colors.textSecondary }]}>
                        {summary.lunch.units} units
                    </ThemedText>
                </View>

                <View style={styles.slot}>
                    <ThemedText style={styles.slotName}>Dinner</ThemedText>
                    <ThemedText style={styles.headcount}>
                        {summary.dinner.headcount}
                    </ThemedText>
                    <ThemedText style={[styles.units, { color: colors.textSecondary }]}>
                        {summary.dinner.units} units
                    </ThemedText>
                </View>
            </View>
        </View>
    );
}

const styles = todayTallyStyle;