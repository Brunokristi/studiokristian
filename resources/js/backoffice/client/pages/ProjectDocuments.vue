<script setup>

import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    watch
} from 'vue'

import FileStructure
    from '../../components/FileStructure.vue'

import DocumentEditor
    from '../../components/DocumentEditor.vue'

import Section
    from '../../components/Section.vue'

import Loading
    from '@shared/components/Loading.vue'


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
    },

    requestedDocumentId: {
        type: [
            String,
            Number
        ],
        default: ''
    }

})


const emit = defineEmits([
    'document-closed'
])


const selectedDocumentId =
    ref(null)


const activeStructureFolderId =
    ref(null)


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


const copy =
    computed(() => {

        if (
            props.locale ===
            'sk'
        ) {

            return {
                documents:
                    'Dokumenty',
                loadingFiles:
                    'Načítavam súbory...',
                signDocument:
                    'Podpísať dokument'
            }

        }


        return {
            documents:
                'Documents',
            loadingFiles:
                'Loading files...',
            signDocument:
                'Sign document'
        }

    })


const initialDocumentStructure =
    computed(() => {

        const structured =
            Array.isArray(
                props.data.project
                    .document_structure
            )
                ? props.data.project
                    .document_structure
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
                                item.type ===
                                'folder'
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
            props.data.project
                .documents ||
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
                props.data.project
                    .document_structure
            )
                ? props.data.project
                    .document_structure
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
            : String(
                folderId
            )


    if (
        !force &&
        projectFilesLoadedFolders
            .value
            .has(
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
                String(
                    folderId
                )
            )

        }


        const response =
            await fetch(
                url.toString(),
                {
                    method:
                        'GET',

                    credentials:
                        'same-origin',

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


        projectFilesLoadedFolders
            .value
            .add(
                cacheKey
            )

    } catch (
        exception
    ) {

        projectFilesError.value =
            exception instanceof Error
                ? exception.message
                : 'Project files could not be loaded.'


        if (
            cacheKey ===
                'root' &&
            !projectFiles.value.length
        ) {

            projectFiles.value =
                initialUploadedProjectFiles
                    .value

        }

    } finally {

        projectFilesLoading.value =
            false

    }

}


function clientProjectFileOpenUrl(
    fileId
) {

    const numericId =
        Number(
            fileId
        )


    if (
        !Number.isInteger(
            numericId
        ) ||
        numericId <= 0
    ) {

        return ''

    }


    return (
        `/client/files/${numericId}/open`
    )

}


function normalizeDocumentContentForClient(
    source
) {

    let parsed =
        source


    if (
        typeof source ===
        'string'
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
        typeof parsed !==
            'object'
    ) {

        return source

    }


    function visit(
        node
    ) {

        if (
            !node ||
            typeof node !==
                'object'
        ) {

            return node

        }


        const normalized = {
            ...node
        }


        if (
            node.attrs &&
            typeof node.attrs ===
                'object'
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


                if (
                    match
                ) {

                    fileId =
                        match[1]

                }

            }


            const clientUrl =
                clientProjectFileOpenUrl(
                    fileId
                )


            if (
                node.type ===
                    'image' &&
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


    return JSON.stringify(
        visit(
            parsed
        )
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
                    String(
                        item.id
                    ) ===
                        String(
                            id
                        ) &&
                    item.type ===
                        'file' &&
                    item.resource_type ===
                        'document'
            )


        if (
            !document
        ) {

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


function lockPageScroll() {

    if (
        pageScrollLockSnapshot.value
    ) {

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
            body.style.overflow
    }


    html.style.overflow =
        'hidden'

    body.style.overflow =
        'hidden'

}


function unlockPageScroll() {

    const snapshot =
        pageScrollLockSnapshot.value


    if (
        !snapshot
    ) {

        return

    }


    document.documentElement
        .style
        .overflow =
            snapshot.htmlOverflow

    document.body
        .style
        .overflow =
            snapshot.bodyOverflow


    pageScrollLockSnapshot.value =
        null

}


function openDocument(
    item
) {

    if (
        !item ||
        item.type !==
            'file' ||
        item.resource_type !==
            'document'
    ) {

        return

    }


    if (
        selectedDocumentId.value ===
        null
    ) {

        previousPageScrollY.value =
            window.scrollY

    }


    selectedDocumentId.value =
        item.id

    activeStructureFolderId.value =
        item.parent_id ??
        null

}


function openDocumentById(
    documentId
) {

    const match =
        documentItems.value.find(
            item =>
                item.type ===
                    'file' &&
                item.resource_type ===
                    'document' &&
                String(
                    item.id
                ) ===
                    String(
                        documentId
                    )
        )


    if (
        !match
    ) {

        return false

    }


    openDocument(
        match
    )


    return true

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


    emit(
        'document-closed'
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
            top:
                Number(
                    targetY
                ),

            left:
                0,

            behavior:
                'auto'
        })

    })

}


function normalizeOpenUrl(
    value
) {

    const raw =
        String(
            value ||
            ''
        ).trim()


    if (
        !raw
    ) {

        return ''

    }


    if (
        raw.startsWith(
            '/'
        ) ||
        raw.startsWith(
            '#'
        )
    ) {

        return raw

    }


    if (
        /^[a-z][a-z\d+.-]*:/i
            .test(
                raw
            )
    ) {

        return raw

    }


    return (
        `https://${raw}`
    )

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


    if (
        item?.resource_type ===
        'link'
    ) {

        const linkUrl =
            normalizeOpenUrl(
                item?.url ||
                ''
            )


        if (
            linkUrl
        ) {

            window.open(
                linkUrl,
                '_blank',
                'noopener,noreferrer'
            )

        }

    }

}


watch(
    () =>
        Boolean(
            selectedDocument.value
        ),

    isDocumentOpen => {

        if (
            isDocumentOpen
        ) {

            lockPageScroll()

            return

        }


        unlockPageScroll()

    },

    {
        immediate:
            true
    }
)


watch(
    [
        documentItems,
        () =>
            props.requestedDocumentId
    ],

    () => {

        const requestedId =
            String(
                props.requestedDocumentId ||
                ''
            ).trim()


        if (
            !requestedId
        ) {

            return

        }


        if (
            openDocumentById(
                requestedId
            )
        ) {

            return

        }


        void loadProjectFilesFolder()

    },

    {
        immediate:
            true
    }
)


onMounted(
    () => {

        void loadProjectFilesFolder()

    }
)


onUnmounted(
    () => {

        unlockPageScroll()

    }
)

</script>


<template>

    <div
        class="
            w-full
        "
    >

        <Teleport
            v-if="
                selectedDocument
            "
            to="body"
        >

            <DocumentEditor
                :model-value="
                    selectedDocument
                        .content ||
                    ''
                "
                :title="
                    selectedDocument
                        .name ||
                    ''
                "
                :editable="
                    false
                "
                :client-mode="
                    true
                "
                :project-id="
                    data.project
                        ?.id ||
                    ''
                "
                :show-signature-status="
                    Boolean(
                        selectedDocument
                            .signed
                    )
                "
                :requires-signature="
                    Boolean(
                        selectedDocument
                            .requires_signature
                    )
                "
                :signature-signed="
                    Boolean(
                        selectedDocument
                            .signed
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
                            selectedDocument
                                ?.can_sign
                        "
                        method="POST"
                        :action="
                            selectedDocument
                                .sign_url
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
                            type="submit"
                            class="
                                bg-light
                                px-3
                                py-1
                                font-mono
                                text-[10px]
                                font-bold
                                uppercase
                                tracking-[0.12em]
                                text-accent
                                hover:bg-accent
                                hover:text-light
                            "
                        >

                            {{
                                copy.signDocument
                            }}

                        </button>

                    </form>

                </template>

            </DocumentEditor>

        </Teleport>


        <Section
            id="client-project-documents"
            :title="
                copy.documents
            "
        >

            <div
                class="
                    space-y-5
                "
            >

                <Loading
                    v-if="
                        projectFilesLoading
                    "
                />


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
                        openDocument
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

        </Section>

    </div>

</template>
