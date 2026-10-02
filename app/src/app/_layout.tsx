import { DarkTheme, DefaultTheme, Slot, ThemeProvider, useRouter, useSegments } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { useEffect } from 'react';
import { useColorScheme } from 'react-native';

import { AnimatedSplashOverlay } from '@/components/animated-icon';
import { AuthProvider, useAuth } from '@/providers/AuthProvider';

SplashScreen.preventAutoHideAsync();

function RootLayoutNav() {
    const { user, isLoading } = useAuth();
    const segments = useSegments();
    const router = useRouter();

    useEffect(() => {
        if (isLoading) return;

        const inAuthGroup = segments[0] === '(auth)';

        if (!user && !inAuthGroup) {
            router.replace('/(auth)/login');
        } else if (user && inAuthGroup) {
            router.replace('/(tabs)');
        }
    }, [user, isLoading, segments]);

    if (isLoading) {
        return null;
    }

    return (
        <>
            <AnimatedSplashOverlay />
            <Slot />
        </>
    );
}

const WinterTheme = {
    ...DefaultTheme,
    colors: {
        ...DefaultTheme.colors,
        background: '#E3E9F0', // base-300
        card: '#FFFFFF', // base-100
        text: '#394E6A', // base-content
        border: '#DCE4EE',
        primary: '#047AFF',
    },
};

const SunsetTheme = {
    ...DarkTheme,
    colors: {
        ...DarkTheme.colors,
        background: '#12151D', // base-300
        card: '#1A1E29', // base-100
        text: '#A6BCDA', // base-content
        border: '#232836',
        primary: '#FF865B',
    },
};

export default function RootLayout() {
    const colorScheme = useColorScheme();
    return (
        <ThemeProvider value={colorScheme === 'dark' ? SunsetTheme : WinterTheme}>
            <AuthProvider>
                <RootLayoutNav />
            </AuthProvider>
        </ThemeProvider>
    );
}