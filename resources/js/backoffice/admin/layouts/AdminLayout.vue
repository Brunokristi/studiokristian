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


const {
    enabled,
    status,
    lastSavedAt
} =
    useAutosavePolicy()


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


const menuOpen =
    ref(true)


const csrfToken =
    document.querySelector(
        'meta[name="csrf-token"]'
    )?.content ?? ''


const navigation = [
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
         * On desktop the user's
         * sidebar state is preserved.
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
                    gap-3
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
                    <i
                        class="
                            bi
                            text-xl
                        "
                        :class="
                            menuOpen
                                ? 'bi-chevron-bar-left'
                                : 'bi-chevron-bar-right'
                        "
                    />
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
            />


            <!-- Application grid -->
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
                                'bg-accent text-light':
                                    isActive(
                                        item
                                    )
                            }"
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
                    <RouterView />
                </main>
            </div>
        </div>


        <Toast />
    </div>
</template>