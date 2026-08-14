<script setup>
import {
    computed,
    nextTick,
    onMounted,
    reactive,
    ref,
    watch
} from 'vue'


import {
    useRoute,
    useRouter
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import AdminPageHeader from '../../components/AdminPageHeader.vue'

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


const route =
    useRoute()


const lookups =
    ref({
        companies: [],
        service_products: []
    })


const contacts =
    ref([])


const coworkers =
    ref([])


const loading =
    ref(true)


const saving =
    ref(false)


const errors =
    ref({})


const requestError =
    ref('')


const showErrorToast =
    ref(false)


const initialCompany =
    ref(null)


const form =
    reactive({
        company_id: '',
        service_product_id: '',
        name: '',
        summary: '',
        internal_notes: '',
        portal_status: 'draft',
        started_at: '',
        completed_at: '',
        contact_ids: [],
        coworker_ids: []
    })


const lockedCompanyId =
    ref('')


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
            return 'New project'
        }


        return (
            form.name ||
            'Project'
        )
    })


const cancelRoute =
    computed(() => {
        const companyId =
            String(
                form.company_id ||
                ''
            ).trim()


        if (
            companyId
        ) {
            return {
                name: 'clients.show',

                params: {
                    id: companyId
                }
            }
        }


        return {
            name: 'clients.index'
        }
    })


const statusOptions = [
    {
        label: 'Draft',
        value: 'draft'
    },

    {
        label: 'Active',
        value: 'active'
    },

    {
        label: 'On hold',
        value: 'on_hold'
    },

    {
        label: 'Completed',
        value: 'completed'
    },

    {
        label: 'Archived',
        value: 'archived'
    }
]


const companyOptions =
    computed(() =>
        lookups.value.companies.map(
            company => ({
                label:
                    company.name,

                value:
                    String(
                        company.id
                    )
            })
        )
    )


const selectedCompanyName =
    computed(() => {
        const company =
            lookups.value.companies.find(
                item =>
                    String(
                        item.id
                    ) ===
                    String(
                        form.company_id
                    )
            )

        return (
            company?.name ||
            ''
        )
    })


const companyLocked =
    computed(() =>
        !editing.value &&
        Boolean(
            lockedCompanyId.value
        )
    )


const serviceOptions =
    computed(() =>
        lookups.value.service_products.map(
            product => ({
                label:
                    product.active
                        ? product.name
                        : `${product.name} (inactive)`,

                value:
                    String(
                        product.id
                    ),

                disabled:
                    !product.active &&
                    String(
                        product.id
                    ) !==
                        String(
                            form.service_product_id
                        )
            })
        )
    )


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


function normalizeCompanyPrefill() {
    const raw =
        route.query.company_id ??
        route.query.client_id ??
        ''


    const value =
        Array.isArray(raw)
            ? raw[0]
            : raw


    return String(
        value ||
        ''
    ).trim()
}


async function loadContacts(
    companyId,
    preserve = false
) {
    if (
        !companyId
    ) {
        contacts.value =
            []

        form.contact_ids =
            []

        return
    }


    try {
        const response =
            await api.get(
                `/companies/${companyId}/contacts/options`
            )


        contacts.value =
            response.data


        if (
            !preserve
        ) {
            form.contact_ids =
                form.contact_ids.filter(
                    id =>
                        contacts.value.some(
                            contact =>
                                String(
                                    contact.id
                                ) ===
                                String(
                                    id
                                )
                        )
                )
        }
    } catch (
        exception
    ) {
        contacts.value =
            []


        showError(
            errorMessage(
                exception
            )
        )
    }
}


async function loadCoworkers(
    preserve = false
) {
    try {
        const response =
            await api.get(
                '/coworkers'
            )

        coworkers.value =
            response.data

        if (
            !preserve
        ) {
            form.coworker_ids =
                form.coworker_ids.filter(
                    id =>
                        coworkers.value.some(
                            coworker =>
                                String(
                                    coworker.id
                                ) ===
                                String(
                                    id
                                )
                        )
                )
        }
    } catch (
        exception
    ) {
        coworkers.value =
            []


        showError(
            errorMessage(
                exception
            )
        )
    }
}


watch(
    () =>
        form.company_id,

    async (
        value,
        oldValue
    ) => {
        if (
            oldValue !==
                undefined &&
            value !==
                oldValue
        ) {
            await loadContacts(
                value,
                String(
                    value
                ) ===
                    String(
                        initialCompany.value
                    )
            )
        }
    }
)


async function load() {
    try {
        const [
            lookupsResponse,
            coworkersResponse
        ] = await Promise.all([
            api.get('/lookups'),
            api.get('/coworkers')
        ])

        lookups.value =
            lookupsResponse.data

        coworkers.value =
            coworkersResponse.data


        if (
            props.id
        ) {
            const response =
                await api.get(
                    `/projects/${props.id}`
                )


            const project =
                response.data.data


            initialCompany.value =
                project.company_id


            Object.assign(
                form,
                {
                    ...project,

                    portal_status:
                        project.status,

                    contact_ids:
                        (
                            project.contacts ||
                            []
                        ).map(
                            contact =>
                                contact.id
                        ),

                    coworker_ids:
                        (
                            project.coworkers ||
                            []
                        ).map(
                            coworker =>
                                coworker.id
                        )
                }
            )


            await loadContacts(
                project.company_id,
                true
            )
        } else {
            const companyId =
                normalizeCompanyPrefill()


            if (
                companyId
            ) {
                lockedCompanyId.value =
                    companyId

                form.company_id =
                    companyId


                await loadContacts(
                    companyId,
                    true
                )
            }

            await loadCoworkers(
                true
            )
        }
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


    errors.value =
        {}


    requestError.value =
        ''


    try {
        const response =
            editing.value
                ? await api.put(
                    `/projects/${props.id}`,
                    form
                )
                : await api.post(
                    '/projects',
                    form
                )


        router.push({
            name: 'projects.show',

            params: {
                id:
                    response.data.data.id
            }
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
    router.push(
        cancelRoute.value
    )
}


onMounted(
    load
)
</script>


<template>
    <div
        class="
            w-full
            space-y-12
            lg:space-y-14
        "
    >
        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="requestError"
            :duration="5000"
        />


        <!-- HEADER -->
        <AdminPageHeader
            :title="pageTitle"
            :description="
                editing
                    ? 'Update project details, status, and team assignments.'
                    : 'Create a project from this client and assign contacts plus coworkers.'
            "
            :breadcrumbs="[
                {
                    label: 'Projects',
                    to: {
                        name: 'projects.index'
                    }
                },

                {
                    label: pageTitle
                }
            ]"
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
                py-10
            "
        >
            <p
                class="
                    p
                    uppercase
                    text-dark/40
                "
            >
                Loading project...
            </p>
        </div>


        <!-- FORM -->
        <form
            v-else
            class="
                space-y-12
            "
            @submit.prevent="submit"
        >
            <div
                class="
                    grid
                    gap-12
                    lg:grid-cols-2
                    lg:gap-20
                "
            >
                <!-- PROJECT INFORMATION -->
                <section
                    class="
                        space-y-8
                        border-t
                        border-accent
                        pt-5
                    "
                >
                    <header>
                        <h2
                            class="
                                h3
                                text-accent
                            "
                        >
                            Project information
                        </h2>
                    </header>


                    <FormField
                        id="project-name"
                        v-model="form.name"
                        name="name"
                        type="text"
                        label="Project name"
                        placeholder="Project name"
                        required
                        :error="
                            errors.name?.[0] ||
                            ''
                        "
                    />


                    <div
                        v-if="
                            companyLocked
                        "
                        class="
                            border
                            border-accent/20
                            p-4
                        "
                    >
                        <p
                            class="
                                p
                                uppercase
                                text-dark/50
                            "
                        >
                            Client
                        </p>

                        <p
                            class="
                                h3
                                mt-2
                                text-accent
                            "
                        >
                            {{
                                selectedCompanyName ||
                                'Selected client'
                            }}
                        </p>
                    </div>


                    <FormField
                        v-else
                        id="project-company"
                        v-model="
                            form.company_id
                        "
                        name="company_id"
                        type="select"
                        label="Client"
                        :options="
                            companyOptions
                        "
                        required
                        :error="
                            errors.company_id?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="project-service"
                        v-model="
                            form.service_product_id
                        "
                        name="service_product_id"
                        type="select"
                        label="Service product"
                        :options="
                            serviceOptions
                        "
                        required
                        :error="
                            errors.service_product_id?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="project-summary"
                        v-model="
                            form.summary
                        "
                        name="summary"
                        type="textarea"
                        label="Summary"
                        placeholder="Brief description of the project"
                        :error="
                            errors.summary?.[0] ||
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
                        <header>
                            <h2
                                class="
                                    h3
                                    text-accent
                                "
                            >
                                Activity
                            </h2>
                        </header>


                        <FormField
                            id="project-status"
                            v-model="
                                form.portal_status
                            "
                            name="portal_status"
                            type="select"
                            label="Status"
                            :options="
                                statusOptions
                            "
                            :error="
                                errors.portal_status?.[0] ||
                                ''
                            "
                        />


                        <div
                            class="
                                grid
                                gap-7
                                sm:grid-cols-2
                            "
                        >
                            <FormField
                                id="project-started"
                                v-model="
                                    form.started_at
                                "
                                name="started_at"
                                type="date"
                                label="Started"
                                :error="
                                    errors.started_at?.[0] ||
                                    ''
                                "
                            />


                            <FormField
                                id="project-completed"
                                v-model="
                                    form.completed_at
                                "
                                name="completed_at"
                                type="date"
                                label="Completed"
                                :error="
                                    errors.completed_at?.[0] ||
                                    ''
                                "
                            />
                        </div>


                        <FormField
                            id="project-notes"
                            v-model="
                                form.internal_notes
                            "
                            name="internal_notes"
                            type="textarea"
                            label="Internal notes"
                            placeholder="Visible only to your team"
                            :error="
                                errors.internal_notes?.[0] ||
                                ''
                            "
                        />


                        <!-- ASSIGNED PEOPLE -->
                        <div
                            class="
                                space-y-5
                                border-t
                                border-accent
                                pt-6
                            "
                        >
                            <div>
                                <h3
                                    class="
                                        h3
                                        text-accent
                                    "
                                >
                                    Assigned people
                                </h3>

                                <p
                                    class="
                                        p
                                        mt-2
                                        uppercase
                                        text-dark/40
                                    "
                                >
                                    Choose client contacts and coworkers who should be part of this project.
                                </p>
                            </div>


                            <div
                                class="
                                    space-y-6
                                "
                            >
                                <div>
                                    <h4
                                        class="
                                            p
                                            uppercase
                                            text-dark/60
                                        "
                                    >
                                        Client contacts
                                    </h4>

                                    <div
                                        v-if="
                                            !form.company_id
                                        "
                                        class="
                                            border
                                            border-accent/20
                                            p-4
                                        "
                                    >
                                        <p
                                            class="
                                                p
                                                uppercase
                                                text-dark/50
                                            "
                                        >
                                            No client selected.
                                        </p>
                                    </div>


                                    <div
                                        v-else-if="
                                            !contacts.length
                                        "
                                        class="
                                            border
                                            border-accent/20
                                            p-4
                                        "
                                    >
                                        <p
                                            class="
                                                p
                                                uppercase
                                                text-dark/50
                                            "
                                        >
                                            This client has no active contacts.
                                        </p>
                                    </div>


                                    <div
                                        v-else
                                        class="
                                            divide-y
                                            divide-accent/20
                                            border-t
                                            border-accent
                                        "
                                    >
                                <label
                                    v-for="
                                        contact
                                        in contacts
                                    "
                                    :key="
                                        contact.id
                                    "
                                    class="
                                        flex
                                        cursor-pointer
                                        gap-4
                                        py-4
                                    "
                                >
                                    <input
                                        v-model="
                                            form.contact_ids
                                        "
                                        type="checkbox"
                                        :value="
                                            contact.id
                                        "
                                        class="
                                            mt-1
                                            h-4
                                            w-4
                                            shrink-0
                                            rounded-none
                                            border-dark
                                            text-accent
                                            focus:ring-accent
                                        "
                                    >


                                    <span
                                        class="
                                            min-w-0
                                        "
                                    >
                                        <span
                                            class="
                                                p
                                                block
                                                font-medium
                                            "
                                        >
                                            {{
                                                contact.first_name
                                            }}
                                            {{
                                                contact.last_name
                                            }}
                                        </span>

                                        <span
                                            class="
                                                p
                                                mt-1
                                                block
                                                text-dark/50
                                            "
                                        >
                                            {{
                                                contact.email
                                            }}
                                        </span>
                                    </span>
                                </label>
                                    </div>
                                </div>

                                <div>
                                    <h4
                                        class="
                                            p
                                            uppercase
                                            text-dark/60
                                        "
                                    >
                                        Coworkers
                                    </h4>

                                    <div
                                        v-if="
                                            !coworkers.length
                                        "
                                        class="
                                            border
                                            border-accent/20
                                            p-4
                                        "
                                    >
                                        <p
                                            class="
                                                p
                                                uppercase
                                                text-dark/50
                                            "
                                        >
                                            No coworkers available yet.
                                        </p>
                                    </div>


                                    <div
                                        v-else
                                        class="
                                            divide-y
                                            divide-accent/20
                                            border-t
                                            border-accent
                                        "
                                    >
                                        <label
                                            v-for="
                                                coworker
                                                in coworkers
                                            "
                                            :key="
                                                coworker.id
                                            "
                                            class="
                                                flex
                                                cursor-pointer
                                                gap-4
                                                py-4
                                            "
                                        >
                                            <input
                                                v-model="
                                                    form.coworker_ids
                                                "
                                                type="checkbox"
                                                :value="
                                                    coworker.id
                                                "
                                                class="
                                                    mt-1
                                                    h-4
                                                    w-4
                                                    shrink-0
                                                    rounded-none
                                                    border-dark
                                                    text-accent
                                                    focus:ring-accent
                                                "
                                            >


                                            <span
                                                class="
                                                    min-w-0
                                                "
                                            >
                                                <span
                                                    class="
                                                        p
                                                        block
                                                        font-medium
                                                    "
                                                >
                                                    {{
                                                        coworker.name
                                                    }}
                                                </span>

                                                <span
                                                    class="
                                                        p
                                                        mt-1
                                                        block
                                                        text-dark/50
                                                    "
                                                >
                                                    {{
                                                        coworker.email
                                                    }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>


                            <p
                                v-if="
                                    errors.contact_ids?.[0]
                                "
                                class="
                                    p
                                    text-red-700
                                "
                            >
                                {{
                                    errors.contact_ids[0]
                                }}
                            </p>


                            <p
                                v-if="
                                    errors.coworker_ids?.[0]
                                "
                                class="
                                    p
                                    text-red-700
                                "
                            >
                                {{
                                    errors.coworker_ids[0]
                                }}
                            </p>
                        </div>
                    </div>


                    <!-- SAVE -->
                    <div
                        class="
                            mt-10
                            flex
                            flex-col
                            gap-4
                            border-t
                            border-accent
                            pt-6
                            sm:flex-row
                            sm:justify-end
                        "
                    >
                        <Button
                            type="button"
                            text="cancel"
                            :disabled="saving"
                            @click="cancel"
                        />


                        <Button
                            type="submit"
                            :text="
                                editing
                                    ? 'save changes'
                                    : 'create project'
                            "
                            loading-text="saving"
                            :loading="saving"
                            :disabled="saving"
                            variant="accent"
                        />
                    </div>
                </section>
            </div>
        </form>
    </div>
</template>