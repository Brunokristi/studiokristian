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
    importLibrary,
    setOptions
} from '@googlemaps/js-api-loader'


import {
    useRouter
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import AdminDataTable from '@shared/components/DataTable.vue'
import Tag from '@shared/components/Tag.vue'
import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'
import useAutosavePolicy from '../../composables/useAutosavePolicy'
import AdminConfirmDialog from '../../../../shared/components/ConfirmDialog.vue'
import { useAdminPageHeader } from '../../composables/useAdminPageHeader'


const {
    setStatus,
    setLastSavedAt
} =
    useAutosavePolicy()


const props =
    defineProps({
        id: {
            type: String,
            default: ''
        }
    })


const router =
    useRouter()


const loading =
    ref(
        Boolean(
            props.id
        )
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

const projects =
    ref([])

const contactsLoading =
    ref(false)

const projectsLoading =
    ref(false)

const showDeleteConfirm = 
    ref(false)

const deleting = 
    ref(false)

const form =
    reactive({
        name: '',
        registration_number: '',
        tax_number: '',
        vat_number: '',
        address: '',
        status: 'active',
        internal_notes: ''
    })


const editing =
    computed(() =>
        Boolean(
            props.id
        )
    )


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


        if (
            editing.value
        ) {
            items.push({
                label:
                    pageTitle.value
            })
        } else {
            items.push({
                label: 'New client'
            })
        }


        return items
    })


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
        import.meta.env.VITE_GOOGLE_MAPS_API_KEY


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


    setOptions({
        key: apiKey,
        v: 'weekly',
        language: 'sk',
        region: 'SK'
    })


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
            await autocompleteSuggestion.fetchAutocompleteSuggestions({
                input: query,
                language: 'sk',
                region: 'SK',
                sessionToken
            })


        if (
            requestId !== addressRequestId
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
                            prediction.text.toString(),

                        label:
                            prediction.text.toString(),

                        prediction
                    })
                )
    } catch (
        exception
    ) {
        if (
            requestId === addressRequestId
        ) {
            addressOptions.value =
                []


            showError(
                'Address suggestions are unavailable. You can still enter the address manually.'
            )
        }
    } finally {
        if (
            requestId === addressRequestId
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



const contactColumns = [
    {
        key: 'name',
        label: 'Contact'
    },


    {
        key: 'email',
        label: 'Email'
    },


    {
        key: 'position',
        label: 'Position'
    },


    {
        key: 'active',
        label: 'Status'
    },


    {
        key: 'can_access_portal',
        label: 'Portal'
    }
]


const projectColumns = [
    {
        key: 'name',
        label: 'Project'
    },


    {
        key: 'service_product',
        label: 'Service'
    },


    {
        key: 'status',
        label: 'Status'
    }
]


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


async function loadClient() {
    if (
        !props.id
    ) {
        loading.value =
            false

        return
    }


    try {
        const response =
            await api.get(
                `/clients/${props.id}`
            )


        const client =
            response.data.data


        Object.assign(
            form,
            {
                name:
                    client.name || '',

                registration_number:
                    client.registration_number || '',

                tax_number:
                    client.tax_number || '',

                vat_number:
                    client.vat_number || '',

                address:
                    client.address || '',

                status:
                    client.status ||
                    'active',

                internal_notes:
                    client.internal_notes || ''
            }
        )


        contacts.value =
            client.contacts ||
            []


        projects.value =
            client.projects ||
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
        const response =
            editing.value
                ? await api.put(
                    `/clients/${props.id}`,
                    {
                        ...form,
                        display_name: undefined,
                        billing_address: undefined,
                        billing_details: undefined
                    }
                )
                : await api.post(
                    '/clients',
                    {
                        ...form,
                        display_name: undefined,
                        billing_address: undefined,
                        billing_details: undefined
                    }
                )


        if (
            !editing.value
        ) {
            router.push({
                name: 'clients.show',

                params: {
                    id:
                        response.data.data.id
                }
            })

            return
        }


        Object.assign(
            form,
            {
                name:
                    response.data.data.name || '',
                registration_number:
                    response.data.data.registration_number || '',
                tax_number:
                    response.data.data.tax_number || '',
                vat_number:
                    response.data.data.vat_number || '',
                address:
                    response.data.data.address || '',
                status:
                    response.data.data.status || 'active',
                internal_notes:
                    response.data.data.internal_notes || ''
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


function scheduleAutosave() {
    if (
        !editing.value ||
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
        setTimeout(() => {
            if (
                !saving.value &&
                editing.value &&
                form.name?.trim()
            ) {
                submit()
            }
        }, 600)
}


function cancel() {
    router.push({
        name: 'clients.index'
    })
}


function deleteClient() {
    if (
        !editing.value ||
        !props.id ||
        deleting.value
    ) {
        return
    }

    showDeleteConfirm.value = true
}

async function confirmDeleteClient() {
    if (
        !editing.value ||
        !props.id ||
        deleting.value
    ) {
        return
    }

    deleting.value = true

    requestError.value = ''

    try {
        await api.delete(
            `/clients/${props.id}`
        )

        showDeleteConfirm.value = false

        router.push({
            name: 'clients.index'
        })
    } catch (
        exception
    ) {
        showDeleteConfirm.value = false

        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        deleting.value = false
    }
}

function closeDeleteConfirm() {
    if (deleting.value) {
        return
    }

    showDeleteConfirm.value = false
}

function openProject(
    project
) {
    router.push({
        name: 'projects.show',

        params: {
            id: project.id
        }
    })
}


function editContact(contact) {
    if (!contact?.id || !props.id) {
        return
    }

    router.push({
        name: 'contacts.edit',
        params: {
            companyId: props.id,
            id: contact.id
        }
    })
}


function createContact() {
    if (!props.id) {
        return
    }

    router.push({
        name: 'contacts.create',
        params: {
            companyId: props.id
        }
    })
}


function contactName(
    contact
) {
    if (
        contact.name
    ) {
        return contact.name
    }


    return [
        contact.first_name,
        contact.last_name
    ]
        .filter(Boolean)
        .join(' ')
}

function createProject() {
    router.push({
        name: 'projects.create',
        query: {
            client_id: props.id
        }
    })
}


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
useAdminPageHeader({
    title: pageTitle,
    description: computed(() =>
        editing.value
            ? 'Manage the company information, activity and people connected to this client.'
            : 'Create a client company and add its information.'
    ),
    breadcrumbs
})
</script>


<template>
    <div
        class="
            w-full
            space-y-14
            lg:space-y-16
        "
    >
        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="requestError"
            :duration="5000"
        />


        <div
            v-if="loading"
            class="
                border-t
                border-accent
                pt-6
            "
        >
            <p
                class="
                    p
                    uppercase
                    text-dark/40
                "
            >
                Loading client...
            </p>
        </div>


        <form
            v-else
            class="
                space-y-16
            "
            @submit.prevent="submit"
        >
            <div
                class="
                    space-y-4
                "
            >
                <h2
                    class="
                        h2
                        col-span-1
                        text-left
                        text-accent
                        md:col-span-2
                    "
                >
                    Client information
                </h2>

                <div class="
                    grid
                    grid-cols-1
                    gap-8
                    md:grid-cols-2
                    md:gap-20
                ">
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
                        <div
                            class="
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
                        </div>
                    </section>
                </div>
            </div>


            <template
                v-if="editing"
            >
                <AdminDataTable
                    title="Contacts"
                    search-placeholder="Search contacts"
                    :columns="contactColumns"
                    :rows="contacts"
                    :loading="contactsLoading"
                    empty-title="No contacts yet."
                    empty-text="Add a contact to give this client a person to work with."
                    add-label=" "
                    @row-click="editContact"
                    @add="createContact"
                >
                    <template
                        #cell-name="{
                            row
                        }"
                    >
                        <span
                            class="
                                p
                                font-medium
                            "
                        >
                            {{
                                contactName(
                                    row
                                )
                            }}
                        </span>
                    </template>


                    <template
                        #cell-email="{
                            value
                        }"
                    >
                        <span class="p">
                            {{
                                value ||
                                '—'
                            }}
                        </span>
                    </template>


                    <template
                        #cell-position="{
                            value
                        }"
                    >
                        <span class="p">
                            {{
                                value ||
                                '—'
                            }}
                        </span>
                    </template>


                    <template
                        #cell-active="{
                            value
                        }"
                    >
                        <Tag
                            :text="
                                value === false
                                    ? 'inactive'
                                    : 'active'
                            "
                        />
                    </template>


                    <template
                        #cell-can_access_portal="{
                            value
                        }"
                    >
                        <Tag
                            :text="
                                value
                                    ? 'enabled'
                                    : 'disabled'
                            "
                        />
                    </template>
                </AdminDataTable>

                <AdminDataTable
                    title="Projects"
                    search-placeholder="Search projects"
                    :columns="projectColumns"
                    :rows="projects"
                    :loading="projectsLoading"
                    empty-title="No projects yet."
                    empty-text="Create a project to start tracking work for this client."
                    add-label=" "
                    @row-click="openProject"
                    @add="createProject"
                >
                    <template
                        #cell-name="{
                            row,
                            value
                        }"
                    >
                        <div>
                            <span
                                class="
                                    p
                                    font-medium
                                "
                            >
                                {{
                                    value
                                }}
                            </span>


                            <span
                                v-if="
                                    row.project_code
                                "
                                class="
                                    mt-1
                                    block
                                    text-xs
                                    uppercase
                                    text-dark/40
                                "
                            >
                                {{
                                    row.project_code
                                }}
                            </span>
                        </div>
                    </template>


                    <template
                        #cell-service_product="{
                            value,
                            row
                        }"
                    >
                        <span class="p">
                            {{
                                value?.name ||
                                row.service_product_name ||
                                value ||
                                '—'
                            }}
                        </span>
                    </template>


                    <template
                        #cell-status="{
                            value
                        }"
                    >
                        <Tag
                            :text="
                                value
                            "
                        />
                    </template>
                </AdminDataTable>

                <section
                    class="
                        space-y-4
                    "
                >
                    <h3
                        class="
                            h2
                            text-accent
                            text-left
                        "
                    >
                        Danger zone
                    </h3>


                    <Button
                        type="submit"
                        :text="'Delete client'"
                        :loading-text="'Deleting...'"
                        :loading="deleting"
                        :disabled="deleting"
                        :lowercase="true"
                        @click.prevent="deleteClient"
                        align="left"
                    />
                </section>

                <AdminConfirmDialog
                    :open="
                        showDeleteConfirm
                    "
                    title="Delete client?"
                    :text="
                        `This will permanently delete ${pageTitle}. This action cannot be undone.`
                    "
                    confirm-label="Delete client"
                    :busy="
                        deleting
                    "
                    @close="
                        closeDeleteConfirm
                    "
                    @confirm="
                        confirmDeleteClient
                    "
                />
            </template>
        </form>
    </div>
</template>