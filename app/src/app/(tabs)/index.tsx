import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
  Pressable,
  RefreshControl,
  ScrollView,
  View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { MyMealsCard } from '@/components/partials/MyMealsCard';
import { RoommatesTodayCard } from '@/components/partials/RoommatesTodayCard';
import { TodayTallyCard } from '@/components/partials/TodayTallyCard';
import { ThemedText } from '@/components/themed-text';
import { useTheme } from '@/hooks/use-theme';
import {
  useMyToday,
  useTodayMembers,
  useTodaySummary,
} from '@/hooks/use-today-meals';
import { useAuth } from '@/providers/AuthProvider';
import { homePageStyle } from '@/styles/home';

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

  const {
    data: myTodayData,
    isLoading: myTodayLoading,
    refetch: refetchMyToday,
  } = useMyToday();
  const { data: summaryData, refetch: refetchSummary } = useTodaySummary();
  const { data: membersData, refetch: refetchMembers } = useTodayMembers();

  const [refreshing, setRefreshing] = useState(false);

  const onRefresh = async () => {
    setRefreshing(true);
    await Promise.all([refetchMyToday(), refetchSummary(), refetchMembers()]);
    setRefreshing(false);
  };

  const meal = myTodayData?.data?.meal;
  const cutoffs = myTodayData?.data?.cutoffs;
  const serverTime = myTodayData?.data?.server_time;

  const handleProfilePress = () => {
    router.push('/profile');
  };

  const handleEditPress = () => {
    // Phase 5 will hook this up to the EditMealWithNote BottomSheet
  };

  return (
    <View style={[styles.screen, { backgroundColor: colors.base300 }]}>
      <SafeAreaView style={styles.safeArea} edges={['top']}>
        {/* Top Header Bar */}
        <View style={styles.header}>
          <View style={styles.headerTextGroup}>
            <ThemedText style={[styles.brandTitle, { color: colors.baseContent }]}>
              MealApp
            </ThemedText>
            <ThemedText style={[styles.dateText, { color: colors.textSecondary }]}>
              {formattedDate}
            </ThemedText>
          </View>

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
            onPress={handleProfilePress} accessibilityRole="button" accessibilityLabel="Open Profile">
            <ThemedText style={[styles.avatarText, { color: colors.primary }]}>
              {initials}
            </ThemedText>
          </Pressable>
        </View>

        {/* Scrollable Dashboard Body */}
        <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false} refreshControl={
          <RefreshControl refreshing={refreshing || myTodayLoading} onRefresh={onRefresh} tintColor={colors.primary} />
        }>

          <MyMealsCard meal={meal} cutoffs={cutoffs} onEditPress={handleEditPress} />

          <TodayTallyCard summary={summaryData?.data} />

          <RoommatesTodayCard members={membersData?.data} />
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

const styles = homePageStyle;