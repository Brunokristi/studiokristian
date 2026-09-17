<script setup>
import {
    computed,
    onBeforeUnmount,
    reactive,
    ref,
    watch
} from 'vue'

import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'

import useAutosavePolicy from '../../composables/useAutosavePolicy'
import { useAdminPageHeader } from '../../composables/useAdminPageHeader'
import { useProjectContext } from '../../composables/useProjectContext'

import CoworkerModal from '../coworkers/CoworkerModal.vue'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'


const {
    project,
    projectId,
    setProject
} = useProjectContext()


const {
    enabled: autosaveEnabled,
    setLastSavedAt,
    setStatus
} = useAutosavePolicy()


const contactOptions = ref([])
const coworkers = ref([])
const selectedContactIds = ref([])
const selectedCoworkerIds = ref([])
const coworkerSearch = ref('')
const coworkerModalOpen = ref(false)
const coworkerCreateName = ref('')
const resendingCoworkerId = ref(null)
const resendingContactId = ref(null)
const errors = ref({})
const requestError = ref('')
const showErrorToast = ref(false)
const saving = ref(false)
const suppressAutosave = ref(true)
const autosaveTimer = ref(null)
const lastSavedSnapshot = ref('')


const shellUser = (() => {
    try {
        return JSON.parse(
            document.querySelector('#client-portal-admin-user')?.textContent ||
            '{}'
        )
    } catch {
        return {}
    }
})()


const canManage = computed(() =>
    Boolean(
        project.value?.current_user?.is_admin ??
        shellUser?.is_admin
    )
)


const contactAssignmentOptions = computed(() =>
    contactOptions.value.map(contact => ({
        label: `${contact.first_name || ''} ${contact.last_name || ''}`.trim() ||
            contact.email ||
            'Contact',
        value: contact.id
    }))
)


const coworkerAssignmentOptions = computed(() => {
    const options = []
    const seen = new Set()

    const add = user => {
        const id = Number(user?.id || 0)

        if (!id || seen.has(id)) {
            return
        }

        seen.add(id)
        options.push({
            label: `${user.name}${user.is_admin ? ' (admin)' : ''}`,
            value: id,
            email: user.email || ''
        })
    }

    if (project.value?.current_user?.is_admin) {
        add(project.value.current_user)
    }

    coworkers.value.forEach(add)

    return options
})


const coworkerAutocompleteOptions = computed(() => {
    const query = coworkerSearch.value.trim().toLowerCase()
    const selected = new Set(selectedCoworkerIds.value.map(String))

    const options = coworkerAssignmentOptions.value.filter(option => {
        if (selected.has(String(option.value))) {
            return true
        }

        if (!query) {
            return true
        }

        return [option.label, option.email].some(value =>
            String(value || '').toLowerCase().includes(query)
        )
    })

    const exactMatch = coworkerAssignmentOptions.value.some(option =>
        [option.label, option.email].some(value =>
            String(value || '').trim().toLowerCase() === query
        )
    )

    if (query && !exactMatch && canManage.value) {
        options.push({
            label: `Create "${coworkerSearch.value.trim()}"`,
            value: '__create_coworker__',
            create: true
        })
    }

    return options
})


const selectedContacts = computed(() =>
    contactOptions.value.filter(contact =>
        selectedContactIds.value.some(value => String(value) === String(contact.id))
    )
)


const selectedCoworkers = computed(() =>
    coworkerAssignmentOptions.value.filter(option =>
        selectedCoworkerIds.value.some(value => String(value) === String(option.value))
    )
)


function showError(message) {
    requestError.value = message
    showErrorToast.value = false

    requestAnimationFrame(() => {
        showErrorToast.value = true
    })
}


function snapshot() {
    return JSON.stringify({
        contact_ids: [...selectedContactIds.value].map(Number).sort((a, b) => a - b),
        coworker_ids: [...selectedCoworkerIds.value].map(Number).sort((a, b) => a - b)
    })
}


function fullProjectPayload() {
    const value = project.value

    return {
        company_id: value.company_id,
        service_product_id: value.service_product_id,
        name: value.name,
        summary: value.summary || '',
        internal_notes: value.internal_notes || '',
        portal_status: value.status || 'draft',
        is_saas: Boolean(value.is_saas),
        started_at: value.started_at || '',
        completed_at: value.completed_at || '',
        contact_ids: selectedContactIds.value,
        coworker_ids: selectedCoworkerIds.value
    }
}


async function loadPeople() {
    suppressAutosave.value = true

    try {
        const [contactsResponse, coworkersResponse] = await Promise.all([
            api.get(`/companies/${project.value.company_id}/contacts/options`),
            api.get('/coworkers', {
                params: {
                    per_page: 1000
                }
            })
        ])

        contactOptions.value = contactsResponse.data || []
        coworkers.value = coworkersResponse.data?.data || []
        selectedContactIds.value = (project.value.contacts || []).map(contact => contact.id)
        selectedCoworkerIds.value = (project.value.coworkers || []).map(coworker => coworker.id)
        lastSavedSnapshot.value = snapshot()
    } catch (exception) {
        showError(errorMessage(exception))
    } finally {
        suppressAutosave.value = false
    }
}


async function saveAssignments() {
    if (!canManage.value || saving.value || !project.value?.id) {
        return
    }

    saving.value = true
    suppressAutosave.value = true
    errors.value = {}
    setStatus('saving')

    try {
        const response = await api.put(
            `/projects/${project.value.id}`,
            fullProjectPayload()
        )

        const updated = response.data?.data || project.value
        setProject(updated)
        selectedContactIds.value = (updated.contacts || []).map(contact => contact.id)
        selectedCoworkerIds.value = (updated.coworkers || []).map(coworker => coworker.id)
        lastSavedSnapshot.value = snapshot()
        setLastSavedAt()
    } catch (exception) {
        errors.value = validationErrors(exception)
        showError(errorMessage(exception))
    } finally {
        saving.value = false
        suppressAutosave.value = false
        setStatus('idle')
    }
}


function scheduleAutosave() {
    if (
        suppressAutosave.value ||
        !autosaveEnabled.value ||
        snapshot() === lastSavedSnapshot.value
    ) {
        return
    }

    clearTimeout(autosaveTimer.value)
    autosaveTimer.value = setTimeout(() => void saveAssignments(), 600)
}


function searchCoworkers(value) {
    coworkerSearch.value = String(value || '')
}


function updateCoworkerIds(values) {
    selectedCoworkerIds.value = Array.isArray(values)
        ? values.filter(value => value !== '__create_coworker__')
        : []
}


function selectCoworkerOption(option) {
    if (!option?.create) {
        return
    }

    selectedCoworkerIds.value = selectedCoworkerIds.value.filter(
        value => value !== '__create_coworker__'
    )
    coworkerCreateName.value = coworkerSearch.value.trim()
    coworkerModalOpen.value = true
}


function handleCoworkerCreated(created) {
    const id = Number(created?.id || 0)

    if (!id) {
        return
    }

    if (!coworkers.value.some(item => Number(item.id) === id)) {
        coworkers.value.push(created)
    }

    if (!selectedCoworkerIds.value.some(value => Number(value) === id)) {
        selectedCoworkerIds.value = [...selectedCoworkerIds.value, id]
    }

    coworkerModalOpen.value = false
    coworkerSearch.value = ''
}


async function resendCoworkerInvitation(userId) {
    if (resendingCoworkerId.value) {
        return
    }

    resendingCoworkerId.value = userId

    try {
        await api.post(`/projects/${projectId.value}/coworkers/${userId}/resend-invitation`)
    } catch (exception) {
        showError(errorMessage(exception))
    } finally {
        resendingCoworkerId.value = null
    }
}


async function resendContactInvitation(contactId) {
    if (resendingContactId.value) {
        return
    }

    resendingContactId.value = contactId

    try {
        await api.post(`/projects/${projectId.value}/contacts/${contactId}/resend-invitation`)
    } catch (exception) {
        showError(errorMessage(exception))
    } finally {
        resendingContactId.value = null
    }
}


watch(
    project,
    value => {
        if (value) {
            void loadPeople()
        }
    },
    {
        immediate: true
    }
)


watch(
    [selectedContactIds, selectedCoworkerIds],
    scheduleAutosave,
    {
        deep: true
    }
)


onBeforeUnmount(() => {
    clearTimeout(autosaveTimer.value)
})


useAdminPageHeader({
    title: computed(() => project.value?.name || 'Project'),
    eyebrow: computed(() => project.value?.project_code || 'Project'),
    description: 'Project contacts, coworkers and invitations.',
    breadcrumbs: computed(() => [
        {
            label: 'Projects',
            to: { name: 'projects.index' }
        },
        {
            label: project.value?.name || 'Project'
        },
        {
            label: 'People'
        }
    ])
})
</script>


<template>
    <section class="w-full space-y-10">
        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="requestError"
            :duration="5000"
        />

        <CoworkerModal
            :open="coworkerModalOpen"
            :project-id="projectId"
            :initial-name="coworkerCreateName"
            @close="coworkerModalOpen = false"
            @created="handleCoworkerCreated"
            @error="showError"
        />

        <div>
            <h2 class="h2 text-left text-accent">
                Assigned people
            </h2>
        </div>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <FormField
                id="project-contacts"
                v-model="selectedContactIds"
                name="contact_ids"
                type="select"
                label="Client contacts"
                placeholder="Select contacts"
                multiple
                :options="contactAssignmentOptions"
                :disabled="saving"
                :error="errors.contact_ids?.[0] || ''"
            />

            <FormField
                id="project-coworkers"
                :model-value="selectedCoworkerIds"
                name="coworker_ids"
                type="autocomplete"
                label="Coworkers"
                placeholder="Search by name or email"
                multiple
                :options="coworkerAutocompleteOptions"
                :disabled="saving"
                :error="errors.coworker_ids?.[0] || ''"
                @update:model-value="updateCoworkerIds"
                @search="searchCoworkers"
                @select="selectCoworkerOption"
            />
        </div>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
            <section class="space-y-3">
                <h3 class="font-mono text-xs font-bold uppercase text-dark/60">
                    Assigned client contacts
                </h3>

                <p
                    v-if="!selectedContacts.length"
                    class="p text-dark/40"
                >
                    No client contacts assigned.
                </p>

                <div
                    v-for="contact in selectedContacts"
                    :key="contact.id"
                    class="flex items-center justify-between gap-4 border-b border-accent py-3"
                >
                    <div class="min-w-0">
                        <p class="p truncate font-medium">
                            {{ `${contact.first_name || ''} ${contact.last_name || ''}`.trim() || contact.email }}
                        </p>
                        <p class="truncate text-xs text-dark/50">
                            {{ contact.email }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 font-mono text-[10px] font-bold uppercase text-accent hover:text-dark"
                        :disabled="resendingContactId === contact.id"
                        @click="resendContactInvitation(contact.id)"
                    >
                        {{ resendingContactId === contact.id ? 'Sending...' : 'Resend invite' }}
                    </button>
                </div>
            </section>

            <section class="space-y-3">
                <h3 class="font-mono text-xs font-bold uppercase text-dark/60">
                    Assigned coworkers
                </h3>

                <p
                    v-if="!selectedCoworkers.length"
                    class="p text-dark/40"
                >
                    No coworkers assigned.
                </p>

                <div
                    v-for="option in selectedCoworkers"
                    :key="option.value"
                    class="flex items-center justify-between gap-4 border-b border-accent py-3"
                >
                    <div class="min-w-0">
                        <p class="p truncate font-medium">
                            {{ option.label }}
                        </p>
                        <p class="truncate text-xs text-dark/50">
                            {{ option.email }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 font-mono text-[10px] font-bold uppercase text-accent hover:text-dark"
                        :disabled="resendingCoworkerId === option.value"
                        @click="resendCoworkerInvitation(option.value)"
                    >
                        {{ resendingCoworkerId === option.value ? 'Sending...' : 'Resend invite' }}
                    </button>
                </div>
            </section>
        </div>
    </section>
</template>
