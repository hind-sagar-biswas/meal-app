import { BottomTabInset, Spacing } from "@/constants/theme";
import { Platform, StyleSheet } from "react-native";

export const myMealsStyle = StyleSheet.create({
    card: {
        borderRadius: 16,
        padding: 16,
        borderWidth: 1,
        gap: 14,
    },
    cardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    cardTitle: {
        fontSize: 16,
        fontWeight: '700',
    },
    totalPill: {
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 12,
    },
    totalText: {
        fontSize: 12,
        fontWeight: '700',
    },
    columnsRow: {
        flexDirection: 'row',
        gap: 10,
    },
    mealSlot: {
        flex: 1,
        borderRadius: 12,
        paddingVertical: 12,
        paddingHorizontal: 6,
        alignItems: 'center',
        gap: 4,
    },
    slotEmoji: {
        fontSize: 22,
    },
    slotName: {
        fontSize: 12,
        fontWeight: '600',
    },
    slotCount: {
        fontSize: 20,
        fontFamily: 'monospace',
        fontWeight: '800',
        marginVertical: 7,
    },
    actionButton: {
        width: '100%',
        paddingVertical: 6,
        borderRadius: 8,
        borderWidth: 1,
        alignItems: 'center',
        justifyContent: 'center',
    },
    actionText: {
        fontSize: 11,
        fontWeight: '700',
    },
    editButton: {
        width: '100%',
        paddingVertical: 10,
        borderRadius: 10,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 8,
    },
    editButtonText: {
        fontSize: 13,
        fontWeight: '600',
    },
});

export const todayTallyStyle = StyleSheet.create({
    card: {
        borderRadius: 16,
        padding: 16,
        borderWidth: 1,
        gap: 12,
    },
    header: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    title: {
        fontSize: 15,
        fontWeight: '700',
    },
    totalUnits: {
        fontSize: 13,
        fontWeight: '700',
    },
    slotsRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
    },
    slot: {
        alignItems: 'center',
        gap: 2,
        flex: 1,
    },
    slotEmoji: {
        fontSize: 18,
    },
    slotName: {
        fontSize: 12,
        fontWeight: '600',
    },
    headcount: {
        fontSize: 24,
        fontFamily: 'monospace',
        fontWeight: '700',
    },
    units: {
        fontSize: 11,
        fontWeight: '500',
    },
});

export const roommatesTodayStyle = StyleSheet.create({
    card: {
        borderRadius: 16,
        padding: 16,
        borderWidth: 1,
        gap: 12,
    },
    title: {
        fontSize: 15,
        fontWeight: '700',
    },
    list: {
        gap: 8,
    },
    row: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingVertical: 6,
        borderBottomWidth: StyleSheet.hairlineWidth,
    },
    nameGroup: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
    },
    memberName: {
        fontSize: 14,
        fontWeight: '600',
    },
    guestBadge: {
        backgroundColor: 'rgba(16, 185, 129, 0.15)',
        paddingHorizontal: 6,
        paddingVertical: 2,
        borderRadius: 6,
    },
    guestText: {
        fontSize: 10,
        fontWeight: '700',
        color: '#10B981',
    },
    countsGroup: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 20,
    },
    countItem: {
        fontFamily: 'monospace',
        fontSize: 12,
        fontWeight: '500',
    },
    totalBadge: {
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 10,
    },
    totalBadgeText: {
        fontSize: 11,
        fontWeight: '700',
    },
});


export const homePageStyle = StyleSheet.create({
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
    scrollContent: {
        paddingHorizontal: 16,
        paddingTop: 8,
        paddingBottom: BottomTabInset + Spacing.four + 24,
        gap: 16,
    },
});