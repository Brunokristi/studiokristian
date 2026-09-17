<script setup>

import {
    computed,
    onBeforeUnmount,
    ref,
    watch
} from 'vue'

import ClientLayout
    from '../layouts/ClientLayout.vue'

import ProjectOverview
    from './ProjectOverview.vue'

import ProjectDocuments
    from './ProjectDocuments.vue'

import ProjectSupport
    from './ProjectSupport.vue'

import ProjectBilling
    from './ProjectBilling.vue'

import {
    useClientPageHeader
} from '../composables/useClientPageHeader.js'


const props = defineProps({

    page: {
        type: Object,
        required: true
    },

    data: {
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


const allowedTabs = [
    'overview',
    'documents',
    'support',
    'billing'
]


function tabFromUrl() {

    const params =
        new URLSearchParams(
            window.location.search
        )


    const value =
        String(
            params.get(
                'tab'
            ) ||
            'overview'
        )


    return allowedTabs.includes(
        value
    )
        ? value
        : 'overview'

}


function documentFromUrl() {

    const params =
        new URLSearchParams(
            window.location.search
        )


    return String(
        params.get(
            'document'
        ) ||
        ''
    )

}


const activeTab =
    ref(
        tabFromUrl()
    )


const requestedDocumentId =
    ref(
        documentFromUrl()
    )


const copy =
    computed(() => {

        if (
            props.locale ===
            'sk'
        ) {

            return {
                allProjects:
                    'Všetky projekty',
                overview:
                    'Prehľad',
                documents:
                    'Dokumenty',
                support:
                    'Podpora',
                billing:
                    'Fakturácia'
            }

        }


        return {
            allProjects:
                'All projects',
            overview:
                'Overview',
            documents:
                'Documents',
            support:
                'Support',
            billing:
                'Billing'
        }

    })


const serviceProduct =
    computed(() =>
        props.data.project
            ?.service_product ||
        null
    )


useClientPageHeader({

    title:
        computed(() =>
            props.data.project
                ?.name ||
            ''
        ),

    eyebrow:
        computed(() =>
            serviceProduct.value
                ?.name ||
            props.data.project
                ?.service_name ||
            ''
        ),

    homeUrl:
        computed(() =>
            props.data.urls
                .dashboard
        ),

    breadcrumbs:
        computed(() => [

            {
                label:
                    copy.value
                        .allProjects,

                href:
                    props.data.urls
                        .dashboard
            },

            {
                label:
                    props.data.project
                        ?.name ||
                    ''
            }

        ])

})


function projectTabHref(
    tab,
    documentId = ''
) {

    const url =
        new URL(
            window.location.href
        )


    if (
        tab ===
        'overview'
    ) {

        url.searchParams.delete(
            'tab'
        )

    } else {

        url.searchParams.set(
            'tab',
            tab
        )

    }


    if (
        documentId
    ) {

        url.searchParams.set(
            'document',
            String(
                documentId
            )
        )

    } else {

        url.searchParams.delete(
            'document'
        )

    }


    url.hash =
        ''


    return (
        url.pathname +
        url.search
    )

}


const tabs =
    computed(() => [

        {
            label:
                copy.value.overview,

            href:
                projectTabHref(
                    'overview'
                ),

            active:
                activeTab.value ===
                'overview'
        },

        {
            label:
                copy.value.documents,

            href:
                projectTabHref(
                    'documents'
                ),

            active:
                activeTab.value ===
                'documents'
        },

        {
            label:
                copy.value.support,

            href:
                projectTabHref(
                    'support'
                ),

            active:
                activeTab.value ===
                'support'
        },

        {
            label:
                copy.value.billing,

            href:
                projectTabHref(
                    'billing'
                ),

            active:
                activeTab.value ===
                'billing'
        }

    ])


function syncFromBrowser() {

    activeTab.value =
        tabFromUrl()

    requestedDocumentId.value =
        documentFromUrl()

}


function openDocument(
    documentId
) {

    const url =
        projectTabHref(
            'documents',
            documentId
        )


    window.history.pushState(
        {},
        '',
        url
    )


    syncFromBrowser()

}


function handleDocumentClosed() {

    const url =
        new URL(
            window.location.href
        )


    url.searchParams.delete(
        'document'
    )


    window.history.replaceState(
        {},
        '',
        url.pathname +
        url.search
    )


    requestedDocumentId.value =
        ''

}


function handlePopState() {

    syncFromBrowser()

}


window.addEventListener(
    'popstate',
    handlePopState
)


onBeforeUnmount(() => {
    window.removeEventListener(
        'popstate',
        handlePopState
    )
})

</script>


<template>

    <ClientLayout
        :page="
            page
        "
        :csrf-token="
            csrfToken
        "
        :locale="
            locale
        "
        :tabs="
            tabs
        "
        @set-locale="
            emit(
                'set-locale',
                $event
            )
        "
    >

        <ProjectOverview
            v-if="
                activeTab ===
                'overview'
            "
            :data="
                data
            "
            :locale="
                locale
            "
            @open-document="
                openDocument
            "
        />


        <ProjectDocuments
            v-else-if="
                activeTab ===
                'documents'
            "
            :data="
                data
            "
            :csrf-token="
                csrfToken
            "
            :locale="
                locale
            "
            :requested-document-id="
                requestedDocumentId
            "
            @document-closed="
                handleDocumentClosed
            "
        />


        <ProjectSupport
            v-else-if="
                activeTab ===
                'support'
            "
            :data="
                data
            "
            :csrf-token="
                csrfToken
            "
            :locale="
                locale
            "
        />


        <ProjectBilling
            v-else-if="
                activeTab ===
                'billing'
            "
            :data="
                data
            "
            :locale="
                locale
            "
        />

    </ClientLayout>

</template>
