export type CookieType = 'necessary' | 'analytics' | 'marketing';
export type CookiePreferences = Record<CookieType, boolean>;

const COOKIE_PREFERENCES_KEY = 'studio-cookie-preferences';
const COOKIE_CONSENT_STATUS_KEY = 'studio-cookie-consent-status';

const DEFAULT_PREFERENCES: CookiePreferences = {
    necessary: true,
    analytics: false,
    marketing: false,
};

export function getCookiePreferences(): CookiePreferences | null {
    try {
        const value = localStorage.getItem(COOKIE_PREFERENCES_KEY);
        if (value) {
            return JSON.parse(value);
        }
    } catch (e) {
        console.error('Failed to parse cookie preferences', e);
    }
    return null;
}

export function setCookiePreferences(preferences: Partial<CookiePreferences>) {
    const currentPrefs = getCookiePreferences() || DEFAULT_PREFERENCES;
    const newPrefs = { ...currentPrefs, ...preferences };
    localStorage.setItem(COOKIE_PREFERENCES_KEY, JSON.stringify(newPrefs));
    localStorage.setItem(COOKIE_CONSENT_STATUS_KEY, 'set');
}

export function getCookieConsentStatus() {
    return localStorage.getItem(COOKIE_CONSENT_STATUS_KEY);
}

export function hasCookieConsentBeenSet(): boolean {
    return getCookieConsentStatus() !== null;
}

export function isCookieTypeAllowed(cookieType: CookieType): boolean {
    const prefs = getCookiePreferences();
    if (!prefs) return false;
    return prefs[cookieType] === true;
}

export function hasAcceptedAnalytics(): boolean {
    return isCookieTypeAllowed('analytics');
}
