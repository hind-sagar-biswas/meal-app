import { zodResolver } from '@hookform/resolvers/zod';
import * as Haptics from 'expo-haptics';
import { SymbolView } from 'expo-symbols';
import { useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { ActivityIndicator, Alert, Platform, Pressable, TextInput, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { ApiError, PUT } from '@/lib/api-client';
import { passwordSchema, PasswordSchema } from '@/schemas/profile';
import { passwordFormStyle } from '@/styles/profile';

export function ChangePasswordForm({ onSuccess }: { onSuccess: () => void }) {
    const colors = useTheme();

    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);

    const [currentFocused, setCurrentFocused] = useState(false);
    const [passwordFocused, setPasswordFocused] = useState(false);
    const [confirmFocused, setConfirmFocused] = useState(false);

    const newPasswordRef = useRef<TextInput>(null);
    const confirmPasswordRef = useRef<TextInput>(null);

    const { control, handleSubmit, setError, reset, formState: { isSubmitting, errors }, } = useForm<PasswordSchema>({
        resolver: zodResolver(passwordSchema),
        defaultValues: { current_password: '', password: '', password_confirmation: '' },
    });

    const onSubmit = async (data: PasswordSchema) => {
        try {
            const response = await PUT<{ message: string }>('/auth/password', data);

            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            }

            reset();
            Alert.alert('Success', response.message);
            onSuccess();
        } catch (error: any) {
            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            }

            if (error instanceof ApiError) {
                if (error.status === 429) {
                    Alert.alert('Rate Limited', 'Too many requests. Please wait a minute.');
                    return;
                }

                if (error.validationErrors) {
                    if (error.validationErrors.current_password) {
                        setError('current_password', { type: 'server', message: error.validationErrors.current_password[0] });
                    }
                    if (error.validationErrors.password) {
                        setError('password', { type: 'server', message: error.validationErrors.password[0] });
                    }
                } else {
                    Alert.alert('Error', error.message || 'Failed to update password.');
                }
            } else {
                Alert.alert('Error', 'An unexpected error occurred.');
            }
        }
    };

    return (
        <View style={styles.container}>
            <View style={styles.form}>

                {/* Current Password */}
                <Controller
                    control={control}
                    name="current_password"
                    render={({ field: { onChange, onBlur, value } }) => (
                        <View style={styles.formControl}>
                            <ThemedText style={[styles.label, { color: colors.baseContent }]}>Current Password</ThemedText>
                            <View
                                style={[
                                    styles.passwordInputContainer,
                                    {
                                        backgroundColor: colors.base200,
                                        borderColor: errors.current_password
                                            ? colors.error
                                            : currentFocused
                                                ? colors.primary
                                                : colors.inputBorder,
                                    },
                                ]}
                            >
                                <TextInput
                                    style={[styles.passwordInput, { color: colors.baseContent }]}
                                    placeholder="Enter current password"
                                    placeholderTextColor={colors.textSecondary}
                                    secureTextEntry={!showCurrentPassword}
                                    autoCapitalize="none"
                                    value={value}
                                    onChangeText={onChange}
                                    onFocus={() => setCurrentFocused(true)}
                                    onBlur={() => {
                                        onBlur();
                                        setCurrentFocused(false);
                                    }}
                                    returnKeyType="next"
                                    onSubmitEditing={() => newPasswordRef.current?.focus()}
                                    editable={!isSubmitting}
                                />
                                <Pressable onPress={() => setShowCurrentPassword((v) => !v)} style={styles.eyeButton} hitSlop={8}>
                                    <SymbolView
                                        name={{
                                            ios: showCurrentPassword ? 'eye.slash' : 'eye',
                                            android: showCurrentPassword ? 'visibility_off' : 'visibility',
                                            web: showCurrentPassword ? 'visibility_off' : 'visibility',
                                        }}
                                        size={18}
                                        tintColor={colors.textSecondary}
                                    />
                                </Pressable>
                            </View>
                            {errors.current_password && (
                                <ThemedText style={[styles.errorText, { color: colors.error }]}>{errors.current_password.message}</ThemedText>
                            )}
                        </View>
                    )}
                />

                {/* New Password */}
                <Controller
                    control={control}
                    name="password"
                    render={({ field: { onChange, onBlur, value } }) => (
                        <View style={styles.formControl}>
                            <ThemedText style={[styles.label, { color: colors.baseContent }]}>New Password</ThemedText>
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
                                    ref={newPasswordRef}
                                    style={[styles.passwordInput, { color: colors.baseContent }]}
                                    placeholder="Enter new password"
                                    placeholderTextColor={colors.textSecondary}
                                    secureTextEntry={!showNewPassword}
                                    autoCapitalize="none"
                                    value={value}
                                    onChangeText={onChange}
                                    onFocus={() => setPasswordFocused(true)}
                                    onBlur={() => {
                                        onBlur();
                                        setPasswordFocused(false);
                                    }}
                                    returnKeyType="next"
                                    onSubmitEditing={() => confirmPasswordRef.current?.focus()}
                                    editable={!isSubmitting}
                                />
                                <Pressable onPress={() => setShowNewPassword((v) => !v)} style={styles.eyeButton} hitSlop={8}>
                                    <SymbolView
                                        name={{
                                            ios: showNewPassword ? 'eye.slash' : 'eye',
                                            android: showNewPassword ? 'visibility_off' : 'visibility',
                                            web: showNewPassword ? 'visibility_off' : 'visibility',
                                        }}
                                        size={18}
                                        tintColor={colors.textSecondary}
                                    />
                                </Pressable>
                            </View>
                            {errors.password && (
                                <ThemedText style={[styles.errorText, { color: colors.error }]}>{errors.password.message}</ThemedText>
                            )}
                        </View>
                    )}
                />

                {/* Confirm New Password */}
                <Controller
                    control={control}
                    name="password_confirmation"
                    render={({ field: { onChange, onBlur, value } }) => (
                        <View style={styles.formControl}>
                            <ThemedText style={[styles.label, { color: colors.baseContent }]}>Confirm New Password</ThemedText>
                            <View
                                style={[
                                    styles.passwordInputContainer,
                                    {
                                        backgroundColor: colors.base200,
                                        borderColor: errors.password_confirmation
                                            ? colors.error
                                            : confirmFocused
                                                ? colors.primary
                                                : colors.inputBorder,
                                    },
                                ]}
                            >
                                <TextInput
                                    ref={confirmPasswordRef}
                                    style={[styles.passwordInput, { color: colors.baseContent }]}
                                    placeholder="Confirm new password"
                                    placeholderTextColor={colors.textSecondary}
                                    secureTextEntry={!showNewPassword} // Link visibility state with the main password field
                                    autoCapitalize="none"
                                    value={value}
                                    onChangeText={onChange}
                                    onFocus={() => setConfirmFocused(true)}
                                    onBlur={() => {
                                        onBlur();
                                        setConfirmFocused(false);
                                    }}
                                    returnKeyType="go"
                                    onSubmitEditing={handleSubmit(onSubmit)}
                                    editable={!isSubmitting}
                                />
                            </View>
                            {errors.password_confirmation && (
                                <ThemedText style={[styles.errorText, { color: colors.error }]}>{errors.password_confirmation.message}</ThemedText>
                            )}
                        </View>
                    )}
                />

                {/* Submit Button */}
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
                            Update Password
                        </ThemedText>
                    )}
                </Pressable>
            </View>
        </View>
    );
}

const styles = passwordFormStyle;