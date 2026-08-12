import { toValue, watchEffect } from 'vue';

type MaybeGetter<T> = T | (() => T);

type SeoMetaOptions = {
    title: MaybeGetter<string>;
    description: MaybeGetter<string>;
};

function upsertMetaDescription(content: string) {
    let meta = document.querySelector('meta[name="description"]');

    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'description');
        document.head.appendChild(meta);
    }

    meta.setAttribute('content', content);
}

export function useSeoMeta(options: SeoMetaOptions) {
    watchEffect(() => {
        const nextTitle = toValue(options.title).trim();
        const nextDescription = toValue(options.description).trim();

        if (nextTitle) {
            document.title = nextTitle;
        }

        if (nextDescription) {
            upsertMetaDescription(nextDescription);
        }
    });
}
