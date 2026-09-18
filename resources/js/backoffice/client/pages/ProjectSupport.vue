<script setup>

import {
    computed,
    ref
} from 'vue'

import FormField
    from '@shared/components/FormField.vue'

import Button
    from '@shared/components/Button.vue'

import Tag
    from '@shared/components/Tag.vue'

import Section
    from '../../components/Section.vue'


const props = defineProps({

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


const supportDescription =
    ref('')


const ticketCreateInFlight =
    ref(false)


const ticketCreateError =
    ref('')


const tickets =
    ref(
        Array.isArray(
            props.data.project
                ?.tickets
        )
            ? [
                ...props.data.project
                    .tickets
            ]
            : []
    )


const copy =
    computed(() => {

        if (
            props.locale ===
            'sk'
        ) {

            return {
                support:
                    'Podpora',
                requests:
                    'Požiadavky',
                noTickets:
                    'Zatiaľ ste nevytvorili žiadne požiadavky.',
                describeRequest:
                    'Opíšte svoju požiadavku',
                supportHint:
                    'Napíšte nám, čo sa stalo a čo potrebujete.',
                descriptionPlaceholder:
                    'Popíšte požiadavku',
                sendRequest:
                    'Odoslať požiadavku',
                sending:
                    'Odosielam'
            }

        }


        return {
            support:
                'Support',
            requests:
                'Requests',
            noTickets:
                'You have not created any requests yet.',
            describeRequest:
                'Describe your request',
            supportHint:
                'Tell us what happened and what you need.',
            descriptionPlaceholder:
                'Describe your request',
            sendRequest:
                'Send request',
            sending:
                'Sending'
        }

    })


function statusLabel(
    value
) {

    return String(
        value ||
        ''
    ).replaceAll(
        '_',
        ' '
    )

}


async function submitTicket() {

    if (
        ticketCreateInFlight.value
    ) {

        return

    }


    const description =
        String(
            supportDescription.value ||
            ''
        ).trim()


    if (
        !description
    ) {

        return

    }


    ticketCreateInFlight.value =
        true

    ticketCreateError.value =
        ''


    try {

        const response =
            await fetch(
                String(
                    props.data.project
                        ?.ticket_url ||
                    ''
                ),
                {
                    method:
                        'POST',

                    credentials:
                        'same-origin',

                    headers: {
                        'Content-Type':
                            'application/json',

                        Accept:
                            'application/json',

                        'X-CSRF-TOKEN':
                            props.csrfToken
                    },

                    body:
                        JSON.stringify({
                            description
                        })
                }
            )


        const payload =
            await response
                .json()
                .catch(
                    () => ({})
                )


        if (
            !response.ok
        ) {

            const message =
                payload?.message ||
                payload?.errors
                    ?.description?.[0] ||
                'Could not create ticket.'


            throw new Error(
                message
            )

        }


        const created =
            payload?.ticket ||
            null


        if (
            created
        ) {

            tickets.value = [
                created,
                ...tickets.value
            ]

        }


        supportDescription.value =
            ''

    } catch (
        error
    ) {

        ticketCreateError.value =
            error instanceof Error
                ? error.message
                : 'Could not create ticket.'

    } finally {

        ticketCreateInFlight.value =
            false

    }

}

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
                copy.support
            "
        >

            <form
                @submit.prevent="
                    submitTicket
                "
            >

                <input
                    type="hidden"
                    name="_token"
                    :value="
                        csrfToken
                    "
                >


                <FormField
                    id="support-description"
                    v-model="
                        supportDescription
                    "
                    name="description"
                    type="textarea"
                    :label="
                        copy.describeRequest
                    "
                    :placeholder="
                        copy.descriptionPlaceholder
                    "
                    :error="
                        ticketCreateError
                    "
                    :required="
                        true
                    "
                    :disabled="
                        ticketCreateInFlight
                    "
                />


                <Button
                    class="
                        mt-6
                    "
                    type="submit"
                    variant="dark"
                    :text="
                        copy.sendRequest
                    "
                    :loading="
                        ticketCreateInFlight
                    "
                    :loading-text="
                        copy.sending
                    "
                    align="right"
                />

            </form>

        </Section>


        <Section
            :title="
                copy.requests
            "
        >

            <div
                class="
                    grid
                    gap-3
                "
            >

                <article
                    v-for="
                        ticket
                        in tickets
                    "
                    :key="
                        ticket.id
                    "
                    class="
                        border
                        border-accent
                        bg-light
                        p-4
                        transition-all
                        duration-200
                        hover:bg-accent/[0.04]
                    "
                >

                    <p
                        class="
                            p
                            min-w-0
                            flex-1
                            font-medium
                        "
                    >
                        {{
                            ticket.title
                        }}
                    </p>


                    <div
                        class="
                            mt-2
                            flex
                            flex-wrap
                            gap-2
                        "
                    >

                        <Tag
                            :text="
                                statusLabel(
                                    ticket.status
                                )
                            "
                        />


                        <Tag
                            v-if="
                                ticket.priority
                            "
                            :text="
                                statusLabel(
                                    ticket.priority
                                )
                            "
                        />

                    </div>


                    <p
                        v-if="
                            ticket.description
                        "
                        class="
                            mt-3
                            p
                            text-dark/60
                        "
                    >
                        {{
                            ticket.description
                        }}
                    </p>

                </article>


                <p
                    v-if="
                        !tickets.length
                    "
                    class="
                        p
                        text-dark/45
                    "
                >
                    {{
                        copy.noTickets
                    }}
                </p>

            </div>

        </Section>

    </div>

</template>
