<script setup>
import {
    computed,
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
import useAutosavePolicy from '../../composables/useAutosavePolicy'


const {
    setStatus,
    setLastSavedAt
} =
    useAutosavePolicy()


const props = defineProps({
    companyId: {
        type: String,
        default: ''
    },

    id: {
        type: String,
        default: ''
    }
})


const route =
    useRoute()


const router =
    useRouter()


const companyId =
    computed(() =>
        props.companyId ||
        route.params.companyId ||
        ''
    )


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


const autosaveTimer =
    ref(null)


const suppressAutosave =
    ref(false)


const inputHasFocus =
    ref(false)


const lastSavedSnapshot =
    ref('')


const form =
    reactive({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        position: '',
        active: true,
        can_access_portal: false,
        can_accept_documents: false
    })


watch(
    () => form.active,
    (value) => {
        if (!value) {
            form.can_access_portal = false
            form.can_accept_documents = false
        }
    }
)


watch(
    () => form.can_access_portal,
    (value) => {
        if (!value) {
            form.can_accept_documents = false
        }
    }
)


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
            return 'New contact'
        }


        return [
            form.first_name,
            form.last_name
        ]
            .filter(Boolean)
            .join(' ') ||
            'Contact'
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
            companyId.value
        ) {
            items.push({
                label: 'Client',
                to: {
                    name: 'clients.show',
                    params: {
                        id:
                            companyId.value
                    }
                }
            })
        }


        items.push({
            label:
                editing.value
                    ? pageTitle.value
                    : 'New contact'
        })


        return items
    })


const positionOptions = [
    {
        label: 'Owner',
        value: 'owner'
    },

    {
        label: 'Managing director',
        value: 'managing_director'
    },

    {
        label: 'Director',
        value: 'director'
    },

    {
        label: 'Manager',
        value: 'manager'
    },

    {
        label: 'Project manager',
        value: 'project_manager'
    },

    {
        label: 'Operations manager',
        value: 'operations_manager'
    },

    {
        label: 'Account manager',
        value: 'account_manager'
    },

    {
        label: 'Marketing manager',
        value: 'marketing_manager'
    },

    {
        label: 'Sales manager',
        value: 'sales_manager'
    },

    {
        label: 'Finance manager',
        value: 'finance_manager'
    },

    {
        label: 'HR manager',
        value: 'hr_manager'
    },

    {
        label: 'Assistant',
        value: 'assistant'
    },

    {
        label: 'Coordinator',
        value: 'coordinator'
    },

    {
        label: 'Other',
        value: 'other'
    }
]


const statusOptions = [
    {
        label: 'Active',
        value: true
    },

    {
        label: 'Inactive',
        value: false
    }
]


const portalAccessOptions = [
    {
        label: 'Enabled',
        value: true
    },

    {
        label: 'Disabled',
        value: false
    }
]


const documentAcceptanceOptions = [
    {
        label: 'Allowed',
        value: true
    },

    {
        label: 'Not allowed',
        value: false
    }
]


function showError(
    message
) {
    requestError.value =
        message
}


function getAutosaveSnapshot() {
    return JSON.stringify({
        ...form
    })
}


async function loadContact() {
    if (
        !props.id ||
        !companyId.value
    ) {
        loading.value =
            false

        return
    }


    suppressAutosave.value =
        true

    try {
        const response =
            await api.get(
                `/clients/${companyId.value}`
            )


        const contact =
            (
                response.data.data.contacts ||
                []
            ).find(
                item =>
                    String(item.id) ===
                    String(props.id)
            )


        if (
            !contact
        ) {
            throw new Error(
                'Contact not found.'
            )
        }


        Object.assign(
            form,
            {
                first_name:
                    contact.first_name ||
                    '',

                last_name:
                    contact.last_name ||
                    '',

                email:
                    contact.email ||
                    '',

                phone:
                    contact.phone ||
                    '',

                position:
                    contact.position ||
                    '',

                active:
                    contact.active !==
                    false,

                can_access_portal:
                    Boolean(
                        contact.can_access_portal
                    ),

                can_accept_documents:
                    Boolean(
                        contact.can_accept_documents
                    )
            }
        )


        lastSavedSnapshot.value =
            getAutosaveSnapshot()
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

        suppressAutosave.value =
            false
    }
}


function buildPayload() {
    return {
        first_name:
            form.first_name,

        last_name:
            form.last_name,

        email:
            form.email,

        phone:
            form.phone,

        position:
            form.position,

        active:
            Boolean(
                form.active
            ),

        can_access_portal:
            Boolean(
                form.active &&
                form.can_access_portal
            ),

        can_accept_documents:
            Boolean(
                form.active &&
                form.can_access_portal &&
                form.can_accept_documents
            )
    }
}


async function submit() {
    if (
        saving.value
    ) {
        return
    }


    suppressAutosave.value =
        true

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
        if (
            !companyId.value
        ) {
            throw new Error(
                'Missing company id.'
            )
        }


        if (
            editing.value
        ) {
            await api.put(
                `/clients/${companyId.value}/contacts/${props.id}`,
                buildPayload()
            )
        } else {
            const response =
                await api.post(
                    `/clients/${companyId.value}/contacts`,
                    buildPayload()
                )


            if (
                response?.data?.data?.id
            ) {
                router.push({
                    name:
                        'clients.show',

                    params: {
                        id:
                            companyId.value
                    }
                })

                return
            }
        }


        setLastSavedAt()

        lastSavedSnapshot.value =
            getAutosaveSnapshot()
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

        suppressAutosave.value =
            false
    }
}


function handleFieldFocus() {
    inputHasFocus.value =
        true
}


function handleFieldBlur() {
    inputHasFocus.value =
        false

    scheduleAutosave()
}


function scheduleAutosave() {
    if (
        suppressAutosave.value ||
        loading.value ||
        saving.value ||
        inputHasFocus.value ||
        !companyId.value ||
        !form.first_name?.trim() &&
        !form.last_name?.trim() &&
        !form.email?.trim()
    ) {
        return
    }

    const snapshot =
        getAutosaveSnapshot()

    if (
        lastSavedSnapshot.value &&
        snapshot ===
        lastSavedSnapshot.value
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
                companyId.value
            ) {
                submit()
            }
        }, 600)
}


function cancel() {
    if (
        companyId.value
    ) {
        router.push({
            name:
                'clients.show',

            params: {
                id:
                    companyId.value
            }
        })

        return
    }


    router.push({
        name:
            'clients.index'
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
        await loadContact()
    }
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
        <!-- Header -->
        <AdminPageHeader
            :title="pageTitle"
            :description="
                editing
                    ? 'Update the contact details and access permissions.'
                    : 'Create a contact for this client company.'
            "
            :breadcrumbs="
                breadcrumbs
            "
        />


        <!-- Loading -->
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
                Loading contact...
            </p>
        </div>


        <!-- Form -->
        <form
            v-else
            class="
                space-y-12
            "
            @submit.prevent="submit"
        >
            <!-- Contact information -->
            <section
                class="
                    grid
                    grid-cols-1
                    gap-8
                    md:grid-cols-2
                    md:gap-x-20
                    md:gap-y-8
                "
            >
                <!-- First name -->
                <FormField
                    id="contact-first-name"
                    v-model="
                        form.first_name
                    "
                    name="first_name"
                    type="text"
                    label="First name"
                    placeholder="Jane"
                    required
                    :error="
                        errors.first_name?.[0] ||
                        ''
                    "
                    @focus="handleFieldFocus"
                    @blur="handleFieldBlur"
                />


                <!-- Last name -->
                <FormField
                    id="contact-last-name"
                    v-model="
                        form.last_name
                    "
                    name="last_name"
                    type="text"
                    label="Last name"
                    placeholder="Doe"
                    required
                    :error="
                        errors.last_name?.[0] ||
                        ''
                    "
                    @focus="handleFieldFocus"
                    @blur="handleFieldBlur"
                />


                <!-- Email -->
                <FormField
                    id="contact-email"
                    v-model="
                        form.email
                    "
                    name="email"
                    type="email"
                    label="Email"
                    placeholder="jane@example.com"
                    required
                    :error="
                        errors.email?.[0] ||
                        ''
                    "
                    @focus="handleFieldFocus"
                    @blur="handleFieldBlur"
                />


                <!-- Phone -->
                <FormField
                    id="contact-phone"
                    v-model="
                        form.phone
                    "
                    name="phone"
                    type="text"
                    label="Phone"
                    placeholder="+421 900 000 000"
                    :error="
                        errors.phone?.[0] ||
                        ''
                    "
                    @focus="handleFieldFocus"
                    @blur="handleFieldBlur"
                />


                <!-- Position -->
                <FormField
                    id="contact-position"
                    v-model="
                        form.position
                    "
                    name="position"
                    type="select"
                    label="Position"
                    :options="
                        positionOptions
                    "
                    :error="
                        errors.position?.[0] ||
                        ''
                    "
                />


                <!-- Status -->
                <FormField
                    id="contact-active"
                    v-model="
                        form.active
                    "
                    name="active"
                    type="select"
                    label="Status"
                    :options="
                        statusOptions
                    "
                    :disabled="
                        saving
                    "
                    :error="
                        errors.active?.[0] ||
                        ''
                    "
                />


                <!-- Portal access -->
                <FormField
                    id="contact-can-access-portal"
                    v-model="
                        form.can_access_portal
                    "
                    name="can_access_portal"
                    type="select"
                    label="Portal access"
                    :options="
                        portalAccessOptions
                    "
                    :disabled="
                        !form.active ||
                        saving
                    "
                    :error="
                        errors.can_access_portal?.[0] ||
                        ''
                    "
                />


                <!-- Document acceptance -->
                <FormField
                    id="contact-can-accept-documents"
                    v-model="
                        form.can_accept_documents
                    "
                    name="can_accept_documents"
                    type="select"
                    label="Document acceptance"
                    :options="
                        documentAcceptanceOptions
                    "
                    :disabled="
                        !form.active ||
                        !form.can_access_portal ||
                        saving
                    "
                    :error="
                        errors.can_accept_documents?.[0] ||
                        ''
                    "
                />
            </section>


            <!-- Actions -->
            <div
                class="
                    flex
                    flex-col
                    gap-4
                    pt-4
                "
            >
                <Button
                    type="button"
                    text="back to client"
                    :disabled="
                        saving
                    "
                    variant="accent"
                    align="right"
                    @click="
                        cancel
                    "
                    hover-variant="dark"
                />
            </div>
        </form>
    </div>
</template>