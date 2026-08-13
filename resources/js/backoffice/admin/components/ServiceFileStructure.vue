<script setup>
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    reactive,
    ref
} from 'vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Tag from '@shared/components/Tag.vue'
import AdminConfirmDialog from '@admin/components/AdminConfirmDialog.vue'


const props =
    defineProps({
        modelValue: {
            type: Array,
            default: () => []
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
        'open-document'
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


const showFileCreator =
    ref(false)


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


    emit(
        'open-document',
        file
    )
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
    closeMenu()
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
            currentFolder.value
                ? String(
                    currentFolder.value
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
        'recommended'


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


    showFileCreator.value =
        true
}


function closeFileCreator() {
    showFileCreator.value =
        false


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
            'Document name is required.'

        return
    }


    if (
        fileDraft.resource_type ===
        'link' &&
        !fileDraft.url.trim()
    ) {
        fileErrors.value.url =
            'URL is required.'

        return
    }


    const fileId =
        `file_${Date.now()}_${Math.random()
            .toString(36)
            .slice(2, 8)}`


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
            fileDraft.requirement_level,

        requires_client_signature:
            Boolean(
                fileDraft.requires_client_signature
            ),

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
            currentFolder.value
                ? String(
                    currentFolder.value
                )
                : null
    }


    update([
        ...props.modelValue,
        file
    ])


    selectedItem.value =
        file.id


    closeFileCreator()


    emit(
        'open-document',
        file
    )
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


        if (
            item.type ===
            'folder'
        ) {
            openFolder(
                item
            )
        } else {
            openDocument(
                item
            )
        }
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


        if (
            item.type ===
            'folder'
        ) {
            openFolder(
                item
            )
        } else {
            openDocument(
                item
            )
        }


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
            </div>
        </div>


        <!-- Layout -->
        <div
            class="
                flex
                min-h-[520px]
                flex-col
                lg:flex-row
            "
        >
            <!-- Folder tree -->
            <aside
                class="
                    w-full
                    shrink-0
                    border-b
                    border-accent
                    lg:w-64
                    lg:border-b-0
                    lg:border-r
                "
            >
                <nav
                    class="
                        max-h-[280px]
                        overflow-y-auto
                        lg:max-h-[calc(100vh-300px)]
                    "
                >
                    <!-- Project -->
                    <button
                        type="button"
                        class="
                            flex
                            w-full
                            items-center
                            justify-between
                            gap-3
                            border-b
                            border-accent
                            px-5
                            py-4
                            text-left
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            transition-colors
                            duration-200
                        "
                        :class="
                            !currentFolder
                                ? 'bg-accent text-light'
                                : 'bg-light text-dark hover:bg-accent hover:text-light'
                        "
                        @click="
                            openRoot
                        "
                    >
                        <span
                            class="
                                min-w-0
                                truncate
                            "
                        >
                            Project
                        </span>


                        <span
                            class="
                                shrink-0
                                text-[10px]
                                font-normal
                                opacity-50
                            "
                        >
                            {{
                                folders.filter(
                                    folder =>
                                        !folder.parent_id
                                ).length +
                                files.filter(
                                    file =>
                                        !file.parent_id
                                ).length
                            }}
                        </span>
                    </button>


                    <!-- Folders -->
                    <template
                        v-for="
                            folder
                            in rootFolders
                        "
                        :key="
                            folder.id
                        "
                    >
                        <button
                            type="button"
                            class="
                                flex
                                w-full
                                items-center
                                justify-between
                                gap-3
                                border-b
                                border-accent
                                py-4
                                text-left
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                transition-colors
                                duration-200
                            "
                            :style="{
                                paddingLeft:
                                    `${20 + folderDepth(folder.id) * 16}px`,
                                paddingRight:
                                    '20px'
                            }"
                            :class="
                                String(
                                    currentFolder
                                ) ===
                                String(
                                    folder.id
                                )
                                    ? 'bg-accent text-light'
                                    : 'bg-light text-dark hover:bg-accent hover:text-light'
                            "
                            @click="
                                openFolder(
                                    folder
                                )
                            "
                            @contextmenu.prevent="
                                startRename(
                                    folder
                                )
                            "
                        >
                            <span
                                class="
                                    min-w-0
                                    truncate
                                "
                            >
                                {{
                                    folder.name
                                }}
                            </span>


                            <span
                                class="
                                    shrink-0
                                    text-[10px]
                                    font-normal
                                    opacity-50
                                "
                            >
                                {{
                                    itemCount(
                                        folder.id
                                    )
                                }}
                            </span>
                        </button>


                        <!-- Nested folders -->
                        <template
                            v-for="
                                child
                                in childrenOf(
                                    folder.id
                                )
                            "
                            :key="
                                child.id
                            "
                        >
                            <button
                                type="button"
                                class="
                                    flex
                                    w-full
                                    items-center
                                    justify-between
                                    gap-3
                                    border-b
                                    border-accent
                                    py-4
                                    text-left
                                    font-mono
                                    text-xs
                                    font-bold
                                    uppercase
                                    transition-colors
                                    duration-200
                                "
                                :style="{
                                    paddingLeft:
                                        `${20 + folderDepth(child.id) * 16}px`,
                                    paddingRight:
                                        '20px'
                                }"
                                :class="
                                    String(
                                        currentFolder
                                    ) ===
                                    String(
                                        child.id
                                    )
                                        ? 'bg-accent text-light'
                                        : 'bg-light text-dark hover:bg-accent hover:text-light'
                                "
                                @click="
                                    openFolder(
                                        child
                                    )
                                "
                                @contextmenu.prevent="
                                    startRename(
                                        child
                                    )
                                "
                            >
                                <span
                                    class="
                                        min-w-0
                                        truncate
                                    "
                                >
                                    {{
                                        child.name
                                    }}
                                </span>


                                <span
                                    class="
                                        shrink-0
                                        text-[10px]
                                        font-normal
                                        opacity-50
                                    "
                                >
                                    {{
                                        itemCount(
                                            child.id
                                        )
                                    }}
                                </span>
                            </button>
                        </template>
                    </template>
                </nav>
            </aside>


            <!-- File area -->
            <main
                class="
                    min-w-0
                    flex-1
                "
            >
                <!-- Document creator -->
                <div
                    v-if="
                        showFileCreator
                    "
                    class="
                        border-b
                        border-accent
                        bg-accent/[0.025]
                        p-5
                        sm:p-6
                    "
                >
                    <div
                        class="
                            flex
                            items-start
                            justify-between
                            gap-5
                        "
                    >
                        <div>
                            <h3
                                class="
                                    h2
                                    mt-2
                                    text-accent
                                "
                            >
                                Create document
                            </h3>
                        </div>
                    </div>


                    <div
                        class="
                            mt-8
                            grid
                            gap-8
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
                            label="Document name"
                            placeholder="Project brief"
                            required
                            :error="
                                fileErrors.name ||
                                ''
                            "
                        />


                        <FormField
                            id="file-resource-type"
                            v-model="
                                fileDraft.resource_type
                            "
                            name="resource_type"
                            type="select"
                            label="Type"
                            :options="[
                                {
                                    label: 'Document',
                                    value: 'document'
                                },
                                {
                                    label: 'Link',
                                    value: 'link'
                                }
                            ]"
                        />


                        <FormField
                            id="file-requirement"
                            v-model="
                                fileDraft.requirement_level
                            "
                            name="requirement_level"
                            type="select"
                            label="Requirement"
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
                            label="URL"
                            placeholder="https://example.com"
                            required
                            :error="
                                fileErrors.url ||
                                ''
                            "
                        />
                    </div>


                    <div
                        class="
                            mt-8
                            flex
                            flex-wrap
                            gap-5
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
                            type="button"
                            text="create document"
                            variant="accent"
                            align="right"
                            @click="
                                saveFileDraft
                            "
                        />
                    </div>
                </div>


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
                        @click="
                            selectItem(
                                item
                            )
                        "
                        @dblclick="
                            item.type === 'folder'
                                ? openFolder(item)
                                : openDocument(item)
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
                                                : 'Document'
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
                                    >
                                        <span>
                                            Delete
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>


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
                    : 'Delete document?'
            "
            :text="
                deleteTarget?.type === 'folder'
                    ? `${deleteTarget?.name || 'This folder'} and everything inside it will be removed.`
                    : `${deleteTarget?.name || 'This document'} will be removed from the project.`
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