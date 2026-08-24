<script setup>

import {
    computed,
    ref,
    watch
} from 'vue'

import {
    RouterLink,
    RouterView,
    useRoute
} from 'vue-router'

import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'
import useAutosavePolicy from '../composables/useAutosavePolicy'
import { useAdminPageHeader } from '../composables/useAdminPageHeader'

const {
    header: pageHeader
} = useAdminPageHeader()

const {
    enabled,
    status,
    lastSavedAt
} = useAutosavePolicy()

const lastSavedLabel =
    computed(() => {
        if (
            !lastSavedAt.value
        ) {
            return 'never'
        }

        const savedAt =
            new Date(
                lastSavedAt.value
            )

        const now =
            new Date()

        const diffMs =
            now.getTime() -
            savedAt.getTime()

        if (
            diffMs <
            60000
        ) {
            return 'now'
        }

        return savedAt.toLocaleTimeString(
            [],
            {
                hour: '2-digit',
                minute: '2-digit'
            }
        )
    })

const route =
    useRoute()

/*
|--------------------------------------------------------------------------
| Menu state
|--------------------------------------------------------------------------
|
| Closed by default on small screens.
| On desktop the sidebar is always visible through lg: classes.
|
*/

const menuOpen =
    ref(false)

const csrfToken =
    document.querySelector(
        'meta[name="csrf-token"]'
    )?.content ?? ''

const currentUserPayload =
    JSON.parse(
        document.querySelector(
            '#client-portal-admin-user'
        )?.textContent ||
        '{}'
    )

const isAdminUser =
    Boolean(
        currentUserPayload?.is_admin
    )

const adminNavigation = [
    {
        label: 'Dashboard',
        route: {
            name: 'dashboard'
        },
        match: 'dashboard'
    },

    {
        label: 'Clients',
        route: {
            name: 'clients.index'
        },
        match: 'clients'
    },

    {
        label: 'Services',
        route: {
            name: 'service-products.index'
        },
        match: 'service-products'
    },

    {
        label: 'Internal storage',
        route: {
            name: 'internal-storage.index'
        },
        match: 'internal-storage'
    },

    {
        label: 'Coworkers',
        route: {
            name: 'coworkers.index'
        },
        match: 'coworkers'
    },

    {
        label: 'Portfolio',
        route: {
            name: 'portfolio.index'
        },
        match: 'portfolio'
    }
]

const coworkerNavigation = [
    {
        label: 'Projects',
        route: {
            name: 'projects.index'
        },
        match: 'projects'
    }
]

const navigation =
    computed(() =>
        isAdminUser
            ? adminNavigation
            : coworkerNavigation
    )

const currentRouteName =
    computed(() => {
        return String(
            route.name ||
            ''
        )
    })

function isActive(
    item
) {
    if (
        item.match ===
        'dashboard'
    ) {
        return (
            currentRouteName.value ===
            'dashboard'
        )
    }

    return currentRouteName.value.startsWith(
        `${item.match}.`
    )
}

function toggleMenu() {
    menuOpen.value =
        !menuOpen.value
}

function closeMenu() {
    menuOpen.value =
        false
}

watch(
    () => route.fullPath,
    () => {
        /*
         * Only close the navigation
         * automatically on mobile.
         *
         * On desktop the sidebar is
         * always visible.
         */
        if (
            window.innerWidth <
            1024
        ) {
            closeMenu()
        }
    }
)

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

        <!-- Header -->

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

            <RouterLink
                :to="{
                    name: 'dashboard'
                }"
                class="
                    flex
                    items-center
                    gap-1
                    transition-opacity
                    duration-200
                    hover:opacity-60
                "
                aria-label="Studio Kristian Backoffice"
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
                        h3
                    "
                >
                    backoffice
                </span>

            </RouterLink>

            <!-- Header actions -->

            <div
                class="
                    flex
                    items-center
                    gap-6
                "
            >

                <!-- Autosave -->

                <div
                    v-if="enabled"
                    class="
                        hidden
                        items-center
                        gap-2
                        sm:flex
                    "
                >

                    <Tag
                        :text="
                            status === 'saving'
                                ? 'saving...'
                                : 'autosave on'
                        "
                    />

                    <span
                        class="
                            p
                            text-[10px]
                            uppercase
                            text-dark
                        "
                    >
                        last saved:
                        {{
                            lastSavedLabel
                        }}
                    </span>

                </div>

                <!-- Navigation toggle -->

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
                        menuOpen
                    "
                    aria-controls="admin-navigation"
                    aria-label="Toggle navigation"
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
                                menuOpen
                        }"
                        aria-hidden="true"
                    >

                        <span
                            class="
                                menu-line
                                menu-line-top
                            "
                        ></span>

                        <span
                            class="
                                menu-line
                                menu-line-bottom
                            "
                        ></span>

                    </span>

                </button>

            </div>

        </header>

        <!-- Application -->

        <div
            class="
                relative
                min-h-0
                flex-1
                overflow-hidden
            "
        >

            <!-- Mobile backdrop -->

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
            ></button>

            <!-- Application grid -->

            <div
                class="
                    grid
                    h-full
                    min-h-0
                    grid-cols-1
                    lg:grid-cols-[250px_minmax(0,1fr)]
                "
            >

                <!-- Sidebar -->

                <aside
                    id="admin-navigation"
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
                    >

                        <RouterLink
                            v-for="
                                item
                                in navigation
                            "
                            :key="
                                item.label
                            "
                            :to="
                                item.route
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
                                    isActive(item)
                            }"
                            @click="
                                closeMenu
                            "
                        >
                            {{
                                item.label
                            }}
                        </RouterLink>

                    </nav>

                    <!-- Logout -->

                    <form
                        method="POST"
                        action="/logout"
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
                            Log out
                        </button>

                    </form>

                </aside>

                <!-- Main -->

                <main
                    class="
                        min-h-0
                        min-w-0
                        overflow-y-auto
                        overscroll-contain
                        px-5
                        py-10
                        pb-20
                        sm:px-8
                        lg:px-10
                    "
                >

                    <header
                        v-if="pageHeader.title"
                        class="pb-10"
                    >

                        <nav
                            v-if="
                                pageHeader.breadcrumbs.length ||
                                pageHeader.eyebrow
                            "
                            aria-label="Breadcrumb"
                            class="
                                mb-2
                                flex
                                min-w-0
                                flex-wrap
                                items-center
                                gap-x-2
                                gap-y-1
                                p
                                uppercase
                            "
                        >

                            <RouterLink
                                :to="{
                                    name: 'dashboard'
                                }"
                                class="
                                    text-dark
                                    transition-colors
                                    hover:text-accent
                                "
                            >
                                Admin
                            </RouterLink>

                            <template
                                v-for="
                                    (
                                        breadcrumb,
                                        index
                                    ) in pageHeader.breadcrumbs
                                "
                                :key="
                                    `${breadcrumb.label}-${index}`
                                "
                            >

                                <span
                                    class="
                                        text-dark
                                    "
                                    aria-hidden="true"
                                >
                                    /
                                </span>

                                <RouterLink
                                    v-if="
                                        breadcrumb.to
                                    "
                                    :to="
                                        breadcrumb.to
                                    "
                                    class="
                                        min-w-0
                                        max-w-[12rem]
                                        truncate
                                        text-dark
                                        transition-colors
                                        hover:text-accent
                                        sm:max-w-none
                                    "
                                >
                                    {{
                                        breadcrumb.label
                                    }}
                                </RouterLink>

                                <span
                                    v-else
                                    class="
                                        min-w-0
                                        max-w-[12rem]
                                        truncate
                                        text-accent
                                        sm:max-w-none
                                    "
                                    aria-current="page"
                                >
                                    {{
                                        breadcrumb.label
                                    }}
                                </span>

                            </template>

                            <template
                                v-if="
                                    !pageHeader.breadcrumbs.length &&
                                    pageHeader.eyebrow
                                "
                            >

                                <span
                                    class="
                                        text-dark
                                    "
                                    aria-hidden="true"
                                >
                                    /
                                </span>

                                <span
                                    class="
                                        text-accent
                                    "
                                >
                                    {{
                                        pageHeader.eyebrow
                                    }}
                                </span>

                            </template>

                        </nav>

                        <div
                            class="
                                flex
                                flex-col
                                gap-6
                                md:flex-row
                                md:items-end
                                md:justify-between
                            "
                        >

                            <div>

                                <h1
                                    class="
                                        h2
                                        text-left
                                    "
                                >
                                    {{
                                        pageHeader.title
                                    }}
                                </h1>

                            </div>

                            <div
                                id="admin-page-header-actions"
                                class="
                                    flex
                                    flex-wrap
                                    gap-x-7
                                    gap-y-4
                                    md:justify-end
                                "
                            ></div>

                        </div>

                    </header>

                    <RouterView />

                </main>

            </div>

        </div>

        <Toast />

    </div>

</template>

<style scoped>

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
        transform 0.25s ease,
        top 0.25s ease;
}

.menu-line-top {
    top: 4px;
}

.menu-line-bottom {
    top: 11px;
}

.menu-icon.is-open .menu-line-top {
    top: 7.5px;
    transform: rotate(45deg);
}

.menu-icon.is-open .menu-line-bottom {
    top: 7.5px;
    transform: rotate(-45deg);
}

</style>