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
import AdminModalShell from '@admin/components/AdminModalShell.vue'
import AdminConfirmDialog from '@admin/components/AdminConfirmDialog.vue'


const props =
    defineProps({
        modelValue: {
            type: Array,
            default: () => []
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

    emit(
        'open-file',
        file
    )
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

function toggleMenu(
    itemId
) {
    if (
        openMenu.value ===
        itemId
    ) {
        openMenu.value =
            null

        return
    }


    openMenu.value =
        itemId
}


function closeMenu() {
    openMenu.value =
        null
}


function handleDocumentClick() {
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
}


function browseMoveRoot() {
    moveBrowserFolderId.value =
        null
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


function selectCurrentMoveDestination() {
    moveDestination.value =
        moveBrowserFolderId.value === null
            ? '__root__'
            : String(
                moveBrowserFolderId.value
            )

    moveError.value =
        ''
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


function confirmMove() {
    if (!moveTarget.value) {
        return
    }

    const destinationId =
        moveDestination.value ===
        '__root__'
            ? null
            : moveDestination.value

    if (
        String(
            moveTarget.value.parent_id ?? '__root__'
        ) ===
        String(
            destinationId ?? '__root__'
        )
    ) {
        closeMoveDialog()
        return
    }

    const destinationFolder =
        destinationId !== null
            ? getItem(destinationId)
            : null

    const normalizedDestinationId =
        destinationFolder
            ? destinationFolder.id
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
            normalizedDestinationId ?? '__root__'
    update(
        props.modelValue.map(
            item =>
                String(item.id) ===
                String(
                    moveTarget.value.id
                )
                    ? {
                        ...item,
                        parent_id:
                            normalizedDestinationId,
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

    closeMoveDialog()
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


function finishRename() {
    if (
        !renamingItem.value
    ) {
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


    update(
        props.modelValue.map(
            value =>
                String(value.id) ===
                String(
                    renamingItem.value
                )
                    ? {
                        ...value,
                        name
                    }
                    : value
        )
    )


    renamingItem.value =
        null


    renameValue.value =
        ''
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


    selectedItem.value =
        folder.id


    await nextTick()


    await startRename(
        folder
    )
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
            fileDraft.content,

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
    if (
        !deleteTarget.value
    ) {
        return
    }


    deleting.value =
        true


    try {
        const ids =
            new Set([
                deleteTarget.value.id
            ])


        if (
            deleteTarget.value.type ===
            'folder'
        ) {
            collectDescendants(
                deleteTarget.value.id,
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


        if (
            ids.has(
                selectedItem.value
            )
        ) {
            selectedItem.value =
                null
        }


        if (
            ids.has(
                currentFolder.value
            )
        ) {
            openRoot()
        }


        deleteTarget.value =
            null
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


        <!-- Layout -->
        <div
            class="
                flex
                min-h-[520px]
                flex-col
            "
        >
            <!-- File area -->
            <main
                class="
                    min-w-0
                    flex-1
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
                                <div
                                    v-if="
                                        openMenu ===
                                        item.id
                                    "
                                    class="
                                        absolute
                                        right-0
                                        top-full
                                        z-30
                                        mt-1
                                        min-w-36
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
                                            openFileEditor(item)
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
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>


        <AdminModalShell
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
                        flex
                        items-center
                        justify-between
                        gap-3
                    "
                >
                    <span
                        class="
                            p
                            uppercase
                            text-dark/60
                        "
                    >
                        Selected: {{ selectedMoveDestinationLabel }}
                    </span>

                    <Button
                        type="button"
                        text="select this location"
                        align="right"
                        @click="
                            selectCurrentMoveDestination
                        "
                    />
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
        </AdminModalShell>


        <AdminModalShell
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
                            ? 'Link name'
                            : 'Document name'
                    "
                    :placeholder="
                        fileDraft.resource_type === 'link'
                            ? 'Client Drive'
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
                    label="External URL"
                    placeholder="https://example.com"
                    required
                    :error="
                        fileErrors.url ||
                        ''
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
                            'type' &&
                        fileCreatorMode !==
                            'edit'
                    "
                    type="button"
                    text="back"
                    align="right"
                    @click="
                        backToTypeSelection
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
                                ? 'create external link'
                                : 'create document'
                    "
                    variant="accent"
                    align="right"
                    @click="
                        saveFileDraft
                    "
                />
            </div>
        </AdminModalShell>


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