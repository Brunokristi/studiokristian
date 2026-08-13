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
    ref(false)


const csrfToken =
    document.querySelector(
        'meta[name="csrf-token"]'
    )?.content ?? ''


const navigation = [
    {
        label: 'Dashboard',
        icon: 'bi-grid',
        route: {
            name: 'dashboard'
        },
        match: 'dashboard'
    },

    {
        label: 'Clients',
        icon: 'bi-people',
        route: {
            name: 'clients.index'
        },
        match: 'clients'
    },

    {
        label: 'Services',
        icon: 'bi-box',
        route: {
            name: 'service-products.index'
        },
        match: 'service-products'
    },

    {
        label: 'Internal storage',
        icon: 'bi-folder2-open',
        route: {
            name: 'internal-storage.index'
        },
        match: 'internal-storage'
    },

    {
        label: 'Coworkers',
        icon: 'bi-people-fill',
        route: {
            name: 'coworkers.index'
        },
        match: 'coworkers'
    },

    {
        label: 'Portfolio',
        icon: 'bi-images',
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


function closeMenu() {
    menuOpen.value =
        false
}


watch(
    () => route.fullPath,
    () => {
        closeMenu()
    }
)
</script>


<template>
    <div
        class="
            min-h-screen
            bg-light
            text-dark
        "
    >
        <!-- Mobile navigation trigger -->
        <div
            class="
                sticky
                top-0
                z-50
                flex
                h-14
                items-center
                justify-between
                border-b
                border-accent
                bg-light
                px-5
            "
        >
            <div
                class="
                    flex
                    items-center
                    gap-3
                "
            >
                <RouterLink
                    :to="{
                        name: 'dashboard'
                    }"
                    class="
                        flex
                        gap-1
                        items-center
                        justify-center
                        transition-opacity
                        duration-200
                    "
                    aria-label="Studio Kristian Admin"
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
            </div>

            <div
                v-if="enabled"
                class="
                    flex
                    items-center
                    gap-2
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
                        text-dark
                        uppercase
                        text-[10px]
                    "
                >
                    last saved: {{
                        lastSavedLabel
                    }}
                </span>

                <button
                    type="button"
                    class="
                        grid
                        h-9
                        w-9
                        place-items-center
                        text-dark
                        transition-colors
                        hover:text-accent
                        lg:hidden
                    "
                    :aria-expanded="
                        menuOpen
                    "
                    aria-controls="admin-navigation"
                    aria-label="Toggle navigation"
                    @click="
                        menuOpen =
                            !menuOpen
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

            
        </div>


        <!-- Mobile backdrop -->
        <button
            v-if="
                menuOpen
            "
            type="button"
            aria-label="Close navigation"
            class="
                fixed
                inset-x-0
                bottom-0
                top-14
                z-30
                bg-dark/20
                backdrop-blur-[2px]
            "
            @click="
                closeMenu
            "
        />


        <div
            class="
                min-h-screen
                lg:grid
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
                    border-r
                    border-accent
                    bg-light
                    transition-transform
                    duration-300
                    ease-out

                    lg:sticky
                    lg:top-0
                    lg:h-screen
                    lg:w-auto
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
                        flex-1
                        overflow-y-auto
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
                            hover:text-white
                            hover:bg-accent
                        "
                        :class="{
                            'text-accent':
                                isActive(
                                    item
                                )
                        }"
                    >
                        {{ item.label }}
                    </RouterLink>
                </nav>


                <!-- Logout -->
                <form
                    method="POST"
                    action="/logout"
                    class="
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
                            hover:text-white
                            hover:bg-accent
                        "
                    >
                        Log out
                    </button>
                </form>
            </aside>


            <!-- Content -->
            <main
                class="
                    min-w-0
                    px-10
                    py-10
                    pb-20
                "
            >
                <RouterView />
            </main>
        </div>


        <Toast />
    </div>
</template>