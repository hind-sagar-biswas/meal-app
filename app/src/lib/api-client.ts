const API_BASE_URL = 'https://mealapp.hindbiswas.com/api';

// const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL;

// if (!API_BASE_URL) {
//   throw new Error('EXPO_PUBLIC_API_URL is not set. Add it to .env and restart the server.');
// }

export class ApiError extends Error {
    readonly status: number;
    readonly validationErrors: Record<string, string[]> | null;

    constructor(
        message: string,
        status: number,
        validationErrors: Record<string, string[]> | null = null,
    ) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.validationErrors = validationErrors;
    }
}

type RequestOptions = {
    method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';
    body?: unknown;
    idempotencyKey?: string;
};

// In-memory copy of the auth token, so requests do not read SecureStore every time.
let currentAuthToken: string | null = null;
let onUnauthorized: (() => void) | null = null;

export function setApiAuthToken(authToken: string | null): void {
    currentAuthToken = authToken;
}

// Called when the server rejects a token we sent (401), so the app can go back to login.
export function setUnauthorizedHandler(handler: (() => void) | null): void {
    onUnauthorized = handler;
}

export async function apiRequest<ResponseBody>(
    path: string, // always starts with '/', e.g. '/auth/login'
    options: RequestOptions = {},
): Promise<ResponseBody> {
    const { method = 'GET', body, idempotencyKey } = options;

    const headers: Record<string, string> = { Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (currentAuthToken) headers.Authorization = `Bearer ${currentAuthToken}`;
    if (idempotencyKey) headers['X-Idempotency-Key'] = idempotencyKey;

    let response: Response;
    try {
        response = await fetch(`${API_BASE_URL}${path}`, {
            method,
            headers,
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new ApiError('Cannot reach the server. Check your internet connection.', 0);
    }

    const responseText = await response.text();
    let responseBody: any = null;
    if (responseText) {
        try {
            responseBody = JSON.parse(responseText);
        } catch {
            responseBody = null;
        }
    }

    if (!response.ok) {
        // Only treat 401 as "session expired" if we actually sent a token (a wrong password is not that).
        if (response.status === 401 && currentAuthToken) onUnauthorized?.();
        throw new ApiError(
            responseBody?.message ?? `Request failed (${response.status}).`,
            response.status,
            responseBody?.errors ?? null,
        );
    }

    return responseBody as ResponseBody;
}