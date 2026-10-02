import BottomSheet from '@expo/ui/community/bottom-sheet';
import { SymbolView } from 'expo-symbols';
import { useRef, useState } from 'react';
import { Alert, Pressable, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ChangePasswordForm } from '@/components/partials/ChangePasswordForm';
import { EditProfileForm } from '@/components/partials/EditProfileForm';
import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/providers/AuthProvider';
import { profileScreenStyle } from '@/styles/profile';

function getInitials(name?: string): string {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
}

export default function ProfileScreen() {
    const { user, logout } = useAuth();
    const colors = useTheme();

    const [isProfileOpen, setIsProfileOpen] = useState(false);
    const [isPasswordOpen, setIsPasswordOpen] = useState(false);

    const profileSheetRef = useRef<BottomSheet>(null);
    const passwordSheetRef = useRef<BottomSheet>(null);

    const handleLogout = () => {
        Alert.alert('Logout', 'Are you sure you want to log out?', [
            { text: 'Cancel', style: 'cancel' },
            {
                text: 'Logout',
                style: 'destructive',
                onPress: () => logout(),
            },
        ]);
    };

    const initials = getInitials(user?.name);

    return (
        <View style={[styles.screen, { backgroundColor: colors.base300 }]}>
            <SafeAreaView style={styles.safeArea}>
                <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
                    {/* User Identity Card */}
                    <View
                        style={[
                            styles.headerCard,
                            {
                                backgroundColor: colors.base100,
                                borderColor: colors.cardBorder,
                            },
                        ]}
                    >
                        <View style={[styles.avatar, { backgroundColor: colors.base200 }]}>
                            <ThemedText style={[styles.avatarText, { color: colors.primary }]}>
                                {initials}
                            </ThemedText>
                        </View>

                        <View style={styles.userInfo}>
                            <ThemedText style={[styles.userName, { color: colors.baseContent }]}>
                                {user?.name || 'User Profile'}
                            </ThemedText>
                            <ThemedText style={[styles.userEmail, { color: colors.textSecondary }]}>
                                {user?.email}
                            </ThemedText>
                        </View>
                    </View>

                    {/* Account & Security Section */}
                    <View style={styles.section}>
                        <ThemedText style={[styles.sectionTitle, { color: colors.textSecondary }]}>
                            Account & Security
                        </ThemedText>

                        <View
                            style={[
                                styles.menuGroup,
                                {
                                    backgroundColor: colors.base100,
                                    borderColor: colors.cardBorder,
                                },
                            ]}
                        >
                            {/* Edit Profile */}
                            <Pressable
                                style={({ pressed }) => [
                                    styles.menuItem,
                                    { backgroundColor: pressed ? colors.base200 : colors.base100 },
                                ]}
                                onPress={() => profileSheetRef.current?.expand()}
                            >
                                <View style={[styles.menuIconBox, { backgroundColor: colors.base200 }]}>
                                    <SymbolView
                                        name={{
                                            ios: 'person.fill',
                                            android: 'person',
                                            web: 'person',
                                        }}
                                        size={16}
                                        tintColor={colors.primary}
                                    />
                                </View>
                                <ThemedText style={[styles.menuLabel, { color: colors.baseContent }]}>
                                    Edit Profile
                                </ThemedText>
                                <SymbolView
                                    name={{
                                        ios: 'chevron.right',
                                        android: 'chevron_right',
                                        web: 'chevron_right',
                                    }}
                                    size={14}
                                    tintColor={colors.textSecondary}
                                />
                            </Pressable>

                            {/* Divider */}
                            <View style={[styles.divider, { backgroundColor: colors.inputBorder }]} />

                            {/* Change Password */}
                            <Pressable
                                style={({ pressed }) => [
                                    styles.menuItem,
                                    { backgroundColor: pressed ? colors.base200 : colors.base100 },
                                ]}
                                onPress={() => passwordSheetRef.current?.expand()}
                            >
                                <View style={[styles.menuIconBox, { backgroundColor: colors.base200 }]}>
                                    <SymbolView
                                        name={{
                                            ios: 'lock.fill',
                                            android: 'lock',
                                            web: 'lock',
                                        }}
                                        size={16}
                                        tintColor={colors.primary}
                                    />
                                </View>
                                <ThemedText style={[styles.menuLabel, { color: colors.baseContent }]}>
                                    Change Password
                                </ThemedText>
                                <SymbolView
                                    name={{
                                        ios: 'chevron.right',
                                        android: 'chevron_right',
                                        web: 'chevron_right',
                                    }}
                                    size={14}
                                    tintColor={colors.textSecondary}
                                />
                            </Pressable>
                        </View>
                    </View>

                    {/* Session Section */}
                    <View style={styles.section}>
                        <ThemedText style={[styles.sectionTitle, { color: colors.textSecondary }]}>
                            Session
                        </ThemedText>

                        <View
                            style={[
                                styles.menuGroup,
                                {
                                    backgroundColor: colors.base100,
                                    borderColor: colors.cardBorder,
                                },
                            ]}
                        >
                            <Pressable style={({ pressed }) => [
                                styles.menuItem,
                                { backgroundColor: pressed ? colors.errorBackground : colors.base100 },
                            ]}
                                onPress={handleLogout}
                            >
                                <View style={[styles.menuIconBox, { backgroundColor: colors.errorBackground }]}>
                                    <SymbolView
                                        name={{
                                            ios: 'rectangle.portrait.and.arrow.right',
                                            android: 'logout',
                                            web: 'logout',
                                        }}
                                        size={16}
                                        tintColor={colors.error}
                                    />
                                </View>
                                <ThemedText style={[styles.menuLabel, { color: colors.error, fontWeight: '600' }]}>
                                    Log Out
                                </ThemedText>
                                <SymbolView
                                    name={{
                                        ios: 'chevron.right',
                                        android: 'chevron_right',
                                        web: 'chevron_right',
                                    }}
                                    size={14}
                                    tintColor={colors.error}
                                />
                            </Pressable>
                        </View>
                    </View>

                    {/* Profile Edit BottomSheet */}
                    <BottomSheet ref={profileSheetRef} index={-1} enablePanDownToClose>
                        <EditProfileForm onSuccess={() => profileSheetRef.current?.close()} />
                    </BottomSheet>

                    <BottomSheet ref={passwordSheetRef} index={-1} enablePanDownToClose>
                        <ChangePasswordForm onSuccess={() => passwordSheetRef.current?.close()} />
                    </BottomSheet>
                </ScrollView>
            </SafeAreaView>
        </View>
    );
}

const styles = profileScreenStyle;