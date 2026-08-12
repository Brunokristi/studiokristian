<script setup>
import {
    computed,
    nextTick,
    onMounted,
    reactive,
    ref
} from 'vue'


import {
    RouterLink,
    useRouter
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminDataTable from '../../components/AdminDataTable.vue'
import AdminPagination from '../../components/AdminPagination.vue'
import Tag from '@shared/components/Tag.vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'


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


const showSuccessToast =
    ref(false)


const contacts =
    ref([])


const projects =
    ref([])


const contactsLoading =
    ref(false)


const projectsLoading =
    ref(false)


const form =
    reactive({
        name: '',
        display_name: '',
        registration_number: '',
        tax_number: '',
        vat_number: '',
        registered_address: '',
        billing_address: '',
        billing_details: '',
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
            form.display_name ||
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
        key: 'status',
        label: 'Status'
    },

    {
        key: 'portal_access',
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

                display_name:
                    client.display_name || '',

                registration_number:
                    client.registration_number || '',

                tax_number:
                    client.tax_number || '',

                vat_number:
                    client.vat_number || '',

                registered_address:
                    client.registered_address || '',

                billing_address:
                    client.billing_address || '',

                billing_details:
                    client.billing_details || '',

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


async function loadContacts() {
    if (
        !props.id
    ) {
        return
    }


    contactsLoading.value =
        true


    try {
        const response =
            await api.get(
                `/clients/${props.id}/contacts`
            )


        contacts.value =
            response.data.data ??
            response.data
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        contactsLoading.value =
            false
    }
}


async function loadProjects() {
    if (
        !props.id
    ) {
        return
    }


    projectsLoading.value =
        true


    try {
        const response =
            await api.get(
                `/clients/${props.id}/projects`
            )


        projects.value =
            response.data.data ??
            response.data
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        projectsLoading.value =
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


    errors.value =
        {}


    requestError.value =
        ''


    try {
        const response =
            editing.value
                ? await api.put(
                    `/clients/${props.id}`,
                    form
                )
                : await api.post(
                    '/clients',
                    form
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
            response.data.data
        )


        await loadContacts()
        await loadProjects()


        showSuccessToast.value =
            false


        nextTick(() => {
            showSuccessToast.value =
                true
        })
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
    }
}


function cancel() {
    router.push({
        name: 'clients.index'
    })
}


function openContact(
    contact
) {
    router.push({
        name: 'contacts.show',

        params: {
            id: contact.id
        }
    })
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


onMounted(
    async () => {
        await loadClient()


        if (
            props.id
        ) {
            await Promise.all([
                loadContacts(),
                loadProjects()
            ])
        }
    }
)
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


        <Toast
            v-model="showSuccessToast"
            heading="Client saved"
            text="The client information has been updated."
            :duration="4000"
        />


        <!-- HEADER -->
        <AdminPageHeader
            :title="pageTitle"
            :description="
                editing
                    ? 'Manage the company information, activity and people connected to this client.'
                    : 'Create a client company and add its information.'
            "
            :breadcrumbs="
                breadcrumbs
            "
        >
            <Button
                type="button"
                text="cancel"
                :disabled="saving"
                @click="cancel"
            />
        </AdminPageHeader>


        <!-- LOADING -->
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
            <!-- CLIENT INFORMATION -->
            <div
                class="
                    grid
                    gap-14
                    lg:grid-cols-2
                    lg:gap-20
                "
            >
                <!-- LEGAL INFORMATION -->
                <section
                    class="
                        space-y-8
                        border-t
                        border-accent
                        pt-5
                    "
                >
                    <h2
                        class="
                            h3
                            text-accent
                        "
                    >
                        Legal information
                    </h2>


                    <FormField
                        id="client-name"
                        v-model="
                            form.name
                        "
                        name="name"
                        type="text"
                        label="Legal / business name"
                        placeholder="Company name"
                        required
                        :error="
                            errors.name?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="client-display-name"
                        v-model="
                            form.display_name
                        "
                        name="display_name"
                        type="text"
                        label="Display / trading name"
                        placeholder="Public-facing name"
                        :error="
                            errors.display_name?.[0] ||
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
                        id="client-registered-address"
                        v-model="
                            form.registered_address
                        "
                        name="registered_address"
                        type="textarea"
                        label="Registered address"
                        placeholder="Registered company address"
                        :error="
                            errors.registered_address?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="client-billing-address"
                        v-model="
                            form.billing_address
                        "
                        name="billing_address"
                        type="textarea"
                        label="Billing address"
                        placeholder="Billing address"
                        :error="
                            errors.billing_address?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="client-billing-details"
                        v-model="
                            form.billing_details
                        "
                        name="billing_details"
                        type="textarea"
                        label="Billing details"
                        placeholder="Payment and billing information"
                        :error="
                            errors.billing_details?.[0] ||
                            ''
                        "
                    />
                </section>


                <!-- ACTIVITY -->
                <section
                    class="
                        flex
                        flex-col
                        border-t
                        border-accent
                        pt-5
                    "
                >
                    <div
                        class="
                            space-y-8
                        "
                    >
                        <h2
                            class="
                                h3
                                text-accent
                            "
                        >
                            Activity & notes
                        </h2>


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


                    <!-- SAVE -->
                    <div
                        class="
                            mt-auto
                            pt-10
                        "
                    >
                        <div
                            class="
                                border-t
                                border-accent
                                pt-6
                            "
                        >
                            <Button
                                type="submit"
                                :text="
                                    editing
                                        ? 'save changes'
                                        : 'create client'
                                "
                                loading-text="saving"
                                :loading="saving"
                                :disabled="saving"
                                variant="accent"
                            />
                        </div>
                    </div>
                </section>
            </div>


            <!-- RELATED DATA -->
            <template
                v-if="editing"
            >
                <!-- CONTACTS -->
                <section
                    class="
                        space-y-5
                    "
                >
                    <div
                        class="
                            flex
                            flex-col
                            gap-4
                            border-b
                            border-accent
                            pb-5
                            sm:flex-row
                            sm:items-end
                            sm:justify-between
                        "
                    >
                        <div>
                            <h2
                                class="
                                    h3
                                    text-accent
                                "
                            >
                                Contacts
                            </h2>

                            <p
                                class="
                                    p
                                    mt-2
                                    uppercase
                                    text-dark/40
                                "
                            >
                                People connected to this client.
                            </p>
                        </div>


                        <Button
                            type="button"
                            text="add contact"
                            @click="
                                router.push({
                                    name:
                                        'contacts.create',

                                    query: {
                                        client_id:
                                            props.id
                                    }
                                })
                            "
                        />
                    </div>


                    <AdminDataTable
                        title="Contacts"
                        search-placeholder="Search contacts"
                        :columns="
                            contactColumns
                        "
                        :rows="contacts"
                        :loading="
                            contactsLoading
                        "
                        empty-title="No contacts yet."
                        empty-text="Add a contact to give this client a person to work with."
                        @row-click="
                            openContact
                        "
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


                        <template
                            #cell-portal_access="{
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
                </section>


                <!-- PROJECTS -->
                <section
                    class="
                        space-y-5
                    "
                >
                    <div
                        class="
                            flex
                            flex-col
                            gap-4
                            border-b
                            border-accent
                            pb-5
                            sm:flex-row
                            sm:items-end
                            sm:justify-between
                        "
                    >
                        <div>
                            <h2
                                class="
                                    h3
                                    text-accent
                                "
                            >
                                Projects
                            </h2>

                            <p
                                class="
                                    p
                                    mt-2
                                    uppercase
                                    text-dark/40
                                "
                            >
                                Projects and services connected to this client.
                            </p>
                        </div>


                        <Button
                            type="button"
                            text="new project"
                            @click="
                                router.push({
                                    name:
                                        'projects.create',

                                    query: {
                                        company_id:
                                            props.id
                                    }
                                })
                            "
                        />
                    </div>


                    <AdminDataTable
                        title="Projects"
                        search-placeholder="Search projects"
                        :columns="
                            projectColumns
                        "
                        :rows="projects"
                        :loading="
                            projectsLoading
                        "
                        empty-title="No projects yet."
                        empty-text="Create a project to start tracking work for this client."
                        @row-click="
                            openProject
                        "
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
                </section>
            </template>
        </form>
    </div>
</template>