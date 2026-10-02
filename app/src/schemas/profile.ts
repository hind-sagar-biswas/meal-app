import { z } from 'zod';

export const profileSchema = z.object({
    name: z.string().min(2, 'Name must be at least 3 characters long.'),
    email: z.email('Invalid email address.'),
});
export type ProfileSchema = z.infer<typeof profileSchema>;

export const passwordSchema = z.object({
    current_password: z.string().min(1, 'Current password is required'),
    password: z.string().min(6, 'New password must be at least 6 characters'),
    password_confirmation: z.string().min(1, 'Please confirm your password'),
}).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords do not match",
    path: ["password_confirmation"],
});
export type PasswordSchema = z.infer<typeof passwordSchema>;