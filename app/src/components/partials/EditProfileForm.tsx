import { zodResolver } from '@hookform/resolvers/zod';
import * as Haptics from 'expo-haptics';
import { useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { ActivityIndicator, Alert, Platform, Pressable, TextInput, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { ApiError, PATCH } from '@/lib/api-client';
import { useAuth, User } from '@/providers/AuthProvider';
import { profileSchema, ProfileSchema } from '@/schemas/profile';
import { profileFormStyle } from '@/styles/profile';

export function EditProfileForm({ onSuccess }: { onSuccess: () => void }) {
    const { user, updateUser } = useAuth();
    const colors = useTheme();

    const [nameFocused, setNameFocused] = useState(false);
    const [emailFocused, setEmailFocused] = useState(false);

    const emailInputRef = useRef<TextInput>(null);

    const { control, handleSubmit, setError, formState: { isSubmitting, errors } } = useForm<ProfileSchema>({
        resolver: zodResolver(profileSchema),
        defaultValues: { name: user?.name || '', email: user?.email || '' },
    });

    const onSubmit = async (data: ProfileSchema) => {
        try {
            const response = await PATCH<{ message: string; user: User }>('/auth/profile', {
                name: data.name.trim(),
                email: data.email.trim(),
            });

            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            }

            updateUser(response.user);
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
                    if (error.validationErrors.name) {
                        setError('name', { type: 'server', message: error.validationErrors.name[0] });
                    }
                    if (error.validationErrors.email) {
                        setError('email', { type: 'server', message: error.validationErrors.email[0] });
                    }
                } else {
                    Alert.alert('Error', error.message || 'Failed to update profile.');
                }
            } else {
                Alert.alert('Error', 'An unexpected error occurred.');
            }
        }
    };

    return (
        <View style={styles.container}>
            <View style={styles.form}>
                {/* Name Field */}
                <Controller
                    control={control}
                    name="name"
                    render={({ field: { onChange, onBlur, value } }) => (
                        <View style={styles.formControl}>
                            <ThemedText style={[styles.label, { color: colors.baseContent }]}>Name</ThemedText>
                            <TextInput
                                style={[
                                    styles.input,
                                    {
                                        backgroundColor: colors.base200,
                                        borderColor: errors.name
                                            ? colors.error
                                            : nameFocused
                                                ? colors.primary
                                                : colors.inputBorder,
                                        color: colors.baseContent,
                                    },
                                ]}
                                placeholder="John Doe"
                                placeholderTextColor={colors.textSecondary}
                                autoCapitalize="words"
                                autoCorrect={false}
                                value={value}
                                onChangeText={onChange}
                                onFocus={() => setNameFocused(true)}
                                onBlur={() => {
                                    onBlur();
                                    setNameFocused(false);
                                }}
                                returnKeyType="next"
                                onSubmitEditing={() => emailInputRef.current?.focus()}
                                editable={!isSubmitting}
                            />
                            {errors.name && (
                                <ThemedText style={[styles.errorText, { color: colors.error }]}>
                                    {errors.name.message}
                                </ThemedText>
                            )}
                        </View>
                    )}
                />

                {/* Email Field */}
                <Controller
                    control={control}
                    name="email"
                    render={({ field: { onChange, onBlur, value } }) => (
                        <View style={styles.formControl}>
                            <ThemedText style={[styles.label, { color: colors.baseContent }]}>Email</ThemedText>
                            <TextInput
                                ref={emailInputRef}
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
                                value={value}
                                onChangeText={onChange}
                                onFocus={() => setEmailFocused(true)}
                                onBlur={() => {
                                    onBlur();
                                    setEmailFocused(false);
                                }}
                                returnKeyType="go"
                                onSubmitEditing={handleSubmit(onSubmit)}
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
                            Save Changes
                        </ThemedText>
                    )}
                </Pressable>
            </View>
        </View>
    );
}

const styles = profileFormStyle;