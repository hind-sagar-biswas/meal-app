import { Platform, StyleSheet } from "react-native";

export const loginStyle = StyleSheet.create({
    screen: { flex: 1 },
    safeArea: { flex: 1 },
    keyboardView: { flex: 1 },
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
        borderRadius: 16,
        borderWidth: 1,
        ...Platform.select({
            ios: {
                shadowColor: '#000',
                shadowOffset: { width: 0, height: 2 },
                shadowOpacity: 0.05,
                shadowRadius: 8,
            },
            android: { elevation: 2 },
            web: { boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)' },
        }),
    },
    cardBody: { padding: 24, gap: 16 },
    cardTitle: { fontSize: 22, fontWeight: '700', lineHeight: 28 },
    alertError: {
        borderWidth: 1,
        borderRadius: 8,
        paddingHorizontal: 12,
        paddingVertical: 10,
    },
    alertErrorText: { fontSize: 13, lineHeight: 18, fontWeight: '500' },
    form: { gap: 16 },
    formControl: { gap: 6 },
    labelRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    label: { fontSize: 14, fontWeight: '600' },
    input: {
        height: 44,
        borderRadius: 8,
        borderWidth: 1,
        paddingHorizontal: 14,
        fontSize: 15,
    },
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