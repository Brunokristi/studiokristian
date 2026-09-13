<script setup>

import {
    computed
} from 'vue'


import {
    RouterLink,
    useRoute
} from 'vue-router'


const props = defineProps({

    tabs: {
        type: Array,
        required: true
    },

    params: {
        type: Object,
        default: () => ({})
    }

})


const route =
    useRoute()


const normalizedTabs =
    computed(() => {

        return props.tabs.map(
            tab => {

                const routeTarget =
                    typeof tab.route === 'function'
                        ? tab.route(route)
                        : tab.route


                return {
                    ...tab,
                    route:
                        routeTarget
                }

            }
        )

    })


function isActive(
    tab
) {

    if (
        typeof tab.active ===
        'function'
    ) {

        return tab.active(
            route
        )

    }


    if (
        tab.active !==
        undefined
    ) {

        return Boolean(
            tab.active
        )

    }


    if (
        tab.route?.name
    ) {

        return (
            route.name ===
            tab.route.name
        )

    }


    return false

}


function getRoute(
    tab
) {

    if (
        typeof tab.route ===
        'string'
    ) {

        return tab.route

    }


    return {

        ...tab.route,

        params: {

            ...props.params,

            ...(tab.route?.params || {})

        }

    }

}

</script>


<template>

    <nav
        v-if="normalizedTabs.length"
        aria-label="Page navigation"
        class="
            flex
            w-fit
            overflow-x-auto
            overscroll-contain
        "
    >

        <RouterLink
            v-for="
                (tab, index)
                in normalizedTabs
            "
            :key="
                tab.label
            "
            :to="
                getRoute(tab)
            "
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
                    index <
                    normalizedTabs.length - 1,

                'bg-accent text-white':
                    isActive(tab),

                'z-10':
                    isActive(tab)
            }"
        >

            {{
                tab.label
            }}

        </RouterLink>

    </nav>

</template>