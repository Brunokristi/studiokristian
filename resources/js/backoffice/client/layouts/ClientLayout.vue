<script setup>
import {
    computed,
    ref
} from 'vue'

import LanguageToggle from '@shared/components/LanguageToggle.vue'
import { useClientPageHeader } from '../composables/useClientPageHeader'


const {
    header: pageHeader
} =
    useClientPageHeader()


const props = defineProps({
    page: {
        type: Object,
        required: true
    },

    csrfToken: {
        type: String,
        required: true
    },

    locale: {
        type: String,
        required: true
    }
})


const emit = defineEmits([
    'set-locale'
])


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

const navigation = [
    {
        key: 'projects',
        href: 'dashboard',
        pages: [
            'dashboard',
            'project'
        ]
    }
]


/*
|--------------------------------------------------------------------------
| Menu state
|--------------------------------------------------------------------------
*/

const menuOpen =
    ref(true)


/*
|--------------------------------------------------------------------------
| Translations
|--------------------------------------------------------------------------
*/

const copy = {
    brand: {
        en: 'backoffice',
        sk: 'backoffice'
    },

    portal: {
        en: 'client portal',
        sk: 'klientsky portal'
    },

    projects: {
        en: 'Projects',
        sk: 'Projekty'
    },

    language: {
        en: 'Language',
        sk: 'Jazyk'
    },

    logout: {
        en: 'Log out',
        sk: 'Odhlásiť sa'
    },

    menu: {
        en: 'Menu',
        sk: 'Menu'
    },

    close: {
        en: 'Close',
        sk: 'Zavrieť'
    }
}


function t(
    key
) {
    return (
        copy[key]?.[props.locale] ||
        copy[key]?.en ||
        key
    )
}


/*
|--------------------------------------------------------------------------
| Computed state
|--------------------------------------------------------------------------
*/

const currentPage =
    computed(() =>
        String(
            props.page.page || ''
        )
    )


const menuIconOpen =
    computed(() =>
        menuOpen.value
    )


/*
|--------------------------------------------------------------------------
| Navigation helpers
|--------------------------------------------------------------------------
*/

function isNavigationItemActive(
    item
) {
    return item.pages.includes(
        currentPage.value
    )
}


function closeMenu() {
    menuOpen.value =
        false
}


function toggleMenu() {
    menuOpen.value =
        !menuOpen.value
}
</script>


<template>
    <div
        class="
            flex
            h-screen
            w-full
            flex-col
            overflow-hidden
            bg-light
            text-dark
        "
    >
        <!-- =========================================================
             Header
        ========================================================== -->

        <header
            class="
                z-50
                flex
                h-14
                shrink-0
                items-center
                justify-between
                border-b
                border-accent
                bg-light
                px-5
            "
        >
            <!-- Brand -->

            <a
                :href="
                    page.urls.dashboard
                "
                class="
                    flex
                    items-center
                    gap-1
                    transition-opacity
                    duration-200
                    hover:opacity-60
                "
                aria-label="Studio Kristian Client Portal"
            >
                <img
                    src="/public/assets/logo.svg"
                    alt=""
                    class="
                        h-2.5
                        w-auto
                    "
                >

                <span
                    class="
                        hidden
                        h3
                        uppercase
                        text-dark
                        sm:block
                    "
                >
                    {{
                        page.contact.company_name
                    }}
                </span>
            </a>


            <!-- Header actions -->

            <div
                class="
                    flex
                    items-center
                    gap-4
                    sm:gap-6
                "
            >
                <!-- Company -->

                


                <!-- Desktop language -->

                <LanguageToggle
                    :model-value="locale"
                    :compact="true"
                    class="hidden sm:flex"
                    @update:model-value="emit('set-locale', $event)"
                />


                <!-- Mobile menu -->

                <button
                    type="button"
                    class="
                        grid
                        h-9
                        w-9
                        shrink-0
                        place-items-center
                        text-dark
                        transition-colors
                        duration-200
                        hover:text-accent
                        lg:hidden
                    "
                    :aria-expanded="
                        menuIconOpen
                    "
                    aria-controls="client-navigation"
                    :aria-label="
                        menuIconOpen
                            ? t('close')
                            : t('menu')
                    "
                    @click="
                        toggleMenu
                    "
                >
                    <span
                        class="
                            menu-icon
                            relative
                            block
                            h-4
                            w-5
                        "
                        :class="{
                            'is-open':
                                menuIconOpen
                        }"
                        aria-hidden="true"
                    >
                        <span
                            class="
                                menu-line
                                menu-line-top
                            "
                        />

                        <span
                            class="
                                menu-line
                                menu-line-bottom
                            "
                        />
                    </span>
                </button>
            </div>
        </header>


        <!-- =========================================================
             Application shell
        ========================================================== -->

        <div
            class="
                relative
                min-h-0
                flex-1
                overflow-hidden
            "
        >
            <!-- Mobile overlay -->

            <button
                v-if="
                    menuOpen
                "
                type="button"
                aria-label="Close navigation"
                class="
                    fixed
                    inset-0
                    z-30
                    bg-dark/20
                    backdrop-blur-[2px]
                    lg:hidden
                "
                @click="
                    closeMenu
                "
            />


            <div
                class="
                    grid
                    h-full
                    min-h-0
                    transition-[grid-template-columns]
                    duration-300
                    ease-out
                "
                :class="
                    menuOpen
                        ? 'lg:grid-cols-[250px_minmax(0,1fr)]'
                        : 'lg:grid-cols-[0_minmax(0,1fr)]'
                "
            >
                <!-- =================================================
                     Sidebar
                ================================================== -->

                <aside
                    id="client-navigation"
                    class="
                        fixed
                        bottom-0
                        left-0
                        top-14
                        z-40
                        flex
                        w-[min(85vw,300px)]
                        flex-col
                        overflow-hidden
                        border-r
                        border-accent
                        bg-light
                        transition-transform
                        duration-300
                        ease-out
                        lg:static
                        lg:h-full
                        lg:w-[250px]
                        lg:translate-x-0
                    "
                    :class="
                        menuOpen
                            ? 'translate-x-0'
                            : '-translate-x-full'
                    "
                >
                    <!-- Navigation -->

                    <nav
                        class="
                            min-h-0
                            flex-1
                            overflow-y-auto
                            overscroll-contain
                        "
                        aria-label="Main navigation"
                    >
                        <template
                            v-for="
                                item in navigation
                            "
                            :key="
                                item.key
                            "
                        >
                            <a
                                :href="
                                    page.urls.dashboard
                                "
                                class="
                                    block
                                    border-b
                                    border-accent
                                    px-5
                                    py-4
                                    font-mono
                                    text-xs
                                    font-bold
                                    uppercase
                                    text-dark
                                    transition-colors
                                    duration-200
                                    hover:bg-accent
                                    hover:text-light
                                "
                                :class="{
                                    'text-accent':
                                        isNavigationItemActive(
                                            item
                                        )
                                }"
                                @click="
                                    closeMenu
                                "
                            >
                                {{
                                    t(
                                        item.key
                                    )
                                }}
                            </a>
                        </template>
                    </nav>


                    <!-- Mobile language -->

                    <div
                        class="
                            border-t
                            border-accent
                            px-5
                            py-4
                            sm:hidden
                        "
                    >
                        <LanguageToggle
                            :model-value="locale"
                            :compact="true"
                            class="w-full"
                            @update:model-value="emit('set-locale', $event)"
                        />
                    </div>


                    <!-- Logout -->

                    <form
                        method="POST"
                        :action="
                            page.urls.logout
                        "
                        class="
                            shrink-0
                            border-t
                            border-accent
                        "
                    >
                        <input
                            type="hidden"
                            name="_token"
                            :value="
                                csrfToken
                            "
                        >

                        <button
                            type="submit"
                            class="
                                block
                                w-full
                                border-b
                                border-accent
                                bg-light
                                px-5
                                py-4
                                text-left
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-dark
                                transition-colors
                                duration-200
                                hover:bg-accent
                                hover:text-light
                            "
                        >
                            {{
                                t('logout')
                            }}
                        </button>
                    </form>
                </aside>


                <!-- =================================================
                     Main content
                ================================================== -->

                <main
                    class="
                        min-h-0
                        min-w-0
                        overflow-y-auto
                        overscroll-contain
                        px-5
                        py-8
                        pb-20
                        sm:px-8
                        sm:py-10
                        lg:px-10
                    "
                >
                    <!-- Status -->

                    <div
                        v-if="
                            page.status
                        "
                        class="
                            mb-6
                            border-l-2
                            border-accent
                            bg-white
                            px-5
                            py-4
                            text-sm
                        "
                        role="status"
                    >
                        {{
                            page.status
                        }}
                    </div>


                    <!-- Error -->

                    <div
                        v-if="
                            page.error
                        "
                        class="
                            mb-6
                            border-l-2
                            border-red-700
                            bg-red-50
                            px-5
                            py-4
                            text-sm
                            text-red-800
                        "
                        role="alert"
                    >
                        {{
                            page.error
                        }}
                    </div>


                    <!-- Page -->

                    <header
                        v-if="pageHeader.title"
                        class="pb-4"
                    >
                        <nav
                            v-if="pageHeader.breadcrumbs.length || pageHeader.eyebrow"
                            aria-label="Breadcrumb"
                            class="mb-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 p uppercase"
                        >
                            <a
                                :href="pageHeader.homeUrl"
                                class="text-dark transition-colors hover:text-accent"
                            >
                                Client
                            </a>

                            <template
                                v-for="(breadcrumb, index) in pageHeader.breadcrumbs"
                                :key="`${breadcrumb.label}-${index}`"
                            >
                                <span class="text-dark" aria-hidden="true">/</span>

                                <a
                                    v-if="breadcrumb.href"
                                    :href="breadcrumb.href"
                                    class="min-w-0 max-w-[12rem] truncate text-dark transition-colors hover:text-accent sm:max-w-none"
                                >
                                    {{ breadcrumb.label }}
                                </a>

                                <span
                                    v-else
                                    class="min-w-0 max-w-[12rem] truncate text-accent sm:max-w-none"
                                    aria-current="page"
                                >
                                    {{ breadcrumb.label }}
                                </span>
                            </template>

                            <template v-if="!pageHeader.breadcrumbs.length && pageHeader.eyebrow">
                                <span class="text-dark" aria-hidden="true">/</span>
                                <span class="text-accent">{{ pageHeader.eyebrow }}</span>
                            </template>
                        </nav>

                        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h1 class="h2 text-left">
                                    {{ pageHeader.title }}
                                </h1>
                            </div>

                            <div
                                id="client-page-header-actions"
                                class="flex flex-wrap gap-x-4 gap-y-3 md:justify-end"
                            />
                        </div>
                    </header>

                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>


<style scoped>
/*
|--------------------------------------------------------------------------
| Menu icon
|--------------------------------------------------------------------------
*/

.menu-icon {
    display: block;
}


.menu-line {
    position: absolute;
    left: 0;
    width: 15px;
    height: 1px;
    background: currentColor;
    transform-origin: center;
    transition:
        top 0.25s ease,
        transform 0.25s ease;
}


.menu-line-top {
    top: 4px;
}


.menu-line-bottom {
    top: 11px;
}


/*
|--------------------------------------------------------------------------
| Open state
|--------------------------------------------------------------------------
*/

.menu-icon.is-open .menu-line-top {
    top: 7.5px;
    transform: rotate(45deg);
}


.menu-icon.is-open .menu-line-bottom {
    top: 7.5px;
    transform: rotate(-45deg);
}
</style>