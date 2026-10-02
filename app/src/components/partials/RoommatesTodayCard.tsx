import { View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { roommatesTodayStyle } from '@/styles/home';
import { RoommateTodayMeal } from '@/types/meal';

interface Props {
    members?: RoommateTodayMeal[];
}

export function RoommatesTodayCard({ members }: Props) {
    const colors = useTheme();

    if (!members || members.length === 0) return null;

    return (
        <View
            style={[
                styles.card,
                { backgroundColor: colors.base200, borderColor: colors.cardBorder },
            ]}
        >
            <ThemedText style={styles.title}>Roommates Today ({members.length})</ThemedText>

            <View style={styles.list}>
                {members.map((m) => {
                    // If a member has > 2 lunch or > 1 dinner/breakfast, they have guest meals
                    const hasGuest = m.lunch > 2 || m.dinner > 1 || m.breakfast > 1;

                    return (
                        <View
                            key={m.user_id}
                            style={[
                                styles.row,
                                { borderBottomColor: colors.cardBorder },
                            ]}
                        >
                            <View style={styles.nameGroup}>
                                <ThemedText style={styles.memberName}>{m.name}</ThemedText>
                                {hasGuest && (
                                    <View style={styles.guestBadge}>
                                        <ThemedText style={styles.guestText}>Guest</ThemedText>
                                    </View>
                                )}
                            </View>

                            <View style={styles.countsGroup}>
                                <ThemedText style={styles.countItem}>
                                    {m.breakfast}
                                </ThemedText>
                                <ThemedText style={styles.countItem}>
                                    {m.lunch}
                                </ThemedText>
                                <ThemedText style={styles.countItem}>
                                    {m.dinner}
                                </ThemedText>
                            </View>
                        </View>
                    );
                })}
            </View>
        </View>
    );
}

const styles = roommatesTodayStyle;