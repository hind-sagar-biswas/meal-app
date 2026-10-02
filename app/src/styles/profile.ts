import { Platform, StyleSheet } from 'react-native';

export const profileScreenStyle = StyleSheet.create({
    screen: {
        flex: 1,
    },
    safeArea: {
        flex: 1,
    },
    scrollContent: {
        paddingHorizontal: 16,
        paddingTop: 16,
        paddingBottom: 48,
        gap: 20,
        maxWidth: 500,
        width: '100%',
        alignSelf: 'center',
    },
    headerCard: {
        borderRadius: 16,
        padding: 20,
        alignItems: 'center',
        gap: 12,
        ...Platform.select({
            ios: {
                shadowColor: '#000',
                shadowOffset: { width: 0, height: 2 },
                shadowOpacity: 0.04,
                shadowRadius: 6,
            },
            android: { elevation: 1 },
            web: { boxShadow: '0 2px 4px -1px rgba(0,0,0,0.04)' },
        }),
    },
    avatar: {
        width: 68,
        height: 68,
        borderRadius: 34,
        justifyContent: 'center',
        alignItems: 'center',
    },
    avatarText: {
        fontSize: 24,
        fontWeight: '700',
    },
    userInfo: {
        alignItems: 'center',
        gap: 4,
    },
    userName: {
        fontSize: 18,
        fontWeight: '700',
        lineHeight: 24,
    },
    userEmail: {
        fontSize: 13,
        lineHeight: 18,
    },
    statusBadge: {
        paddingHorizontal: 10,
        paddingVertical: 3,
        borderRadius: 12,
        marginTop: 4,
    },
    statusBadgeText: {
        fontSize: 11,
        fontWeight: '600',
        letterSpacing: 0.3,
        textTransform: 'uppercase',
    },
    section: {
        gap: 8,
    },
    sectionTitle: {
        fontSize: 12,
        fontWeight: '700',
        letterSpacing: 0.5,
        textTransform: 'uppercase',
        paddingHorizontal: 4,
    },
    menuGroup: {
        borderRadius: 16,
        borderWidth: 1,
        overflow: 'hidden',
        ...Platform.select({
            ios: {
                shadowColor: '#000',
                shadowOffset: { width: 0, height: 2 },
                shadowOpacity: 0.04,
                shadowRadius: 6,
            },
            android: { elevation: 1 },
            web: { boxShadow: '0 2px 4px -1px rgba(0,0,0,0.04)' },
        }),
    },
    menuItem: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 14,
        paddingHorizontal: 16,
        gap: 12,
    },
    menuIconBox: {
        width: 32,
        height: 32,
        borderRadius: 8,
        justifyContent: 'center',
        alignItems: 'center',
    },
    menuLabel: {
        flex: 1,
        fontSize: 15,
        fontWeight: '500',
    },
    divider: {
        height: 1,
        marginLeft: 60,
    },
});

export const profileFormStyle = StyleSheet.create({
    container: {
        paddingHorizontal: 24,
        paddingTop: 8,
        paddingBottom: 40,
    },
    form: { gap: 16 },
    formControl: { gap: 6 },
    label: { fontSize: 14, fontWeight: '600' },
    input: {
        height: 44,
        borderRadius: 8,
        borderWidth: 1,
        paddingHorizontal: 14,
        fontSize: 15,
    },
    errorText: { fontSize: 12, fontWeight: '500', marginTop: 2 },
    buttonPrimary: {
        height: 44,
        borderRadius: 8,
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: 8,
    },
    buttonText: { fontSize: 15, fontWeight: '600' },
});

export const passwordFormStyle = StyleSheet.create({
    container: {
        paddingHorizontal: 24,
        paddingTop: 8,
        paddingBottom: 40,
    },
    form: { gap: 16 },
    formControl: { gap: 6 },
    label: { fontSize: 14, fontWeight: '600' },
    passwordInputContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        height: 44,
        borderRadius: 8,
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
    errorText: { fontSize: 12, fontWeight: '500', marginTop: 2 },
    buttonPrimary: {
        height: 44,
        borderRadius: 8,
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: 8,
    },
    buttonText: { fontSize: 15, fontWeight: '600' },
});