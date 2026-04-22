import { hasAcceptedAnalytics } from './useCookieConsent';

declare global {
    interface Window {
        dataLayer: unknown[];
        gtag?: (...args: unknown[]) => void;
        __studioGaInitialized?: boolean;
        __GA_MEASUREMENT_ID?: string;
    }
}

const GA_MEASUREMENT_ID = window.__GA_MEASUREMENT_ID ?? '';

function hasMeasurementId() {
    return typeof GA_MEASUREMENT_ID === 'string' && GA_MEASUREMENT_ID.trim().length > 0;
}

function ensureGtagBase() {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag(...args: unknown[]) {
        window.dataLayer.push(args);
    };
}

function hasGoogleTagScript() {
    if (!hasMeasurementId()) {
        return false;
    }

    return Array.from(document.scripts).some((script) => script.src.includes(`gtag/js?id=${GA_MEASUREMENT_ID}`));
}

function loadGoogleTagScript() {
    if (!hasMeasurementId() || hasGoogleTagScript()) {
        return;
    }

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`;
    document.head.appendChild(script);
}

function setConsent(analyticsGranted: boolean) {
    ensureGtagBase();

    window.gtag?.('consent', 'update', {
        ad_storage: 'denied',
        analytics_storage: analyticsGranted ? 'granted' : 'denied',
        functionality_storage: 'granted',
        personalization_storage: 'denied',
        security_storage: 'granted',
    });
}

export function enableAnalytics() {
    if (!hasMeasurementId()) {
        return false;
    }

    ensureGtagBase();
    loadGoogleTagScript();
    setConsent(true);

    if (!window.__studioGaInitialized) {
        window.gtag?.('js', new Date());
        window.gtag?.('config', GA_MEASUREMENT_ID, {
            anonymize_ip: true,
            send_page_view: false,
        });

        window.__studioGaInitialized = true;
    }

    return true;
}

export function disableAnalytics() {
    if (!hasMeasurementId()) {
        return;
    }

    ensureGtagBase();
    loadGoogleTagScript();
    setConsent(false);
}

export function initializeAnalyticsIfConsented() {
    if (!hasMeasurementId()) {
        return;
    }

    ensureGtagBase();
    loadGoogleTagScript();

    if (hasAcceptedAnalytics()) {
        enableAnalytics();
    } else {
        disableAnalytics();
    }
}

export function trackPageViewIfConsented(path?: string) {
    if (!hasAcceptedAnalytics()) {
        return;
    }

    if (!enableAnalytics() || !window.gtag) {
        return;
    }

    const pagePath = path || `${window.location.pathname}${window.location.search}`;

    window.gtag('event', 'page_view', {
        page_path: pagePath,
        page_location: window.location.href,
        page_title: document.title,
    });
}
