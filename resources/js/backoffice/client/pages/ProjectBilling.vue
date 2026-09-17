<script setup>

import {
    computed
} from 'vue'

import ProjectInvoicesTable
    from '../components/ProjectInvoicesTable.vue'


const props = defineProps({

    data: {
        type: Object,
        required: true
    },

    locale: {
        type: String,
        required: true
    }

})


const copy =
    computed(() => {

        if (
            props.locale ===
            'sk'
        ) {

            return {
                billing:
                    'Fakturácia',
                description:
                    'Faktúry a platobné dokumenty súvisiace s týmto projektom.'
            }

        }


        return {
            billing:
                'Billing',
            description:
                'Invoices and payment documents related to this project.'
        }

    })

</script>


<template>


    <ProjectInvoicesTable
        :invoices="
            data.project
                .invoices ||
            []
        "
        :locale="
            locale
        "
    />


</template>
