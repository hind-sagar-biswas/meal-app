import * as SecureStore from 'expo-secure-store';

const AUTH_TOKEN_KEY = 'auth_token';

export async function loadAuthToken(): Promise<string | null> {
  return SecureStore.getItemAsync(AUTH_TOKEN_KEY);
}

export async function saveAuthToken(authToken: string): Promise<void> {
  await SecureStore.setItemAsync(AUTH_TOKEN_KEY, authToken);
}

export async function clearAuthToken(): Promise<void> {
  await SecureStore.deleteItemAsync(AUTH_TOKEN_KEY);
}