import { useRouter } from 'expo-router';
import { Platform, Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BottomTabInset, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { useAuth } from '@/providers/AuthProvider';

function getInitials(name?: string): string {
  if (!name) return 'U';
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
  }
  return parts[0].slice(0, 2).toUpperCase();
}

function getFormattedDate(): string {
  const date = new Date();
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

export default function HomeScreen() {
  const colors = useTheme();
  const { user } = useAuth();
  const router = useRouter();

  const initials = getInitials(user?.name);
  const formattedDate = getFormattedDate();

  const handleProfilePress = () => {
    router.navigate('/(tabs)/profile');
  };

  return (
    <View style={[styles.screen, { backgroundColor: colors.base300 }]}>
      <SafeAreaView style={styles.safeArea} edges={['top']}>

        {/* Top Header Bar with Profile Avatar */}
        <View style={styles.header}>
          <View style={styles.headerTextGroup}>
            <ThemedText style={[styles.brandTitle, { color: colors.baseContent }]}>
              MealApp
            </ThemedText>
            <ThemedText style={[styles.dateText, { color: colors.textSecondary }]}>
              {formattedDate}
            </ThemedText>
          </View>

          {/* Profile Avatar Button */}
          <Pressable
            style={({ pressed }) => [
              styles.avatarButton,
              {
                backgroundColor: colors.base200,
                borderColor: colors.cardBorder,
                opacity: pressed ? 0.8 : 1,
                transform: [{ scale: pressed ? 0.95 : 1 }],
              },
            ]}
            onPress={handleProfilePress}
            accessibilityRole="button"
            accessibilityLabel="Open Profile"
          >
            <ThemedText style={[styles.avatarText, { color: colors.primary }]}>
              {initials}
            </ThemedText>
          </Pressable>
        </View>

        {/* Main Dashboard Content */}
        <View style={styles.content}>
          <ThemedView
            type="backgroundElement"
            style={[styles.placeholderCard, { borderColor: colors.cardBorder }]}
          >
            <ThemedText type="smallBold" style={{ color: colors.baseContent }}>
              Today's Dashboard
            </ThemedText>
            <ThemedText type="small" style={{ color: colors.textSecondary }}>
              Welcome back, {user?.name || 'Member'}.
            </ThemedText>
          </ThemedView>
        </View>
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
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  headerTextGroup: {
    gap: 2,
  },
  brandTitle: {
    fontSize: 22,
    fontWeight: '800',
    letterSpacing: -0.5,
  },
  dateText: {
    fontSize: 13,
    fontWeight: '500',
  },
  avatarButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1.5,
    ...Platform.select({
      ios: {
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.06,
        shadowRadius: 4,
      },
      android: { elevation: 2 },
      web: { boxShadow: '0 2px 4px rgba(0,0,0,0.06)' },
    }),
  },
  avatarText: {
    fontSize: 16,
    fontWeight: '700',
  },
  content: {
    flex: 1,
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: BottomTabInset + Spacing.four,
  },
  placeholderCard: {
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    gap: 8,
  },
});

