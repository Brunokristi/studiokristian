<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref
} from 'vue'


import {
    RouterLink,
    useRouter
} from 'vue-router'


import api, {
    errorMessage
} from '../../composables/useAdminApi'


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminDataTable from '../../components/AdminDataTable.vue'
import AdminConfirmDialog from '../../components/AdminConfirmDialog.vue'
import DocumentEditor from '../../components/DocumentEditor.vue'
import ProjectFilesDrive from '../../components/ProjectFilesDrive.vue'
import ServiceFileStructure from '../../components/ServiceFileStructure.vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Tag from '@shared/components/Tag.vue'
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


const project =
    ref(null)


const loading =
    ref(true)


const error =
    ref('')


const showErrorToast =
    ref(false)


const busy =
    ref(false)


const showArchiveConfirm =
    ref(false)


const tickets =
    ref([])


const contactOptions =
    ref([])


const coworker =
    reactive({
        name: '',
        email: ''
    })


const ticketForm =
    reactive({
        title: '',
        description: '',
        priority: 'normal',
        assigned_to: null
    })


const resendingCoworkerId =
    ref(null)


const resendingContactId =
    ref(null)


const projectFolders =
    ref([])


const structureSaveTimer =
    ref(null)


const structureSaving =
    ref(false)


const documentEditorOpen =
    ref(false)


const documentTemplate =
    ref(null)


const documentBlocks =
    ref({})


const documentSaveInFlight =
    ref(false)


const documentSaveError =
    ref('')


const documentSaveRevision =
    ref(0)


const documentSavedRevision =
    ref(0)


const ticketAssignees =
    computed(() => {
        const options = []
        const seen = new Set()


        const pushUser =
            user => {
                const id =
                    Number(
                        user?.id ||
                        0
                    )


                if (
                    !id ||
                    seen.has(id)
                ) {
                    return
                }


                seen.add(id)


                options.push({
                    id,
                    name:
                        String(
                            user?.name ||
                            'Unknown user'
                        ),
                    is_admin:
                        Boolean(
                            user?.is_admin
                        )
                })
            }


        const currentUser =
            project.value?.current_user ||
            null


        if (
            currentUser?.is_admin
        ) {
            pushUser(
                currentUser
            )
        }


        for (
            const user
            of project.value?.coworkers ||
            []
        ) {
            pushUser(
                user
            )
        }


        return options
    })


const ticketAssigneeOptions =
    computed(() => [
        {
            label: 'Unassigned',
            value: null
        },


        ...ticketAssignees.value.map(
            user => ({
                label:
                    `${user.name}${user.is_admin ? ' (admin)' : ''}`,
                value:
                    user.id
            })
        )
    ])


const ticketPriorityOptions = [
    {
        label: 'Low',
        value: 'low'
    },


    {
        label: 'Normal',
        value: 'normal'
    },


    {
        label: 'High',
        value: 'high'
    },


    {
        label: 'Urgent',
        value: 'urgent'
    }
]


const ticketStatusOptions = [
    {
        label: 'New',
        value: 'new'
    },


    {
        label: 'In progress',
        value: 'in_progress'
    },


    {
        label: 'Finished',
        value: 'finished'
    }
]


const projectInfo = computed(() => [
    {
        label: 'Client',
        value:
            project.value?.company?.name ||
            '—'
    },


    {
        label: 'Product',
        value:
            project.value?.service_product?.name ||
            '—'
    },


    {
        label: 'Blueprint',
        value:
            project.value?.blueprint_version
                ? `${project.value.blueprint_version.name || '—'} v${project.value.blueprint_version.version || ''}`
                : '—'
    },


    {
        label: 'Started',
        value:
            project.value?.started_at ||
            '—'
    }
])


function showError(
    message
) {
    error.value =
        message


    showErrorToast.value =
        false


    requestAnimationFrame(() => {
        showErrorToast.value =
            true
    })
}


function isPersistedFolderId(
    value
) {
    if (
        value === null ||
        value === undefined
    ) {
        return false
    }


    const numeric =
        Number(
            value
        )


    return (
        Number.isInteger(
            numeric
        ) &&
        numeric > 0
    )
}


function normalizeProjectFolders(
    serverFolders = [],
    previousFolders = []
) {
    const source =
        Array.isArray(
            serverFolders
        )
            ? [
                ...serverFolders
            ]
            : []


    const previous =
        Array.isArray(
            previousFolders
        )
            ? [
                ...previousFolders
            ]
            : []


    source.sort(
        (
            a,
            b
        ) =>
            Number(
                a?.sort_order ||
                0
            ) -
            Number(
                b?.sort_order ||
                0
            )
    )


    previous.sort(
        (
            a,
            b
        ) =>
            Number(
                a?.sort_order ||
                0
            ) -
            Number(
                b?.sort_order ||
                0
            )
    )


    const normalized =
        source.map(
            (
                item,
                index
            ) => {
                const previousItem =
                    previous[index] ||
                    null


                return {
                    ...item,

                    client_key:
                        previousItem?.client_key ||
                        String(
                            item.id
                        ),

                    parent_client_key:
                        null,

                    client_visible:
                        item.client_visible ??
                        true
                }
            }
        )


    const idToClientKey =
        new Map(
            normalized.map(
                item => [
                    String(
                        item.id
                    ),
                    item.client_key
                ]
            )
        )


    return normalized.map(
        item => ({
            ...item,

            parent_client_key:
                item.parent_id !== null &&
                item.parent_id !== undefined
                    ? (
                        idToClientKey.get(
                            String(
                                item.parent_id
                            )
                        ) ||
                        String(
                            item.parent_id
                        )
                    )
                    : null
        })
    )
}


function foldersPayloadForSave() {
    const items =
        (
            projectFolders.value ||
            []
        ).map(
            item => ({
                ...item,

                id:
                    isPersistedFolderId(
                        item.id
                    )
                        ? Number(
                            item.id
                        )
                        : null,

                client_key:
                    String(
                        item.client_key ||
                        item.id
                    ),

                parent_client_key:
                    item.parent_client_key ??
                    null,

                client_visible:
                    item.client_visible ??
                    true
            })
        )


    const keyById =
        new Map(
            items.map(
                item => [
                    String(
                        item.id
                    ),
                    String(
                        item.client_key
                    )
                ]
            )
        )


    return items.map(
        item => ({
            ...item,

            parent_client_key:
                item.parent_id !== null &&
                item.parent_id !== undefined
                    ? (
                        keyById.get(
                            String(
                                item.parent_id
                            )
                        ) ||
                        String(
                            item.parent_client_key ||
                            item.parent_id
                        )
                    )
                    : null
        })
    )
}


function normalizeOpenUrl(
    value
) {
    const raw =
        String(
            value ||
            ''
        ).trim()


    if (!raw) {
        return ''
    }


    if (
        raw.startsWith('/') ||
        raw.startsWith('#')
    ) {
        return raw
    }


    if (
        /^[a-z][a-z\d+.-]*:/i.test(
            raw
        )
    ) {
        return raw
    }


    return `https://${raw}`
}


function readDocumentEnvelope(
    content
) {
    try {
        const parsed =
            JSON.parse(
                String(
                    content ||
                    ''
                )
            )


        if (
            parsed &&
            typeof parsed === 'object' &&
            !Array.isArray(parsed)
        ) {
            return {
                title:
                    String(
                        parsed.title ||
                        ''
                    ),

                subtitle:
                    String(
                        parsed.subtitle ||
                        ''
                    ),

                doc:
                    parsed.doc ||
                    parsed
            }
        }
    } catch {
        // Legacy content is handled by the editor.
    }


    return {
        title: '',
        subtitle: '',
        doc:
            content ||
            ''
    }
}


async function load() {
    loading.value =
        true


    try {
        const response =
            await api.get(
                `/projects/${props.id}`
            )


        project.value =
            response.data.data


        projectFolders.value =
            normalizeProjectFolders(
                project.value?.folders ||
                [],
                projectFolders.value ||
                []
            )


        tickets.value =
            (
                await api.get(
                    `/projects/${props.id}/tickets`
                )
            ).data


        contactOptions.value =
            (
                await api.get(
                    `/companies/${project.value.company.id}/contacts/options`
                )
            ).data
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


onMounted(
    load
)


async function togglePublishing() {
    if (
        !project.value ||
        busy.value
    ) {
        return
    }


    busy.value =
        true


    try {
        const response =
            await api.put(
                `/projects/${props.id}/publishing`,
                {
                    is_published:
                        !project.value.is_published
                }
            )


        project.value.is_published =
            response.data.data.is_published
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        busy.value =
            false
    }
}


function requestArchive() {
    showArchiveConfirm.value =
        true
}


function closeArchiveConfirm() {
    if (
        busy.value
    ) {
        return
    }


    showArchiveConfirm.value =
        false
}


async function archive() {
    if (
        busy.value
    ) {
        return
    }


    busy.value =
        true


    try {
        await api.post(
            `/projects/${props.id}/archive`
        )


        showArchiveConfirm.value =
            false


        router.push({
            name:
                'projects.index'
        })
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        busy.value =
            false
    }
}


async function inviteCoworker() {
    try {
        await api.post(
            `/projects/${props.id}/coworkers`,
            coworker
        )


        coworker.name =
            ''


        coworker.email =
            ''


        await load()
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


async function inviteContact(
    contactId
) {
    if (
        !contactId
    ) {
        return
    }


    try {
        await api.post(
            `/projects/${props.id}/contacts/invite`,
            {
                contact_id:
                    contactId
            }
        )


        await load()
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


async function resendCoworkerInvitation(
    userId
) {
    if (
        !userId ||
        resendingCoworkerId.value
    ) {
        return
    }


    resendingCoworkerId.value =
        userId


    try {
        await api.post(
            `/projects/${props.id}/coworkers/${userId}/resend-invitation`
        )
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        resendingCoworkerId.value =
            null
    }
}


async function resendContactInvitation(
    contactId
) {
    if (
        !contactId ||
        resendingContactId.value
    ) {
        return
    }


    resendingContactId.value =
        contactId


    try {
        await api.post(
            `/projects/${props.id}/contacts/${contactId}/resend-invitation`
        )
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        resendingContactId.value =
            null
    }
}


async function createTicket() {
    if (
        !ticketForm.title ||
        !ticketForm.description
    ) {
        return
    }


    try {
        await api.post(
            `/projects/${props.id}/tickets`,
            ticketForm
        )


        Object.assign(
            ticketForm,
            {
                title: '',
                description: '',
                priority: 'normal',
                assigned_to: null
            }
        )


        tickets.value =
            (
                await api.get(
                    `/projects/${props.id}/tickets`
                )
            ).data
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


async function moveTicket(
    ticket,
    status
) {
    try {
        await api.put(
            `/projects/${props.id}/tickets/${ticket.id}`,
            {
                status,
                priority:
                    ticket.priority,
                assigned_to:
                    ticket.assigned_to
            }
        )


        tickets.value =
            (
                await api.get(
                    `/projects/${props.id}/tickets`
                )
            ).data
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


function ticketsFor(
    status
) {
    return tickets.value.filter(
        ticket =>
            ticket.status ===
            status
    )
}


function queueStructureSave(
    value
) {
    projectFolders.value =
        value


    if (
        structureSaveTimer.value
    ) {
        clearTimeout(
            structureSaveTimer.value
        )
    }


    structureSaveTimer.value =
        setTimeout(
            () => {
                structureSaveTimer.value =
                    null

                void saveProjectStructure()
            },
            250
        )
}


async function saveProjectStructure() {
    if (
        structureSaving.value
    ) {
        return
    }


    structureSaving.value =
        true


    try {
        const response =
            await api.put(
                `/projects/${props.id}/structure`,
                {
                    folders:
                        foldersPayloadForSave()
                }
            )


        projectFolders.value =
            normalizeProjectFolders(
                response.data?.folders ||
                [],
                projectFolders.value ||
                []
            )
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        structureSaving.value =
            false
    }
}


function handleProjectStructureOpenFile(
    item
) {
    if (
        item?.resource_type ===
        'link'
    ) {
        const openUrl =
            normalizeOpenUrl(
                item?.url ||
                ''
            )


        if (
            openUrl
        ) {
            window.open(
                openUrl,
                '_blank',
                'noopener,noreferrer'
            )

            return
        }
    }


    showError(
        'This project file entry has no storage-backed file to open. Use Project Files below for uploaded binaries.'
    )
}


function handleProjectStructureDownloadFile(
    item
) {
    const downloadUrl =
        String(
            item?.download_url ||
            ''
        )


    if (
        downloadUrl
    ) {
        const link =
            document.createElement(
                'a'
            )


        link.href =
            downloadUrl


        link.download =
            String(
                item?.name ||
                'download'
            )


        document.body.appendChild(
            link
        )


        link.click()


        document.body.removeChild(
            link
        )


        return
    }


    showError(
        'This project file entry has no storage-backed binary to download. Use Project Files below for uploaded binaries.'
    )
}


function openProjectDocument(
    item
) {
    if (
        !item?.id
    ) {
        return
    }


    const envelope =
        readDocumentEnvelope(
            item.content ||
            ''
        )


    documentTemplate.value = {
        id:
            item.id,

        client_key:
            item.client_key ||
            String(
                item.id
            ),

        name:
            item.name ||
            envelope.title ||
            'Untitled document',

        title:
            item.name ||
            envelope.title ||
            'Untitled document',

        subtitle:
            item.subtitle ||
            envelope.subtitle ||
            '',

        content:
            item.content ||
            ''
    }


    documentBlocks.value =
        envelope.doc


    documentSaveRevision.value =
        Number(
            item.document_revision ||
            0
        )


    documentSavedRevision.value =
        documentSaveRevision.value


    documentSaveError.value =
        ''


    documentEditorOpen.value =
        true


    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    })
}


function updateDocumentBlocks(
    value
) {
    documentBlocks.value =
        value
}


function updateDocumentTitle(
    value
) {
    if (
        !documentTemplate.value
    ) {
        return
    }


    const title =
        String(
            value ||
            ''
        ).trim()


    documentTemplate.value = {
        ...documentTemplate.value,
        name:
            title,
        title:
            title
    }
}


function updateDocumentSubtitle(
    value
) {
    if (
        !documentTemplate.value
    ) {
        return
    }


    documentTemplate.value = {
        ...documentTemplate.value,
        subtitle:
            String(
                value ||
                ''
            )
    }
}


async function saveProjectDocument(
    template
) {
    const payload =
        template ||
        documentTemplate.value


    if (
        !payload?.id
    ) {
        return
    }


    const title =
        String(
            payload.title ||
            payload.name ||
            'Untitled document'
        ).trim() ||
        'Untitled document'


    const subtitle =
        String(
            payload.subtitle ||
            ''
        )


    const content =
        JSON.stringify({
            title,
            subtitle,
            doc:
                payload.document_schema ||
                documentBlocks.value ||
                {}
        })


    const index =
        (
            projectFolders.value ||
            []
        ).findIndex(
            item =>
                String(
                    item.id
                ) ===
                String(
                    payload.id
                ) ||
                String(
                    item.client_key
                ) ===
                String(
                    payload.client_key ||
                    ''
                )
        )


    if (
        index < 0
    ) {
        documentSaveError.value =
            'Document file could not be found in the project structure.'


        showError(
            documentSaveError.value
        )


        return
    }


    documentSaveInFlight.value =
        true


    documentSaveError.value =
        ''


    try {
        const next =
            [
                ...projectFolders.value
            ]


        next[index] = {
            ...next[index],
            name:
                title,
            template_name:
                title,
            content
        }


        projectFolders.value =
            next


        documentBlocks.value =
            payload.document_schema ||
            documentBlocks.value ||
            {}


        documentTemplate.value = {
            ...documentTemplate.value,
            id:
                next[index].id,
            client_key:
                next[index].client_key ||
                payload.client_key,
            name:
                title,
            title,
            subtitle,
            content
        }


        documentSaveRevision.value +=
            1


        documentSavedRevision.value =
            documentSaveRevision.value


        await saveProjectStructure()
    } catch (
        exception
    ) {
        documentSaveError.value =
            errorMessage(
                exception
            )


        showError(
            documentSaveError.value
        )
    } finally {
        documentSaveInFlight.value =
            false
    }
}


function handleDocumentBack() {
    documentEditorOpen.value =
        false
}


function openPortfolioEditor() {
    router.push({
        name:
            'portfolio.edit',

        params: {
            id:
                props.id
        }
    })
}


function openProjectEditor() {
    router.push({
        name:
            'projects.edit',

        params: {
            id:
                props.id
        }
    })
}


function openClient() {
    if (
        !project.value?.company?.id
    ) {
        return
    }


    router.push({
        name:
            'clients.show',

        params: {
            id:
                project.value.company.id
        }
    })
}


function createProjectTicket() {
    const element =
        document.getElementById(
            'new-ticket'
        )


    element?.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    })


    requestAnimationFrame(() => {
        document.getElementById(
            'ticket-title'
        )?.focus()
    })
}


onBeforeUnmount(() => {
    if (
        structureSaveTimer.value
    ) {
        clearTimeout(
            structureSaveTimer.value
        )
    }
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
        <!-- Document editor -->
        <DocumentEditor
            v-if="
                documentEditorOpen
            "
            :model-value="
                documentBlocks
            "
            :template="
                documentTemplate
            "
            :title="
                documentTemplate?.name ||
                ''
            "
            :subtitle="
                documentTemplate?.subtitle ||
                ''
            "
            :editable="true"
            :saving="
                documentSaveInFlight
            "
            :save-revision="
                documentSaveRevision
            "
            :saved-revision="
                documentSavedRevision
            "
            :save-error="
                documentSaveError
            "
            @update:title="
                updateDocumentTitle
            "
            @update:subtitle="
                updateDocumentSubtitle
            "
            @update:model-value="
                updateDocumentBlocks
            "
            @back="
                handleDocumentBack
            "
            @save="
                saveProjectDocument
            "
        />


        <template
            v-else
        >
            <Toast
                v-model="
                    showErrorToast
                "
                heading="Something went wrong"
                :text="
                    error
                "
                :duration="5000"
            />


            <!-- Header -->
            <AdminPageHeader
                :title="
                    project?.name ||
                    'Project'
                "
                :description="
                    project?.summary ||
                    'Project workspace and delivery.'
                "
                :eyebrow="
                    project?.project_code ||
                    'Project'
                "
                :breadcrumbs="[
                    {
                        label: 'Projects',
                        to: {
                            name: 'projects.index'
                        }
                    },

                    {
                        label:
                            project?.name ||
                            'Project'
                    }
                ]"
            >
                <div
                    v-if="
                        project
                    "
                    class="
                        flex
                        flex-wrap
                        items-center
                        gap-x-6
                        gap-y-3
                    "
                >
                    <Button
                        type="button"
                        :text="
                            project.is_published
                                ? 'shown on website'
                                : 'show on website'
                        "
                        :variant="
                            project.is_published
                                ? 'accent'
                                : 'dark'
                        "
                        :loading="
                            busy
                        "
                        :disabled="
                            busy
                        "
                        align="left"
                        @click="
                            togglePublishing
                        "
                    />


                    <button
                        type="button"
                        class="
                            font-mono
                            text-sm
                            font-bold
                            text-dark
                            transition-colors
                            hover:text-accent
                        "
                        @click="
                            openPortfolioEditor
                        "
                    >
                        edit website content
                    </button>


                    <button
                        type="button"
                        class="
                            font-mono
                            text-sm
                            font-bold
                            text-dark
                            transition-colors
                            hover:text-accent
                        "
                        @click="
                            requestArchive
                        "
                    >
                        archive
                    </button>


                    <button
                        type="button"
                        class="
                            font-mono
                            text-sm
                            font-bold
                            text-accent
                            transition-colors
                            hover:text-dark
                        "
                        @click="
                            openProjectEditor
                        "
                    >
                        edit project
                    </button>
                </div>
            </AdminPageHeader>


            <!-- Loading -->
            <div
                v-if="
                    loading
                "
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
                    Loading project...
                </p>
            </div>


            <template
                v-else-if="
                    project
                "
            >
                <!-- Project information -->
                <section
                    class="
                        space-y-8
                    "
                >
                    <h2
                        class="
                            h2
                            text-accent
                        "
                    >
                        Project information
                    </h2>


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
                            <div
                                class="
                                    grid
                                    grid-cols-[120px_1fr]
                                    gap-x-6
                                    gap-y-5
                                "
                            >
                                <p
                                    class="
                                        p
                                        uppercase
                                        text-dark/40
                                    "
                                >
                                    Client
                                </p>


                                <button
                                    type="button"
                                    class="
                                        p
                                        text-left
                                        transition-colors
                                        hover:text-accent
                                    "
                                    @click="
                                        openClient
                                    "
                                >
                                    {{
                                        project.company?.name ||
                                        '—'
                                    }}
                                </button>


                                <p
                                    class="
                                        p
                                        uppercase
                                        text-dark/40
                                    "
                                >
                                    Service
                                </p>


                                <p class="p">
                                    {{
                                        project.service_product?.name ||
                                        '—'
                                    }}
                                </p>


                                <p
                                    class="
                                        p
                                        uppercase
                                        text-dark/40
                                    "
                                >
                                    Blueprint
                                </p>


                                <p class="p">
                                    {{
                                        project.blueprint_version?.name ||
                                        '—'
                                    }}

                                    <span
                                        v-if="
                                            project.blueprint_version?.version
                                        "
                                    >
                                        v{{
                                            project.blueprint_version.version
                                        }}
                                    </span>
                                </p>


                                <p
                                    class="
                                        p
                                        uppercase
                                        text-dark/40
                                    "
                                >
                                    Started
                                </p>


                                <p class="p">
                                    {{
                                        project.started_at ||
                                        '—'
                                    }}
                                </p>
                            </div>
                        </section>


                        <section
                            class="
                                space-y-8
                            "
                        >
                            <div
                                class="
                                    flex
                                    items-center
                                    justify-between
                                    border-b
                                    border-accent
                                    pb-4
                                "
                            >
                                <h3
                                    class="
                                        h3
                                        text-accent
                                    "
                                >
                                    Status
                                </h3>


                                <Tag
                                    :text="
                                        project.status
                                    "
                                />
                            </div>


                            <div
                                v-if="
                                    project.configuration &&
                                    Object.keys(
                                        project.configuration
                                    ).length
                                "
                                class="
                                    grid
                                    grid-cols-1
                                    gap-5
                                    sm:grid-cols-2
                                "
                            >
                                <div
                                    v-for="
                                        (
                                            value,
                                            key
                                        ) in project.configuration
                                    "
                                    :key="
                                        key
                                    "
                                    class="
                                        border-b
                                        border-accent/20
                                        pb-3
                                    "
                                >
                                    <p
                                        class="
                                            p
                                            uppercase
                                            text-dark/40
                                        "
                                    >
                                        {{
                                            key.replaceAll(
                                                '_',
                                                ' '
                                            )
                                        }}
                                    </p>


                                    <p
                                        class="
                                            p
                                            mt-1
                                        "
                                    >
                                        {{
                                            Array.isArray(
                                                value
                                            )
                                                ? value.join(
                                                    ', '
                                                )
                                                : value
                                        }}
                                    </p>
                                </div>
                            </div>


                            <div
                                v-else
                            >
                                <p
                                    class="
                                        p
                                        text-dark/40
                                    "
                                >
                                    No additional project configuration.
                                </p>
                            </div>
                        </section>
                    </div>
                </section>


                <!-- Tickets -->
                <section
                    class="
                        space-y-8
                    "
                >
                    <div
                        class="
                            flex
                            items-end
                            justify-between
                            gap-6
                        "
                    >
                        <h2
                            class="
                                h2
                                text-accent
                            "
                        >
                            Tickets
                        </h2>


                        <Button
                            type="button"
                            text="new ticket"
                            variant="accent"
                            align="right"
                            @click="
                                createProjectTicket
                            "
                        />
                    </div>


                    <div
                        id="new-ticket"
                        class="
                            grid
                            grid-cols-1
                            gap-8
                            md:grid-cols-2
                            lg:grid-cols-4
                        "
                    >
                        <FormField
                            id="ticket-title"
                            v-model="
                                ticketForm.title
                            "
                            name="title"
                            type="text"
                            label="Title"
                            placeholder="Ticket title"
                            required
                        />


                        <FormField
                            id="ticket-description"
                            v-model="
                                ticketForm.description
                            "
                            name="description"
                            type="text"
                            label="Description"
                            placeholder="What needs to be done?"
                            required
                        />


                        <FormField
                            id="ticket-priority"
                            v-model="
                                ticketForm.priority
                            "
                            name="priority"
                            type="select"
                            label="Priority"
                            :options="
                                ticketPriorityOptions
                            "
                        />


                        <FormField
                            id="ticket-assignee"
                            v-model="
                                ticketForm.assigned_to
                            "
                            name="assigned_to"
                            type="select"
                            label="Assignee"
                            :options="
                                ticketAssigneeOptions
                            "
                        />


                        <div
                            class="
                                md:col-span-2
                                lg:col-span-4
                            "
                        >
                            <Button
                                type="button"
                                text="add ticket"
                                variant="accent"
                                align="left"
                                @click="
                                    createTicket
                                "
                            />
                        </div>
                    </div>


                    <div
                        class="
                            grid
                            gap-8
                            lg:grid-cols-3
                        "
                    >
                        <section
                            v-for="
                                column in [
                                    {
                                        key: 'new',
                                        label: 'New'
                                    },

                                    {
                                        key: 'in_progress',
                                        label: 'In progress'
                                    },

                                    {
                                        key: 'finished',
                                        label: 'Finished'
                                    }
                                ]
                            "
                            :key="
                                column.key
                            "
                            class="
                                border-t
                                border-accent
                            "
                        >
                            <div
                                class="
                                    flex
                                    items-center
                                    justify-between
                                    py-4
                                "
                            >
                                <h3
                                    class="
                                        h3
                                        text-accent
                                    "
                                >
                                    {{
                                        column.label
                                    }}
                                </h3>


                                <span
                                    class="
                                        p
                                        text-dark/40
                                    "
                                >
                                    {{
                                        ticketsFor(
                                            column.key
                                        ).length
                                    }}
                                </span>
                            </div>


                            <div
                                class="
                                    divide-y
                                    divide-accent/20
                                    border-b
                                    border-accent
                                "
                            >
                                <article
                                    v-for="
                                        ticket in ticketsFor(
                                            column.key
                                        )
                                    "
                                    :key="
                                        ticket.id
                                    "
                                    class="
                                        py-5
                                    "
                                >
                                    <div
                                        class="
                                            flex
                                            items-start
                                            justify-between
                                            gap-4
                                        "
                                    >
                                        <p
                                            class="
                                                p
                                                font-medium
                                            "
                                        >
                                            {{
                                                ticket.title
                                            }}
                                        </p>


                                        <Tag
                                            :text="
                                                ticket.priority
                                            "
                                        />
                                    </div>


                                    <p
                                        class="
                                            p
                                            mt-3
                                            text-dark/60
                                        "
                                    >
                                        {{
                                            ticket.description
                                        }}
                                    </p>


                                    <p
                                        class="
                                            p
                                            mt-4
                                            text-dark/40
                                        "
                                    >
                                        By
                                        {{
                                            ticket.creator?.name ||
                                            `${ticket.client_creator?.first_name || 'Client'} ${ticket.client_creator?.last_name || ''}`
                                        }}
                                    </p>


                                    <div
                                        class="
                                            mt-5
                                        "
                                    >
                                        <FormField
                                            :id="
                                                `ticket-status-${ticket.id}`
                                            "
                                            :model-value="
                                                ticket.status
                                            "
                                            name="status"
                                            type="select"
                                            label="Status"
                                            :options="
                                                ticketStatusOptions
                                            "
                                            @update:model-value="
                                                moveTicket(
                                                    ticket,
                                                    $event
                                                )
                                            "
                                        />
                                    </div>
                                </article>


                                <div
                                    v-if="
                                        !ticketsFor(
                                            column.key
                                        ).length
                                    "
                                    class="
                                        py-8
                                    "
                                >
                                    <p
                                        class="
                                            p
                                            uppercase
                                            text-dark/30
                                        "
                                    >
                                        No tickets
                                    </p>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>


                <!-- Files -->
                <section
                    class="
                        space-y-8
                    "
                >
                    <div
                        class="
                            flex
                            flex-col
                            gap-3
                            sm:flex-row
                            sm:items-end
                            sm:justify-between
                        "
                    >
                        <div>
                            <h2
                                class="
                                    h2
                                    text-accent
                                "
                            >
                                Project files
                            </h2>


                            <p
                                class="
                                    p
                                    mt-2
                                    max-w-2xl
                                    text-dark/50
                                "
                            >
                                Manage the project's folders,
                                documents and uploaded files.
                            </p>
                        </div>


                        <p
                            v-if="
                                structureSaving
                            "
                            class="
                                p
                                uppercase
                                text-dark/40
                            "
                        >
                            Saving...
                        </p>
                    </div>


                    <ServiceFileStructure
                        :model-value="
                            projectFolders
                        "
                        :allow-upload-control="
                            false
                        "
                        :allow-metadata-editing="
                            false
                        "
                        :prevent-deleting-required="
                            true
                        "
                        @update:model-value="
                            queueStructureSave
                        "
                        @open-document="
                            openProjectDocument
                        "
                        @open-file="
                            handleProjectStructureOpenFile
                        "
                        @download-file="
                            handleProjectStructureDownloadFile
                        "
                    />


                    <ProjectFilesDrive
                        :project-id="
                            id
                        "
                    />
                </section>


                <!-- Team -->
                <section
                    class="
                        space-y-8
                    "
                >
                    <h2
                        class="
                            h2
                            text-accent
                        "
                    >
                        Team
                    </h2>


                    <div
                        class="
                            grid
                            grid-cols-1
                            gap-12
                            md:grid-cols-2
                            md:gap-20
                        "
                    >
                        <!-- Coworkers -->
                        <section
                            class="
                                space-y-8
                            "
                        >
                            <div
                                class="
                                    border-b
                                    border-accent
                                    pb-4
                                "
                            >
                                <h3
                                    class="
                                        h3
                                        text-accent
                                    "
                                >
                                    Coworkers
                                </h3>
                            </div>


                            <div
                                v-if="
                                    project.coworkers?.length
                                "
                                class="
                                    divide-y
                                    divide-accent/20
                                "
                            >
                                <article
                                    v-for="
                                        user in project.coworkers
                                    "
                                    :key="
                                        user.id
                                    "
                                    class="
                                        flex
                                        flex-col
                                        gap-4
                                        py-5
                                        sm:flex-row
                                        sm:items-center
                                        sm:justify-between
                                    "
                                >
                                    <div>
                                        <p
                                            class="
                                                p
                                                font-medium
                                            "
                                        >
                                            {{
                                                user.name
                                            }}
                                        </p>


                                        <p
                                            class="
                                                p
                                                mt-1
                                                text-dark/40
                                            "
                                        >
                                            {{
                                                user.email
                                            }}
                                        </p>
                                    </div>


                                    <Button
                                        type="button"
                                        :text="
                                            resendingCoworkerId === user.id
                                                ? 'resending...'
                                                : 'resend invitation'
                                        "
                                        :loading="
                                            resendingCoworkerId === user.id
                                        "
                                        :disabled="
                                            Boolean(
                                                resendingCoworkerId
                                            )
                                        "
                                        align="left"
                                        @click="
                                            resendCoworkerInvitation(
                                                user.id
                                            )
                                        "
                                    />
                                </article>
                            </div>


                            <p
                                v-else
                                class="
                                    p
                                    text-dark/40
                                "
                            >
                                No coworkers yet.
                            </p>


                            <form
                                class="
                                    space-y-8
                                    border-t
                                    border-accent
                                    pt-8
                                "
                                @submit.prevent="
                                    inviteCoworker
                                "
                            >
                                <h4
                                    class="
                                        h3
                                        text-accent
                                    "
                                >
                                    Invite coworker
                                </h4>


                                <FormField
                                    id="coworker-name"
                                    v-model="
                                        coworker.name
                                    "
                                    name="name"
                                    type="text"
                                    label="Name"
                                    placeholder="Full name"
                                    required
                                />


                                <FormField
                                    id="coworker-email"
                                    v-model="
                                        coworker.email
                                    "
                                    name="email"
                                    type="email"
                                    label="Email"
                                    placeholder="name@company.com"
                                    required
                                />


                                <Button
                                    type="submit"
                                    text="invite coworker"
                                    variant="accent"
                                    align="left"
                                />
                            </form>
                        </section>


                        <!-- Client contacts -->
                        <section
                            class="
                                space-y-8
                            "
                        >
                            <div
                                class="
                                    border-b
                                    border-accent
                                    pb-4
                                "
                            >
                                <h3
                                    class="
                                        h3
                                        text-accent
                                    "
                                >
                                    Client contacts
                                </h3>
                            </div>


                            <div
                                v-if="
                                    project.contacts?.length
                                "
                                class="
                                    divide-y
                                    divide-accent/20
                                "
                            >
                                <article
                                    v-for="
                                        contact in project.contacts
                                    "
                                    :key="
                                        contact.id
                                    "
                                    class="
                                        flex
                                        flex-col
                                        gap-4
                                        py-5
                                        sm:flex-row
                                        sm:items-center
                                        sm:justify-between
                                    "
                                >
                                    <div>
                                        <p
                                            class="
                                                p
                                                font-medium
                                            "
                                        >
                                            {{
                                                contact.name
                                            }}
                                        </p>


                                        <p
                                            class="
                                                p
                                                mt-1
                                                text-dark/40
                                            "
                                        >
                                            {{
                                                contact.email
                                            }}
                                        </p>
                                    </div>


                                    <Button
                                        type="button"
                                        :text="
                                            resendingContactId === contact.id
                                                ? 'resending...'
                                                : 'resend invitation'
                                        "
                                        :loading="
                                            resendingContactId === contact.id
                                        "
                                        :disabled="
                                            Boolean(
                                                resendingContactId
                                            )
                                        "
                                        align="left"
                                        @click="
                                            resendContactInvitation(
                                                contact.id
                                            )
                                        "
                                    />
                                </article>
                            </div>


                            <p
                                v-else
                                class="
                                    p
                                    text-dark/40
                                "
                            >
                                No client contacts have access yet.
                            </p>


                            <FormField
                                id="invite-contact"
                                name="contact"
                                type="select"
                                label="Invite another contact"
                                placeholder="Select contact"
                                :options="
                                    contactOptions.map(
                                        contact => ({
                                            label:
                                                `${contact.first_name} ${contact.last_name} · ${contact.email}`,
                                            value:
                                                contact.id
                                        })
                                    )
                                "
                                @update:model-value="
                                    inviteContact
                                "
                            />
                        </section>
                    </div>
                </section>


                <!-- Danger zone -->
                <section
                    class="
                        space-y-5
                        border-t
                        border-accent
                        pt-8
                    "
                >
                    <div>
                        <h2
                            class="
                                h2
                                text-accent
                            "
                        >
                            Danger zone
                        </h2>


                        <p
                            class="
                                p
                                mt-2
                                text-dark/50
                            "
                        >
                            Archive this project when it is no longer active.
                        </p>
                    </div>


                    <Button
                        type="button"
                        text="archive project"
                        align="left"
                        :disabled="
                            busy
                        "
                        @click="
                            requestArchive
                        "
                    />
                </section>
            </template>


            <AdminConfirmDialog
                :open="
                    showArchiveConfirm
                "
                title="Archive project?"
                text="The project will be removed from active projects but will remain in historical records."
                confirm-label="Archive project"
                :busy="
                    busy
                "
                @close="
                    closeArchiveConfirm
                "
                @confirm="
                    archive
                "
            />
        </template>
    </div>
</template>