import { toValue, watchEffect } from 'vue';

type MaybeGetter<T> = T | (() => T);

type SeoMetaOptions = {
    title: MaybeGetter<string>;
    description: MaybeGetter<string>;
    image?: MaybeGetter<string>;
    noindex?: MaybeGetter<boolean>;
};

const SITE_NAME = 'studio kristian';
const DEFAULT_IMAGE = '/assets/logo.png';

function upsertMeta(selector: string, tagName: 'meta' | 'link', attributes: Record<string, string>) {
    let element = document.head.querySelector(selector);

    if (!element) {
        element = document.createElement(tagName);
        document.head.appendChild(element);
    }

    Object.entries(attributes).forEach(([key, value]) => {
        element!.setAttribute(key, value);
    });
}

function canonicalUrl(): string {
    return `${window.location.origin}${window.location.pathname}`;
}

function absoluteUrl(path: string): string {
    if (/^https?:\/\//.test(path)) {
        return path;
    }

    return `${window.location.origin}${path}`;
}

export function useSeoMeta(options: SeoMetaOptions) {
    watchEffect(() => {
        const nextTitle = toValue(options.title).trim();
        const nextDescription = toValue(options.description).trim();
        const nextImage = absoluteUrl(
            (options.image ? toValue(options.image) : '') || DEFAULT_IMAGE
        );
        const isNoindex = options.noindex ? Boolean(toValue(options.noindex)) : false;
        const url = canonicalUrl();

        const fullTitle = nextTitle.includes(SITE_NAME)
            ? nextTitle
            : `${nextTitle} | ${SITE_NAME}`;

        if (nextTitle) {
            document.title = fullTitle;

            upsertMeta('meta[property="og:title"]', 'meta', {
                property: 'og:title',
                content: fullTitle,
            });

            upsertMeta('meta[name="twitter:title"]', 'meta', {
                name: 'twitter:title',
                content: fullTitle,
            });
        }

        if (nextDescription) {
            upsertMeta('meta[name="description"]', 'meta', {
                name: 'description',
                content: nextDescription,
            });

            upsertMeta('meta[property="og:description"]', 'meta', {
                property: 'og:description',
                content: nextDescription,
            });

            upsertMeta('meta[name="twitter:description"]', 'meta', {
                name: 'twitter:description',
                content: nextDescription,
            });
        }

        upsertMeta('meta[name="robots"]', 'meta', {
            name: 'robots',
            content: isNoindex ? 'noindex, nofollow' : 'index, follow',
        });

        upsertMeta('link[rel="canonical"]', 'link', {
            rel: 'canonical',
            href: url,
        });

        upsertMeta('meta[property="og:site_name"]', 'meta', {
            property: 'og:site_name',
            content: SITE_NAME,
        });

        upsertMeta('meta[property="og:type"]', 'meta', {
            property: 'og:type',
            content: 'website',
        });

        upsertMeta('meta[property="og:url"]', 'meta', {
            property: 'og:url',
            content: url,
        });

        upsertMeta('meta[property="og:image"]', 'meta', {
            property: 'og:image',
            content: nextImage,
        });

        upsertMeta('meta[name="twitter:card"]', 'meta', {
            name: 'twitter:card',
            content: 'summary_large_image',
        });

        upsertMeta('meta[name="twitter:image"]', 'meta', {
            name: 'twitter:image',
            content: nextImage,
        });
    });
}
