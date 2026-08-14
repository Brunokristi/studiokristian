<script setup>
import {
    computed,
    nextTick,
    onUnmounted,
    ref,
    watch
} from 'vue'

import {
    EditorContent,
    useEditor
} from '@tiptap/vue-3'

import { BubbleMenu } from '@tiptap/vue-3/menus'

import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'
import Underline from '@tiptap/extension-underline'

import {
    Table,
    TableRow,
    TableHeader,
    TableCell
} from '@tiptap/extension-table'

import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import HorizontalRule from '@tiptap/extension-horizontal-rule'

import useAutosavePolicy from '../composables/useAutosavePolicy'


const {
    setStatus,
    setLastSavedAt,
    status: autosaveStatus,
    lastSavedAt
} = useAutosavePolicy()


const props = defineProps({
    modelValue: {
        type: [Array, Object, String],
        default: () => []
    },

    title: {
        type: String,
        default: ''
    },

    subtitle: {
        type: String,
        default: ''
    },

    template: {
        type: Object,
        default: null
    },

    editable: {
        type: Boolean,
        default: true
    },

    saving: {
        type: Boolean,
        default: false
    },

    saveRevision: {
        type: Number,
        default: 0
    },

    savedRevision: {
        type: Number,
        default: 0
    },

    saveError: {
        type: String,
        default: ''
    }
})


const emit = defineEmits([
    'update:modelValue',
    'update:title',
    'update:subtitle',
    'save',
    'back'
])


const hasPendingSave = ref(false)
const autosaveTimer = ref(null)
const imageInput = ref(null)
const savedSelection = ref(null)

const saveRevision = ref(0)
const pendingRevision = ref(0)

const commandMenu = ref({
    open: false,
    query: '',
    mode: 'slash',
    range: null,
    coords: null
})

const insertHandle = ref({
    visible: false,
    coords: null
})


const AUTOSAVE_DELAY = 800


const titleModel = computed({
    get() {
        return props.title ||
            props.template?.name ||
            ''
    },

    set(value) {
        emit(
            'update:title',
            value
        )

        markDirty()
    }
})


const subtitleModel = computed({
    get() {
        return props.subtitle || ''
    },

    set(value) {
        emit(
            'update:subtitle',
            value
        )

        markDirty()
    }
})


function createTextNode(
    text
) {
    if (!text) {
        return []
    }

    return [
        {
            type: 'text',
            text
        }
    ]
}


function paragraphNode(
    text
) {
    return {
        type: 'paragraph',
        content:
            createTextNode(text)
    }
}


function headingNode(
    level,
    text
) {
    return {
        type: 'heading',
        attrs: {
            level
        },
        content:
            createTextNode(text)
    }
}


function blockquoteNode(
    text
) {
    return {
        type: 'blockquote',
        content: [
            paragraphNode(text)
        ]
    }
}


function taskListNode(
    text,
    checked = false
) {
    return {
        type: 'taskList',
        content: [
            {
                type: 'taskItem',
                attrs: {
                    checked
                },
                content:
                    createTextNode(text)
            }
        ]
    }
}


function listNodes(
    type,
    items
) {
    return {
        type,
        content: items.map(
            item => ({
                type: 'listItem',
                content: [
                    paragraphNode(item)
                ]
            })
        )
    }
}


function imageNode(
    src,
    alt = '',
    title = ''
) {
    return {
        type: 'image',
        attrs: {
            src,
            alt,
            title
        }
    }
}


function nodesFromText(
    text
) {
    const trimmed =
        String(
            text || ''
        ).trim()

    if (!trimmed) {
        return [
            paragraphNode('')
        ]
    }

    const chunks =
        trimmed
            .split(/\n\s*\n/)
            .map(
                chunk =>
                    chunk.trim()
            )
            .filter(Boolean)

    const nodes = []

    for (
        const chunk of chunks
    ) {
        if (
            chunk === '---'
        ) {
            nodes.push({
                type:
                    'horizontalRule'
            })

            continue
        }

        const imageMatch =
            chunk.match(
                /^!\[(.*?)\]\((.*?)\)$/
            )

        if (imageMatch) {
            nodes.push(
                imageNode(
                    imageMatch[2] || '',
                    imageMatch[1] ||
                        'image'
                )
            )

            continue
        }

        const headingMatch =
            chunk.match(
                /^(#{1,3})\s+(.*)$/
            )

        if (headingMatch) {
            nodes.push(
                headingNode(
                    headingMatch[1].length,
                    headingMatch[2] || ''
                )
            )

            continue
        }

        const taskMatch =
            chunk.match(
                /^\[(x| )\]\s+(.*)$/i
            )

        if (taskMatch) {
            nodes.push(
                taskListNode(
                    taskMatch[2] || '',
                    taskMatch[1]
                        .toLowerCase() ===
                        'x'
                )
            )

            continue
        }

        const bulletLines =
            chunk
                .split(/\n/)
                .filter(
                    line =>
                        /^[-*]\s+/.test(
                            line
                        )
                )

        if (
            bulletLines.length
        ) {
            nodes.push(
                listNodes(
                    'bulletList',
                    bulletLines.map(
                        line =>
                            line.replace(
                                /^[-*]\s+/,
                                ''
                            )
                    )
                )
            )

            continue
        }

        const orderedLines =
            chunk
                .split(/\n/)
                .filter(
                    line =>
                        /^\d+\.\s+/.test(
                            line
                        )
                )

        if (
            orderedLines.length
        ) {
            nodes.push(
                listNodes(
                    'orderedList',
                    orderedLines.map(
                        line =>
                            line.replace(
                                /^\d+\.\s+/,
                                ''
                            )
                    )
                )
            )

            continue
        }

        const quoteLines =
            chunk
                .split(/\n/)
                .filter(
                    line =>
                        /^>\s+/.test(
                            line
                        )
                )

        if (
            quoteLines.length
        ) {
            nodes.push(
                blockquoteNode(
                    quoteLines
                        .map(
                            line =>
                                line.replace(
                                    /^>\s+/,
                                    ''
                                )
                        )
                        .join('\n')
                )
            )

            continue
        }

        nodes.push(
            paragraphNode(chunk)
        )
    }

    return nodes.length
        ? nodes
        : [paragraphNode('')]
}


function normalizeDoc(
    source
) {
    if (!source) {
        return {
            type: 'doc',
            content: [
                paragraphNode('')
            ]
        }
    }

    if (
        typeof source ===
        'string'
    ) {
        const text =
            source.trim()

        if (!text) {
            return {
                type: 'doc',
                content: [
                    paragraphNode('')
                ]
            }
        }

        try {
            const parsed =
                JSON.parse(text)

            if (
                parsed?.type ===
                    'doc' &&
                Array.isArray(
                    parsed.content
                )
            ) {
                return parsed
            }

            if (
                parsed?.doc?.type ===
                'doc'
            ) {
                return parsed.doc
            }

            if (
                Array.isArray(
                    parsed
                )
            ) {
                return {
                    type: 'doc',
                    content:
                        parsed.length
                            ? parsed
                            : [
                                paragraphNode(
                                    ''
                                )
                            ]
                }
            }
        } catch {
            return {
                type: 'doc',
                content:
                    nodesFromText(text)
            }
        }

        return {
            type: 'doc',
            content:
                nodesFromText(text)
        }
    }

    if (
        source?.type === 'doc'
    ) {
        return source
    }

    if (
        source?.doc?.type ===
        'doc'
    ) {
        return source.doc
    }

    return {
        type: 'doc',
        content: [
            paragraphNode('')
        ]
    }
}


function clearAutosaveTimer() {
    if (
        autosaveTimer.value
    ) {
        clearTimeout(
            autosaveTimer.value
        )

        autosaveTimer.value =
            null
    }
}


function buildSavePayload() {
    const doc =
        editor.value?.getJSON() ||
        normalizeDoc(
            props.template?.content ||
                props.modelValue
        )

    const revision =
        ++saveRevision.value

    return {
        id:
            props.template?.id ||
            null,

        revision,

        title:
            (
                titleModel.value ||
                'Untitled document'
            )
                .trim() ||
            'Untitled document',

        subtitle:
            subtitleModel.value ||
            '',

        document_schema:
            doc
    }
}


function markDirty() {
    hasPendingSave.value =
        true

    queueAutosave()
}


function queueAutosave(
    immediate = false
) {
    if (
        !props.editable ||
        !hasPendingSave.value
    ) {
        return
    }

    clearAutosaveTimer()

    if (immediate) {
        flushAutosave()
        return
    }

    autosaveTimer.value =
        setTimeout(
            () => {
                flushAutosave()
            },
            AUTOSAVE_DELAY
        )
}


function flushAutosave() {
    if (
        !props.editable ||
        !hasPendingSave.value
    ) {
        return
    }

    try {
        const payload =
            buildSavePayload()

        pendingRevision.value =
            payload.revision

        emit(
            'save',
            payload
        )
    } finally {
        setStatus(
            'saving'
        )
    }
}


function handleBack() {
    if (
        !hasPendingSave.value
    ) {
        emit('back')
        return
    }

    const payload =
        buildSavePayload()

    pendingRevision.value =
        payload.revision

    emit(
        'save',
        payload
    )

    emit(
        'back',
        {
            waitForRevision:
                payload.revision
        }
    )
}


function closeMenus() {
    commandMenu.value = {
        open: false,
        query: '',
        mode: 'slash',
        range: null,
        coords: null
    }

    insertHandle.value.visible =
        false

    insertHandle.value.coords =
        null
}


function updateSelectionUI() {
    const instance =
        editor.value

    if (
        !instance ||
        !instance.isFocused ||
        instance.state.selection
            .empty === false
    ) {
        insertHandle.value.visible =
            false

        insertHandle.value.coords =
            null

        return
    }

    try {
        const coords =
            instance.view.coordsAtPos(
                instance.state.selection.from
            )

        insertHandle.value.visible =
            true

        insertHandle.value.coords = {
            top:
                coords.top,

            left:
                Math.max(
                    20,
                    coords.left - 42
                )
        }
    } catch {
        insertHandle.value.visible =
            false

        insertHandle.value.coords =
            null
    }
}


function syncSlashMenu() {
    const instance =
        editor.value

    if (
        !instance ||
        !instance.isFocused
    ) {
        if (
            commandMenu.value
                .mode ===
            'slash'
        ) {
            closeMenus()
        }

        return
    }

    const selection =
        instance.state.selection

    if (
        !selection.empty
    ) {
        if (
            commandMenu.value
                .mode ===
            'slash'
        ) {
            closeMenus()
        }

        updateSelectionUI()

        return
    }

    const from =
        selection.from

    const textBefore =
        instance.state.doc.textBetween(
            Math.max(
                0,
                from - 80
            ),
            from,
            '\n',
            '\ufffc'
        )

    const match =
        textBefore.match(
            /(?:^|\s)\/([a-z]*)$/
        )

    if (!match) {
        if (
            commandMenu.value
                .mode ===
            'slash'
        ) {
            closeMenus()
        }

        updateSelectionUI()

        return
    }

    const query =
        match[1] || ''

    const rangeStart =
        from -
        query.length -
        1

    try {
        const coords =
            instance.view.coordsAtPos(
                from
            )

        commandMenu.value = {
            open: true,
            query,
            mode: 'slash',

            range: {
                from:
                    rangeStart,
                to:
                    from
            },

            coords: {
                top:
                    coords.bottom +
                    8,

                left:
                    Math.max(
                        16,
                        coords.left
                    )
            }
        }
    } catch {
        commandMenu.value = {
            open: true,
            query,
            mode: 'slash',

            range: {
                from:
                    rangeStart,
                to:
                    from
            },

            coords: {
                top: 120,
                left: 32
            }
        }
    }
}


const commands = [
    {
        id: 'paragraph',
        label: 'Text',
        description: 'Regular paragraph',
        keywords: [
            'text',
            'paragraph',
            'body'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .setParagraph()
                .run()
        }
    },

    {
        id: 'heading',
        label: 'Heading',
        description: 'Large section heading',
        keywords: [
            'heading',
            'title'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .setHeading({
                    level: 1
                })
                .run()
        }
    },

    {
        id: 'subheading',
        label: 'Subheading',
        description: 'Smaller section heading',
        keywords: [
            'subheading',
            'secondary'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .setHeading({
                    level: 2
                })
                .run()
        }
    },

    {
        id: 'bulletList',
        label: 'Bullet list',
        description: 'Unordered list',
        keywords: [
            'bullet',
            'list'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .toggleBulletList()
                .run()
        }
    },

    {
        id: 'orderedList',
        label: 'Numbered list',
        description: 'Ordered list',
        keywords: [
            'numbered',
            'ordered',
            'list'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .toggleOrderedList()
                .run()
        }
    },

    {
        id: 'taskList',
        label: 'Checklist',
        description: 'Tasks with checkboxes',
        keywords: [
            'checkbox',
            'task',
            'checklist'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .toggleTaskList()
                .run()
        }
    },

    {
        id: 'quote',
        label: 'Quote',
        description: 'Highlighted quotation',
        keywords: [
            'quote',
            'blockquote'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .toggleBlockquote()
                .run()
        }
    },

    {
        id: 'divider',
        label: 'Divider',
        description: 'Horizontal separator',
        keywords: [
            'divider',
            'rule',
            'break'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .setHorizontalRule()
                .run()
        }
    },

    {
        id: 'table',
        label: 'Table',
        description: 'Three by three table',
        keywords: [
            'table',
            'grid'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .insertTable({
                    rows: 3,
                    cols: 3,
                    withHeaderRow: true
                })
                .run()
        }
    },

    {
        id: 'image',
        label: 'Image',
        description: 'Insert an image',
        keywords: [
            'image',
            'photo',
            'picture'
        ],

        action() {
            imageInput.value?.click()
        }
    },

    {
        id: 'link',
        label: 'Link',
        description: 'Add a hyperlink',
        keywords: [
            'link',
            'url'
        ],

        action(instance) {
            const href =
                window.prompt(
                    'Enter link URL'
                )

            if (!href) {
                return
            }

            instance
                .chain()
                .focus()
                .extendMarkRange(
                    'link'
                )
                .setLink({
                    href
                })
                .run()
        }
    }
]


const filteredCommands =
    computed(() => {
        const query =
            commandMenu.value.query
                .trim()
                .toLowerCase()

        if (!query) {
            return commands
        }

        return commands.filter(
            command =>
                command.label
                    .toLowerCase()
                    .includes(query) ||
                command.description
                    .toLowerCase()
                    .includes(query) ||
                command.keywords.some(
                    keyword =>
                        keyword.includes(
                            query
                        )
                )
        )
    })


function executeCommand(
    command
) {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    if (
        savedSelection.value !==
        null
    ) {
        instance
            .chain()
            .focus()
            .setTextSelection(
                savedSelection.value
            )
            .run()
    }

    if (
        commandMenu.value.mode ===
            'slash' &&
        commandMenu.value.range
    ) {
        instance
            .chain()
            .focus()
            .deleteRange(
                commandMenu.value.range
            )
            .run()
    }

    command.action(
        instance
    )

    closeMenus()
    markDirty()
}


function openCommandMenu(
    mode = 'plus'
) {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    savedSelection.value =
        instance.state.selection
            .from

    try {
        const coords =
            instance.view.coordsAtPos(
                instance.state.selection.from
            )

        commandMenu.value = {
            open: true,
            query: '',
            mode,
            range: null,

            coords: {
                top:
                    coords.bottom + 8,

                left:
                    Math.max(
                        16,
                        coords.left
                    )
            }
        }
    } catch {
        commandMenu.value = {
            open: true,
            query: '',
            mode,
            range: null,

            coords: {
                top: 120,
                left: 32
            }
        }
    }
}


function handleImageUpload(
    event
) {
    const file =
        event.target.files?.[0]

    if (!file) {
        return
    }

    const reader =
        new FileReader()

    reader.onload =
        () => {
            const src =
                typeof reader.result ===
                'string'
                    ? reader.result
                    : ''

            if (
                !src ||
                !editor.value
            ) {
                return
            }

            editor.value
                .chain()
                .focus()
                .setImage({
                    src,
                    alt:
                        file.name
                })
                .run()

            closeMenus()
            markDirty()
        }

    reader.readAsDataURL(
        file
    )

    event.target.value = ''
}


function handleLinkButton() {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    const href =
        window.prompt(
            'Enter link URL'
        )

    if (!href) {
        return
    }

    instance
        .chain()
        .focus()
        .extendMarkRange(
            'link'
        )
        .setLink({
            href
        })
        .run()

    markDirty()
}


const editor = useEditor({
    editable:
        props.editable,

    content:
        normalizeDoc(
            props.template?.content ||
                props.modelValue
        ),

    extensions: [
        StarterKit.configure({
            horizontalRule:
                false,

            link:
                false,

            underline:
                false,

            heading: {
                levels: [
                    1,
                    2,
                    3
                ]
            }
        }),

        Placeholder.configure({
            placeholder({
                node
            }) {
                if (
                    node.type.name ===
                    'heading'
                ) {
                    return 'Heading'
                }

                if (
                    node.type.name ===
                    'paragraph'
                ) {
                    return 'Start writing or type "/" for commands...'
                }

                return 'Start writing...'
            }
        }),

        Underline,

        Link.configure({
            autolink: true,
            openOnClick: false,
            linkOnPaste: true
        }),

        Image.configure({
            inline: false,
            allowBase64: true
        }),

        HorizontalRule,

        TaskList,

        TaskItem.configure({
            nested: true
        }),

        Table.configure({
            resizable: true
        }),

        TableRow,
        TableHeader,
        TableCell
    ],

    editorProps: {
        attributes: {
            class:
                'document-editor-content'
        },

        handleKeyDown(
            _view,
            event
        ) {
            if (
                event.key ===
                'Escape'
            ) {
                closeMenus()
                return true
            }

            if (
                event.key === '/'
            ) {
                nextTick(
                    () =>
                        syncSlashMenu()
                )
            }

            return false
        }
    },

    onUpdate({
        editor
    }) {
        emit(
            'update:modelValue',
            editor.getJSON()
        )

        markDirty()
        syncSlashMenu()
        updateSelectionUI()
    },

    onSelectionUpdate() {
        syncSlashMenu()
        updateSelectionUI()
    },

    onFocus() {
        updateSelectionUI()
    },

    onBlur() {
        if (
            commandMenu.value
                .mode ===
            'slash'
        ) {
            closeMenus()
        }
    }
})


watch(
    () => props.editable,
    value => {
        editor.value?.setEditable(
            Boolean(value)
        )
    }
)


watch(
    () => props.savedRevision,
    value => {
        if (
            !pendingRevision.value ||
            value <
                pendingRevision.value
        ) {
            return
        }

        pendingRevision.value =
            0

        hasPendingSave.value =
            false

        setStatus('idle')
        setLastSavedAt(
            new Date()
        )

        if (
            value >
            saveRevision.value
        ) {
            saveRevision.value =
                value
        }
    }
)


watch(
    () => props.saveError,
    value => {
        if (value) {
            setStatus('idle')
        }
    }
)


onUnmounted(() => {
    clearAutosaveTimer()
    editor.value?.destroy()
})
</script>


<template>
    <div
        class="
            flex
            min-h-screen
            w-full
            flex-col
            bg-light
            text-dark
        "
    >
        <!-- Top bar -->
        <header
            class="
                sticky
                top-0
                z-30
                flex
                h-16
                shrink-0
                items-center
                justify-between
                border-b
                border-dark/10
                bg-light/95
                px-4
                backdrop-blur
                sm:px-6
                lg:px-8
            "
        >
            <button
                type="button"
                class="
                    group
                    flex
                    items-center
                    gap-2
                    font-mono
                    text-xs
                    font-bold
                    uppercase
                    tracking-wide
                    text-dark
                    transition-colors
                    hover:text-accent
                "
                @click="
                    handleBack
                "
            >
                <span
                    class="
                        transition-transform
                        duration-200
                        group-hover:-translate-x-1
                    "
                >
                    ←
                </span>

                <span>
                    Back
                </span>
            </button>


            <div
                class="
                    absolute
                    left-1/2
                    hidden
                    max-w-[40rem]
                    -translate-x-1/2
                    truncate
                    px-4
                    font-mono
                    text-[10px]
                    font-bold
                    uppercase
                    tracking-[0.14em]
                    text-dark/40
                    md:block
                "
            >
                {{
                    titleModel ||
                    props.template?.name ||
                    'Untitled document'
                }}
            </div>


            <div
                class="
                    flex
                    items-center
                    gap-3
                "
            >
                <span
                    class="
                        hidden
                        font-mono
                        text-[10px]
                        font-bold
                        uppercase
                        tracking-[0.12em]
                        text-dark/40
                        sm:inline
                    "
                >
                    <template
                        v-if="
                            props.saving
                        "
                    >
                        Saving...
                    </template>

                    <template
                        v-else-if="
                            props.saveError
                        "
                    >
                        Save failed
                    </template>

                    <template
                        v-else-if="
                            hasPendingSave
                        "
                    >
                        Unsaved changes
                    </template>

                    <template
                        v-else-if="
                            autosaveStatus ===
                                'idle' &&
                            lastSavedAt
                        "
                    >
                        Saved
                    </template>

                    <template
                        v-else
                    >
                        Ready
                    </template>
                </span>

                <span
                    class="
                        h-1.5
                        w-1.5
                        rounded-full
                        bg-current
                        text-accent
                    "
                />
            </div>
        </header>


        <!-- Formatting toolbar -->
        <div
            v-if="
                editable
            "
            class="
                sticky
                top-16
                z-20
                border-b
                border-dark/10
                bg-light
            "
        >
            <div
                class="
                    mx-auto
                    flex
                    w-full
                    max-w-[900px]
                    items-center
                    gap-1
                    overflow-x-auto
                    px-4
                    py-2
                    sm:px-6
                "
            >
                <!-- Text formatting -->
                <button
                    type="button"
                    class="
                        editor-tool
                        font-bold
                    "
                    title="Bold"
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .toggleBold()
                            .run()
                    "
                >
                    B
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                        italic
                    "
                    title="Italic"
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .toggleItalic()
                            .run()
                    "
                >
                    I
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                        underline
                    "
                    title="Underline"
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .toggleUnderline()
                            .run()
                    "
                >
                    U
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    title="Link"
                    @click="
                        handleLinkButton
                    "
                >
                    Link
                </button>


                <span
                    class="
                        mx-2
                        h-5
                        w-px
                        shrink-0
                        bg-dark/10
                    "
                />


                <!-- Blocks -->
                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .setParagraph()
                            .run()
                    "
                >
                    Text
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .setHeading({
                                level: 1
                            })
                            .run()
                    "
                >
                    H1
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .setHeading({
                                level: 2
                            })
                            .run()
                    "
                >
                    H2
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .toggleBulletList()
                            .run()
                    "
                >
                    • List
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .toggleOrderedList()
                            .run()
                    "
                >
                    1. List
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .toggleTaskList()
                            .run()
                    "
                >
                    ✓ Tasks
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    @click="
                        openCommandMenu(
                            'plus'
                        )
                    "
                >
                    + Insert
                </button>


                <span
                    class="
                        mx-2
                        h-5
                        w-px
                        shrink-0
                        bg-dark/10
                    "
                />


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    title="Undo"
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .undo()
                            .run()
                    "
                >
                    Undo
                </button>


                <button
                    type="button"
                    class="
                        editor-tool
                    "
                    title="Redo"
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .redo()
                            .run()
                    "
                >
                    Redo
                </button>
            </div>
        </div>


        <!-- Writing area -->
        <main
            class="
                min-h-0
                flex-1
                overflow-y-auto
            "
        >
            <article
                class="
                    mx-auto
                    min-h-full
                    w-full
                    max-w-[900px]
                    px-5
                    py-12
                    sm:px-10
                    sm:py-16
                    lg:px-16
                    lg:py-20
                "
            >
                <!-- Document heading -->
                <div
                    class="
                        mb-12
                        border-b
                        border-dark/10
                        pb-10
                    "
                >
                    <input
                        v-model="
                            titleModel
                        "
                        type="text"
                        :readonly="
                            !editable
                        "
                        placeholder="Untitled"
                        class="
                            block
                            w-full
                            border-0
                            bg-transparent
                            p-0
                            font-serif
                            text-4xl
                            font-bold
                            leading-[1.05]
                            tracking-[-0.035em]
                            text-dark
                            outline-none
                            placeholder:text-dark/20
                            sm:text-5xl
                            lg:text-6xl
                        "
                        @blur="
                            queueAutosave(
                                true
                            )
                        "
                    />


                    <textarea
                        v-model="
                            subtitleModel
                        "
                        rows="1"
                        :readonly="
                            !editable
                        "
                        placeholder="Add a subtitle..."
                        class="
                            mt-5
                            block
                            w-full
                            resize-none
                            border-0
                            bg-transparent
                            p-0
                            text-lg
                            leading-relaxed
                            text-dark/50
                            outline-none
                            placeholder:text-dark/25
                            sm:text-xl
                        "
                        @blur="
                            queueAutosave(
                                true
                            )
                        "
                    />
                </div>


                <!-- Editor -->
                <div
                    class="
                        relative
                    "
                >
                    <!-- Contextual plus -->
                    <div
                        v-if="
                            insertHandle.visible &&
                            insertHandle.coords &&
                            editable
                        "
                        class="
                            fixed
                            z-30
                        "
                        :style="{
                            top:
                                `${insertHandle.coords.top}px`,
                            left:
                                `${insertHandle.coords.left}px`
                        }"
                    >
                        <button
                            type="button"
                            class="
                                grid
                                h-8
                                w-8
                                place-items-center
                                border
                                border-dark/15
                                bg-light
                                font-mono
                                text-lg
                                font-light
                                leading-none
                                text-dark/50
                                shadow-sm
                                transition-all
                                hover:border-accent
                                hover:bg-accent
                                hover:text-light
                            "
                            aria-label="Insert block"
                            @mousedown.prevent
                            @click="
                                openCommandMenu(
                                    'plus'
                                )
                            "
                        >
                            +
                        </button>
                    </div>


                    <EditorContent
                        :editor="
                            editor
                        "
                        class="
                            article-editor
                        "
                    />
                </div>


                <!-- End of document -->
                <div
                    v-if="
                        editable
                    "
                    class="
                        mt-16
                        flex
                        items-center
                        gap-4
                        text-dark/20
                    "
                >
                    <span
                        class="
                            h-px
                            flex-1
                            bg-dark/10
                        "
                    />

                    <button
                        type="button"
                        class="
                            font-mono
                            text-[10px]
                            font-bold
                            uppercase
                            tracking-[0.14em]
                            transition-colors
                            hover:text-accent
                        "
                        @click="
                            openCommandMenu(
                                'plus'
                            )
                        "
                    >
                        Add block
                    </button>

                    <span
                        class="
                            h-px
                            flex-1
                            bg-dark/10
                        "
                    />
                </div>
            </article>
        </main>


        <!-- Text selection menu -->
        <BubbleMenu
            v-if="
                editor
            "
            :editor="
                editor
            "
            :tippy-options="{
                duration: 120,
                placement: 'top'
            }"
        >
            <div
                class="
                    flex
                    items-center
                    border
                    border-dark/10
                    bg-light
                    shadow-xl
                "
            >
                <button
                    type="button"
                    class="
                        bubble-tool
                        font-bold
                    "
                    @click="
                        editor
                            .chain()
                            .focus()
                            .toggleBold()
                            .run()
                    "
                >
                    B
                </button>


                <button
                    type="button"
                    class="
                        bubble-tool
                        italic
                    "
                    @click="
                        editor
                            .chain()
                            .focus()
                            .toggleItalic()
                            .run()
                    "
                >
                    I
                </button>


                <button
                    type="button"
                    class="
                        bubble-tool
                        underline
                    "
                    @click="
                        editor
                            .chain()
                            .focus()
                            .toggleUnderline()
                            .run()
                    "
                >
                    U
                </button>


                <button
                    type="button"
                    class="
                        bubble-tool
                    "
                    @click="
                        handleLinkButton
                    "
                >
                    Link
                </button>
            </div>
        </BubbleMenu>


        <!-- Command menu -->
        <div
            v-if="
                commandMenu.open &&
                commandMenu.coords
            "
            class="
                fixed
                z-40
                w-80
                max-w-[calc(100vw-2rem)]
            "
            :style="{
                top:
                    `${commandMenu.coords.top}px`,
                left:
                    `${commandMenu.coords.left}px`
            }"
        >
            <div
                class="
                    overflow-hidden
                    border
                    border-dark/15
                    bg-light
                    shadow-2xl
                "
            >
                <div
                    class="
                        border-b
                        border-dark/10
                        px-4
                        py-3
                    "
                >
                    <input
                        v-model="
                            commandMenu.query
                        "
                        type="text"
                        placeholder="Search blocks..."
                        class="
                            w-full
                            border-0
                            bg-transparent
                            p-0
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            tracking-[0.1em]
                            text-dark
                            outline-none
                            placeholder:text-dark/25
                        "
                        @keydown.escape.stop.prevent="
                            closeMenus
                        "
                    />
                </div>


                <div
                    class="
                        max-h-80
                        overflow-y-auto
                        p-1
                    "
                >
                    <button
                        v-for="
                            command in
                                filteredCommands
                        "
                        :key="
                            command.id
                        "
                        type="button"
                        class="
                            flex
                            w-full
                            items-center
                            justify-between
                            gap-4
                            px-3
                            py-3
                            text-left
                            transition-colors
                            hover:bg-accent
                            hover:text-light
                        "
                        @mousedown.prevent
                        @click="
                            executeCommand(
                                command
                            )
                        "
                    >
                        <span
                            class="
                                text-sm
                                font-medium
                            "
                        >
                            {{
                                command.label
                            }}
                        </span>

                        <span
                            class="
                                text-[11px]
                                text-dark/35
                                transition-colors
                                group-hover:text-light/60
                            "
                        >
                            {{
                                command.description
                            }}
                        </span>
                    </button>


                    <div
                        v-if="
                            !filteredCommands.length
                        "
                        class="
                            px-3
                            py-6
                            text-center
                            text-sm
                            text-dark/40
                        "
                    >
                        No matching blocks.
                    </div>
                </div>
            </div>
        </div>


        <input
            ref="
                imageInput
            "
            type="file"
            accept="image/*"
            class="hidden"
            @change="
                handleImageUpload
            "
        />
    </div>
</template>


<style scoped>
.editor-tool {
    flex-shrink: 0;
    padding: 0.45rem 0.6rem;
    border: 1px solid transparent;
    color: rgb(23 23 23 / 0.65);
    font-family: monospace;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
    white-space: nowrap;
    text-transform: uppercase;
    transition:
        color 180ms ease,
        border-color 180ms ease,
        background-color 180ms ease;
}

.editor-tool:hover {
    border-color: rgb(23 23 23 / 0.12);
    color: var(--color-accent);
    background: rgb(23 23 23 / 0.025);
}

.bubble-tool {
    min-width: 38px;
    padding: 0.55rem 0.7rem;
    color: rgb(23 23 23 / 0.75);
    font-family: monospace;
    font-size: 0.7rem;
    font-weight: 700;
    transition:
        color 180ms ease,
        background-color 180ms ease;
}

.bubble-tool:hover {
    color: var(--color-accent);
    background: rgb(23 23 23 / 0.04);
}

.article-editor :deep(.ProseMirror) {
    min-height: 60vh;
    color: rgb(23 23 23);
    font-size: 1.08rem;
    line-height: 1.85;
    outline: none;
}

.article-editor :deep(.ProseMirror > * + *) {
    margin-top: 1.25rem;
}

.article-editor :deep(.ProseMirror p) {
    margin: 0;
}

.article-editor :deep(.ProseMirror h1),
.article-editor :deep(.ProseMirror h2),
.article-editor :deep(.ProseMirror h3) {
    margin: 2.5rem 0 0.8rem;
    color: rgb(23 23 23);
    font-family: inherit;
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1.12;
}

.article-editor :deep(.ProseMirror h1) {
    font-size: clamp(2rem, 4vw, 3.1rem);
}

.article-editor :deep(.ProseMirror h2) {
    font-size: clamp(1.55rem, 3vw, 2.2rem);
}

.article-editor :deep(.ProseMirror h3) {
    font-size: 1.35rem;
}

.article-editor :deep(.ProseMirror blockquote) {
    margin: 2rem 0;
    border-left: 3px solid rgb(23 23 23 / 0.18);
    padding: 0.25rem 0 0.25rem 1.25rem;
    color: rgb(23 23 23 / 0.68);
    font-size: 1.2rem;
    font-style: italic;
}

.article-editor :deep(.ProseMirror hr) {
    margin: 3rem 0;
    border: 0;
    border-top: 1px solid rgb(23 23 23 / 0.14);
}

.article-editor :deep(.ProseMirror ul),
.article-editor :deep(.ProseMirror ol) {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

.article-editor :deep(.ProseMirror li) {
    padding-left: 0.25rem;
}

.article-editor :deep(.ProseMirror li + li) {
    margin-top: 0.3rem;
}

.article-editor :deep(.ProseMirror img) {
    display: block;
    width: 100%;
    max-height: 720px;
    margin: 2rem 0;
    object-fit: contain;
}

.article-editor :deep(.ProseMirror a) {
    color: var(--color-accent);
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;
}

.article-editor :deep(.ProseMirror table) {
    width: 100%;
    margin: 2rem 0;
    border-collapse: collapse;
    overflow: hidden;
}

.article-editor :deep(.ProseMirror td),
.article-editor :deep(.ProseMirror th) {
    min-width: 120px;
    border: 1px solid rgb(23 23 23 / 0.12);
    padding: 0.75rem;
    vertical-align: top;
    text-align: left;
}

.article-editor :deep(.ProseMirror th) {
    background: rgb(23 23 23 / 0.035);
    font-weight: 700;
}

.article-editor :deep(.ProseMirror .selectedCell) {
    background: rgb(23 23 23 / 0.06);
}

.article-editor :deep(.ProseMirror ul[data-type='taskList']) {
    list-style: none;
    margin: 1rem 0;
    padding: 0;
}

.article-editor :deep(.ProseMirror li[data-type='taskItem']) {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    padding-left: 0;
}

.article-editor :deep(.ProseMirror li[data-type='taskItem'] > label) {
    flex: 0 0 auto;
    margin-top: 0.35rem;
}

.article-editor :deep(.ProseMirror li[data-type='taskItem'] > div) {
    flex: 1;
}

.article-editor :deep(.ProseMirror li[data-type='taskItem'] input[type='checkbox']) {
    width: 1rem;
    height: 1rem;
    accent-color: var(--color-accent);
}

.article-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    float: left;
    height: 0;
    color: rgb(23 23 23 / 0.25);
    content: attr(data-placeholder);
    pointer-events: none;
}

.article-editor :deep(.ProseMirror h1.is-empty::before),
.article-editor :deep(.ProseMirror h2.is-empty::before),
.article-editor :deep(.ProseMirror h3.is-empty::before) {
    float: left;
    height: 0;
    color: rgb(23 23 23 / 0.2);
    content: attr(data-placeholder);
    pointer-events: none;
}

.article-editor :deep(.ProseMirror:focus) {
    outline: none;
}

@media (max-width: 640px) {
    .article-editor :deep(.ProseMirror) {
        min-height: 55vh;
        font-size: 1rem;
        line-height: 1.8;
    }

    .article-editor :deep(.ProseMirror h1) {
        font-size: 2rem;
    }

    .article-editor :deep(.ProseMirror h2) {
        font-size: 1.55rem;
    }

    .article-editor :deep(.ProseMirror blockquote) {
        padding-left: 1rem;
        font-size: 1.05rem;
    }

    .article-editor :deep(.ProseMirror td),
    .article-editor :deep(.ProseMirror th) {
        min-width: 100px;
        padding: 0.55rem;
    }
}
</style>