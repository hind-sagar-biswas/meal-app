import { Platform, StyleSheet } from "react-native";

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