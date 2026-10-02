import { zodResolver } from '@hookform/resolvers/zod';
import * as Device from 'expo-device';
import * as Haptics from 'expo-haptics';
import { SymbolView } from 'expo-symbols';
import { useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import {
    ActivityIndicator,
    KeyboardAvoidingView,
    Platform,
    Pressable,
    ScrollView,
    TextInput,
    View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { ApiError, apiRequest } from '@/lib/api-client';
import { useAuth, User } from '@/providers/AuthProvider';
import { loginSchema, LoginSchema } from '@/schemas/login';
import { loginStyle } from '@/styles/login';

type LoginApiResponse = {
    message: string;
    token: string;
    user: User;
};

export default function LoginScreen() {
    const colors = useTheme();
    const { login } = useAuth();

    const [serverError, setServerError] = useState<string | null>(null);
    const [showPassword, setShowPassword] = useState(false);
    const [emailFocused, setEmailFocused] = useState(false);
    const [passwordFocused, setPasswordFocused] = useState(false);

    const passwordInputRef = useRef<TextInput>(null);

    const { control, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<LoginSchema>({
        resolver: zodResolver(loginSchema),
        defaultValues: { email: '', password: '' },
    });

    const onSubmit = async (data: LoginSchema) => {
        setServerError(null);

        try {
            const deviceName = Device.modelName ?? Device.deviceName ?? `${Platform.OS.toUpperCase()} App`;

            const response = await apiRequest<LoginApiResponse>('/auth/login', {
                method: 'POST',
                body: {
                    email: data.email.trim(),
                    password: data.password,
                    device_name: deviceName,
                },
            });

            if (!response.user.is_active) {
                setServerError('Your account is inactive. Please contact the manager.');
                return;
            }

            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            }

            await login(response.token, response.user);
        } catch (error: any) {
            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            }

            if (error instanceof ApiError) {
                if (error.status === 429) {
                    setServerError('Too many login attempts. Please wait a minute before trying again.');
                    return;
                }

                if (error.validationErrors) {
                    if (error.validationErrors.email) {
                        setError('email', { type: 'server', message: error.validationErrors.email[0] });
                    }
                    if (error.validationErrors.password) {
                        setError('password', { type: 'server', message: error.validationErrors.password[0] });
                    }
                } else {
                    setServerError(error.message || 'Login failed. Please check your credentials.');
                }
            } else {
                setServerError('An unexpected error occurred. Please try again.');
            }
        }
    };

    return (
        <View style={[styles.screen, { backgroundColor: colors.base300 }]}>
            <SafeAreaView style={styles.safeArea}>
                <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.keyboardView}>
                    <ScrollView contentContainerStyle={styles.scrollContent} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
                        <View style={styles.container}>
                            <View
                                style={[
                                    styles.card,
                                    {
                                        backgroundColor: colors.base100,
                                        borderColor: colors.cardBorder,
                                    },
                                ]}>
                                <View style={styles.cardBody}>
                                    <ThemedText style={[styles.cardTitle, { color: colors.baseContent }]}>
                                        Login
                                    </ThemedText>

                                    {serverError && (
                                        <View
                                            style={[
                                                styles.alertError,
                                                {
                                                    backgroundColor: colors.errorBackground,
                                                    borderColor: colors.errorBorder,
                                                },
                                            ]}>
                                            <ThemedText style={[styles.alertErrorText, { color: colors.error }]}>
                                                {serverError}
                                            </ThemedText>
                                        </View>
                                    )}

                                    <View style={styles.form}>
                                        {/* Email Field via Controller */}
                                        <Controller
                                            control={control}
                                            name="email"
                                            render={({ field: { onChange, onBlur, value } }) => (
                                                <View style={styles.formControl}>
                                                    <ThemedText style={[styles.label, { color: colors.baseContent }]}>
                                                        Email
                                                    </ThemedText>
                                                    <TextInput
                                                        style={[
                                                            styles.input,
                                                            {
                                                                backgroundColor: colors.base200,
                                                                borderColor: errors.email
                                                                    ? colors.error
                                                                    : emailFocused
                                                                        ? colors.primary
                                                                        : colors.inputBorder,
                                                                color: colors.baseContent,
                                                            },
                                                        ]}
                                                        placeholder="email@example.com"
                                                        placeholderTextColor={colors.textSecondary}
                                                        keyboardType="email-address"
                                                        autoCapitalize="none"
                                                        autoCorrect={false}
                                                        autoComplete="email"
                                                        textContentType="emailAddress"
                                                        value={value}
                                                        onChangeText={(text) => {
                                                            onChange(text);
                                                            setServerError(null); // Clear server error on type
                                                        }}
                                                        onFocus={() => setEmailFocused(true)}
                                                        onBlur={() => {
                                                            onBlur();
                                                            setEmailFocused(false);
                                                        }}
                                                        returnKeyType="next"
                                                        onSubmitEditing={() => passwordInputRef.current?.focus()}
                                                        editable={!isSubmitting}
                                                    />
                                                    {errors.email && (
                                                        <ThemedText style={[styles.errorText, { color: colors.error }]}>
                                                            {errors.email.message}
                                                        </ThemedText>
                                                    )}
                                                </View>
                                            )}
                                        />

                                        {/* Password Field via Controller */}
                                        <Controller
                                            control={control}
                                            name="password"
                                            render={({ field: { onChange, onBlur, value } }) => (
                                                <View style={styles.formControl}>
                                                    <View style={styles.labelRow}>
                                                        <ThemedText style={[styles.label, { color: colors.baseContent }]}>
                                                            Password
                                                        </ThemedText>
                                                    </View>

                                                    <View
                                                        style={[
                                                            styles.passwordInputContainer,
                                                            {
                                                                backgroundColor: colors.base200,
                                                                borderColor: errors.password
                                                                    ? colors.error
                                                                    : passwordFocused
                                                                        ? colors.primary
                                                                        : colors.inputBorder,
                                                            },
                                                        ]}
                                                    >
                                                        <TextInput
                                                            ref={passwordInputRef}
                                                            style={[
                                                                styles.passwordInput,
                                                                {
                                                                    color: colors.baseContent,
                                                                },
                                                            ]}
                                                            placeholder="Enter password"
                                                            placeholderTextColor={colors.textSecondary}
                                                            secureTextEntry={!showPassword}
                                                            autoCapitalize="none"
                                                            autoCorrect={false}
                                                            autoComplete="password"
                                                            textContentType="password"
                                                            value={value}
                                                            onChangeText={(text) => {
                                                                onChange(text);
                                                                setServerError(null); // Clear server error on type
                                                            }}
                                                            onFocus={() => setPasswordFocused(true)}
                                                            onBlur={() => {
                                                                onBlur();
                                                                setPasswordFocused(false);
                                                            }}
                                                            returnKeyType="go"
                                                            onSubmitEditing={handleSubmit(onSubmit)}
                                                            editable={!isSubmitting}
                                                        />

                                                        <Pressable
                                                            onPress={() => setShowPassword((v) => !v)}
                                                            style={styles.eyeButton}
                                                            hitSlop={8}
                                                        >
                                                            <SymbolView
                                                                name={{
                                                                    ios: showPassword ? 'eye.slash' : 'eye',
                                                                    android: showPassword ? 'visibility_off' : 'visibility',
                                                                    web: showPassword ? 'visibility_off' : 'visibility',
                                                                }}
                                                                size={18}
                                                                tintColor={colors.textSecondary}
                                                            />
                                                        </Pressable>
                                                    </View>

                                                    {errors.password && (
                                                        <ThemedText style={[styles.errorText, { color: colors.error }]}>
                                                            {errors.password.message}
                                                        </ThemedText>
                                                    )}
                                                </View>
                                            )}
                                        />

                                        <Pressable
                                            onPress={handleSubmit(onSubmit)}
                                            disabled={isSubmitting}
                                            style={({ pressed }) => [
                                                styles.buttonPrimary,
                                                {
                                                    backgroundColor: colors.primary,
                                                    opacity: isSubmitting ? 0.7 : pressed ? 0.85 : 1,
                                                },
                                            ]}>
                                            {isSubmitting ? (
                                                <ActivityIndicator color={colors.primaryForeground} size="small" />
                                            ) : (
                                                <ThemedText style={[styles.buttonText, { color: colors.primaryForeground }]}>
                                                    Login
                                                </ThemedText>
                                            )}
                                        </Pressable>
                                    </View>
                                </View>
                            </View>
                        </View>
                    </ScrollView>
                </KeyboardAvoidingView>
            </SafeAreaView>
        </View>
    );
}

const styles = loginStyle;