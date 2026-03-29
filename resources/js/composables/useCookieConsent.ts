export type CookieConsentStatus = 'accepted' | 'rejected';

const COOKIE_CONSENT_KEY = 'studio-cookie-consent';

export function getCookieConsent(): CookieConsentStatus | null {
    const value = localStorage.getItem(COOKIE_CONSENT_KEY);
    return value === 'accepted' || value === 'rejected' ? value : null;
}

export function setCookieConsent(status: CookieConsentStatus) {
    localStorage.setItem(COOKIE_CONSENT_KEY, status);
}

export function hasAcceptedCookies() {
    return getCookieConsent() === 'accepted';
}
