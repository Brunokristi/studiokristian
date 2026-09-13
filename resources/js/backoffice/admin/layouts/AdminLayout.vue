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


import Tabs from '../../components/Tabs.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'


import useAutosavePolicy from '../composables/useAutosavePolicy'


import {
    useAdminPageHeader
} from '../composables/useAdminPageHeader'


const {
    header: pageHeader
} =
    useAdminPageHeader()


const {
    enabled,
    status,
    lastSavedAt
} =
    useAutosavePolicy()


const route =
    useRoute()


/*
|--------------------------------------------------------------------------
| Autosave
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Mobile navigation
|--------------------------------------------------------------------------
*/

const menuOpen =
    ref(false)


function toggleMenu() {

    menuOpen.value =
        !menuOpen.value

}


function closeMenu() {

    menuOpen.value =
        false

}


/*
|--------------------------------------------------------------------------
| Close mobile menu when route changes
|--------------------------------------------------------------------------
*/

watch(
    () => route.fullPath,

    () => {

        if (
            window.innerWidth <
            1024
        ) {

            closeMenu()

        }

    }
)


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

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
        label: 'SaaS',

        route: {
            name: 'saas.projects.index'
        },

        match: 'saas'
    },

    {
        label: 'Projects',

        route: {
            name: 'projects.index'
        },

        match: 'projects'
    },

    {
        label: 'Coworkers',

        route: {
            name: 'coworkers.index'
        },

        match: 'coworkers'
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


/*
|--------------------------------------------------------------------------
| Contextual tabs
|--------------------------------------------------------------------------
*/

const clientTabs =
    computed(() => {

        if (
            !route.params.id
        ) {

            return []

        }


        const clientRoutes = [

            'clients.show',

            'clients.contacts',

            'clients.projects',

            'clients.danger-zone'

        ]


        if (
            !clientRoutes.includes(
                currentRouteName.value
            )
        ) {

            return []

        }


        return [

            {
                label: 'Information',

                route: {
                    name: 'clients.show',

                    params: {
                        id:
                            route.params.id
                    }
                }
            },

            {
                label: 'Contacts',

                route: {
                    name: 'clients.contacts',

                    params: {
                        id:
                            route.params.id
                    }
                }
            },

            {
                label: 'Projects',

                route: {
                    name: 'clients.projects',

                    params: {
                        id:
                            route.params.id
                    }
                }
            },

            {
                label: 'Danger zone',

                route: {
                    name: 'clients.danger-zone',

                    params: {
                        id:
                            route.params.id
                    }
                }
            }

        ]

    })


const contextualTabs =
    computed(() => {

        if (
            clientTabs.value.length
        ) {

            return clientTabs.value

        }


        return []

    })

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

        <!-- ================================================================ -->
        <!-- HEADER -->
        <!-- ================================================================ -->

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

            <!-- Left side -->

            <div
                class="
                    flex
                    min-w-0
                    items-center
                    gap-6
                "
            >

                <!-- Brand -->

                <RouterLink
                    :to="{
                        name: 'dashboard'
                    }"
                    class="
                        flex
                        shrink-0
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

            </div>


            <!-- Header actions -->

            <div
                class="
                    flex
                    shrink-0
                    items-center
                    gap-5
                "
            >

                <!-- Language -->

                <div
                    id="admin-page-language-toggle"
                    class="
                        flex
                        items-center
                        h3
                    "
                ></div>


                <!-- Autosave -->

                <div
                    v-if="enabled"
                    class="
                        hidden
                        items-center
                        gap-3
                        sm:flex
                        p
                        uppercase
                        text-xs
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
                        "
                    >
                        last saved:
                        {{ lastSavedLabel }}
                    </span>

                </div>


                <!-- Mobile navigation -->

                <button
                    type="button"
                    class="
                        flex
                        nav-control
                        md:hidden
                    "
                    :aria-expanded="
                        menuOpen
                    "
                    aria-controls="admin-navigation"
                    :aria-label="
                        menuOpen
                            ? 'Close navigation'
                            : 'Open navigation'
                    "
                    @click="toggleMenu"
                >

                    <span
                        class="menu-icon"
                        :class="{
                            'menu-icon-open':
                                menuOpen
                        }"
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


        <!-- ================================================================ -->
        <!-- APPLICATION -->
        <!-- ================================================================ -->

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
                v-if="menuOpen"
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
                @click="closeMenu"
            ></button>


            <!-- ============================================================ -->
            <!-- APPLICATION GRID -->
            <!-- ============================================================ -->

            <div
                class="
                    grid
                    h-full
                    min-h-0
                    grid-cols-1
                    lg:grid-cols-[250px_minmax(0,1fr)]
                "
            >

                <!-- ======================================================== -->
                <!-- SIDEBAR -->
                <!-- ======================================================== -->

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
                                text-accent
                                transition-colors
                                duration-200
                                hover:bg-accent
                                hover:text-light
                            "
                            :class="{
                                'text-accent':
                                    isActive(item)
                            }"
                            @click="closeMenu"
                        >

                            {{
                                item.label
                            }}

                        </RouterLink>

                    </nav>


                    <!-- Bottom navigation -->

                    <div
                        class="
                            shrink-0
                            border-t
                            border-accent
                        "
                    >

                        <!-- Portfolio -->

                        <RouterLink
                            :to="{
                                name: 'portfolio.index'
                            }"
                            class="
                                flex
                                h-12
                                w-full
                                items-center
                                border-b
                                border-accent
                                px-5
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-accent
                                transition-colors
                                duration-200
                                hover:bg-accent
                                hover:text-light
                            "
                            :class="{
                                'text-accent':
                                    isActive({
                                        match: 'portfolio'
                                    })
                            }"
                            @click="closeMenu"
                        >
                            Portfolio
                        </RouterLink>


                        <!-- Internal storage -->

                        <RouterLink
                            :to="{
                                name: 'internal-storage.index'
                            }"
                            class="
                                flex
                                h-12
                                w-full
                                items-center
                                border-b
                                border-accent
                                px-5
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-accent
                                transition-colors
                                duration-200
                                hover:bg-accent
                                hover:text-light
                            "
                            :class="{
                                'text-accent':
                                    isActive({
                                        match: 'internal-storage'
                                    })
                            }"
                            @click="closeMenu"
                        >
                            Internal storage
                        </RouterLink>


                        <!-- Log out -->

                        <form
                            method="POST"
                            action="/logout"
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
                                    flex
                                    h-12
                                    w-full
                                    items-center
                                    border-b
                                    border-accent
                                    bg-light
                                    px-5
                                    text-left
                                    font-mono
                                    text-xs
                                    font-bold
                                    uppercase
                                    text-accent
                                    transition-colors
                                    duration-200
                                    hover:bg-accent
                                    hover:text-light
                                "
                            >
                                Log out
                            </button>

                        </form>

                    </div>

                </aside>


                <!-- ======================================================== -->
                <!-- RIGHT SIDE -->
                <!-- ======================================================== -->

                <div
                    class="
                        min-h-0
                        min-w-0
                        flex
                        flex-col
                        overflow-hidden
                    "
                >
                
                <div class="flex flexgap-2 justify-between border-b border-accent">
                    <h2
                        class="
                            h3
                            uppercase
                            h-12
                            flex
                            items-center
                            pl-5
                            text-accent
                        "
                    >
                        {{ pageHeader.title }}
                    </h2>

                    <!-- <nav
                        v-if="
                            pageHeader.breadcrumbs.length ||
                            pageHeader.eyebrow
                        "
                        aria-label="Breadcrumb"
                        class="
                            hidden
                            min-w-0
                            items-center
                            gap-x-2
                            overflow-hidden
                            md:flex
                            p
                            uppercase
                            text-xs
                            h-12
                            px-4
                        "
                    >

                        <RouterLink
                            :to="{
                                name: 'dashboard'
                            }"
                            class="
                                shrink-0
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
                                    shrink-0
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
                                    truncate
                                    text-dark
                                    transition-colors
                                    hover:text-accent
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
                                    truncate
                                    text-accent
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
                                    shrink-0
                                    text-dark/30
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

                    </nav> -->


                    <!-- ==================================================== -->
                    <!-- CONTEXTUAL TABS -->
                    <!-- ==================================================== -->


                    <div
                        v-if="
                            contextualTabs.length
                        "
                        class="
                            z-40
                            shrink-0
                            bg-light
                        "
                    >

                        <Tabs
                            :tabs="
                                contextualTabs
                            "
                        />

                    </div>
                </div>


                    <!-- ==================================================== -->
                    <!-- SCROLLABLE CONTENT -->
                    <!-- ==================================================== -->

                    <main
                        class="
                            min-h-0
                            min-w-0
                            flex-1
                            overflow-y-auto
                            overscroll-contain
                            p-10
                            p-8
                            pb-20
                        "
                    >

                        <!-- Page -->

                        <RouterView />

                    </main>

                </div>

            </div>

        </div>


        <Toast />

    </div>

</template>


<style scoped>

/*
|--------------------------------------------------------------------------
| Navigation control
|--------------------------------------------------------------------------
*/

.nav-control {
    position: relative;
    width: 24px;
    height: 24px;
    padding: 0;
    margin: 0;

    align-items: center;
    justify-content: center;

    color: inherit;
    background: transparent;
    border: 0;

    cursor: pointer;

    transition:
        transform 220ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}


.nav-control:hover {
    transform:
        scale(1.08);
}


.nav-control:active {
    transform:
        scale(0.94);
}


/*
|--------------------------------------------------------------------------
| Menu icon
|--------------------------------------------------------------------------
*/

.menu-icon {
    position: relative;

    width: 18px;
    height: 14px;

    transition:
        transform 350ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}


.menu-line {
    position: absolute;

    left: 0;

    width: 18px;
    height: 1px;

    background: currentColor;

    transform-origin: center;

    transition:
        transform 350ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        ),
        top 350ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}


.menu-line-top {
    top: 2px;
}


.menu-line-bottom {
    top: 10px;
}


/*
|--------------------------------------------------------------------------
| Hamburger → X
|--------------------------------------------------------------------------
*/

.menu-icon-open .menu-line-top {
    top: 6px;

    transform:
        rotate(45deg);
}


.menu-icon-open .menu-line-bottom {
    top: 6px;

    transform:
        rotate(-45deg);
}


/*
|--------------------------------------------------------------------------
| Reduced motion
|--------------------------------------------------------------------------
*/

@media (
    prefers-reduced-motion: reduce
) {

    .nav-control,
    .menu-icon,
    .menu-line {

        animation: none;

        transition: none;

    }

}

</style>