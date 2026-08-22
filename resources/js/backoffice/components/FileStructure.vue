<script setup>
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    reactive,
    ref,
    watch
} from 'vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Tag from '@shared/components/Tag.vue'
import AdminConfirmDialog from '@shared/components/ConfirmDialog.vue'
import Modal from '@shared/components/Modal.vue'


import api, {
    errorMessage
} from '../admin/composables/useAdminApi'


const props =
    defineProps({
        modelValue: {
            type: Array,
            default: () => []
        },

        projectId: {
            type: [String, Number, null],
            default: null
        },

        initialFolderId: {
            type: [String, Number, null],
            default: null
        },

        allowUploadControl: {
            type: Boolean,
            default: false
        },

        allowMetadataEditing: {
            type: Boolean,
            default: true
        },

        preventDeletingRequired: {
            type: Boolean,
            default: false
        },

        disabled: {
            type: Boolean,
            default: false
        },

        language: {
            type: String,
            default: 'en'
        }
    })


const emit =
    defineEmits([
        'update:modelValue',
        'open-folder',
        'open-document',
        'open-file',
        'download-file',
        'upload-files'
    ])


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const currentFolder =
    ref(null)


const selectedItem =
    ref(null)


const openMenu =
    ref(null)


const contextMenuCoords =
    ref(null)


const contextMenuRef =
    ref(null)


const menuButtonRefs =
    {}


const renamingItem =
    ref(null)


const renameValue =
    ref('')


const renameInput =
    ref(null)


const uploadInput =
    ref(null)


const uploadDirectoryInput =
    ref(null)


const uploadMenuOpen =
    ref(false)


const showFileCreator =
    ref(false)


const fileCreatorStep =
    ref('type')


const fileCreatorMode =
    ref('create')


const editingFileId =
    ref(null)


const moveTarget =
    ref(null)


const moveDestination =
    ref('__root__')


const moveError =
    ref('')


const moveBrowserFolderId =
    ref(null)


const deleteTarget =
    ref(null)


const externalLinkTarget =
    ref(null)


const deleting =
    ref(false)


const fileErrors =
    ref({})


const fileDraft =
    reactive({
        name: '',
        resource_type: 'document',
        requirement_level: 'recommended',
        requires_client_signature: false,
        client_visible: true,
        description: '',
        template_name: '',
        content: '',
        url: ''
    })


/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/

const folders =
    computed(() =>
        props.modelValue.filter(
            item =>
                item.type === 'folder'
        )
    )


const files =
    computed(() =>
        props.modelValue.filter(
            item =>
                item.type === 'file'
        )
    )


const currentFolderItems =
    computed(() =>
        props.modelValue.filter(
            item =>
                item.parent_id ===
                currentFolder.value
        )
    )


const currentFolders =
    computed(() =>
        currentFolderItems.value.filter(
            item =>
                item.type === 'folder'
        )
    )


const currentFiles =
    computed(() =>
        currentFolderItems.value.filter(
            item =>
                item.type === 'file'
        )
    )


const normalizedLanguage =
    computed(() => {
        return String(
            props.language ||
            'en'
        )
            .trim()
            .toLowerCase()
    })


const isSlovak =
    computed(() => {
        return normalizedLanguage.value === 'sk'
    })


const linkCopy =
    computed(() => {
        if (isSlovak.value) {
            return {
                linkName: 'Nazov odkazu',
                linkNamePlaceholder: 'Klientsky disk',
                externalUrl: 'Externa URL adresa',
                descriptionLabel: 'Popis',
                descriptionPlaceholder: 'Strucny kontext k tomuto externemu zdroju',
                leavingTitle: 'Externy odkaz',
                leavingSubtitle: 'Opustate tuto stranku',
                leavingDescriptionDefault: 'Tento odkaz otvori externu stranku v novej karte.',
                cancel: 'zrusit',
                openLink: 'otvorit odkaz',
                createExternalLink: 'vytvorit externy odkaz'
            }
        }

        return {
            linkName: 'Link name',
            linkNamePlaceholder: 'Client Drive',
            externalUrl: 'External URL',
            descriptionLabel: 'Description',
            descriptionPlaceholder: 'Short context for this external resource',
            leavingTitle: 'External link',
            leavingSubtitle: 'You are leaving this site',
            leavingDescriptionDefault: 'This link opens an external website in a new tab.',
            cancel: 'cancel',
            openLink: 'open link',
            createExternalLink: 'create external link'
        }
    })


/*
|--------------------------------------------------------------------------
| Tree
|--------------------------------------------------------------------------
*/

const rootFolders =
    computed(() =>
        folders.value.filter(
            folder =>
                !folder.parent_id
        )
    )


const folderTreeRows =
    computed(() => {
        const rows = []

        const walk = (
            parentId,
            depth
        ) => {
            const children =
                folders.value
                    .filter(
                        folder => {
                            if (
                                parentId === null
                            ) {
                                return (
                                    folder.parent_id === null ||
                                    folder.parent_id === undefined
                                )
                            }

                            return String(
                                folder.parent_id
                            ) === String(
                                parentId
                            )
                        }
                    )
                    .sort(
                        (a, b) =>
                            String(
                                a.name ||
                                ''
                            ).localeCompare(
                                String(
                                    b.name ||
                                    ''
                                )
                            )
                    )

            children.forEach(
                folder => {
                    rows.push({
                        folder,
                        depth
                    })

                    walk(
                        folder.id,
                        depth + 1
                    )
                }
            )
        }

        walk(
            null,
            0
        )

        return rows
    })


function childrenOf(
    folderId
) {
    return folders.value.filter(
        folder =>
            String(
                folder.parent_id
            ) ===
            String(
                folderId
            )
    )
}


function folderDepth(
    folderId
) {
    let depth = 0
    let folder =
        getItem(
            folderId
        )

    while (
        folder?.parent_id
    ) {
        depth += 1

        folder =
            getItem(
                folder.parent_id
            )
    }

    return depth
}


const breadcrumbs =
    computed(() => {
        const result = []


        let folder =
            getItem(
                currentFolder.value
            )


        while (folder) {
            result.unshift(
                folder
            )


            folder =
                folder.parent_id
                    ? getItem(
                        folder.parent_id
                    )
                    : null
        }


        return result
    })


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function update(
    items
) {
    emit(
        'update:modelValue',
        items
    )
}


function getItem(
    id
) {
    return props.modelValue.find(
        item =>
            String(item.id) ===
            String(id)
    )
}


function resolvedProjectIdForItem(
    item
) {
    if (props.projectId) {
        return props.projectId
    }

    if (item?.project_id) {
        return item.project_id
    }

    const urls = [
        item?.open_url,
        item?.download_url
    ]

    for (const value of urls) {
        const match =
            String(value || '').match(
                /\/projects\/(\d+)\/files\//
            )

        if (match?.[1]) {
            return match[1]
        }
    }

    return null
}


function itemCount(
    folderId
) {
    return props.modelValue.filter(
        item =>
            String(
                item.parent_id
            ) ===
            String(folderId)
    ).length
}


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

function openRoot() {
    currentFolder.value =
        null


    selectedItem.value =
        null


    renamingItem.value =
        null


    openMenu.value =
        null

    emit(
        'open-folder',
        null
    )
}


function openFolder(
    folder
) {
    if (
        !folder?.id
    ) {
        return
    }


    currentFolder.value =
        folder.id


    selectedItem.value =
        null


    renamingItem.value =
        null


    openMenu.value =
        null


    emit(
        'open-folder',
        folder
    )
}


function openDocument(
    file
) {
    if (
        !file?.id
    ) {
        return
    }


    selectedItem.value =
        file.id

    openMenu.value =
        null

    openResource(
        file
    )
}


function openFile(
    file
) {
    if (
        !file?.id
    ) {
        return
    }

    if (
        file?.resource_type ===
        'link'
    ) {
        requestOpenExternalLink(
            file
        )

        return
    }


    emit(
        'open-file',
        file
    )
}


function normalizeExternalUrl(
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


function requestOpenExternalLink(
    file
) {
    if (!file?.id) {
        return
    }


    externalLinkTarget.value =
        file
}


function closeExternalLinkModal() {
    externalLinkTarget.value =
        null
}


function openExternalLink() {
    const linkUrl =
        normalizeExternalUrl(
            externalLinkTarget.value?.open_url ||
            externalLinkTarget.value?.url ||
            ''
        )


    if (!linkUrl) {
        closeExternalLinkModal()

        return
    }


    window.open(
        linkUrl,
        '_blank',
        'noopener,noreferrer'
    )

    closeExternalLinkModal()
}


function openStructuredDocument(
    item
) {
    nextTick(() => {
        emit(
            'open-document',
            item
        )
    })
}


function getResourceType(
    item
) {
    if (
        item?.type ===
        'folder'
    ) {
        return 'folder'
    }

    if (
        item?.type ===
        'file' &&
        item?.resource_type ===
        'document'
    ) {
        return 'document'
    }

    if (
        item?.type ===
        'file'
    ) {
        return 'file'
    }

    return 'file'
}


function openResource(
    item
) {
    const resourceType =
        getResourceType(
            item
        )

    if (
        resourceType ===
        'folder'
    ) {
        openFolder(
            item
        )

        return
    }

    if (
        resourceType ===
        'document'
    ) {
        openStructuredDocument(
            item
        )

        return
    }

    openFile(
        item
    )
}


function downloadFile(
    file
) {
    if (
        !file?.id
    ) {
        return
    }

    emit(
        'download-file',
        file
    )
}


function extensionFromName(
    value
) {
    const name =
        String(
            value ||
            ''
        )

    const parts =
        name.split('.')

    return parts.length > 1
        ? String(
            parts.pop()
        ).toLowerCase()
        : ''
}


function normalizeUploadedResourceType(
    file
) {
    const mime =
        String(
            file?.type ||
            ''
        ).toLowerCase()

    const extension =
        extensionFromName(
            file?.name
        )

    if (
        mime ===
        'application/pdf' ||
        mime.startsWith(
            'image/'
        ) ||
        mime.startsWith(
            'audio/'
        ) ||
        mime.startsWith(
            'video/'
        )
    ) {
        return 'file'
    }

    if (
        [
            'txt',
            'md',
            'json',
            'xml',
            'csv',
            'js',
            'ts',
            'vue',
            'php',
            'py',
            'java',
            'c',
            'cpp',
            'h',
            'sql',
            'sh',
            'yaml',
            'yml',
            'css',
            'html',
            'svg'
        ].includes(
            extension
        )
    ) {
        return 'file'
    }

    return 'file'
}


function humanFileType(
    file
) {
    const mime =
        String(
            file?.mime_type ||
            ''
        ).toLowerCase()

    if (
        mime ===
        'application/pdf'
    ) {
        return 'PDF'
    }

    if (
        mime ===
        'image/svg+xml'
    ) {
        return 'SVG'
    }

    if (
        mime.startsWith(
            'image/'
        )
    ) {
        return 'Image'
    }

    if (
        mime.startsWith(
            'audio/'
        )
    ) {
        return 'Audio'
    }

    if (
        mime.startsWith(
            'video/'
        )
    ) {
        return 'Video'
    }

    return file.resource_type === 'link'
        ? 'Link'
        : file.resource_type === 'document'
            ? 'Document'
            : 'File'
}


function openBreadcrumb(
    folder
) {
    if (!folder) {
        openRoot()

        return
    }


    openFolder(
        folder
    )
}


function restoreFolderFromParent(
    value
) {
    if (
        value === null ||
        value === undefined ||
        value === ''
    ) {
        currentFolder.value =
            null

        return
    }

    const folder =
        getItem(value)

    if (
        folder?.type ===
        'folder'
    ) {
        currentFolder.value =
            folder.id

        return
    }

    currentFolder.value =
        null
}


/*
|--------------------------------------------------------------------------
| Selection
|--------------------------------------------------------------------------
*/

function selectItem(
    item
) {
    if (
        !item?.id
    ) {
        return
    }


    selectedItem.value =
        item.id


    openMenu.value =
        null
}


/*
|--------------------------------------------------------------------------
| Menu
|--------------------------------------------------------------------------
*/

function setMenuButtonRef(
    itemId,
    element
) {
    if (element) {
        menuButtonRefs[
            String(itemId)
        ] = element
    } else {
        delete menuButtonRefs[
            String(itemId)
        ]
    }
}


function setContextMenuRef(
    element
) {
    contextMenuRef.value =
        element
}


async function positionContextMenu() {
    await nextTick()

    const itemId =
        openMenu.value

    if (
        itemId === null ||
        itemId === undefined
    ) {
        return
    }

    const button =
        menuButtonRefs[
            String(itemId)
        ]

    const menu =
        Array.isArray(
            contextMenuRef.value
        )
            ? contextMenuRef.value[0]
            : contextMenuRef.value

    if (
        !button ||
        !menu
    ) {
        return
    }

    const buttonRect =
        button.getBoundingClientRect()

    const menuRect =
        menu.getBoundingClientRect()

    const padding =
        8

    const gap =
        4

    let left =
        buttonRect.right -
        menuRect.width

    left =
        Math.max(
            padding,
            Math.min(
                left,
                window.innerWidth -
                    menuRect.width -
                    padding
            )
        )

    let top =
        buttonRect.bottom +
        gap

    if (
        top +
            menuRect.height >
        window.innerHeight -
            padding
    ) {
        top =
            buttonRect.top -
            menuRect.height -
            gap
    }

    top =
        Math.max(
            padding,
            Math.min(
                top,
                window.innerHeight -
                    menuRect.height -
                    padding
            )
        )

    contextMenuCoords.value = {
        top,
        left
    }
}


async function toggleMenu(
    itemId
) {
    if (
        openMenu.value ===
        itemId
    ) {
        closeMenu()

        return
    }

    /*
     * The menu itself is rendered only when
     * contextMenuCoords exists. Therefore we must
     * give it an initial position first; otherwise
     * positionContextMenu() can never measure the
     * menu because the menu has not been rendered.
     */
    contextMenuCoords.value = {
        top: 0,
        left: 0
    }

    openMenu.value =
        itemId

    await nextTick()

    await positionContextMenu()
}


function closeMenu() {
    openMenu.value =
        null

    contextMenuCoords.value =
        null
}


function handleDocumentClick(
    event
) {
    const target =
        event?.target

    if (
        target?.closest?.(
            '[data-file-structure-menu]'
        )
    ) {
        return
    }

    uploadMenuOpen.value =
        false

    closeMenu()
}


function toggleUploadMenu() {
    if (
        !props.allowUploadControl
    ) {
        return
    }


    uploadMenuOpen.value =
        !uploadMenuOpen.value
}


function triggerUploadFiles() {
    uploadMenuOpen.value =
        false


    uploadInput.value?.click()
}


function triggerUploadFolder() {
    uploadMenuOpen.value =
        false


    uploadDirectoryInput.value?.click()
}


function collectFolderDescendants(
    folderId,
    ids
) {
    folders.value
        .filter(
            folder =>
                String(
                    folder.parent_id
                ) ===
                String(
                    folderId
                )
        )
        .forEach(
            folder => {
                ids.add(
                    String(
                        folder.id
                    )
                )

                collectFolderDescendants(
                    folder.id,
                    ids
                )
            }
        )
}


const moveBlockedFolderIds =
    computed(() => {
        const blocked =
            new Set()

        const target =
            moveTarget.value

        if (
            target?.type ===
            'folder'
        ) {
            blocked.add(
                String(
                    target.id
                )
            )
            collectFolderDescendants(
                target.id,
                blocked
            )
        }

        return blocked
    })


const moveBrowserFolders =
    computed(() => {
        const activeParent =
            moveBrowserFolderId.value

        return folders.value
            .filter(
                folder => {
                    const matchesParent =
                        activeParent === null
                            ? (
                                folder.parent_id === null ||
                                folder.parent_id === undefined
                            )
                            : String(
                                folder.parent_id
                            ) === String(
                                activeParent
                            )

                    return (
                        matchesParent &&
                        !moveBlockedFolderIds.value.has(
                            String(
                                folder.id
                            )
                        )
                    )
                }
            )
            .sort(
                (a, b) =>
                    String(
                        a.name ||
                        ''
                    ).localeCompare(
                        String(
                            b.name ||
                            ''
                        )
                    )
            )
    })


const moveBrowserBreadcrumbs =
    computed(() => {
        const trail = []

        let cursor =
            moveBrowserFolderId.value
                ? getItem(
                    moveBrowserFolderId.value
                )
                : null

        while (cursor) {
            trail.unshift(
                cursor
            )
            cursor =
                cursor.parent_id
                    ? getItem(
                        cursor.parent_id
                    )
                    : null
        }

        return trail
    })


const selectedMoveDestinationLabel =
    computed(() => {
        if (
            moveDestination.value ===
            '__root__'
        ) {
            return 'Project (root)'
        }

        const folder =
            getItem(
                moveDestination.value
            )

        return folder?.name || 'Selected folder'
    })


function browseMoveFolder(
    folder
) {
    if (
        !folder?.id ||
        moveBlockedFolderIds.value.has(
            String(
                folder.id
            )
        )
    ) {
        return
    }

    moveBrowserFolderId.value =
        folder.id

    moveDestination.value =
        String(
            folder.id
        )

    moveError.value =
        ''
}


function browseMoveRoot() {
    moveBrowserFolderId.value =
        null

    moveDestination.value =
        '__root__'

    moveError.value =
        ''
}


function browseMoveUp() {
    if (
        moveBrowserFolderId.value ===
        null
    ) {
        return
    }

    const current =
        getItem(
            moveBrowserFolderId.value
        )

    moveBrowserFolderId.value =
        current?.parent_id ??
        null
}



function openMoveDialog(
    item
) {
    if (
        props.disabled ||
        !item?.id
    ) {
        return
    }

    openMenu.value =
        null

    moveTarget.value =
        item

    moveDestination.value =
        item.parent_id !== null &&
        item.parent_id !== undefined
            ? String(
                item.parent_id
            )
            : '__root__'

    moveError.value =
        ''

    moveBrowserFolderId.value =
        null
}


function closeMoveDialog() {
    moveTarget.value =
        null
    moveDestination.value =
        '__root__'
    moveError.value =
        ''
    moveBrowserFolderId.value =
        null
}


async function confirmMove() {
    if (!moveTarget.value) {
        return
    }

    const target =
        moveTarget.value

    const destinationId =
        moveDestination.value ===
        '__root__'
            ? null
            : Number(
                moveDestination.value
            )

    if (
        destinationId !== null &&
        !Number.isInteger(
            destinationId
        )
    ) {
        moveError.value =
            'Please select a valid destination folder.'

        return
    }

    if (
        String(
            target.parent_id ??
            '__root__'
        ) ===
        String(
            destinationId ??
            '__root__'
        )
    ) {
        closeMoveDialog()
        return
    }

    const destinationFolder =
        destinationId !== null
            ? getItem(
                destinationId
            )
            : null

    if (
        destinationId !== null &&
        destinationFolder?.type !==
            'folder'
    ) {
        moveError.value =
            'Please select a valid destination folder.'
        return
    }

    try {
        if (target.__uploaded_file) {
            const fileId =
                String(
                    target.id
                ).replace(
                    'project-file-',
                    ''
                )

            const resolvedProjectId =
                resolvedProjectIdForItem(
                    target
                )

            if (!resolvedProjectId) {
                throw new Error(
                    'Project ID is required to move an uploaded file.'
                )
            }

            const response =
                await api.put(
                    `/projects/${resolvedProjectId}/files/${fileId}/move`,
                    {
                        folder_id:
                            destinationId
                    }
                )

            const updated =
                response.data?.data ||
                response.data ||
                {}

            update(
                props.modelValue.map(
                    item =>
                        String(item.id) ===
                        String(target.id)
                            ? {
                                ...item,
                                parent_id:
                                    updated.folder_id ??
                                    destinationId,
                                parent_client_key:
                                    updated.folder_id !== null &&
                                    updated.folder_id !== undefined
                                        ? String(
                                            updated.folder_id
                                        )
                                        : null
                            }
                            : item
                )
            )
        } else {
            update(
                props.modelValue.map(
                    item =>
                        String(item.id) ===
                        String(target.id)
                            ? {
                                ...item,
                                parent_id:
                                    destinationId,
                                parent_client_key:
                                    destinationFolder
                                        ? String(
                                            destinationFolder.client_key ||
                                            destinationFolder.id
                                        )
                                        : null
                            }
                            : item
                )
            )
        }

        closeMoveDialog()
    } catch (exception) {
        moveError.value =
            errorMessage(
                exception
            )
    }
}


/*
|--------------------------------------------------------------------------
| Rename
|--------------------------------------------------------------------------
*/

async function startRename(
    item
) {
    if (
        props.disabled ||
        !item?.id
    ) {
        return
    }


    openMenu.value =
        null


    renamingItem.value =
        item.id


    renameValue.value =
        item.name || ''


    await nextTick()


    if (
        renameInput.value
    ) {
        renameInput.value.focus()
        renameInput.value.select()
    }
}


async function finishRename() {
    if (!renamingItem.value) {
        return
    }

    const item =
        getItem(
            renamingItem.value
        )

    if (!item) {
        cancelRename()
        return
    }

    const name =
        renameValue.value.trim()

    if (!name) {
        renameValue.value =
            item.name

        renamingItem.value =
            null
        return
    }

    try {
        if (item.__uploaded_file) {
            const fileId =
                String(
                    item.id
                ).replace(
                    'project-file-',
                    ''
                )

            const resolvedProjectId =
                resolvedProjectIdForItem(
                    item
                )

            if (!resolvedProjectId) {
                throw new Error(
                    'Project ID is required to rename an uploaded file.'
                )
            }

            const response =
                await api.patch(
                    `/projects/${resolvedProjectId}/files/${fileId}`,
                    {
                        name
                    }
                )

            const updated =
                response.data?.data ||
                response.data ||
                {}

            update(
                props.modelValue.map(
                    value =>
                        String(value.id) ===
                        String(item.id)
                            ? {
                                ...value,
                                name:
                                    updated.display_name ||
                                    updated.original_filename ||
                                    name,
                                mime_type:
                                    updated.mime_type ||
                                    value.mime_type,
                                extension:
                                    updated.extension ||
                                    value.extension,
                                size:
                                    updated.size ??
                                    value.size,
                                updated_at:
                                    updated.updated_at ||
                                    value.updated_at
                            }
                            : value
                )
            )
        } else {
            update(
                props.modelValue.map(
                    value =>
                        String(value.id) ===
                        String(item.id)
                            ? {
                                ...value,
                                name
                            }
                            : value
                )
            )
        }

        renamingItem.value =
            null
        renameValue.value =
            ''
    } catch (exception) {
        renameValue.value =
            item.name

        fileErrors.value = {
            ...fileErrors.value,
            name:
                errorMessage(
                    exception
                )
        }
    }
}


function cancelRename() {
    renamingItem.value =
        null


    renameValue.value =
        ''
}


/*
|--------------------------------------------------------------------------
| Create folder
|--------------------------------------------------------------------------
*/

async function createFolder() {
    if (
        props.disabled
    ) {
        return
    }


    const folderId =
        `folder_${Date.now()}_${Math.random()
            .toString(36)
            .slice(2, 8)}`


    const parent =
        currentFolder.value
            ? getItem(
                currentFolder.value
            )
            : null


    const folder = {
        id:
            folderId,

        client_key:
            folderId,

        type:
            'folder',

        name:
            'New folder',

        parent_id:
            currentFolder.value,

        parent_client_key:
            parent
                ? String(
                    parent.client_key ||
                    parent.id
                )
                : null,

        client_visible:
            true
    }


    update([
        ...props.modelValue,
        folder
    ])
}


/*
|--------------------------------------------------------------------------
| Create document
|--------------------------------------------------------------------------
*/

function resetFileDraft() {
    fileDraft.name =
        'New document'


    fileDraft.resource_type =
        'document'


    fileDraft.requirement_level =
        props.allowMetadataEditing
            ? 'recommended'
            : ''


    fileDraft.requires_client_signature =
        false


    fileDraft.client_visible =
        true


    fileDraft.description =
        ''


    fileDraft.template_name =
        ''


    fileDraft.content =
        ''


    fileDraft.url =
        ''


    fileErrors.value =
        {}
}


function openFileCreator() {
    resetFileDraft()

    fileCreatorStep.value =
        'type'

    fileCreatorMode.value =
        'create'

    editingFileId.value =
        null


    showFileCreator.value =
        true
}


function selectCreatorType(
    type
) {
    if (
        type !== 'document' &&
        type !== 'link'
    ) {
        return
    }

    fileDraft.resource_type =
        type

    fileDraft.name =
        type === 'link'
            ? 'New external link'
            : 'New document'

    fileDraft.url =
        ''

    fileDraft.description =
        ''

    fileDraft.requires_client_signature =
        type === 'document'
            ? fileDraft.requires_client_signature
            : false

    fileDraft.template_name =
        ''

    fileErrors.value =
        {}

    fileCreatorStep.value =
        'details'
}


function backToTypeSelection() {
    if (
        fileCreatorMode.value ===
        'edit'
    ) {
        return
    }

    fileCreatorStep.value =
        'type'
}


function canDownloadResource(
    item
) {
    return (
        item?.type ===
            'file' &&
        item?.resource_type ===
            'file'
    )
}


function canDeleteItem(
    item
) {
    if (!item?.id) {
        return false
    }

    if (!props.preventDeletingRequired) {
        return true
    }

    if (
        item.type === 'file' &&
        item.requirement_level === 'required'
    ) {
        return false
    }

    if (
        item.type === 'folder'
    ) {
        const ids =
            new Set([
                item.id
            ])

        collectDescendants(
            item.id,
            ids
        )

        const containsRequired =
            props.modelValue.some(
                value =>
                    ids.has(
                        value.id
                    ) &&
                    value.type === 'file' &&
                    value.requirement_level === 'required'
            )

        return !containsRequired
    }

    return true
}


function openFileEditor(
    item
) {
    if (
        props.disabled ||
        !item?.id ||
        item?.type !== 'file'
    ) {
        return
    }

    openMenu.value =
        null

    fileDraft.name =
        String(
            item.name ||
            ''
        )

    fileDraft.resource_type =
        String(
            item.resource_type ||
            'file'
        )

    fileDraft.requirement_level =
        String(
            item.requirement_level ||
            'recommended'
        )

    fileDraft.requires_client_signature =
        fileDraft.resource_type ===
            'document' &&
        Boolean(
            item.requires_client_signature
        )

    fileDraft.client_visible =
        Boolean(
            item.client_visible ??
            true
        )

    fileDraft.description =
        String(
            item.description ||
            item.content ||
            ''
        )

    fileDraft.template_name =
        String(
            item.template_name ||
            ''
        )

    fileDraft.content =
        String(
            item.content ||
            ''
        )

    fileDraft.url =
        String(
            item.url ||
            ''
        )

    fileErrors.value =
        {}

    fileCreatorMode.value =
        'edit'

    editingFileId.value =
        item.id

    fileCreatorStep.value =
        'details'

    showFileCreator.value =
        true
}


function parentMetadata() {
    const parent =
        currentFolder.value
            ? getItem(
                currentFolder.value
            )
            : null

    return {
        parentId:
            currentFolder.value,
        parentClientKey:
            parent
                ? String(
                    parent.client_key ||
                    parent.id
                )
                : null
    }
}


function createUploadedFileItems(
    filesList
) {
    const files =
        Array.from(
            filesList || []
        )

    if (!files.length) {
        return
    }

    const {
        parentId,
        parentClientKey
    } = parentMetadata()

    const newItems =
        files.map(
            file => {
                const fileId =
                    `file_${Date.now()}_${Math.random()
                        .toString(36)
                        .slice(2, 8)}`

                return {
                    id: fileId,
                    client_key: fileId,
                    type: 'file',
                    name: file.name,
                    resource_type:
                        normalizeUploadedResourceType(
                            file
                        ),
                    requirement_level:
                        props.allowMetadataEditing
                            ? 'recommended'
                            : null,
                    requires_client_signature:
                        props.allowMetadataEditing
                            ? false
                            : false,
                    client_visible: true,
                    template_name: file.name,
                    content: '',
                    url: '',
                    mime_type: file.type || 'application/octet-stream',
                    extension: extensionFromName(file.name),
                    size: Number(file.size || 0),
                    parent_id: parentId,
                    parent_client_key: parentClientKey
                }
            }
        )

    update([
        ...props.modelValue,
        ...newItems
    ])

    const last =
        newItems[
            newItems.length - 1
        ]

    selectedItem.value =
        last?.id ||
        null
}


function handleUploadChange(
    event
) {
    const selectedFiles =
        Array.from(
            event?.target?.files ||
            []
        )

    const isDirectoryUpload =
        Boolean(
            event?.target
                ?.webkitdirectory
        )

    const relativePaths =
        selectedFiles.map(
            file =>
                String(
                    file
                        ?.webkitRelativePath ||
                    file?.name ||
                    ''
                )
        )

    if (selectedFiles.length) {
        emit(
            'upload-files',
            {
                files: selectedFiles,
                relativePaths,
                isDirectoryUpload,
                folderId: currentFolder.value,
                parent: currentFolder.value
                    ? getItem(
                        currentFolder.value
                    )
                    : null
            }
        )
    }

    if (event?.target) {
        event.target.value =
            ''
    }
}


function closeFileCreator() {
    showFileCreator.value =
        false

    fileCreatorStep.value =
        'type'

    fileCreatorMode.value =
        'create'

    editingFileId.value =
        null


    fileErrors.value =
        {}
}


function saveFileDraft() {
    fileErrors.value =
        {}


    const name =
        fileDraft.name.trim()


    if (!name) {
        fileErrors.value.name =
            'Name is required.'

        return
    }


    if (
        fileDraft.resource_type ===
        'link' &&
        !fileDraft.url.trim()
    ) {
        fileErrors.value.url =
            'External URL is required.'

        return
    }


    const fileId =
        fileCreatorMode.value ===
        'edit' &&
        editingFileId.value
            ? editingFileId.value
            : `file_${Date.now()}_${Math.random()
                .toString(36)
                .slice(2, 8)}`


    const parent =
        currentFolder.value
            ? getItem(
                currentFolder.value
            )
            : null


    const file = {
        id:
            fileId,

        client_key:
            fileId,

        type:
            'file',

        name,

        resource_type:
            fileDraft.resource_type,

        requirement_level:
            props.allowMetadataEditing
                ? (
                    fileDraft.requirement_level ||
                    null
                )
                : null,

        requires_client_signature:
            props.allowMetadataEditing &&
            fileDraft.resource_type ===
            'document'
                ? Boolean(
                    fileDraft.requires_client_signature
                )
                : false,

        client_visible:
            Boolean(
                fileDraft.client_visible
            ),

        template_name:
            fileDraft.template_name.trim(),

        content:
            fileDraft.resource_type ===
            'link'
                ? String(
                    fileDraft.description ||
                    ''
                )
                : fileDraft.content,

        description:
            fileDraft.resource_type ===
            'link'
                ? String(
                    fileDraft.description ||
                    ''
                )
                : '',

        url:
            fileDraft.resource_type ===
            'link'
                ? fileDraft.url.trim()
                : '',

        parent_id:
            currentFolder.value,

        parent_client_key:
            parent
                ? String(
                    parent.client_key ||
                    parent.id
                )
                : null
    }


    if (
        fileCreatorMode.value ===
            'edit' &&
        editingFileId.value
    ) {
        update(
            props.modelValue.map(
                item =>
                    String(item.id) ===
                    String(
                        editingFileId.value
                    )
                        ? {
                            ...item,
                            ...file
                        }
                        : item
            )
        )
    } else {
        update([
            ...props.modelValue,
            file
        ])
    }


    selectedItem.value =
        file.id


    closeFileCreator()


}


/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

function requestDelete(
    item
) {
    if (
        props.disabled ||
        !item?.id
    ) {
        return
    }

    if (!canDeleteItem(item)) {
        return
    }


    openMenu.value =
        null


    deleteTarget.value =
        item
}


function collectDescendants(
    parentId,
    ids
) {
    props.modelValue
        .filter(
            item =>
                String(
                    item.parent_id
                ) ===
                String(
                    parentId
                )
        )
        .forEach(
            child => {
                ids.add(
                    child.id
                )


                if (
                    child.type ===
                    'folder'
                ) {
                    collectDescendants(
                        child.id,
                        ids
                    )
                }
            }
        )
}


async function confirmDelete() {
    if (!deleteTarget.value) {
        return
    }

    deleting.value =
        true

    try {
        const target =
            deleteTarget.value

        if (target.__uploaded_file) {
            const fileId =
                String(
                    target.id
                ).replace(
                    'project-file-',
                    ''
                )

            const resolvedProjectId =
                resolvedProjectIdForItem(
                    target
                )

            if (!resolvedProjectId) {
                throw new Error(
                    'Project ID is required to delete an uploaded file.'
                )
            }

            await api.delete(
                `/projects/${resolvedProjectId}/files/${fileId}`
            )
        }

        const ids =
            new Set([
                target.id
            ])

        if (target.type === 'folder') {
            collectDescendants(
                target.id,
                ids
            )
        }

        update(
            props.modelValue.filter(
                item =>
                    !ids.has(
                        item.id
                    )
            )
        )

        if (ids.has(selectedItem.value)) {
            selectedItem.value =
                null
        }

        if (ids.has(currentFolder.value)) {
            openRoot()
        }

        deleteTarget.value =
            null
    } catch (exception) {
        fileErrors.value = {
            ...fileErrors.value,
            general:
                errorMessage(
                    exception
                )
        }
    } finally {
        deleting.value =
            false
    }
}


/*
|--------------------------------------------------------------------------
| Keyboard
|--------------------------------------------------------------------------
*/

function handleGlobalKeydown(
    event
) {
    if (
        props.disabled ||
        renamingItem.value
    ) {
        return
    }


    const target =
        event.target


    if (
        target instanceof
            HTMLInputElement ||
        target instanceof
            HTMLTextAreaElement ||
        target instanceof
            HTMLSelectElement ||
        target?.isContentEditable
    ) {
        return
    }


    if (
        event.key ===
        'F2' &&
        selectedItem.value
    ) {
        event.preventDefault()


        const item =
            getItem(
                selectedItem.value
            )


        if (item) {
            startRename(
                item
            )
        }


        return
    }


    if (
        event.key ===
        'Delete' &&
        selectedItem.value
    ) {
        event.preventDefault()


        const item =
            getItem(
                selectedItem.value
            )


        if (item) {
            requestDelete(
                item
            )
        }


        return
    }


    if (
        event.key ===
        'Enter' &&
        selectedItem.value
    ) {
        event.preventDefault()


        const item =
            getItem(
                selectedItem.value
            )


        if (!item) {
            return
        }

        openResource(
            item
        )
    }
}


function handleItemKeydown(
    event,
    item
) {
    if (
        event.key ===
        'Enter'
    ) {
        event.preventDefault()

        openResource(
            item
        )


        return
    }


    if (
        event.key ===
        'F2'
    ) {
        event.preventDefault()


        startRename(
            item
        )


        return
    }


    if (
        event.key ===
        'Delete'
    ) {
        event.preventDefault()


        requestDelete(
            item
        )
    }
}


/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {
    window.addEventListener(
        'keydown',
        handleGlobalKeydown
    )


    document.addEventListener(
        'click',
        handleDocumentClick
    )

    window.addEventListener(
        'resize',
        closeMenu
    )
})


onUnmounted(() => {
    window.removeEventListener(
        'keydown',
        handleGlobalKeydown
    )


    document.removeEventListener(
        'click',
        handleDocumentClick
    )

    window.removeEventListener(
        'resize',
        closeMenu
    )
})


watch(
    () => [
        props.initialFolderId,
        props.modelValue.length
    ],
    () => {
        restoreFolderFromParent(
            props.initialFolderId
        )
    },
    {
        immediate: true
    }
)
</script>


<template>
    <div
        class="
            flex
            max-h-[60vh]
            min-h-0
            flex-col
            overflow-hidden
            border
            border-accent
            bg-light
        "
    >
        <!-- Toolbar -->
        <div
            class="
                flex
                shrink-0
                flex-col
                gap-5
                border-b
                border-accent
                p-4
                sm:flex-row
                sm:items-center
                sm:justify-between
            "
        >
            <!-- Breadcrumb -->
            <div
                class="
                    flex
                    min-w-0
                    items-center
                    gap-2
                    overflow-x-auto
                "
            >
                <button
                    type="button"
                    class="
                        shrink-0
                        p
                        uppercase
                        transition-colors
                        hover:text-accent
                    "
                    :class="
                        currentFolder
                            ? 'text-dark'
                            : 'text-accent'
                    "
                    @click="
                        openRoot
                    "
                >
                    Project
                </button>


                <template
                    v-for="
                        folder
                        in breadcrumbs
                    "
                    :key="
                        folder.id
                    "
                >
                    <span
                        class="
                            shrink-0
                            p
                            text-dark/30
                        "
                    >
                        /
                    </span>


                    <button
                        type="button"
                        class="
                            max-w-40
                            shrink-0
                            truncate
                            p
                            uppercase
                            transition-colors
                            hover:text-accent
                        "
                        :class="
                            String(
                                currentFolder
                            ) ===
                            String(
                                folder.id
                            )
                                ? 'text-accent'
                                : 'text-dark/50'
                        "
                        @click="
                            openBreadcrumb(
                                folder
                            )
                        "
                    >
                        {{
                            folder.name
                        }}
                    </button>
                </template>
            </div>


            <!-- Actions -->
            <div
                class="
                    flex
                    items-center
                    gap-2
                "
            >
                <button
                    v-if="
                        !disabled
                    "
                    type="button"
                    aria-label="New folder"
                    class="
                        flex
                        h-8
                        w-8
                        items-center
                        justify-center
                    "
                    @click="
                        createFolder
                    "
                >
                    <i
                        class="
                            bi
                            bi-folder-plus
                            text-lg
                            text-accent
                            transition-colors
                            hover:text-accent/70
                        "
                        aria-hidden="true"
                    />
                </button>


                <button
                    v-if="
                        !disabled
                    "
                    type="button"
                    aria-label="New document"
                    class="
                        flex
                        h-8
                        w-8
                        items-center
                        justify-center
                    "
                    @click="
                        openFileCreator
                    "
                >
                    <i
                        class="
                            bi
                            bi-file-earmark-plus
                            text-lg
                            text-accent
                            transition-colors
                            hover:text-accent/70
                        "
                        aria-hidden="true"
                    />
                </button>


                <div
                    v-if="
                        !disabled
                    "
                    class="
                        relative
                    "
                >
                    <button
                        type="button"
                        aria-label="Upload"
                        class="
                            flex
                            h-8
                            w-8
                            items-center
                            justify-center
                        "
                        :class="
                            allowUploadControl
                                ? 'text-accent'
                                : 'cursor-not-allowed text-dark/30'
                        "
                        :title="
                            allowUploadControl
                                ? 'Upload'
                                : 'Upload is disabled in blueprint structure. Use Project Files workspace.'
                        "
                        :disabled="
                            !allowUploadControl
                        "
                        :aria-expanded="
                            uploadMenuOpen
                                ? 'true'
                                : 'false'
                        "
                        @click.stop="
                            toggleUploadMenu
                        "
                    >
                        <i
                            class="
                                bi
                                bi-upload
                                text-lg
                                transition-colors
                                hover:text-accent/70
                            "
                            aria-hidden="true"
                        />
                    </button>


                    <div
                        v-if="
                            uploadMenuOpen &&
                            allowUploadControl
                        "
                        class="
                            absolute
                            right-0
                            top-full
                            z-30
                            mt-1
                            min-w-40
                            border
                            border-dark
                            bg-light
                        "
                        @click.stop
                    >
                        <button
                            type="button"
                            class="
                                flex
                                w-full
                                items-center
                                gap-3
                                px-3
                                py-2.5
                                text-left
                                p
                                text-dark
                                transition-colors
                                hover:bg-dark
                                hover:text-light
                            "
                            @click="
                                triggerUploadFiles
                            "
                        >
                            Upload files
                        </button>


                        <button
                            type="button"
                            class="
                                flex
                                w-full
                                items-center
                                gap-3
                                px-3
                                py-2.5
                                text-left
                                p
                                text-dark
                                transition-colors
                                hover:bg-dark
                                hover:text-light
                            "
                            @click="
                                triggerUploadFolder
                            "
                        >
                            Upload folder
                        </button>
                    </div>
                </div>


                <input
                    v-if="
                        allowUploadControl
                    "
                    ref="uploadInput"
                    type="file"
                    multiple
                    class="hidden"
                    @change="
                        handleUploadChange
                    "
                >


                <input
                    v-if="
                        allowUploadControl
                    "
                    ref="uploadDirectoryInput"
                    type="file"
                    webkitdirectory
                    multiple
                    class="hidden"
                    @change="
                        handleUploadChange
                    "
                >
            </div>
        </div>


        <!-- Layout --->
        <main
            class="
                min-h-0
                flex-1
                overflow-y-auto
                overscroll-contain
            "
            @scroll="
                closeMenu
            "
        >
            <!-- Items -->
            <div
                class="
                    divide-y
                    divide-accent/20
                "
            >
                <div
                    v-for="
                        item
                        in currentFolderItems
                    "
                    :key="
                        item.id
                    "
                    class="
                        group
                        flex
                        w-full
                        min-h-16
                        items-center
                        justify-between
                        gap-4
                        px-5
                        py-3
                        transition-colors
                    "
                    :class="
                        selectedItem ===
                        item.id
                            ? 'bg-accent/10'
                            : 'hover:bg-accent/[0.04]'
                    "
                    tabindex="0"
                    @click.stop="
                        selectItem(item)
                    "
                    @dblclick.stop="
                        openResource(item)
                    "
                    @keydown="
                        handleItemKeydown(
                            $event,
                            item
                        )
                    "
                >
                    <!-- Name -->
                    <div
                        class="
                            col-span-2
                            flex
                            min-w-0
                            items-center
                            gap-3
                            sm:col-span-1
                        "
                    >
                        <i
                            class="
                                bi
                                shrink-0
                                text-xl
                            "
                            :class="
                                item.type === 'folder'
                                    ? 'bi-folder-fill text-accent'
                                    : item.resource_type === 'link'
                                        ? 'bi-link-45deg text-accent'
                                        : 'bi-file-earmark text-accent'
                            "
                            aria-hidden="true"
                        />


                        <div
                            class="
                                min-w-0
                                flex-1
                            "
                        >
                            <input
                                v-if="
                                    renamingItem ===
                                    item.id
                                "
                                ref="
                                    renameInput
                                "
                                v-model="
                                    renameValue
                                "
                                type="text"
                                class="
                                    w-full
                                    max-w-lg
                                    border-0
                                    border-b
                                    border-accent
                                    bg-transparent
                                    p-0
                                    font-mono
                                    text-xs
                                    font-bold
                                    uppercase
                                    outline-none
                                    focus:outline-none
                                    focus:ring-0
                                    focus:border-accent
                                "
                                @click.stop
                                @keydown.enter.stop="
                                    finishRename
                                "
                                @keydown.esc.stop="
                                    cancelRename
                                "
                                @blur="
                                    finishRename
                                "
                            />


                            <span
                                v-else
                                class="
                                    block
                                    truncate
                                    font-mono
                                    text-xs
                                    font-bold
                                    uppercase
                                "
                            >
                                {{
                                    item.name
                                }}
                            </span>


                            <span
                                class="
                                    mt-1
                                    block
                                    truncate
                                    font-mono
                                    text-[10px]
                                    uppercase
                                    text-dark/30
                                    sm:hidden
                                "
                            >
                                {{
                                    item.type === 'folder'
                                        ? 'Folder'
                                        : item.resource_type === 'link'
                                            ? 'Link'
                                            : item.resource_type === 'document'
                                                ? 'Document'
                                                : humanFileType(item)
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <!-- Details -->
                        <div
                            class="
                                hidden
                                min-w-0
                                flex-wrap
                                items-center
                                gap-2
                                sm:flex
                            "
                        >
                            <template
                                v-if="
                                    item.type === 'file'
                                "
                            >
                                <Tag
                                    v-if="
                                        item.requirement_level
                                    "
                                    :text="
                                        item.requirement_level
                                    "
                                />


                                <Tag
                                    v-if="
                                        item.resource_type === 'document' &&
                                        item.requires_client_signature
                                    "
                                    text="signature"
                                />
                            </template>
                        </div>


                        <!-- Actions -->
                        <div
                            class="
                                relative
                                flex
                                shrink-0
                                items-center
                                justify-end
                            "
                        >
                            <button
                                v-if="
                                    !disabled
                                "
                                type="button"
                                class="
                                    flex
                                    h-8
                                    w-8
                                    items-center
                                    justify-center
                                    text-dark
                                    transition-colors
                                    hover:text-accent
                                "
                                :aria-label="
                                    `Actions for ${item.name}`
                                "
                                :aria-expanded="
                                    openMenu ===
                                    item.id
                                        ? 'true'
                                        : 'false'
                                "
                                :ref="
                                    element =>
                                        setMenuButtonRef(
                                            item.id,
                                            element
                                        )
                                "
                                @click.stop="
                                    toggleMenu(
                                        item.id
                                    )
                                "
                            >
                                <i
                                    class="
                                        bi
                                        bi-three-dots
                                        text-lg
                                    "
                                    aria-hidden="true"
                                />
                            </button>


                            <!-- Context menu -->
                            <Teleport to="body">
                                <div
                                    v-if="
                                        openMenu ===
                                        item.id &&
                                        contextMenuCoords
                                    "
                                    :ref="
                                        setContextMenuRef
                                    "
                                    data-file-structure-menu
                                    class="
                                        fixed
                                        z-[9999]
                                        min-w-36
                                        border
                                        border-dark
                                        bg-light
                                        shadow-lg
                                    "
                                    :style="{
                                        top:
                                            `${contextMenuCoords.top}px`,
                                        left:
                                            `${contextMenuCoords.left}px`
                                    }"
                                    @click.stop
                                >
                                <button
                                    type="button"
                                    class="
                                        flex
                                        w-full
                                        items-center
                                        gap-3
                                        px-3
                                        py-2.5
                                        text-left
                                        p
                                        text-dark
                                        transition-colors
                                        hover:bg-dark
                                        hover:text-light
                                    "
                                    @click="
                                        openMenu = null;
                                        openResource(item)
                                    "
                                >
                                    <span>
                                        Open
                                    </span>
                                </button>


                                <button
                                    v-if="
                                        canDownloadResource(item)
                                    "
                                    type="button"
                                    class="
                                        flex
                                        w-full
                                        items-center
                                        gap-3
                                        px-3
                                        py-2.5
                                        text-left
                                        p
                                        text-dark
                                        transition-colors
                                        hover:bg-dark
                                        hover:text-light
                                    "
                                    @click="
                                        openMenu = null;
                                        downloadFile(item)
                                    "
                                >
                                    <span>
                                        Download
                                    </span>
                                </button>


                                <button
                                    v-if="
                                        item.type === 'file'
                                    "
                                    type="button"
                                    class="
                                        flex
                                        w-full
                                        items-center
                                        gap-3
                                        px-3
                                        py-2.5
                                        text-left
                                        p
                                        text-dark
                                        transition-colors
                                        hover:bg-dark
                                        hover:text-light
                                    "
                                    @click="
                                        openMenu = null;
                                        item.__uploaded_file
                                            ? openFile(item)
                                            : openFileEditor(item)
                                    "
                                >
                                    <span>
                                        Edit
                                    </span>
                                </button>


                                <button
                                    type="button"
                                    class="
                                        flex
                                        w-full
                                        items-center
                                        gap-3
                                        px-3
                                        py-2.5
                                        text-left
                                        p
                                        text-dark
                                        transition-colors
                                        hover:bg-dark
                                        hover:text-light
                                    "
                                    @click="
                                        openMenu = null;
                                        openMoveDialog(item)
                                    "
                                >
                                    <span>
                                        Move
                                    </span>
                                </button>


                                <button
                                    type="button"
                                    class="
                                        flex
                                        w-full
                                        items-center
                                        gap-3
                                        px-3
                                        py-2.5
                                        text-left
                                        p
                                        text-dark
                                        transition-colors
                                        hover:bg-dark
                                        hover:text-light
                                    "
                                    @click="
                                        openMenu = null;
                                        startRename(item)
                                    "
                                >

                                    <span>
                                        Rename
                                    </span>
                                </button>


                                <button
                                    type="button"
                                    class="
                                        flex
                                        w-full
                                        items-center
                                        gap-3
                                        px-3
                                        py-2.5
                                        text-left
                                        p
                                        text-dark
                                        transition-colors
                                        hover:bg-dark
                                        hover:text-light
                                    "
                                    @click="
                                        openMenu = null;
                                        requestDelete(item)
                                    "
                                    :disabled="
                                        !canDeleteItem(item)
                                    "
                                >
                                    <span>
                                        {{
                                            !canDeleteItem(item)
                                                ? 'Required'
                                                : 'Delete'
                                        }}
                                    </span>
                                </button>
                                                            </div>
                            </Teleport>
                        </div>
                    </div>
                </div>

                <div
                    v-if="
                        !currentFolderItems.length
                    "
                    class="
                        flex
                        flex-col
                        items-center
                        gap-2
                        px-5
                        py-16
                        text-center
                    "
                >
                    <i
                        class="
                            bi
                            bi-folder2-open
                            text-3xl
                            text-accent/40
                        "
                        aria-hidden="true"
                    />

                    <p
                        class="
                            p
                            uppercase
                            text-dark/40
                        "
                    >
                        No files or folders yet.
                    </p>

                    <p
                        v-if="
                            !disabled
                        "
                        class="
                            p
                            text-dark/30
                        "
                    >
                        Use the buttons above to add a folder or document.
                    </p>
                </div>
            </div>
        </main>


        <Modal
            :open="
                Boolean(
                    moveTarget
                )
            "
            title="Move item"
            max-width-class="max-w-lg"
            @close="
                closeMoveDialog
            "
        >
            <template #header>
                <p
                    class="
                        p
                        uppercase
                        text-dark/60
                    "
                >
                    Move
                    <strong>
                        {{ moveTarget?.name }}
                    </strong>
                    to:
                </p>
            </template>

            <div
                class="
                    mt-5
                    border
                    border-accent/30
                "
            >
                <div
                    class="
                        flex
                        items-center
                        justify-between
                        gap-3
                        border-b
                        border-accent/20
                        px-4
                        py-3
                    "
                >
                    <div
                        class="
                            flex
                            min-w-0
                            items-center
                            gap-2
                            overflow-x-auto
                        "
                    >
                        <button
                            type="button"
                            class="
                                p
                                uppercase
                                transition-colors
                                hover:text-accent
                            "
                            @click="
                                browseMoveRoot
                            "
                        >
                            Project
                        </button>

                        <template
                            v-for="
                                crumb
                                in moveBrowserBreadcrumbs
                            "
                            :key="
                                crumb.id
                            "
                        >
                            <span
                                class="
                                    p
                                    text-dark/40
                                "
                            >
                                /
                            </span>

                            <button
                                type="button"
                                class="
                                    p
                                    uppercase
                                    transition-colors
                                    hover:text-accent
                                "
                                @click="
                                    browseMoveFolder(crumb)
                                "
                            >
                                {{ crumb.name }}
                            </button>
                        </template>
                    </div>

                    <button
                        type="button"
                        class="
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            text-dark/60
                            transition-colors
                            hover:text-accent
                        "
                        @click="
                            browseMoveUp
                        "
                    >
                        Up
                    </button>
                </div>

                <div
                    class="
                        max-h-64
                        overflow-y-auto
                        divide-y
                        divide-accent/15
                    "
                >
                    <button
                        v-for="
                            folder
                            in moveBrowserFolders
                        "
                        :key="
                            folder.id
                        "
                        type="button"
                        class="
                            flex
                            w-full
                            items-center
                            justify-between
                            gap-3
                            px-4
                            py-3
                            text-left
                            transition-colors
                            hover:bg-accent/[0.05]
                        "
                        @click="
                            browseMoveFolder(folder)
                        "
                    >
                        <span
                            class="
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                            "
                        >
                            {{ folder.name }}
                        </span>

                        <i
                            class="
                                bi
                                bi-chevron-right
                                text-dark/50
                            "
                            aria-hidden="true"
                        />
                    </button>

                    <p
                        v-if="
                            !moveBrowserFolders.length
                        "
                        class="
                            p-4
                            text-center
                            font-mono
                            text-xs
                            uppercase
                            text-dark/50
                        "
                    >
                        No folders here
                    </p>
                </div>

                <div
                    class="
                        border-t
                        border-accent/20
                        px-4
                        py-3
                    "
                >
                    <span
                        class="
                            p
                            uppercase
                            text-dark/60
                        "
                    >
                        Destination: {{ selectedMoveDestinationLabel }}
                    </span>
                </div>
            </div>

            <p
                v-if="
                    moveError
                "
                class="
                    mt-3
                    p
                    uppercase
                    text-red-700
                "
            >
                {{ moveError }}
            </p>

            <div
                class="
                    mt-8
                    flex
                    flex-wrap
                    gap-4
                    justify-end
                "
            >
                <Button
                    type="button"
                    text="cancel"
                    align="right"
                    @click="
                        closeMoveDialog
                    "
                />

                <Button
                    type="button"
                    text="move"
                    variant="accent"
                    align="right"
                    @click="
                        confirmMove
                    "
                />
            </div>
        </Modal>


        <Modal
            :open="
                showFileCreator
            "
            :title="
                fileCreatorMode ===
                'edit'
                    ? 'Edit file'
                    : 'Create file'
            "
            max-width-class="max-w-2xl"
            @close="
                closeFileCreator
            "
        >
            <div
                v-if="
                    fileCreatorStep ===
                    'type'
                "
                class="
                    space-y-4
                "
            >
                <p
                    class="
                        p
                        uppercase
                        text-dark/60
                    "
                >
                    What do you want to create?
                </p>

                <div
                    class="
                        grid
                        gap-3
                        sm:grid-cols-2
                    "
                >
                    <button
                        type="button"
                        class="
                            flex
                            items-center
                            justify-center
                            border
                            border-accent
                            px-4
                            py-4
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            text-accent
                            transition-colors
                            hover:bg-accent
                            hover:text-light
                        "
                        @click="
                            selectCreatorType('document')
                        "
                    >
                        Document
                    </button>

                    <button
                        type="button"
                        class="
                            flex
                            items-center
                            justify-center
                            border
                            border-accent
                            px-4
                            py-4
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            text-accent
                            transition-colors
                            hover:bg-accent
                            hover:text-light
                        "
                        @click="
                            selectCreatorType('link')
                        "
                    >
                        External link
                    </button>
                </div>
            </div>

            <div
                v-else
                class="
                    grid
                    gap-6
                    md:grid-cols-2
                "
            >
                <FormField
                    id="file-name"
                    v-model="
                        fileDraft.name
                    "
                    name="name"
                    type="text"
                    :label="
                        fileDraft.resource_type === 'link'
                            ? linkCopy.linkName
                            : 'Document name'
                    "
                    :placeholder="
                        fileDraft.resource_type === 'link'
                            ? linkCopy.linkNamePlaceholder
                            : 'Project brief'
                    "
                    required
                    :error="
                        fileErrors.name ||
                        ''
                    "
                />

                <FormField
                    v-if="
                        fileDraft.resource_type ===
                        'link'
                    "
                    id="file-url"
                    v-model="
                        fileDraft.url
                    "
                    name="url"
                    type="text"
                    :label="
                        linkCopy.externalUrl
                    "
                    placeholder="https://example.com"
                    required
                    :error="
                        fileErrors.url ||
                        ''
                    "
                />

                <FormField
                    v-if="
                        fileDraft.resource_type ===
                        'link'
                    "
                    id="file-description"
                    v-model="
                        fileDraft.description
                    "
                    name="description"
                    type="textarea"
                    :label="
                        linkCopy.descriptionLabel
                    "
                    :placeholder="
                        linkCopy.descriptionPlaceholder
                    "
                />

                <FormField
                    v-if="
                        allowMetadataEditing
                    "
                    id="file-requirement"
                    v-model="
                        fileDraft.requirement_level
                    "
                    name="requirement_level"
                    type="select"
                    label="Tag"
                    :options="[
                        {
                            label: 'Required',
                            value: 'required'
                        },
                        {
                            label: 'Recommended',
                            value: 'recommended'
                        },
                        {
                            label: 'Optional',
                            value: 'optional'
                        }
                    ]"
                />

                <FormField
                    v-if="
                        allowMetadataEditing &&
                        fileDraft.resource_type ===
                        'document'
                    "
                    id="file-signature"
                    v-model="
                        fileDraft.requires_client_signature
                    "
                    name="requires_client_signature"
                    type="select"
                    label="Client signature"
                    :options="[
                        {
                            label: 'Not required',
                            value: false
                        },
                        {
                            label: 'Required',
                            value: true
                        }
                    ]"
                />
            </div>

            <div
                class="
                    mt-8
                    flex
                    flex-wrap
                    gap-4
                    justify-end
                "
            >
                <Button
                    type="button"
                    text="cancel"
                    align="right"
                    @click="
                        closeFileCreator
                    "
                />

                <Button
                    v-if="
                        fileCreatorStep !==
                        'type'
                    "
                    type="button"
                    :text="
                        fileCreatorMode === 'edit'
                            ? 'save changes'
                            : fileDraft.resource_type === 'link'
                                ? linkCopy.createExternalLink
                                : 'create document'
                    "
                    variant="accent"
                    hover-variant="dark"
                    align="right"
                    @click="
                        saveFileDraft
                    "
                />
            </div>
        </Modal>


        <Modal
            :open="
                Boolean(
                    externalLinkTarget
                )
            "
            :title="
                externalLinkTarget?.name ||
                linkCopy.leavingTitle
            "
            :subtitle="
                linkCopy.leavingSubtitle
            "
            max-width-class="max-w-xl"
            @close="
                closeExternalLinkModal
            "
        >
            <div
                class="
                    space-y-6
                "
            >
                <p
                    class="
                        p
                        uppercase
                        text-dark
                    "
                >
                    {{
                        externalLinkTarget?.description ||
                        externalLinkTarget?.content ||
                        linkCopy.leavingDescriptionDefault
                    }}
                </p>


                <div
                    class="
                        flex
                        flex-col
                        gap-4
                    "
                >
                    <Button
                        type="button"
                        :text="
                            linkCopy.cancel
                        "
                        align="right"
                        @click="
                            closeExternalLinkModal
                        "
                    />

                    <Button
                        type="button"
                        :text="
                            linkCopy.openLink
                        "
                        variant="accent"
                        align="right"
                        @click="
                            openExternalLink
                        "
                    />
                </div>
            </div>
        </Modal>


        <!-- Delete confirmation -->
        <AdminConfirmDialog
            :open="
                Boolean(
                    deleteTarget
                )
            "
            :title="
                deleteTarget?.type === 'folder'
                    ? 'Delete folder?'
                    : getResourceType(deleteTarget) === 'document'
                        ? 'Delete document?'
                        : 'Delete file?'
            "
            :text="
                deleteTarget?.type === 'folder'
                    ? `${deleteTarget?.name || 'This folder'} and everything inside it will be removed.`
                    : getResourceType(deleteTarget) === 'document'
                        ? `${deleteTarget?.name || 'This document'} will be removed from the project.`
                        : `${deleteTarget?.name || 'This file'} will be removed from the project.`
            "
            confirm-label="Delete"
            :busy="
                deleting
            "
            @close="
                deleteTarget = null
            "
            @confirm="
                confirmDelete
            "
        />

    </div>
</template>