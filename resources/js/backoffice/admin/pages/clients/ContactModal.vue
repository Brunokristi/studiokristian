<script setup>

import {
    computed,
    reactive,
    ref,
    watch
} from 'vue'

import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'

import AdminConfirmDialog from '@shared/components/ConfirmDialog.vue'
import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Modal from '@shared/components/Modal.vue'

const props = defineProps({

    open: {
        type: Boolean,
        default: false
    },

    companyId: {
        type: [String, Number],
        default: ''
    },

    contact: {
        type: Object,
        default: null
    }

})

const emit = defineEmits([
    'close',
    'saved',
    'error'
])

const saving = ref(false)

const deleting = ref(false)

const resendingInvitation = ref(false)

const showDeleteConfirm = ref(false)

const errors = ref({})

const form = reactive({

    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    position: '',
    active: true,
    can_access_portal: false,
    can_accept_documents: false

})

const editing = computed(() => {

    return Boolean(
        props.contact?.id
    )

})

const contactName = computed(() => {

    return [
        form.first_name,
        form.last_name
    ]
        .filter(Boolean)
        .join(' ')

})

const title = computed(() => {

    if (!editing.value) {
        return 'New contact'
    }

    return contactName.value || 'Edit contact'

})

const subtitle = computed(() => {

    if (editing.value) {
        return 'Update contact details and access permissions.'
    }

    return 'Add a person connected to this client.'

})

/*
|--------------------------------------------------------------------------
| Options
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Form dependencies
|--------------------------------------------------------------------------
*/

watch(
    () => form.active,
    value => {

        if (!value) {

            form.can_access_portal = false

            form.can_accept_documents = false

        }

    }
)

watch(
    () => form.can_access_portal,
    value => {

        if (!value) {

            form.can_accept_documents = false

        }

    }
)

/*
|--------------------------------------------------------------------------
| Modal initialization
|--------------------------------------------------------------------------
*/

watch(
    () => props.open,
    open => {

        if (open) {
            initialize()
        }

    }
)

watch(
    () => props.contact,
    () => {

        if (props.open) {
            initialize()
        }

    }
)

function initialize() {

    errors.value = {}

    if (props.contact) {

        Object.assign(
            form,
            {

                first_name:
                    props.contact.first_name || '',

                last_name:
                    props.contact.last_name || '',

                email:
                    props.contact.email || '',

                phone:
                    props.contact.phone || '',

                position:
                    props.contact.position || '',

                active:
                    props.contact.active !== false,

                can_access_portal:
                    Boolean(
                        props.contact.can_access_portal
                    ),

                can_accept_documents:
                    Boolean(
                        props.contact.can_accept_documents
                    )

            }
        )

        return

    }

    Object.assign(
        form,
        {

            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            position: '',
            active: true,
            can_access_portal: false,
            can_accept_documents: false

        }
    )

}

/*
|--------------------------------------------------------------------------
| Payload
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

async function submit() {

    if (saving.value) {
        return
    }

    saving.value = true

    errors.value = {}

    try {

        if (!props.companyId) {

            throw new Error(
                'Missing company id.'
            )

        }

        let response

        if (editing.value) {

            response = await api.put(
                `/clients/${props.companyId}/contacts/${props.contact.id}`,
                buildPayload()
            )

        } else {

            response = await api.post(
                `/clients/${props.companyId}/contacts`,
                buildPayload()
            )

        }

        emit(
            'saved',
            response?.data?.data || null
        )

    } catch (exception) {

        errors.value =
            validationErrors(
                exception
            )

        emit(
            'error',
            errorMessage(
                exception
            )
        )

    } finally {

        saving.value = false

    }

}

/*
|--------------------------------------------------------------------------
| Resend invitation
|--------------------------------------------------------------------------
*/

async function resendInvitation() {

    if (
        !editing.value ||
        !props.companyId ||
        !form.can_access_portal ||
        resendingInvitation.value
    ) {
        return
    }

    resendingInvitation.value = true

    try {

        await api.post(
            `/clients/${props.companyId}/contacts/${props.contact.id}/resend-invitation`
        )

    } catch (exception) {

        emit(
            'error',
            errorMessage(
                exception
            )
        )

    } finally {

        resendingInvitation.value = false

    }

}

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

function deleteContact() {

    if (
        !editing.value ||
        deleting.value
    ) {
        return
    }

    showDeleteConfirm.value = true

}

async function confirmDeleteContact() {

    if (
        !editing.value ||
        !props.companyId ||
        deleting.value
    ) {
        return
    }

    deleting.value = true

    try {

        await api.delete(
            `/clients/${props.companyId}/contacts/${props.contact.id}`
        )

        showDeleteConfirm.value = false

        emit(
            'saved',
            null
        )

    } catch (exception) {

        showDeleteConfirm.value = false

        emit(
            'error',
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

</script>

<template>

    <Modal
        :open="open"
        :title="title"
        :subtitle="subtitle"
        @close="emit('close')"
    >

        <!-- ============================================================ -->
        <!-- FORM -->
        <!-- ============================================================ -->

        <form
            class="
                grid
                grid-cols-1
                gap-6
                md:grid-cols-2
            "
            @submit.prevent="submit"
        >

            <!-- First name -->

            <FormField
                id="contact-first-name"
                v-model="form.first_name"
                name="first_name"
                type="text"
                label="First name"
                placeholder="Jane"
                required
                :error="
                    errors.first_name?.[0] ||
                    ''
                "
            />

            <!-- Last name -->

            <FormField
                id="contact-last-name"
                v-model="form.last_name"
                name="last_name"
                type="text"
                label="Last name"
                placeholder="Doe"
                required
                :error="
                    errors.last_name?.[0] ||
                    ''
                "
            />

            <!-- Email -->

            <FormField
                id="contact-email"
                v-model="form.email"
                name="email"
                type="email"
                label="Email"
                placeholder="jane@example.com"
                required
                :error="
                    errors.email?.[0] ||
                    ''
                "
            />

            <!-- Phone -->

            <FormField
                id="contact-phone"
                v-model="form.phone"
                name="phone"
                type="text"
                label="Phone"
                placeholder="+421 900 000 000"
                :error="
                    errors.phone?.[0] ||
                    ''
                "
            />

            <!-- Position -->

            <FormField
                id="contact-position"
                v-model="form.position"
                name="position"
                type="select"
                label="Position"
                :options="positionOptions"
                :error="
                    errors.position?.[0] ||
                    ''
                "
            />

            <!-- Status -->

            <FormField
                id="contact-active"
                v-model="form.active"
                name="active"
                type="select"
                label="Status"
                :options="statusOptions"
                :disabled="saving"
                :error="
                    errors.active?.[0] ||
                    ''
                "
            />

            <!-- Portal access -->

            <FormField
                id="contact-can-access-portal"
                v-model="form.can_access_portal"
                name="can_access_portal"
                type="select"
                label="Portal access"
                :options="portalAccessOptions"
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
                v-model="form.can_accept_documents"
                name="can_accept_documents"
                type="select"
                label="Document acceptance"
                :options="documentAcceptanceOptions"
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

        </form>

        <!-- ============================================================ -->
        <!-- DELETE CONFIRMATION -->
        <!-- ============================================================ -->

        <AdminConfirmDialog
            :open="showDeleteConfirm"
            :title="'Delete contact?'"
            :text="
                `This will permanently delete ${title}. This action cannot be undone.`
            "
            confirm-label="Delete contact"
            :busy="deleting"
            @close="closeDeleteConfirm"
            @confirm="confirmDeleteContact"
        />

        <!-- ============================================================ -->
        <!-- FOOTER -->
        <!-- ============================================================ -->

        <template #footer>

            <!-- Resend invitation -->

            <Button
                v-if="editing"
                type="button"
                text="Resend invitation"
                loading-text="Sending..."
                :loading="resendingInvitation"
                :disabled="
                    resendingInvitation ||
                    !form.can_access_portal ||
                    saving
                "
                :lowercase="true"
                @click="resendInvitation"
            />

            <!-- Delete -->

            <Button
                v-if="editing"
                type="button"
                text="Delete contact"
                variant="danger"
                :loading="deleting"
                :disabled="
                    deleting ||
                    saving ||
                    resendingInvitation
                "
                :lowercase="true"
                @click="deleteContact"
            />

            <!-- Cancel -->

            <Button
                type="button"
                text="Cancel"
                variant="secondary"
                :disabled="
                    saving ||
                    deleting ||
                    resendingInvitation
                "
                :lowercase="true"
                @click="emit('close')"
            />

            <!-- Save -->

            <Button
                type="button"
                :text="
                    editing
                        ? 'Save contact'
                        : 'Create contact'
                "
                :loading="saving"
                :disabled="
                    saving ||
                    deleting ||
                    resendingInvitation
                "
                :lowercase="true"
                @click="submit"
            />

        </template>

    </Modal>

</template>