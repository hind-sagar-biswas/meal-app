import { apiRequest, setApiAuthToken, setUnauthorizedHandler } from '@/lib/api-client';
import { clearAuthToken, loadAuthToken, saveAuthToken } from '@/lib/session-storage';
import React, { createContext, useContext, useEffect, useState } from 'react';

export type User = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
};

type AuthContextType = {
    user: User | null;
    isLoading: boolean;
    login: (token: string, user: User) => Promise<void>;
    logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        // Set up the unauthorized interceptor to automatically kick users out if the token dies
        setUnauthorizedHandler(() => {
            setUser(null);
            clearAuthToken();
            setApiAuthToken(null);
        });

        async function initializeAuth() {
            try {
                const token = await loadAuthToken();
                if (token) {
                    setApiAuthToken(token);
                    // Validate the token and ensure the user is still active
                    const response = await apiRequest<{ user: User }>('/auth/me');
                    setUser(response.user);
                }
            } catch (error) {
                console.error('Auth validation failed on startup:', error);
            } finally {
                setIsLoading(false);
            }
        }

        initializeAuth();
    }, []);

    const login = async (token: string, user: User) => {
        await saveAuthToken(token);
        setApiAuthToken(token);
        setUser(user);
    };

    const logout = async () => {
        try {
            await apiRequest('/auth/logout', { method: 'POST' }); // Rejects push tokens server-side
        } catch (error) {
            console.error('Server logout failed, clearing local state anyway', error);
        } finally {
            await clearAuthToken();
            setApiAuthToken(null);
            setUser(null);
        }
    };

    return (
        <AuthContext.Provider value={{ user, isLoading, login, logout }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) throw new Error('useAuth must be used within an AuthProvider');
    return context;
}