<script setup>

import {
    computed,
    ref
} from 'vue'

import LanguageToggle
    from '@shared/components/LanguageToggle.vue'

import {
    useClientPageHeader
} from '../composables/useClientPageHeader'


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
    },

    tabs: {
        type: Array,
        default: () => []
    }

})


const emit = defineEmits([
    'set-locale'
])


const menuOpen =
    ref(false)


const copy = {

    project: {
        en: 'Project',
        sk: 'Projekt'
    },

    projects: {
        en: 'Projects',
        sk: 'Projekty'
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


const currentPage =
    computed(() =>
        String(
            props.page.page ||
            ''
        )
    )


const navigation =
    computed(() => [

        {
            key: 'projects',
            href:
                props.page.urls.dashboard,
            pages: [
                'dashboard',
                'project'
            ]
        }

    ])


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

            <a
                :href="
                    page.urls.dashboard
                "
                class="
                    flex
                    min-w-0
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
                        shrink-0
                    "
                >


                <span
                    class="
                        hidden
                        h3
                        truncate
                        uppercase
                        text-dark
                        sm:block
                    "
                >

                    {{
                        page.contact
                            ?.company_name ||
                        ''
                    }}

                </span>

            </a>


            <div
                class="
                    flex
                    shrink-0
                    items-center
                    gap-5
                "
            >

                <LanguageToggle
                    :model-value="
                        locale
                    "
                    :compact="
                        true
                    "
                    @update:model-value="
                        emit(
                            'set-locale',
                            $event
                        )
                    "
                />


                <button
                    type="button"
                    class="
                        flex
                        nav-control
                        lg:hidden
                    "
                    :aria-expanded="
                        menuOpen
                    "
                    aria-controls="client-navigation"
                    :aria-label="
                        menuOpen
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
                        "
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


        <div
            class="
                relative
                min-h-0
                flex-1
                overflow-hidden
            "
        >

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


            <div
                class="
                    grid
                    h-full
                    min-h-0
                    grid-cols-1
                    lg:grid-cols-[250px_minmax(0,1fr)]
                "
            >

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

                    <nav
                        class="
                            min-h-0
                            flex-1
                            overflow-y-auto
                            overscroll-contain
                        "
                        aria-label="Main navigation"
                    >

                        <a
                            v-for="
                                item in navigation
                            "
                            :key="
                                item.key
                            "
                            :href="
                                item.href
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
                                'bg-accent text-light':
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

                    </nav>


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

                            {{
                                t('logout')
                            }}

                        </button>

                    </form>

                </aside>


                <div
                    class="
                        min-h-0
                        min-w-0
                        flex
                        flex-col
                        overflow-hidden
                    "
                >

                    <div
                        v-if="
                            tabs.length ||
                            pageHeader.title
                        "
                        class="
                            flex
                            h-12
                            shrink-0
                            items-stretch
                            border-b
                            border-accent
                            bg-light
                        "
                    >
                        <!-- Project name -->
                        <div
                            v-if="pageHeader.title"
                            class="
                                flex
                                h-12
                                min-w-0
                                flex-1
                                items-center
                                px-5
                            "
                        >
                            <span
                                class="
                                    truncate
                                    font-mono
                                    text-xs
                                    font-bold
                                    uppercase
                                    text-accent
                                "
                            >
                                {{ copy.project[locale] }} {{ pageHeader.title }}
                            </span>
                        </div>

                        <!-- Tabs -->
                        <nav
                            v-if="tabs.length"
                            aria-label="Project navigation"
                            class="
                                ml-auto
                                flex
                                h-full
                                shrink-0
                                border-l
                                border-accent
                            "
                        >
                            <a
                                v-for="(tab, index) in tabs"
                                :key="tab.label"
                                :href="tab.href"
                                class="
                                    relative
                                    flex
                                    h-12
                                    shrink-0
                                    items-center
                                    px-5
                                    font-mono
                                    text-xs
                                    font-bold
                                    uppercase
                                    text-accent
                                    transition-colors
                                    duration-200
                                    hover:bg-accent
                                    hover:text-white
                                "
                                :class="{
                                    'border-r border-accent':
                                        index < tabs.length - 1,
                                    'bg-accent text-white':
                                        tab.active,
                                    'z-10':
                                        tab.active
                                }"
                            >
                                {{ tab.label }}
                            </a>
                        </nav>
                    </div>


                    <main
                        class="
                            min-h-0
                            min-w-0
                            flex-1
                            overflow-y-auto
                            overscroll-contain
                            p-8
                            pb-20
                            lg:p-10
                            lg:pb-20
                        "
                    >

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
                                p
                            "
                            role="status"
                        >

                            {{
                                page.status
                            }}

                        </div>


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
                                p
                                text-red-800
                            "
                            role="alert"
                        >

                            {{
                                page.error
                            }}

                        </div>


                        <slot />

                    </main>

                </div>

            </div>

        </div>

    </div>

</template>


<style scoped>

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
