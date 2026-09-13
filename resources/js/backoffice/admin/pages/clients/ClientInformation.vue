<script setup>

import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch
} from 'vue'


import {
    importLibrary
} from '@googlemaps/js-api-loader'


import {
    useRoute,
    useRouter
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import FormField from '@shared/components/FormField.vue'
import Loading from '@shared/components/Loading.vue'
import Section from '../../../components/Section.vue'
import Toast from '@shared/components/Toast.vue'


import useAutosavePolicy from '../../composables/useAutosavePolicy'


import {
    useAdminPageHeader
} from '../../composables/useAdminPageHeader'


import {
    configureGoogleMaps
} from '@shared/googleMapsLoader'


const {
    setStatus,
    setLastSavedAt
} =
    useAutosavePolicy()


const route =
    useRoute()


const router =
    useRouter()


const id =
    computed(() =>
        String(
            route.params.id || ''
        )
    )


const editing =
    computed(() =>
        Boolean(
            id.value
        )
    )


const loading =
    ref(
        editing.value
    )


const saving =
    ref(false)


const errors =
    ref({})


const requestError =
    ref('')


const showErrorToast =
    ref(false)


const autosaveTimer =
    ref(null)


const contacts =
    ref([])


const form =
    reactive({

        name: '',

        registration_number: '',

        tax_number: '',

        vat_number: '',

        address: '',

        status: 'active',

        internal_notes: '',

        billing_contact_id: ''

    })


/*
|--------------------------------------------------------------------------
| Billing contacts
|--------------------------------------------------------------------------
*/

const billingContactOptions =
    computed(() => {

        const selectable =
            (
                contacts.value ||
                []
            )
                .filter(
                    contact =>
                        contact.email &&
                        contact.active !== false
                )
                .map(
                    contact => ({

                        value:
                            String(
                                contact.id
                            ),

                        label:
                            `${
                                [
                                    contact.first_name,
                                    contact.last_name
                                ]
                                    .filter(Boolean)
                                    .join(' ')
                            } — ${
                                contact.email
                            }`

                    })
                )


        return [

            {
                value: '',
                label: 'No billing contact'
            },

            ...selectable

        ]

    })


/*
|--------------------------------------------------------------------------
| Page title
|--------------------------------------------------------------------------
*/

const pageTitle =
    computed(() => {

        if (
            !editing.value
        ) {

            return 'New client'

        }


        return (
            form.name ||
            'Client'
        )

    })


/*
|--------------------------------------------------------------------------
| Breadcrumbs
|--------------------------------------------------------------------------
*/

const breadcrumbs =
    computed(() => {

        const items = [

            {
                label: 'Clients',

                to: {
                    name: 'clients.index'
                }
            }

        ]


        items.push({

            label:
                pageTitle.value

        })


        return items

    })


/*
|--------------------------------------------------------------------------
| Google address autocomplete
|--------------------------------------------------------------------------
*/

const addressOptions =
    ref([])


const addressLoading =
    ref(false)


let autocompleteSuggestion =
    null


let autocompleteSessionToken =
    null


let sessionToken =
    null


let addressSearchTimer =
    null


let addressRequestId =
    0


async function initializeAddressSearch() {

    const apiKey =
        import.meta.env
            .VITE_GOOGLE_MAPS_API_KEY


    if (
        !apiKey
    ) {

        return false

    }


    if (
        autocompleteSuggestion
    ) {

        return true

    }


    configureGoogleMaps(
        apiKey
    )


    const places =
        await importLibrary(
            'places'
        )


    autocompleteSuggestion =
        places.AutocompleteSuggestion


    autocompleteSessionToken =
        places.AutocompleteSessionToken


    sessionToken =
        new autocompleteSessionToken()


    return true

}


function searchAddresses(
    query
) {

    clearTimeout(
        addressSearchTimer
    )


    if (
        !query ||
        query.trim().length < 3
    ) {

        addressOptions.value =
            []

        addressLoading.value =
            false

        return

    }


    addressSearchTimer =
        setTimeout(
            () =>
                fetchAddressOptions(
                    query.trim()
                ),
            250
        )

}


async function fetchAddressOptions(
    query
) {

    const requestId =
        ++addressRequestId


    addressLoading.value =
        true


    try {

        if (
            !await initializeAddressSearch()
        ) {

            addressOptions.value =
                []

            return

        }


        const response =
            await autocompleteSuggestion
                .fetchAutocompleteSuggestions({

                    input:
                        query,

                    language:
                        'sk',

                    region:
                        'SK',

                    sessionToken

                })


        if (
            requestId !==
            addressRequestId
        ) {

            return

        }


        addressOptions.value =
            response.suggestions

                .map(
                    suggestion =>
                        suggestion.placePrediction
                )

                .filter(
                    Boolean
                )

                .map(
                    prediction => ({

                        value:
                            prediction.text
                                .toString(),

                        label:
                            prediction.text
                                .toString(),

                        prediction

                    })
                )

    } catch (
        exception
    ) {

        if (
            requestId ===
            addressRequestId
        ) {

            addressOptions.value =
                []

            showError(
                'Address suggestions are unavailable. You can still enter the address manually.'
            )

        }

    } finally {

        if (
            requestId ===
            addressRequestId
        ) {

            addressLoading.value =
                false

        }

    }

}


async function handleAddressSelect(
    option
) {

    if (
        !option
    ) {

        return

    }


    if (
        !option.prediction
    ) {

        form.address =
            option.label ||
            option.value ||
            ''

        return

    }


    addressLoading.value =
        true


    try {

        const place =
            option.prediction.toPlace()


        await place.fetchFields({

            fields: [
                'formattedAddress'
            ]

        })


        form.address =
            place.formattedAddress ||
            option.label


        addressOptions.value =
            []


        sessionToken =
            new autocompleteSessionToken()

    } catch (
        exception
    ) {

        showError(
            'The selected address could not be loaded. You can still enter the address manually.'
        )

    } finally {

        addressLoading.value =
            false

    }

}


/*
|--------------------------------------------------------------------------
| Errors
|--------------------------------------------------------------------------
*/

function showError(
    message
) {

    requestError.value =
        message


    showErrorToast.value =
        false


    nextTick(() => {

        showErrorToast.value =
            true

    })

}


/*
|--------------------------------------------------------------------------
| Load client
|--------------------------------------------------------------------------
*/

async function loadClient() {

    if (
        !id.value
    ) {

        loading.value =
            false

        return

    }


    loading.value =
        true


    try {

        const response =
            await api.get(
                `/clients/${id.value}`
            )


        const client =
            response.data.data


        Object.assign(
            form,
            {

                name:
                    client.name ||
                    '',

                registration_number:
                    client.registration_number ||
                    '',

                tax_number:
                    client.tax_number ||
                    '',

                vat_number:
                    client.vat_number ||
                    '',

                address:
                    client.address ||
                    '',

                status:
                    client.status ||
                    'active',

                internal_notes:
                    client.internal_notes ||
                    '',

                billing_contact_id:
                    client.billing_contact_id
                        ? String(
                            client.billing_contact_id
                        )
                        : ''

            }
        )


        contacts.value =
            client.contacts ||
            []

    } catch (
        exception
    ) {

        showError(
            errorMessage(
                exception
            )
        )

    } finally {

        loading.value =
            false

    }

}


/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

async function submit() {

    if (
        saving.value
    ) {

        return

    }


    saving.value =
        true


    setStatus(
        'saving'
    )


    errors.value =
        {}


    requestError.value =
        ''


    try {

        const payload = {

            ...form,

            billing_contact_id:
                form.billing_contact_id ||
                null,

            display_name:
                undefined,

            billing_address:
                undefined,

            billing_details:
                undefined

        }


        const response =
            editing.value

                ? await api.put(
                    `/clients/${id.value}`,
                    payload
                )

                : await api.post(
                    '/clients',
                    payload
                )


        if (
            !editing.value
        ) {

            router.replace({

                name:
                    'clients.show',

                params: {

                    id:
                        response.data.data.id

                }

            })


            return

        }


        const client =
            response.data.data


        Object.assign(
            form,
            {

                name:
                    client.name ||
                    '',

                registration_number:
                    client.registration_number ||
                    '',

                tax_number:
                    client.tax_number ||
                    '',

                vat_number:
                    client.vat_number ||
                    '',

                address:
                    client.address ||
                    '',

                status:
                    client.status ||
                    'active',

                internal_notes:
                    client.internal_notes ||
                    '',

                billing_contact_id:
                    client.billing_contact_id
                        ? String(
                            client.billing_contact_id
                        )
                        : ''

            }
        )


        await loadClient()


        setLastSavedAt()

    } catch (
        exception
    ) {

        errors.value =
            validationErrors(
                exception
            )


        showError(
            errorMessage(
                exception
            )
        )

    } finally {

        saving.value =
            false


        setStatus(
            'idle'
        )


        setLastSavedAt()

    }

}


/*
|--------------------------------------------------------------------------
| Autosave
|--------------------------------------------------------------------------
*/

function scheduleAutosave() {

    if (
        loading.value ||
        saving.value ||
        !form.name?.trim()
    ) {

        return

    }


    if (
        autosaveTimer.value
    ) {

        clearTimeout(
            autosaveTimer.value
        )

    }


    autosaveTimer.value =
        setTimeout(
            () => {

                if (
                    !saving.value &&
                    form.name?.trim()
                ) {

                    submit()

                }

            },
            600
        )

}


/*
|--------------------------------------------------------------------------
| Watch form
|--------------------------------------------------------------------------
*/

watch(
    () => ({
        ...form
    }),

    () => {

        scheduleAutosave()

    },

    {
        deep: true
    }
)


/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(
    async () => {

        await loadClient()

    }
)


onBeforeUnmount(() => {

    clearTimeout(
        addressSearchTimer
    )


    if (
        autosaveTimer.value
    ) {

        clearTimeout(
            autosaveTimer.value
        )

    }

})


/*
|--------------------------------------------------------------------------
| Page header
|--------------------------------------------------------------------------
*/

useAdminPageHeader({

    title:
        pageTitle,

    description:
        computed(() =>
            editing.value

                ? 'Manage the company information connected to this client.'

                : 'Create a client company and add its information.'
        ),

    breadcrumbs

})

</script>


<template>

    <div
        class="
            w-full
        "
    >

        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="requestError"
            :duration="5000"
        />


        <Section
            title="Client information"
        >

            <Loading
                v-if="loading"
            />


            <form
                v-else
                @submit.prevent="submit"
            >

                <div
                    class="
                        grid
                        grid-cols-1
                        gap-8
                        md:grid-cols-2
                        md:gap-20
                    "
                >

                    <section
                        class="
                            space-y-8
                        "
                    >

                        <FormField
                            id="client-name"
                            v-model="
                                form.name
                            "
                            name="name"
                            type="text"
                            label="Business name"
                            placeholder="Company name"
                            :error="
                                errors.name?.[0] ||
                                ''
                            "
                        />


                        <div
                            class="
                                grid
                                gap-7
                                sm:grid-cols-3
                            "
                        >

                            <FormField
                                id="client-ico"
                                v-model="
                                    form.registration_number
                                "
                                name="registration_number"
                                type="text"
                                label="IČO"
                                :error="
                                    errors.registration_number?.[0] ||
                                    ''
                                "
                            />


                            <FormField
                                id="client-dic"
                                v-model="
                                    form.tax_number
                                "
                                name="tax_number"
                                type="text"
                                label="DIČ"
                                :error="
                                    errors.tax_number?.[0] ||
                                    ''
                                "
                            />


                            <FormField
                                id="client-vat"
                                v-model="
                                    form.vat_number
                                "
                                name="vat_number"
                                type="text"
                                label="IČ DPH"
                                :error="
                                    errors.vat_number?.[0] ||
                                    ''
                                "
                            />

                        </div>


                        <FormField
                            id="client-billing-contact"
                            v-model="
                                form.billing_contact_id
                            "
                            name="billing_contact_id"
                            type="select"
                            label="Billing contact"
                            hint="Invoices and the Stripe customer use this contact."
                            :options="
                                billingContactOptions
                            "
                            :error="
                                errors.billing_contact_id?.[0] ||
                                ''
                            "
                        />


                        <p
                            v-if="
                                editing &&
                                billingContactOptions.length <= 1
                            "
                            class="
                                text-xs
                                text-dark/60
                            "
                        >
                            Add a contact with an email address before selecting a billing contact.
                        </p>


                        <FormField
                            id="client-address"
                            v-model="
                                form.address
                            "
                            name="address"
                            type="autocomplete"
                            label="Company address"
                            placeholder="Start typing the address"
                            autocomplete="street-address"
                            :options="
                                addressOptions
                            "
                            :loading="
                                addressLoading
                            "
                            :error="
                                errors.address?.[0] ||
                                ''
                            "
                            @search="
                                searchAddresses
                            "
                            @select="
                                handleAddressSelect
                            "
                        />

                    </section>


                    <section
                        class="
                            flex
                            flex-col
                            space-y-8
                        "
                    >

                        <FormField
                            id="client-status"
                            v-model="
                                form.status
                            "
                            name="status"
                            type="select"
                            label="Status"
                            :options="[

                                {
                                    label: 'Active',
                                    value: 'active'
                                },

                                {
                                    label: 'Inactive',
                                    value: 'inactive'
                                },

                                {
                                    label: 'Archived',
                                    value: 'archived'
                                }

                            ]"
                            :error="
                                errors.status?.[0] ||
                                ''
                            "
                        />


                        <FormField
                            id="client-notes"
                            v-model="
                                form.internal_notes
                            "
                            name="internal_notes"
                            type="textarea"
                            label="Internal notes"
                            placeholder="Notes for your team"
                            :error="
                                errors.internal_notes?.[0] ||
                                ''
                            "
                        />

                    </section>

                </div>

            </form>

        </Section>

    </div>

</template>