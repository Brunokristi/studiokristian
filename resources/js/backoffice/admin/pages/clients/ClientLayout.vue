<script setup>

import {
    computed
} from 'vue'

import {
    useRoute
} from 'vue-router'

import Tabs from '../../../components/Tabs.vue'


const route =
    useRoute()


const clientId =
    computed(() =>
        String(
            route.params.id || ''
        )
    )


const tabs = [
    {
        label: 'Information',

        route: {
            name: 'clients.show'
        }
    },

    {
        label: 'Contacts',

        route: {
            name: 'clients.contacts'
        }
    },

    {
        label: 'Projects',

        route: {
            name: 'clients.projects'
        }
    },

    {
        label: 'Danger zone',

        route: {
            name: 'clients.danger-zone'
        }
    }
]

</script>


<template>

    <div
        class="
            w-full
        "
    >

        <RouterView />

    </div>

</template>