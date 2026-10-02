import * as Device from 'expo-device';
import * as Haptics from 'expo-haptics';
import { SymbolView } from 'expo-symbols';
import { useRef, useState } from 'react';
import {
    ActivityIndicator,
    KeyboardAvoidingView,
    Platform,
    Pressable,
    ScrollView,
    StyleSheet,
    TextInput,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { ApiError, apiRequest } from '@/lib/api-client';
import { User, useAuth } from '@/providers/AuthProvider';

type LoginApiResponse = {
    token?: string;
    access_token?: string;
    data?: {
        token?: string;
        access_token?: string;
        user?: User;
    };
    user?: User;
};

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export default function LoginScreen() {
    const colors = useTheme();
    const { login } = useAuth();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const [emailFocused, setEmailFocused] = useState(false);
    const [passwordFocused, setPasswordFocused] = useState(false);

    const [emailTouched, setEmailTouched] = useState(false);
    const [passwordTouched, setPasswordTouched] = useState(false);

    const [clientErrors, setClientErrors] = useState<{ email?: string; password?: string }>({});
    const [serverError, setServerError] = useState<string | null>(null);

    const passwordInputRef = useRef<TextInput>(null);

    const validateEmail = (value: string): string | undefined => {
        const trimmed = value.trim();
        if (!trimmed) {
            return 'Email is required';
        }
        if (!EMAIL_REGEX.test(trimmed)) {
            return 'Please enter a valid email address';
        }
        return undefined;
    };

    const validatePassword = (value: string): string | undefined => {
        if (!value) {
            return 'Password is required';
        }
        if (value.length < 6) {
            return 'Password must be at least 6 characters';
        }
        return undefined;
    };

    const handleEmailChange = (text: string) => {
        setEmail(text);
        setServerError(null);
        if (emailTouched) {
            setClientErrors((prev) => ({ ...prev, email: validateEmail(text) }));
        }
    };

    const handlePasswordChange = (text: string) => {
        setPassword(text);
        setServerError(null);
        if (passwordTouched) {
            setClientErrors((prev) => ({ ...prev, password: validatePassword(text) }));
        }
    };

    const handleEmailBlur = () => {
        setEmailTouched(true);
        setEmailFocused(false);
        setClientErrors((prev) => ({ ...prev, email: validateEmail(email) }));
    };

    const handlePasswordBlur = () => {
        setPasswordTouched(true);
        setPasswordFocused(false);
        setClientErrors((prev) => ({ ...prev, password: validatePassword(password) }));
    };

    const handleSubmit = async () => {
        setEmailTouched(true);
        setPasswordTouched(true);

        const emailError = validateEmail(email);
        const passwordError = validatePassword(password);

        setClientErrors({
            email: emailError,
            password: passwordError,
        });

        if (emailError || passwordError) {
            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            }
            return;
        }

        setIsSubmitting(true);
        setServerError(null);

        try {
            const deviceName =
                Device.modelName ?? Device.deviceName ?? `${Platform.OS.toUpperCase()} App`;

            const response = await apiRequest<LoginApiResponse>('/auth/login', {
                method: 'POST',
                body: {
                    email: email.trim(),
                    password,
                    device_name: deviceName,
                },
            });

            const authToken =
                response.token ??
                response.access_token ??
                response.data?.token ??
                response.data?.access_token;

            let authUser = response.user ?? response.data?.user;

            if (!authToken) {
                throw new ApiError('Unexpected response from server: Missing authentication token.', 500);
            }

            if (!authUser) {
                const meResponse = await apiRequest<{ data: User }>('/auth/me');
                authUser = meResponse.data;
            }

            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            }

            await login(authToken, authUser);
        } catch (error: any) {
            if (Platform.OS !== 'web') {
                Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
            }

            if (error instanceof ApiError) {
                if (error.validationErrors) {
                    setClientErrors({
                        email: error.validationErrors.email?.[0],
                        password: error.validationErrors.password?.[0],
                    });
                }
                setServerError(error.message || 'Login failed. Please check your credentials.');
            } else {
                setServerError('An unexpected error occurred. Please try again.');
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const emailErrorMessage = emailTouched ? clientErrors.email : undefined;
    const passwordErrorMessage = passwordTouched ? clientErrors.password : undefined;

    return (
        <View style={[styles.screen, { backgroundColor: colors.base300 }]}>
            <SafeAreaView style={styles.safeArea}>
                <KeyboardAvoidingView
                    behavior={Platform.OS === 'ios' ? 'padding' : undefined}
                    style={styles.keyboardView}>
                    <ScrollView
                        contentContainerStyle={styles.scrollContent}
                        keyboardShouldPersistTaps="handled"
                        showsVerticalScrollIndicator={false}>
                        <View style={styles.container}>
                            {/* DaisyUI Card: bg-base-100 */}
                            <View
                                style={[
                                    styles.card,
                                    {
                                        backgroundColor: colors.base100,
                                        borderColor: colors.cardBorder,
                                    },
                                ]}>
                                {/* Card Body */}
                                <View style={styles.cardBody}>
                                    {/* Card Title */}
                                    <ThemedText style={[styles.cardTitle, { color: colors.baseContent }]}>
                                        Login
                                    </ThemedText>

                                    {/* Server Error Alert */}
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

                                    {/* Form */}
                                    <View style={styles.form}>
                                        {/* Email Field */}
                                        <View style={styles.formControl}>
                                            <ThemedText style={[styles.label, { color: colors.baseContent }]}>
                                                Email
                                            </ThemedText>
                                            <TextInput
                                                style={[
                                                    styles.input,
                                                    {
                                                        backgroundColor: colors.base200,
                                                        borderColor: emailErrorMessage
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
                                                value={email}
                                                onChangeText={handleEmailChange}
                                                onFocus={() => setEmailFocused(true)}
                                                onBlur={handleEmailBlur}
                                                returnKeyType="next"
                                                onSubmitEditing={() => passwordInputRef.current?.focus()}
                                                editable={!isSubmitting}
                                            />
                                            {emailErrorMessage && (
                                                <ThemedText style={[styles.errorText, { color: colors.error }]}>
                                                    {emailErrorMessage}
                                                </ThemedText>
                                            )}
                                        </View>

                                        {/* Password Field */}
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
                                                        borderColor: passwordErrorMessage
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
                                                    value={password}
                                                    onChangeText={handlePasswordChange}
                                                    onFocus={() => setPasswordFocused(true)}
                                                    onBlur={handlePasswordBlur}
                                                    returnKeyType="go"
                                                    onSubmitEditing={handleSubmit}
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

                                            {passwordErrorMessage && (
                                                <ThemedText style={[styles.errorText, { color: colors.error }]}>
                                                    {passwordErrorMessage}
                                                </ThemedText>
                                            )}
                                        </View>

                                        {/* Submit Button: btn btn-primary */}
                                        <Pressable
                                            onPress={handleSubmit}
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

const styles = StyleSheet.create({
    screen: {
        flex: 1,
    },
    safeArea: {
        flex: 1,
    },
    keyboardView: {
        flex: 1,
    },
    scrollContent: {
        flexGrow: 1,
        justifyContent: 'center',
        alignItems: 'center',
        padding: 16,
    },
    container: {
        width: '100%',
        maxWidth: 400,
    },
    card: {
        borderRadius: 16, // --radius-box: 1rem
        borderWidth: 1, // --border: 1px
        ...Platform.select({
            ios: {
                shadowColor: '#000',
                shadowOffset: { width: 0, height: 2 },
                shadowOpacity: 0.05,
                shadowRadius: 8,
            },
            android: {
                elevation: 2,
            },
            web: {
                boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
            },
        }),
    },
    cardBody: {
        padding: 24,
        gap: 16,
    },
    cardTitle: {
        fontSize: 22,
        fontWeight: '700',
        lineHeight: 28,
    },
    alertError: {
        borderWidth: 1,
        borderRadius: 8,
        paddingHorizontal: 12,
        paddingVertical: 10,
    },
    alertErrorText: {
        fontSize: 13,
        lineHeight: 18,
        fontWeight: '500',
    },
    form: {
        gap: 16,
    },
    formControl: {
        gap: 6,
    },
    labelRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    label: {
        fontSize: 14,
        fontWeight: '600',
    },
    input: {
        height: 44,
        borderRadius: 8, // --radius-field: 0.5rem
        borderWidth: 1,
        paddingHorizontal: 14,
        fontSize: 15,
    },
    passwordInputContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        height: 44,
        borderRadius: 8, // --radius-field: 0.5rem
        borderWidth: 1,
        paddingLeft: 14,
        paddingRight: 8,
    },
    passwordInput: {
        flex: 1,
        height: '100%',
        fontSize: 15,
        paddingVertical: 0,
    },
    eyeButton: {
        padding: 6,
        justifyContent: 'center',
        alignItems: 'center',
    },
    errorText: {
        fontSize: 12,
        fontWeight: '500',
        marginTop: 2,
    },
    buttonPrimary: {
        height: 44,
        borderRadius: 8, // --radius-field: 0.5rem
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: 8,
    },
    buttonText: {
        fontSize: 15,
        fontWeight: '600',
    },
});
