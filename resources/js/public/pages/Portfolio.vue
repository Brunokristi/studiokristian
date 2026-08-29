<script setup lang="ts">
import {
    onMounted,
    ref,
    watch,
} from 'vue'

import { useGlobalActions } from '../composables/useGlobalActions'
import { useI18n } from 'vue-i18n'
import { useSeoMeta } from '../composables/useSeoMeta'

import Button from '../../shared/components/Button.vue'
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
        t('seo.portfolio.title'),

    description: () =>
        t('seo.portfolio.description'),
})

type ApiProject = {
    name: string
    url: string
    summary: string | null
    hex_color: string | null
    logo_path: string | null
    cover_image: string | null
}

type PortfolioCard = {
    heading: string
    text: string
    image: string
    bgColor: string
    link: string
}

const cards =
    ref<PortfolioCard[]>([])

const isLoading =
    ref(true)

async function loadProjects() {
    isLoading.value = true

    try {
        const response =
            await fetch(
                `/api/projects?locale=${encodeURIComponent(
                    locale.value
                )}`
            )

        if (!response.ok) {
            throw new Error(
                'Failed to load projects'
            )
        }

        const projects:
            ApiProject[] =
            await response.json()

        cards.value =
            projects.map(
                project => ({
                    heading:
                        project.name,

                    text:
                        project.summary ||
                        '',

                    image:
                        project.logo_path ||
                        project.cover_image ||
                        '',

                    bgColor:
                        project.hex_color ||
                        '',

                    link:
                        `/portfolio/${project.url}`,
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
    loadProjects()
})

watch(
    () => locale.value,
    () => {
        loadProjects()
    }
)
</script>

<template>
    <main
        class="
            py-10
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
                gap-6
            "
        >
            <h1
                class="
                    h2
                    text-accent
                "
            >
                {{ t('portfolio.title') }}
            </h1>

            <p
                class="
                    p
                    uppercase
                    text-center
                "
            >
                {{
                    t(
                        'portfolio.description'
                    )
                }}
            </p>
        </section>

        <!-- Loading -->
        <section
            v-if="isLoading"
            class="px-6"
        >
            <p class="p">
                {{
                    t(
                        'portfolio.loading'
                    )
                }}
            </p>
        </section>

        <!-- Projects -->
        <section
            v-else
            class="w-full"
        >
            <GridLayout
                :cards="cards"
            />
        </section>

        <!-- Contact -->
        <Button
            :text="
                t(
                    'portfolio.contact'
                )
            "
            variant="dark"
            @click="openContacts"
        />
    </main>
</template>
