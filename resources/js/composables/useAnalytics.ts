import { hasAcceptedCookies } from './useCookieConsent';

declare global {
    interface Window {
        dataLayer: unknown[];
        gtag?: (...args: unknown[]) => void;
        __studioGaInitialized?: boolean;
        [key: `ga-disable-${string}`]: boolean | undefined;
    }
}

const GA_MEASUREMENT_ID = import.meta.env.VITE_GA_MEASUREMENT_ID;

function hasMeasurementId() {
    return typeof GA_MEASUREMENT_ID === 'string' && GA_MEASUREMENT_ID.trim().length > 0;
}

function ensureGtagBase() {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function gtag(...args: unknown[]) {
        window.dataLayer.push(args);
    };
}

function setGaDisabled(disabled: boolean) {
    if (!hasMeasurementId()) {
        return;
    }

    window[`ga-disable-${GA_MEASUREMENT_ID}`] = disabled;
}

export function enableAnalytics() {
    if (!hasMeasurementId()) {
        return false;
    }

    setGaDisabled(false);

    if (!window.__studioGaInitialized) {
        const scriptExists = document.querySelector(`script[data-ga-id="${GA_MEASUREMENT_ID}"]`);

        if (!scriptExists) {
            const script = document.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_MEASUREMENT_ID}`;
            script.dataset.gaId = GA_MEASUREMENT_ID;
            document.head.appendChild(script);
        }

        ensureGtagBase();
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
    setGaDisabled(true);
}

export function initializeAnalyticsIfConsented() {
    if (hasAcceptedCookies()) {
        enableAnalytics();
    } else {
        disableAnalytics();
    }
}

export function trackPageViewIfConsented(path?: string) {
    if (!hasAcceptedCookies()) {
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
