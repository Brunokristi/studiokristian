<script setup lang="ts">
import {
    computed,
    onMounted,
    ref,
    watch,
} from 'vue'

import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'

import {
    useGlobalActions,
} from '../composables/useGlobalActions'

import {
    useSeoMeta,
} from '../composables/useSeoMeta'

import Button from '../../shared/components/Button.vue'

const route = useRoute()

const {
    openContacts,
} = useGlobalActions()

const {
    t,
    locale,
} = useI18n()

type ApiService = {
    id: number
    name: string
}

type ApiServiceProduct = {
    id: number
    name: string
    slug: string
    description: string | null
    services: ApiService[]
}

const service =
    ref<ApiServiceProduct | null>(null)

const isLoading =
    ref(true)

const error =
    ref(false)

const slug = computed(() =>
    String(
        route.params.slug || ''
    )
)

useSeoMeta({
    title: () =>
        service.value?.name ||
        t('seo.service.title'),

    description: () =>
        service.value?.description ||
        t('seo.service.description'),
})

async function loadService() {
    isLoading.value = true
    error.value = false

    try {
        const response =
            await fetch(
                `/api/services/${encodeURIComponent(
                    slug.value
                )}?locale=${encodeURIComponent(
                    locale.value
                )}`
            )

        if (!response.ok) {
            throw new Error(
                'Failed to load service'
            )
        }

        service.value =
            await response.json()
    } catch (loadError) {
        console.error(loadError)

        service.value = null
        error.value = true
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadService()
})

watch(
    () => [
        slug.value,
        locale.value,
    ],
    () => {
        loadService()
    }
)
</script>

<template>
    <main
        class="
            min-h-screen
            py-10
            flex
            flex-col
            gap-24
        "
        data-theme="dark"
    >
        <!--
        |--------------------------------------------------------------------------
        | LOADING
        |--------------------------------------------------------------------------
        -->

        <section
            v-if="isLoading"
            class="
                min-h-[60vh]
                px-6
                flex
                items-center
            "
        >
            <p class="p text-light">
                {{
                    t(
                        'services.loading'
                    )
                }}
            </p>
        </section>

        <!--
        |--------------------------------------------------------------------------
        | NOT FOUND
        |--------------------------------------------------------------------------
        -->

        <section
            v-else-if="
                error ||
                !service
            "
            class="
                min-h-[60vh]
                px-6
                flex
                flex-col
                justify-center
                gap-6
            "
        >
            <p
                class="
                    p
                    text-light
                    opacity-50
                "
            >
                404
            </p>

            <h1
                class="
                    h1
                    text-light
                    max-w-4xl
                "
            >
                {{
                    t(
                        'services.notFound'
                    )
                }}
            </h1>

            <p
                class="
                    p
                    text-light
                    opacity-70
                    max-w-2xl
                "
            >
                {{
                    t(
                        'services.notFoundDescription'
                    )
                }}
            </p>
        </section>

        <!--
        |--------------------------------------------------------------------------
        | SERVICE
        |--------------------------------------------------------------------------
        -->

        <template v-else>
            <!--
            |--------------------------------------------------------------------------
            | HERO
            |--------------------------------------------------------------------------
            -->

            <section
                class="
                    px-6
                    flex
                    flex-col
                    gap-10
                "
            >
                <div
                    class="
                        flex
                        flex-col
                        gap-8
                        justify-center
                        items-center
                    "
                >
                    <h1
                        class="
                            h2
                            text-light
                        "
                    >
                        {{ service.name }}
                    </h1>

                    <p
                        v-if="
                            service.description
                        "
                        class="
                            p
                            text-light
                            uppercase
                            text-center
                            max-w-3xl
                        "
                    >
                        {{
                            service.description
                        }}
                    </p>
                </div>
            </section>

            <!--
            |--------------------------------------------------------------------------
            | INCLUDED SERVICES
            |--------------------------------------------------------------------------
            -->

            <section
                v-if="
                    service.services.length
                "
                class="
                    flex
                    flex-col
                    gap-12
                "
            >
                <div
                    class="
                        px-6
                        flex
                        flex-col
                        gap-4
                    "
                >
                    <h2
                        class="
                            h2
                            text-accent
                        "
                    >
                        {{
                            t(
                                'services.included'
                            )
                        }}
                    </h2>
                </div>

                <!-- Items -->
                <div
                    class="
                        flex
                        flex-col
                    "
                >
                    <article
                        v-for="(
                            item,
                            index
                        ) in service.services"
                        :key="
                            item.id
                        "
                        class="
                            group
                            px-6
                            py-4
                            border-t
                            border-light
                            flex
                            items-start
                            gap-6
                            transition-colors
                            duration-300
                            hover:bg-accent
                        "
                    >
                        <!-- Name -->
                        <h3
                            class="
                                h3
                                text-light
                                transition-colors
                                duration-300
                            "
                        >
                            {{
                                item.name
                            }}
                        </h3>
                    </article>

                    <div
                        class="
                            border-t
                            border-light
                        "
                    ></div>
                </div>
            </section>

            <!--
            |--------------------------------------------------------------------------
            | NO SERVICES
            |--------------------------------------------------------------------------
            -->

            <section
                v-else
                class="
                    px-6
                "
            >
                <p
                    class="
                        p
                        text-light
                        opacity-50
                    "
                >
                    —
                </p>
            </section>

            <!--
            |--------------------------------------------------------------------------
            | CONTACT
            |--------------------------------------------------------------------------
            -->

            <section
                class="
                    px-6
                    flex
                    flex-col
                    gap-8
                "
            >   
                <p
                    class="
                        p
                        text-light
                        uppercase
                        text-center
                    "
                >
                    {{
                        t(
                            'services.reassure'
                        )
                    }}
                </p>

                <Button
                    :text="
                        t(
                            'services.contact'
                        )
                    "
                    variant="light"
                    @click="
                        openContacts
                    "
                />
            </section>
        </template>
    </main>
</template>
