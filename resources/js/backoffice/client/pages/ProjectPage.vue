<script setup>
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    watch
} from 'vue'


import FileStructure from '../../components/FileStructure.vue'
import DocumentEditor from '../../components/DocumentEditor.vue'
import Info from '@shared/components/Info.vue'
import { useClientPageHeader } from '../composables/useClientPageHeader'
import FormField from '@shared/components/FormField.vue'
import Button from '@shared/components/Button.vue'
import Tag from '@shared/components/Tag.vue'


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

const selectedDocumentId =
    ref(null)

const activeStructureFolderId =
    ref(null)


const documentsSection =
    ref(null)


let documentsSectionObserver =
    null


const projectFiles =
    ref([])


const projectFilesLoading =
    ref(false)


const projectFilesError =
    ref('')


const projectFilesLoadedFolders =
    ref(
        new Set()
    )


const previousPageScrollY =
    ref(null)

const pageScrollLockSnapshot =
    ref(null)

const supportDescription =
    ref('')

const ticketCreateInFlight =
    ref(false)

const ticketCreateError =
    ref('')

const tickets =
    ref(
        Array.isArray(
            props.data.project?.tickets
        )
            ? [
                ...props.data.project.tickets
            ]
            : []
    )

/*
|--------------------------------------------------------------------------
| Copy
|--------------------------------------------------------------------------
*/

const copy =
    computed(() => {
        if (
            props.locale === 'sk'
        ) {
            return {
                allProjects:
                    'Všetky projekty',

                waitingSignature:
                    'Čaká na váš podpis',

                toDoNow:
                    'Vyžaduje vašu pozornosť',

                reviewAndSign:
                    'Skontrolujte a podpíšte tieto dokumenty.',

                projectDetails:
                    'Detaily projektu',

                signatureRequired:
                    'Podpísať',

                company:
                    'Spoločnosť',

                service:
                    'Služba',

                status:
                    'Stav',

                pendingSignatures:
                    'Čakajúce podpisy',

                contracts:
                    'Zmluvy',

                offers:
                    'Ponuky',

                files:
                    'Súbory',

                documents:
                    'Dokumenty',

                services:
                    'Služby a účty',

                noContracts:
                    'Nie sú dostupné žiadne zmluvy.',

                noOffers:
                    'Nie sú dostupné žiadne ponuky.',

                noFiles:
                    'Nie sú dostupné žiadne klientske súbory.',

                noDocuments:
                    'Nie sú dostupné žiadne dokumenty.',

                noServices:
                    'Nie sú dostupné žiadne služby.',

                readOnly:
                    'Len na čítanie',

                signed:
                    'Podpísané',

                noContent:
                    '<p>Obsah nie je k dispozícii.</p>',

                signDocument:
                    'Podpísať dokument',

                owner:
                    'Vlastník',

                billing:
                    'Fakturácia',

                managedBy:
                    'Spravuje',

                notSpecified:
                    'Neuvedené',

                signIn:
                    'Prihlásiť sa',

                accessDetails:
                    'Prístupové údaje sú zdieľané bezpečne.',

                support:
                    'Podpora',

                noTickets:
                    'Zatiaľ ste nevytvorili žiadne požiadavky.',

                describeProblem:
                    'Opíšte svoj problém',

                supportHint:
                    'Napíšte nám, čo sa stalo a čo potrebujete.',

                descriptionPlaceholder:
                    'Popíšte požiadavku',

                sendRequest:
                    'Odoslať požiadavku',

                version:
                    'Verzia',

                view:
                    'zobraziť',

                openDocumentHint:
                    'Dvojklikom otvoríte dokument na zobrazenie a podpis.',

                noAction:
                    'Žiadna akcia'
            }
        }


        return {
            allProjects:
                'All projects',

            waitingSignature:
                'Waiting for your signature',

            toDoNow:
                'Action required',

            reviewAndSign:
                'Please review and sign these documents.',

            projectDetails:
                'Project details',

            signatureRequired:
                'Sign',

            company:
                'Company',

            service:
                'Service',

            status:
                'Status',

            pendingSignatures:
                'Pending signatures',

            contracts:
                'Contracts',

            offers:
                'Offers',

            files:
                'Files',

            documents:
                'Documents',

            services:
                'Services and accounts',

            noContracts:
                'No contracts are available.',

            noOffers:
                'No offers are available.',

            noFiles:
                'No client files are available.',

            noDocuments:
                'No documents are available.',

            noServices:
                'No services are available.',

            readOnly:
                'Read only',

            signed:
                'Signed',

            noContent:
                '<p>No content provided.</p>',

            signDocument:
                'Sign document',

            owner:
                'Owner',

            billing:
                'Billing',

            managedBy:
                'Managed by',

            notSpecified:
                'Not specified',

            signIn:
                'Sign in',

            accessDetails:
                'Access details are shared securely.',

            support:
                'Support',

            noTickets:
                'You have not created any requests yet.',

            describeProblem:
                'Describe your request',

            supportHint:
                'Tell us what happened and what you need.',

            descriptionPlaceholder:
                'Describe your request',

            sendRequest:
                'Send request',

            version:
                'Version',

            view:
                'view',

            openDocumentHint:
                'Double-click a document to review and sign.',

            noAction:
                'No action'
        }
    })


/*
|--------------------------------------------------------------------------
| Documents
|--------------------------------------------------------------------------
*/

const initialDocumentStructure =
    computed(() => {
        const structured =
            Array.isArray(
                props.data.project.document_structure
            )
                ? props.data.project.document_structure
                : []

        if (
            structured.length
        ) {
            return structured
                .filter(
                    item =>
                        item?.resource_type !==
                        'file'
                )
                .map(
                    item => ({
                        id:
                            item.id,

                        parent_id:
                            item.parent_id ??
                            null,

                        type:
                            item.type,

                        name:
                            item.name,

                        resource_type:
                            item.resource_type ||
                            (
                                item.type === 'folder'
                                    ? 'folder'
                                    : 'document'
                            ),

                        content:
                            item.content ||
                            '',

                        requires_client_signature:
                            Boolean(
                                item.requires_client_signature
                            ),

                        requires_signature:
                            Boolean(
                                item.requires_signature
                            ),

                        signed:
                            Boolean(
                                item.signed
                            ),

                        can_sign:
                            Boolean(
                                item.can_sign
                            ),

                        sign_url:
                            item.sign_url ||
                            null,

                        open_url:
                            item.open_url ||
                            null,

                        download_url:
                            item.download_url ||
                            null,

                        requirement_level:
                            item.requirement_level ||
                            null
                    })
                )
        }

        return (
            props.data.project.documents ||
            []
        ).map(
            document => ({
                id:
                    document.id,

                parent_id:
                    null,

                type:
                    'file',

                resource_type:
                    'document',

                name:
                    document.name,

                content:
                    document.content ||
                    '',

                requires_client_signature:
                    Boolean(
                        document.requires_signature
                    ),

                requires_signature:
                    Boolean(
                        document.requires_signature
                    ),

                signed:
                    Boolean(
                        document.signed
                    ),

                can_sign:
                    Boolean(
                        document.can_sign
                    ),

                sign_url:
                    document.sign_url ||
                    null,

                requirement_level:
                    document.requires_signature
                        ? 'required'
                        : 'recommended'
            })
        )
    })


function normalizeProjectFile(
    file
) {
    const id =
        file?.id

    if (
        id === null ||
        id === undefined
    ) {
        return null
    }

    return {
        id:
            `project-file-${id}`,

        parent_id:
            file?.folder_id ??
            null,

        type:
            'file',

        resource_type:
            'file',

        name:
            file?.display_name ||
            file?.original_filename ||
            'file',

        mime_type:
            file?.mime_type ||
            'application/octet-stream',

        extension:
            file?.extension ||
            '',

        size:
            Number(
                file?.size ||
                0
            ),

        open_url:
            `/client/files/${id}/open`,

        thumbnail_url:
            file?.thumbnail_url ||
            '',

        download_url:
            `/client/files/${id}/download`,

        content:
            '',

        __uploaded_file:
            true
    }
}


const initialUploadedProjectFiles =
    computed(() => {
        const structured =
            Array.isArray(
                props.data.project.document_structure
            )
                ? props.data.project.document_structure
                : []

        return structured
            .filter(
                item =>
                    item?.resource_type ===
                    'file'
            )
            .map(
                normalizeProjectFile
            )
            .filter(
                Boolean
            )
    })


const documentItems =
    computed(() => [
        ...initialDocumentStructure.value,
        ...projectFiles.value
    ])


async function loadProjectFilesFolder(
    folderId = null,
    force = false
) {
    const projectId =
        props.data.project?.id

    if (
        !projectId
    ) {
        return
    }

    const cacheKey =
        folderId === null
            ? 'root'
            : String(folderId)

    if (
        !force &&
        projectFilesLoadedFolders.value.has(
            cacheKey
        )
    ) {
        return
    }

    projectFilesLoading.value =
        true

    projectFilesError.value =
        ''

    try {
        const url =
            new URL(
                `/client/projects/${projectId}/files`,
                window.location.origin
            )

        if (
            folderId !== null &&
            folderId !== undefined
        ) {
            url.searchParams.set(
                'folder_id',
                String(folderId)
            )
        }

        const response =
            await fetch(
                url.toString(),
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept:
                            'application/json'
                    }
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
            throw new Error(
                payload?.message ||
                'Project files could not be loaded.'
            )
        }

        const files =
            Array.isArray(
                payload?.files
            )
                ? payload.files
                : (
                    Array.isArray(
                        payload?.data
                    )
                        ? payload.data
                        : []
                )

        const mappedFiles =
            files
                .map(
                    normalizeProjectFile
                )
                .filter(
                    Boolean
                )

        const parentKey =
            String(
                folderId ??
                ''
            )

        projectFiles.value = [
            ...projectFiles.value.filter(
                item =>
                    String(
                        item?.parent_id ??
                        ''
                    ) !==
                    parentKey
            ),
            ...mappedFiles
        ]

        projectFilesLoadedFolders.value.add(
            cacheKey
        )
    } catch (
        exception
    ) {
        projectFilesError.value =
            exception instanceof Error
                ? exception.message
                : 'Project files could not be loaded.'

        /*
         * Compatibility fallback:
         * if the lazy client endpoint is not available yet,
         * retain the file metadata that was already included
         * in the initial project payload.
         */
        if (
            cacheKey === 'root' &&
            !projectFiles.value.length
        ) {
            projectFiles.value =
                initialUploadedProjectFiles.value
        }
    } finally {
        projectFilesLoading.value =
            false
    }
}


function resetProjectFilesCache() {
    projectFiles.value =
        []

    projectFilesLoadedFolders.value =
        new Set()

    projectFilesError.value =
        ''
}


function refreshProjectFilesFolder(
    folderId = null
) {
    const cacheKey =
        folderId === null
            ? 'root'
            : String(folderId)

    projectFilesLoadedFolders.value.delete(
        cacheKey
    )

    return loadProjectFilesFolder(
        folderId
    )
}



function clientProjectFileOpenUrl(
    fileId
) {
    const numericId =
        Number(fileId)

    if (
        !Number.isInteger(
            numericId
        ) ||
        numericId <= 0
    ) {
        return ''
    }

    return `/client/files/${numericId}/open`
}


function normalizeDocumentContentForClient(
    source
) {
    let parsed =
        source

    if (
        typeof source === 'string'
    ) {
        try {
            parsed =
                JSON.parse(
                    source
                )
        } catch {
            return source
        }
    }

    if (
        !parsed ||
        typeof parsed !== 'object'
    ) {
        return source
    }

    function visit(
        node
    ) {
        if (
            !node ||
            typeof node !== 'object'
        ) {
            return node
        }

        const normalized = {
            ...node
        }

        if (
            node.attrs &&
            typeof node.attrs === 'object'
        ) {
            const attrs = {
                ...node.attrs
            }

            let fileId =
                attrs.projectFileId

            if (
                !fileId
            ) {
                const src =
                    String(
                        attrs.src ||
                        ''
                    )

                const match =
                    src.match(
                        /\/files\/(\d+)\/open(?:[/?#]|$)/
                    )

                if (match) {
                    fileId =
                        match[1]
                }
            }

            const clientUrl =
                clientProjectFileOpenUrl(
                    fileId
                )

            if (
                node.type === 'image' &&
                clientUrl
            ) {
                attrs.src =
                    clientUrl
            }

            normalized.attrs =
                attrs
        }

        if (
            Array.isArray(
                node.content
            )
        ) {
            normalized.content =
                node.content.map(
                    visit
                )
        }

        return normalized
    }

    const normalized =
        visit(
            parsed
        )

    return JSON.stringify(
        normalized
    )
}


const selectedDocument =
    computed(() => {
        const id =
            selectedDocumentId.value

        if (
            id === null ||
            id === undefined
        ) {
            return null
        }

        const document =
            documentItems.value.find(
                item =>
                    String(item.id) ===
                        String(id) &&
                    item.type === 'file' &&
                    item.resource_type ===
                        'document'
            )

        if (!document) {
            return null
        }

        return {
            ...document,
            content:
                normalizeDocumentContentForClient(
                    document.content
                )
        }
    })


function selectedDocumentIdFromUrl() {
    const params =
        new URLSearchParams(
            window.location.search
        )

    const value =
        String(
            params.get('document') ||
            ''
        ).trim()

    return value || null
}


function updateDocumentUrlState(
    documentId
) {
    const url = new URL(
        window.location.href
    )

    if (
        documentId === null ||
        documentId === undefined ||
        documentId === ''
    ) {
        url.searchParams.delete(
            'document'
        )
        url.hash = ''
    } else {
        url.searchParams.set(
            'document',
            String(documentId)
        )
        url.hash =
            'client-project-documents'
    }

    window.history.replaceState(
        {},
        '',
        url.toString()
    )
}


function lockPageScroll() {
    if (pageScrollLockSnapshot.value) {
        return
    }

    const html =
        document.documentElement
    const body =
        document.body

    pageScrollLockSnapshot.value = {
        htmlOverflow:
            html.style.overflow,
        bodyOverflow:
            body.style.overflow,
    }

    html.style.overflow =
        'hidden'
    body.style.overflow =
        'hidden'
}


function unlockPageScroll() {
    const snapshot =
        pageScrollLockSnapshot.value

    if (!snapshot) {
        return
    }

    const html =
        document.documentElement
    const body =
        document.body

    html.style.overflow =
        snapshot.htmlOverflow
    body.style.overflow =
        snapshot.bodyOverflow

    pageScrollLockSnapshot.value =
        null
}


function openDocument(
    item,
    {
        updateUrl = true,
        scroll = false,
    } = {}
) {
    if (
        !item ||
        item.type !== 'file' ||
        item.resource_type !==
            'document'
    ) {
        return
    }

    if (
        selectedDocumentId.value === null
    ) {
        previousPageScrollY.value =
            window.scrollY
    }

    selectedDocumentId.value =
        item.id

    activeStructureFolderId.value =
        item.parent_id ??
        null

    if (updateUrl) {
        updateDocumentUrlState(
            item.id
        )
    }

    if (!scroll) {
        return
    }

    const section =
        document.getElementById(
            'client-project-documents'
        )

    section?.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    })
}


async function handleStructureFolderOpen(
    folder
) {
    const folderId =
        folder?.id ??
        null

    activeStructureFolderId.value =
        folderId

    await loadProjectFilesFolder(
        folderId
    )
}


function clearSelectedDocument() {
    selectedDocumentId.value =
        null

    updateDocumentUrlState(
        null
    )

    const targetY =
        previousPageScrollY.value

    previousPageScrollY.value =
        null

    if (
        targetY === null ||
        targetY === undefined
    ) {
        return
    }

    nextTick(() => {
        window.scrollTo({
            top: Number(targetY),
            left: 0,
            behavior: 'auto',
        })
    })
}


watch(
    () => Boolean(selectedDocument.value),
    isDocumentOpen => {
        if (isDocumentOpen) {
            lockPageScroll()
            return
        }

        unlockPageScroll()
    },
    {
        immediate: true,
    }
)


onUnmounted(() => {
    documentsSectionObserver?.disconnect()

    documentsSectionObserver =
        null

    unlockPageScroll()
})


watch(
    documentItems,
    items => {
        if (!items.length) {
            selectedDocumentId.value =
                null

            return
        }

        const requestedId =
            selectedDocumentIdFromUrl()

        if (requestedId !== null) {
            const requested =
                items.find(
                    item =>
                        item.type ===
                            'file' &&
                        item.resource_type ===
                            'document' &&
                        String(item.id) ===
                            String(
                                requestedId
                            )
                )

            if (requested) {
                openDocument(
                    requested,
                    {
                        updateUrl:
                            false,
                        scroll: false,
                    }
                )

                return
            }
        }

        const exists =
            items.some(
                item =>
                    item.type ===
                        'file' &&
                    item.resource_type ===
                        'document' &&
                    String(item.id) ===
                        String(
                            selectedDocumentId.value
                        )
            )

        if (exists) {
            return
        }

        clearSelectedDocument()
    },
    {
        immediate: true,
    }
)


function openProjectDocument(
    item
) {
    openDocument(item)
}


function openDocumentById(
    documentId
) {
    const match =
        documentItems.value.find(
            item =>
                item.type === 'file' &&
                item.resource_type === 'document' &&
                String(item.id) ===
                    String(documentId)
        )

    if (!match) {
        return
    }

    openDocument(match)
}


function normalizeOpenUrl(
    value
) {
    const raw =
        String(value || '').trim()

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


function openProjectFile(
    item
) {
    const openUrl =
        String(
            item?.open_url ||
            item?.download_url ||
            ''
        ).trim()

    if (openUrl) {
        window.open(
            openUrl,
            '_blank',
            'noopener,noreferrer'
        )

        return
    }

    if (
        item?.resource_type ===
        'link'
    ) {
        const linkUrl =
            normalizeOpenUrl(
                item?.url || ''
            )

        if (linkUrl) {
            window.open(
                linkUrl,
                '_blank',
                'noopener,noreferrer'
            )
        }
    }
}


/*
|--------------------------------------------------------------------------
| Project details
|--------------------------------------------------------------------------
*/

const projectDetails =
    computed(() => [
        {
            heading:
                copy.value.company,

            text:
                String(
                    props.data.contact?.company_name ||
                    copy.value.notSpecified
                )
        },

        {
            heading:
                copy.value.service,

            text:
                String(
                    props.data.project?.service_name ||
                    copy.value.notSpecified
                )
        },

        {
            heading:
                copy.value.status,

            text:
                String(
                    props.data.project?.status ||
                    copy.value.notSpecified
                )
        },

        {
            heading:
                copy.value.pendingSignatures,

            text:
                String(
                    props.data.project?.pending_signatures_count ||
                    0
                )
        }
    ])


/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/

function money(
    value,
    currency
) {
    return `${new Intl.NumberFormat(
        'sk-SK',
        {
            minimumFractionDigits:
                2
        }
    ).format(value)} ${currency}`
}


function fileSize(
    bytes
) {
    return `${new Intl.NumberFormat(
        'sk-SK',
        {
            maximumFractionDigits:
                1
        }
    ).format(
        bytes / 1024
    )} KB`
}


function statusLabel(
    value
) {
    return String(
        value || ''
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

    if (!description) {
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
                    method: 'POST',
                    credentials:
                        'same-origin',
                    headers: {
                        'Content-Type':
                            'application/json',
                        Accept:
                            'application/json',
                        'X-CSRF-TOKEN':
                            props.csrfToken,
                    },
                    body: JSON.stringify(
                        {
                            description,
                        }
                    ),
                }
            )

        const payload =
            await response
                .json()
                .catch(() => ({}))

        if (!response.ok) {
            const message =
                payload?.message ||
                payload?.errors
                    ?.description?.[0] ||
                'Could not create ticket.'

            throw new Error(message)
        }

        const created =
            payload?.ticket || null

        if (created) {
            tickets.value = [
                created,
                ...tickets.value,
            ]
        }

        supportDescription.value =
            ''
    } catch (error) {
        ticketCreateError.value =
            error instanceof Error
                ? error.message
                : 'Could not create ticket.'
    } finally {
        ticketCreateInFlight.value =
            false
    }
}
onMounted(() => {
    /*
     * Do not request project files during the initial page render.
     *
     * The project structure and authored documents are already in the
     * page payload. Uploaded files are only needed when the Documents
     * section becomes relevant to the user.
     */
    if (
        selectedDocumentIdFromUrl()
    ) {
        void loadProjectFilesFolder()

        return
    }

    if (
        !documentsSection.value ||
        typeof IntersectionObserver ===
            'undefined'
    ) {
        return
    }

    documentsSectionObserver =
        new IntersectionObserver(
            entries => {
                const entry =
                    entries[0]

                if (
                    !entry?.isIntersecting
                ) {
                    return
                }

                void loadProjectFilesFolder()

                documentsSectionObserver?.disconnect()

                documentsSectionObserver =
                    null
            },
            {
                rootMargin:
                    '500px 0px'
            }
        )

    documentsSectionObserver.observe(
        documentsSection.value
    )
})


useClientPageHeader({
    title: computed(() => props.data.project?.name || ''),
    eyebrow: computed(() => props.data.project?.service_name || ''),
    homeUrl: computed(() => props.data.urls.dashboard),
    breadcrumbs: computed(() => [
        {
            label: copy.value.allProjects,
            href: props.data.urls.dashboard
        },
        {
            label: props.data.project?.name || ''
        }
    ])
})
</script>


<template>
    <div
        class="
            w-full
            space-y-28
            lg:space-y-24
        "
    >
        <Teleport
            v-if="selectedDocument"
            to="body"
        >
            <DocumentEditor
            :model-value="
                selectedDocument.content ||
                ''
            "
            :title="
                selectedDocument.name ||
                ''
            "
            :editable="
                false
            "
            :client-mode="
                true
            "
            :project-id="
                props.data.project?.id || ''
            "
            :show-signature-status="
                Boolean(
                    selectedDocument.signed
                )
            "
            :requires-signature="
                Boolean(
                    selectedDocument.requires_signature
                )
            "
            :signature-signed="
                Boolean(
                    selectedDocument.signed
                )
            "
            :language="
                locale
            "
            @back="
                clearSelectedDocument
            "
        >
            <template
                #header-actions
            >
                <form
                    v-if="
                        selectedDocument?.can_sign
                    "
                    method="POST"
                    :action="
                        selectedDocument.sign_url
                    "
                >
                    <input
                        type="hidden"
                        name="_token"
                        :value="
                            csrfToken
                        "
                    >

                    <button
                        class="
                            px-3
                            py-1
                            font-mono
                            text-[10px]
                            font-bold
                            uppercase
                            tracking-[0.12em]
                            bg-light
                            text-accent
                            hover:bg-accent
                            hover:text-light
                        "
                        type="submit"
                    >
                        {{
                            copy.signDocument
                        }}
                    </button>
                </form>
            </template>
            </DocumentEditor>
        </Teleport>


        <template
            v-else
        >

        <!--
        |--------------------------------------------------------------------------
        | Project details
        |--------------------------------------------------------------------------
        -->

        <section>
            <h2
                class="
                    h2
                    text-accent
                    text-left
                "
            >
                {{
                    copy.projectDetails
                }}
            </h2>

            <div
                class="
                    mt-6
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
                        False
                    "
                />
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Actions required
        |--------------------------------------------------------------------------
        -->

        <section
            v-if="
                data.project
                    .todo_signatures?.length
            "
        >
            <h2
                class="
                    h2
                    text-accent
                    text-left
                "
            >
                {{
                    copy.toDoNow
                }}
            </h2>

            <p
                class="
                    p
                    uppercase
                "
            >
                {{
                    copy.reviewAndSign
                }}
            </p>

            <ul
                class="
                    mt-6
                    grid
                    gap-2
                "
            >
                <li
                    v-for="
                        document in data.project.todo_signatures
                    "
                    :key="
                        `todo-${document.id}`
                    "
                    class="border border-accent bg-accent"
                >
                    <a
                        :href="
                            document.open_url ||
                            '#'
                        "
                        class="
                            flex
                            items-center
                            justify-between
                            gap-4
                            px-4
                            py-4
                            text-light
                        "
                        @click.prevent="
                            openDocumentById(
                                document.id
                            )
                        "
                    >   
                        <div class="flex items-center gap-2">
                            <i class="bi bi-file-earmark p" />
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

                        <i class="bi bi-arrow-up-right p" />
                    </a>
                </li>
            </ul>
        </section>

                <!--
        |--------------------------------------------------------------------------
        | Support
        |--------------------------------------------------------------------------
        -->

        <section
            class="
                mt-12
                flex
                flex-col
                gap-6
            "
        >
            <h2
                class="
                    h2
                    text-accent
                    text-left
                "
            >
                {{
                    copy.support
                }}
            </h2>

            <form
                class="

                "
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

                <strong
                    class="
                        h3
                        uppercase
                    "
                >
                    {{
                        copy.describeProblem
                    }}
                </strong>

                <p
                    class="
                        p
                        text-dark
                    "
                >
                    {{
                        copy.supportHint
                    }}
                </p>

                <FormField
                    v-model="
                        supportDescription
                    "
                    class="
                        mt-5
                    "
                    type="textarea"
                    name="description"
                    :placeholder="
                        copy.descriptionPlaceholder
                    "
                    :required="
                        true
                    "
                    :disabled="
                        ticketCreateInFlight
                    "
                />

                <p
                    v-if="
                        ticketCreateError
                    "
                    class="
                        mt-3
                        p
                        text-red-600
                    "
                >
                    {{
                        ticketCreateError
                    }}
                </p>

                <Button
                    class="
                        mt-4
                    "
                    type="submit"
                    variant="dark"
                    :text="
                        copy.sendRequest
                    "
                    :loading="
                        ticketCreateInFlight
                    "
                    loading-text="Sending"
                    align="left"
                />
            </form>

            <div
                class="
                    mt-6
                    grid
                    gap-3
                "
            >
                <article
                    v-for="
                        ticket in tickets
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

        </section>


        <!--
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        -->

        <section
            id="client-project-documents"
            ref="documentsSection"
        >
            <h2
                class="
                    h2
                    text-accent
                    text-left
                "
            >
                {{
                    copy.documents
                }}
            </h2>

            <div
                class="
                    mt-6
                    space-y-5
                "
            >
                <div
                    v-if="
                        projectFilesLoading
                    "
                    class="
                        border-t
                        border-accent
                        pt-4
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
                            locale === 'sk'
                                ? 'Načítavam súbory...'
                                : 'Loading files...'
                        }}
                    </p>
                </div>

                <p
                    v-if="
                        projectFilesError
                    "
                    class="
                        p
                        text-red-700
                    "
                >
                    {{
                        projectFilesError
                    }}
                </p>

                <FileStructure
                    :model-value="
                        documentItems
                    "
                    :language="
                        locale
                    "
                    :initial-folder-id="
                        activeStructureFolderId
                    "
                    :allow-upload-control="
                        false
                    "
                    :allow-metadata-editing="
                        false
                    "
                    :disabled="
                        true
                    "
                    @open-document="
                        openProjectDocument
                    "
                    @open-folder="
                        handleStructureFolderOpen
                    "
                    @open-file="
                        openProjectFile
                    "
                    @download-file="
                        openProjectFile
                    "
                />
            </div>
        </section>



        </template>
    </div>
</template>