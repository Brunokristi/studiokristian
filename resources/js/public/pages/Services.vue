<script setup lang="ts">
import {
    onMounted,
    ref,
    watch,
} from 'vue'

import { useI18n } from 'vue-i18n'

import {
    useGlobalActions,
} from '../composables/useGlobalActions'

import {
    useSeoMeta,
} from '../composables/useSeoMeta'

import Button from '@shared/components/Button.vue'
import GridLayout from '../components/GridLayout.vue'

const {
    openContacts,
} = useGlobalActions()

const {
    t,
    locale,
} = useI18n()

useSeoMeta({
    title: () =>
        t('seo.services.title'),

    description: () =>
        t('seo.services.description'),
})

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

type ServiceCard = {
    heading: string
    text: string
    services: ApiService[]
    image: string
    bgColor: string
    link: string
}

const cards =
    ref<ServiceCard[]>([])

const isLoading =
    ref(true)

async function loadServices() {
    isLoading.value = true

    try {
        const response =
            await fetch(
                `/api/services?locale=${encodeURIComponent(
                    locale.value
                )}`
            )

        if (!response.ok) {
            throw new Error(
                'Failed to load services'
            )
        }

        const serviceProducts:
            ApiServiceProduct[] =
            await response.json()

        cards.value =
            serviceProducts.map(
                serviceProduct => ({
                    heading:
                        serviceProduct.name,

                    text:
                        serviceProduct.description ||
                        '',

                    services:
                        serviceProduct.services,

                    image: '',

                    bgColor: '',

                    /*
                     * Each card opens its
                     * individual service page.
                     */
                    link:
                        `/services/${serviceProduct.slug}`,
                })
            )
    } catch (error) {
        console.error(error)

        cards.value = []
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadServices()
})

watch(
    () => locale.value,
    () => {
        loadServices()
    }
)
</script>

<template>
    <main
        class="
            py-5
            flex
            flex-col
            gap-20
        "
        data-theme="light"
    >
        <!-- Header -->
        <section
            class="
                px-6
                flex
                flex-col
                gap-4
            "
        >
            <h1 class="h1">
                {{ t('services.title') }}
            </h1>

            <p class="p max-w-2xl">
                {{
                    t(
                        'services.description'
                    )
                }}
            </p>
        </section>

        <!-- Services -->
        <section>
            <p
                v-if="isLoading"
                class="p px-6"
            >
                {{
                    t(
                        'services.loading'
                    )
                }}
            </p>

            <GridLayout
                v-else
                :cards="cards"
            />
        </section>

        <!-- Contact -->
        <Button
            :text="
                t(
                    'services.contact'
                )
            "
            variant="dark"
            @click="openContacts"
        />
    </main>
</template>

