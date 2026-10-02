import { SymbolView } from 'expo-symbols';
import { StyleSheet, View } from 'react-native';

interface Props {
    type: 'breakfast' | 'lunch' | 'dinner';
    color: string;
    size?: number;
}

export function MealIcon({ type, color, size = 20 }: Props) {
    const iconConfig = {
        breakfast: {
            ios: 'cup.and.saucer.fill' as const,
            android: 'free_breakfast' as const,
            web: 'free_breakfast' as const,
        },
        lunch: {
            ios: 'sun.max.fill' as const,
            android: 'wb_sunny' as const,
            web: 'wb_sunny' as const,
        },
        dinner: {
            ios: 'moon.stars.fill' as const,
            android: 'nights_stay' as const,
            web: 'nights_stay' as const,
        },
    }[type];

    return (
        <View style={styles.container}>
            <SymbolView name={iconConfig} size={size} tintColor={color} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        justifyContent: 'center',
        alignItems: 'center',
        height: 26,
        width: 26,
    },
});