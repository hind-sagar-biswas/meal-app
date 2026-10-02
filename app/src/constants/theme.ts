/**
 * Below are the colors that are used in the app. The colors are defined in the light and dark mode.
 * There are many other ways to style your app. For example, [Nativewind](https://www.nativewind.dev/), [Tamagui](https://tamagui.dev/), [unistyles](https://reactnativeunistyles.vercel.app), etc.
 */

import '@/global.css';

import { Platform } from 'react-native';

export const Colors = {
  light: {
    // DaisyUI Winter Layering Hierarchy:
    // base-300 = furthest back canvas / screen background
    // base-200 = intermediate layer (inputs, sections, recessed elements)
    // base-100 = elevated cards, modals, highest surface
    // base-content = text
    text: '#394E6A', // --color-base-content
    textSecondary: '#6B7E96',
    background: '#E3E9F0', // --color-base-300 (backest background)
    backgroundElement: '#F2F7FF', // --color-base-200 (intermediate containers/inputs)
    backgroundSelected: '#D2DCE8',
    card: '#FFFFFF', // --color-base-100 (highest elevated cards)
    cardBorder: '#DCE4EE',
    inputBackground: '#F2F7FF', // --color-base-200
    inputBorder: '#D2DCE8',
    inputBorderFocused: '#047AFF',
    primary: '#047AFF', // --color-primary
    primaryDark: '#0062D2',
    primaryLight: '#E0EFFF', // --color-primary-content
    primaryForeground: '#FFFFFF',
    secondary: '#463AA2', // --color-secondary
    secondaryLight: '#EAE8FA', // --color-secondary-content
    accent: '#C148AC', // --color-accent
    accentContent: '#280024',
    neutral: '#021431', // --color-neutral
    neutralContent: '#CBE2F4',
    info: '#93E6FB',
    infoContent: '#0C3E4C',
    success: '#8CEAD4',
    successBackground: '#E6FBF5',
    warning: '#FEE184',
    warningContent: '#3B310A',
    error: '#F87272',
    errorBackground: '#FEEAEA',
    errorBorder: '#FCA5A5',

    // DaisyUI direct token aliases
    base100: '#FFFFFF',
    base200: '#F2F7FF',
    base300: '#E3E9F0',
    baseContent: '#394E6A',
  },
  dark: {
    // DaisyUI Sunset Theme (color-scheme: "dark")
    // Layering: base-300 (#12151D) -> base-200 (#161923) -> base-100 (#1A1E29)
    text: '#A6BCDA', // --color-base-content (oklch(77.383% 0.043 245.096))
    textSecondary: '#6C7E99',
    background: '#12151D', // --color-base-300 (oklch(18% 0.019 237.69))
    backgroundElement: '#161923', // --color-base-200 (oklch(20% 0.019 237.69))
    backgroundSelected: '#232836',
    card: '#1A1E29', // --color-base-100 (oklch(22% 0.019 237.69))
    cardBorder: '#232836',
    inputBackground: '#161923', // --color-base-200
    inputBorder: '#282E3E',
    inputBorderFocused: '#FF865B', // --color-primary
    primary: '#FF865B', // --color-primary (oklch(74.703% 0.158 39.947))
    primaryDark: '#F56E3F',
    primaryLight: '#341105', // --color-primary-content (oklch(14.94% 0.031 39.947))
    primaryForeground: '#341105', // --color-primary-content (dark text on bright sunset coral)
    secondary: '#FF6F8F', // --color-secondary (oklch(72.537% 0.177 2.72))
    secondaryLight: '#350812', // --color-secondary-content
    accent: '#C686FF', // --color-accent (oklch(71.294% 0.166 299.844))
    accentContent: '#200C33', // --color-accent-content
    neutral: '#232836', // --color-neutral (oklch(26% 0.019 237.69))
    neutralContent: '#8B9EBA', // --color-neutral-content (oklch(70% 0.019 237.69))
    info: '#7BD9F6', // --color-info (oklch(85.559% 0.085 206.015))
    infoContent: '#0B2833',
    success: '#83E7A8', // --color-success (oklch(85.56% 0.085 144.778))
    successBackground: '#0E2F1B',
    warning: '#FFDE7B', // --color-warning (oklch(85.569% 0.084 74.427))
    warningContent: '#392F08',
    error: '#FF7878', // --color-error (oklch(85.511% 0.078 16.886))
    errorBackground: '#3C1313',
    errorBorder: '#5C1D1D',

    // DaisyUI direct token aliases
    base100: '#1A1E29',
    base200: '#161923',
    base300: '#12151D',
    baseContent: '#A6BCDA',
  },
} as const;

export type ThemeColor = keyof typeof Colors.light & keyof typeof Colors.dark;

export const Fonts = Platform.select({
  ios: {
    /** iOS `UIFontDescriptorSystemDesignDefault` */
    sans: 'system-ui',
    /** iOS `UIFontDescriptorSystemDesignSerif` */
    serif: 'ui-serif',
    /** iOS `UIFontDescriptorSystemDesignRounded` */
    rounded: 'ui-rounded',
    /** iOS `UIFontDescriptorSystemDesignMonospaced` */
    mono: 'ui-monospace',
  },
  default: {
    sans: 'normal',
    serif: 'serif',
    rounded: 'normal',
    mono: 'monospace',
  },
  web: {
    sans: 'var(--font-display)',
    serif: 'var(--font-serif)',
    rounded: 'var(--font-rounded)',
    mono: 'var(--font-mono)',
  },
});

export const Spacing = {
  half: 2,
  one: 4,
  two: 8,
  three: 16,
  four: 24,
  five: 32,
  six: 64,
} as const;

export const BottomTabInset = Platform.select({ ios: 50, android: 80 }) ?? 0;
export const MaxContentWidth = 800;
