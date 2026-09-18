<script setup>

import {
    computed
} from 'vue'

import Info
    from '@shared/components/Info.vue'

import Section
    from '../../components/Section.vue'


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


const emit = defineEmits([
    'open-document'
])


const copy =
    computed(() => {

        if (
            props.locale ===
            'sk'
        ) {

            return {
                projectDetails:
                    'Detaily projektu',
                company:
                    'Spoločnosť',
                service:
                    'Služba',
                status:
                    'Stav',
                pendingSignatures:
                    'Čakajúce podpisy',
                notSpecified:
                    'Neuvedené',
                toDoNow:
                    'Vyžaduje vašu pozornosť',
                reviewAndSign:
                    'Skontrolujte a podpíšte tieto dokumenty.'
            }

        }


        return {
            projectDetails:
                'Project details',
            company:
                'Company',
            service:
                'Service',
            status:
                'Status',
            pendingSignatures:
                'Pending signatures',
            notSpecified:
                'Not specified',
            toDoNow:
                'Action required',
            reviewAndSign:
                'Please review and sign these documents.'
        }

    })


const serviceProduct =
    computed(() => {

        const product =
            props.data.project
                ?.service_product ||
            null


        if (
            !product
        ) {

            return null

        }


        return {
            id:
                product.id ||
                null,

            name:
                product.name ||
                '',

            description:
                product.description ||
                '',

            services:
                Array.isArray(
                    product.services
                )
                    ? product.services
                    : []
        }

    })


const projectDetails =
    computed(() => [

        {
            heading:
                copy.value.company,

            text:
                String(
                    props.data.contact
                        ?.company_name ||
                    copy.value
                        .notSpecified
                )
        },

        {
            heading:
                copy.value.service,

            text:
                String(
                    props.data.project
                        ?.service_name ||
                    copy.value
                        .notSpecified
                )
        },

        {
            heading:
                copy.value.status,

            text:
                String(
                    props.data.project
                        ?.status ||
                    copy.value
                        .notSpecified
                )
        },

        {
            heading:
                copy.value
                    .pendingSignatures,

            text:
                String(
                    props.data.project
                        ?.pending_signatures_count ||
                    0
                )
        }

    ])

</script>


<template>

    <div
        class="
            w-full
            space-y-24
        "
    >

        <Section
            :title="
                copy.projectDetails
            "
        >

            <div
                class="
                    grid
                    gap-0
                "
            >

                <Info
                    v-for="
                        (
                            detail,
                            index
                        ) in projectDetails
                    "
                    :key="
                        `project-detail-${index}`
                    "
                    :heading="
                        detail.heading
                    "
                    :text="
                        detail.text
                    "
                    :opened="
                        false
                    "
                />

            </div>

        </Section>


        <Section
            :title="
                copy.toDoNow
            "
        >

            <ul
                class="
                    grid
                    gap-2
                "
            >

                <li
                    v-for="
                        document
                        in data.project
                            .todo_signatures
                    "
                    :key="
                        `todo-${document.id}`
                    "
                    class="
                        border
                        border-accent
                        bg-accent
                    "
                >

                    <button
                        type="button"
                        class="
                            flex
                            w-full
                            items-center
                            justify-between
                            gap-4
                            px-4
                            py-4
                            text-left
                            text-light
                        "
                        @click="
                            emit(
                                'open-document',
                                document.id
                            )
                        "
                    >

                        <div
                            class="
                                flex
                                items-center
                                gap-2
                            "
                        >

                            <i
                                class="
                                    bi
                                    bi-file-earmark
                                    p
                                "
                            ></i>


                            <span
                                class="
                                    p
                                    uppercase
                                "
                            >

                                {{
                                    document.name
                                }}

                            </span>

                        </div>


                        <i
                            class="
                                bi
                                bi-arrow-up-right
                                p
                            "
                        ></i>

                    </button>

                </li>

            </ul>

        </Section>

    </div>

</template>
