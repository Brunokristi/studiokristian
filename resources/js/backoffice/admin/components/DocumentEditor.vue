<script setup>
import {
    computed,
    nextTick,
    onMounted,
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
import Underline from '@tiptap/extension-underline'

import {
    Table,
    TableRow,
    TableHeader,
    TableCell
} from '@tiptap/extension-table'
import {
    NodeSelection,
    Selection,
    TextSelection
} from '@tiptap/pm/state'

import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import HorizontalRule from '@tiptap/extension-horizontal-rule'

import useAutosavePolicy from '../composables/useAutosavePolicy'
import api, {
    errorMessage
} from '../composables/useAdminApi'
import Slider from './document-editor/extensions/Slider'
import InfoBlock from './document-editor/extensions/Info'
import ResizableImage from './document-editor/extensions/ResizableImage'

import AdminModal from './AdminModal.vue'
import ProjectFilePickerModal from './ProjectFilePickerModal.vue'
import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'


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
    },

    showSignatureStatus: {
        type: Boolean,
        default: false
    },

    requiresSignature: {
        type: Boolean,
        default: false
    },

    signatureSigned: {
        type: Boolean,
        default: false
    },

    projectFilesEnabled: {
        type: Boolean,
        default: false
    },

    projectId: {
        type: [String, Number],
        default: ''
    },

    language: {
        type: String,
        default: 'en'
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
const savedSelection = ref(null)
const commandMenuRoot = ref(null)

const showLinkModal = ref(false)
const linkHref = ref('')
const linkError = ref('')
const savedLinkSelection = ref(null)

const showImagePickerModal = ref(false)
const imagePickerLoading = ref(false)
const imagePickerError = ref('')
const imagePickerItems = ref([])
const imagePickerCurrentFolderId = ref(null)
const imagePickerUploading = ref(false)

const sliderPickerContext = ref(null)
const imageNodePickerContext = ref(false)
const imageNodePickerPos = ref(null)

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

const blockTools = ref({
    visible: false,
    top: 0,
    left: 0,
    index: -1
})


const AUTOSAVE_DELAY = 800
const COMMAND_MENU_WIDTH = 320
const COMMAND_MENU_MAX_HEIGHT = 320


const imagePickerTitle = computed(() => {
    return 'Project files'
})


const imagePickerSubtitle = computed(() => {
    return imageNodePickerContext.value
        ? 'Select an image file from project files.'
        : 'Same project structure as workspace. Double-click a PNG file to select it.'
})


function getPositionedCommandMenuCoords(
    preferredTop,
    preferredLeft
) {
    const viewportWidth =
        window.innerWidth ||
        1280

    const viewportHeight =
        window.innerHeight ||
        720

    const maxLeft = Math.max(
        16,
        viewportWidth -
            COMMAND_MENU_WIDTH -
            16
    )

    const left = Math.min(
        maxLeft,
        Math.max(
            16,
            preferredLeft
        )
    )

    let top = Math.max(
        16,
        preferredTop
    )

    if (
        top + COMMAND_MENU_MAX_HEIGHT >
        viewportHeight - 16
    ) {
        top = Math.max(
            16,
            viewportHeight -
                COMMAND_MENU_MAX_HEIGHT -
                16
        )
    }

    return {
        top,
        left
    }
}


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


const signatureStatusLabel =
    computed(() => {
        if (
            !props.requiresSignature
        ) {
            return ''
        }

        const language =
            String(
                props.language ||
                'en'
            )
                .trim()
                .toLowerCase()

        const slovak =
            language === 'sk'


        return props.signatureSigned
            ? (
                slovak
                    ? 'podpísané'
                    : 'signed'
            )
            : (
                slovak
                    ? 'nepodpísané'
                    : 'not signed'
            )
    })


const signatureStatusClass =
    computed(() => {
        if (
            !props.requiresSignature
        ) {
            return ''
        }


        return props.signatureSigned
            ? 'bg-light text-accent'
            : 'bg-dark text-light'
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
            title,
            width: '100%',
            pendingProjectImage: false
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


function getTopLevelBlockStartPos(
    doc,
    index
) {
    let position = 0

    for (
        let i = 0;
        i < index;
        i += 1
    ) {
        position +=
            doc.child(i)
                .nodeSize
    }

    return position
}


function topLevelChildCount(doc) {
    return Number(
        doc?.childCount ||
        0
    )
}


function topLevelInsertPos(
    doc,
    index
) {
    return getTopLevelBlockStartPos(
        doc,
        Math.max(
            0,
            Math.min(
                index,
                topLevelChildCount(doc)
            )
        )
    )
}


function getActiveTopLevelIndex(
    selection,
    doc
) {
    if (
        !selection ||
        !doc?.childCount
    ) {
        return -1
    }

    if (
        selection.$from?.depth >= 1
    ) {
        return selection.$from.index(0)
    }

    const safePos = Math.max(
        0,
        Math.min(
            Number(
                selection.from || 0
            ),
            doc.content.size
        )
    )

    return doc.resolve(
        safePos
    ).index(0)
}


function clearActiveBlockVisuals() {
    const root =
        editor.value?.view
            ?.dom

    if (!(root instanceof HTMLElement)) {
        return
    }

    root
        .querySelectorAll(
            '.is-editor-active-block, .is-editor-drop-target'
        )
        .forEach(node => {
            node.classList.remove(
                'is-editor-active-block'
            )
            node.classList.remove(
                'is-editor-drop-target'
            )
        })
}


function updateBlockToolsUI() {
    const instance =
        editor.value

    if (
        !instance ||
        !props.editable
    ) {
        clearActiveBlockVisuals()
        blockTools.value.visible =
            false
        return
    }

    const {
        selection,
        doc
    } = instance.state

    const root =
        instance.view
            ?.dom

    if (
        !(root instanceof HTMLElement)
    ) {
        blockTools.value.visible =
            false
        return
    }

    if (!doc.childCount) {
        clearActiveBlockVisuals()
        blockTools.value.visible =
            false
        return
    }

    const index =
        getActiveTopLevelIndex(
            selection,
            doc
        )

    if (
        index < 0 ||
        index >= doc.childCount
    ) {
        clearActiveBlockVisuals()
        blockTools.value.visible =
            false
        return
    }

    clearActiveBlockVisuals()

    const blockDom =
        root.children[index]

    if (
        !(blockDom instanceof HTMLElement)
    ) {
        blockTools.value.visible =
            false
        return
    }

    blockDom.classList.add(
        'is-editor-active-block'
    )

    const rect =
        blockDom.getBoundingClientRect()

    blockTools.value = {
        visible: true,
        top: Math.max(
            12,
            rect.top + 6
        ),
        left: Math.max(
            12,
            rect.right - 168
        ),
        index
    }
}


function moveBlockByInsertIndex(
    sourceIndex,
    insertIndex
) {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    const {
        state,
        view
    } = instance

    const blockCount =
        topLevelChildCount(
            state.doc
        )

    if (
        sourceIndex < 0 ||
        sourceIndex >= blockCount ||
        insertIndex < 0 ||
        insertIndex > blockCount
    ) {
        return
    }

    const normalizedInsertIndex =
        sourceIndex <
        insertIndex
            ? insertIndex - 1
            : insertIndex

    if (
        normalizedInsertIndex ===
        sourceIndex
    ) {
        return
    }

    const sourcePos =
        getTopLevelBlockStartPos(
            state.doc,
            sourceIndex
        )

    const sourceNode =
        state.doc.child(
            sourceIndex
        )

    const sourceEndPos =
        sourcePos +
        sourceNode.nodeSize

    const tr =
        state.tr.delete(
            sourcePos,
            sourceEndPos
        )

    const insertPos =
        topLevelInsertPos(
            tr.doc,
            normalizedInsertIndex
        )

    tr.insert(
        insertPos,
        sourceNode
    )

    const movedNodePos =
        topLevelInsertPos(
            tr.doc,
            normalizedInsertIndex
        )

    const movedNode =
        tr.doc.child(
            normalizedInsertIndex
        )

    if (
        tr.doc.nodeAt(
            movedNodePos
        )
    ) {
        if (
            movedNode?.isTextblock
        ) {
            const textPos = Math.max(
                1,
                Math.min(
                    movedNodePos + 1,
                    tr.doc.content
                        .size
                )
            )

            tr.setSelection(
                TextSelection.near(
                    tr.doc.resolve(
                        textPos
                    )
                )
            )
        } else {
            try {
                tr.setSelection(
                    NodeSelection.create(
                        tr.doc,
                        movedNodePos
                    )
                )
            } catch {
                tr.setSelection(
                    Selection.near(
                        tr.doc.resolve(
                            Math.min(
                                movedNodePos + 1,
                                tr.doc.content
                                    .size
                            )
                        ),
                        1
                    )
                )
            }
        }
    }

    view.dispatch(
        tr.scrollIntoView()
    )

    instance.commands.focus()

    markDirty()

    nextTick(() => {
        try {
            const coords =
                instance.view.coordsAtPos(
                    Math.max(
                        1,
                        movedNodePos + 1
                    )
                )

            const viewportHeight =
                window.innerHeight ||
                900

            if (
                coords.top < 96 ||
                coords.bottom >
                    viewportHeight -
                        96
            ) {
                window.scrollTo({
                    top:
                        window.scrollY +
                        coords.top -
                        viewportHeight / 2,
                    behavior:
                        'smooth'
                })
            }
        } catch {
            // Keep move operation resilient when positions shift rapidly.
        }

        updateBlockToolsUI()
    })
}


function deleteActiveBlock() {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    const index =
        blockTools.value.index

    if (index < 0) {
        return
    }

    const docJson =
        instance.getJSON()

    const content =
        Array.isArray(
            docJson?.content
        )
            ? [...docJson.content]
            : []

    if (
        index >= content.length
    ) {
        return
    }

    content.splice(index, 1)

    if (!content.length) {
        content.push({
            type: 'paragraph'
        })
    }

    instance
        .chain()
        .focus()
        .setContent(
            {
                ...docJson,
                content
            },
            false
        )
        .run()

    markDirty()

    nextTick(() => {
        updateBlockToolsUI()
    })
}


function duplicateActiveBlock() {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    const index =
        blockTools.value.index

    if (index < 0) {
        return
    }

    const docJson =
        instance.getJSON()

    const content =
        Array.isArray(
            docJson?.content
        )
            ? [...docJson.content]
            : []

    if (
        index >= content.length
    ) {
        return
    }

    const clone =
        JSON.parse(
            JSON.stringify(
                content[index]
            )
        )

    content.splice(
        index + 1,
        0,
        clone
    )

    instance
        .chain()
        .focus()
        .setContent(
            {
                ...docJson,
                content
            },
            false
        )
        .run()

    markDirty()

    nextTick(() => {
        updateBlockToolsUI()
    })
}


function moveActiveBlock(
    direction
) {
    const index =
        blockTools.value.index

    if (index < 0) {
        return
    }

    const insertIndex =
        direction === 'up'
            ? index - 1
            : index + 2

    moveBlockByInsertIndex(
        index,
        insertIndex
    )
}


function handleViewportReposition() {
    updateBlockToolsUI()
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

    const selection =
        instance.state.selection

    const parent =
        instance.state.doc.resolve(
            selection.from
        ).parent

    const isEmptyParagraph =
        parent?.type.name ===
            'paragraph' &&
        parent.textContent === ''

    if (!isEmptyParagraph) {
        insertHandle.value.visible =
            false

        insertHandle.value.coords =
            null

        return
    }

    try {
        const coords =
            instance.view.coordsAtPos(
                selection.from
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

        const positioned =
            getPositionedCommandMenuCoords(
                coords.bottom + 8,
                coords.left
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
                    positioned.top,

                left:
                    positioned.left
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
            const placeholderPos =
                insertPendingImagePlaceholder()

            openProjectFilesForImageNode({
                replacePos:
                    placeholderPos
            })
        }
    },

    {
        id: 'slider',
        label: 'Slider',
        description: 'Image slideshow',
        keywords: [
            'slider',
            'slideshow',
            'gallery',
            'images'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .insertContent({
                    type: 'slider',
                    attrs: {
                        images: [],
                        editable:
                            props.editable,
                        language:
                            props.language ||
                            'en'
                    }
                })
                .run()
        }
    },

    {
        id: 'info',
        label: 'Info',
        description: 'Expandable information section',
        keywords: [
            'info',
            'information',
            'accordion',
            'details'
        ],

        action(instance) {
            instance
                .chain()
                .focus()
                .insertContent({
                    type: 'info',
                    attrs: {
                        heading:
                            'New information',
                        text:
                            'Add information here.',
                        editable:
                            props.editable
                    }
                })
                .run()
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

        action() {
            openLinkModal()
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

        const positioned =
            getPositionedCommandMenuCoords(
                coords.bottom + 8,
                coords.left
            )

        commandMenu.value = {
            open: true,
            query: '',
            mode,
            range: null,

            coords: {
                top:
                    positioned.top,

                left:
                    positioned.left
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


function runTableCommand(command) {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    const chain =
        instance
            .chain()
            .focus()

    if (
        command ===
        'add-row'
    ) {
        chain
            .addRowAfter()
            .run()
        markDirty()
        return
    }

    if (
        command ===
        'remove-row'
    ) {
        chain
            .deleteRow()
            .run()
        markDirty()
        return
    }

    if (
        command ===
        'add-column'
    ) {
        chain
            .addColumnAfter()
            .run()
        markDirty()
        return
    }

    if (
        command ===
        'remove-column'
    ) {
        chain
            .deleteColumn()
            .run()
    }

    markDirty()
}


function findPendingImagePos(
    doc,
    anchorPos
) {
    let foundPos = null
    let bestDistance =
        Number.POSITIVE_INFINITY

    doc.descendants(
        (node, pos) => {
            if (
                node.type.name !==
                    'image' ||
                !node.attrs
                    ?.pendingProjectImage
            ) {
                return
            }

            const distance =
                Math.abs(
                    pos - anchorPos
                )

            if (
                distance <
                bestDistance
            ) {
                bestDistance =
                    distance
                foundPos = pos
            }
        }
    )

    return foundPos
}


function insertPendingImagePlaceholder() {
    const instance =
        editor.value

    if (!instance) {
        return null
    }

    const insertPos =
        instance.state.selection.from

    const placeholderSrc =
        'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 420"><rect width="1200" height="420" fill="%23f5f8ff"/></svg>'

    const inserted =
        instance
            .chain()
            .focus()
            .insertContent({
                type: 'image',
                attrs: {
                    src:
                        placeholderSrc,
                    alt: '',
                    title: '',
                    width: '100%',
                    pendingProjectImage:
                        true
                }
            })
            .run()

    if (!inserted) {
        return null
    }

    const pendingPos =
        findPendingImagePos(
            instance.state.doc,
            insertPos
        )

    markDirty()

    return pendingPos ?? insertPos
}


function openProjectFilesForImageNode(
    context = {}
) {
    if (
        !props.projectId
    ) {
        imagePickerError.value =
            'Project files are not available for this document.'
        showImagePickerModal.value =
            true
        return
    }

    imageNodePickerContext.value =
        true
    imageNodePickerPos.value =
        Number.isInteger(
            context?.replacePos
        )
            ? Number(
                context.replacePos
            )
            : null
    sliderPickerContext.value =
        null
    imagePickerCurrentFolderId.value =
        null
    showImagePickerModal.value =
        true

    void loadImagePickerItems()
}


function imagePickerFileName(
    file
) {
    return (
        file?.display_name ||
        file?.original_filename ||
        file?.name ||
        'image'
    )
}


function isPngProjectFile(
    file
) {
    const mime = String(
        file?.mime_type ||
        ''
    ).toLowerCase()

    if (
        mime ===
        'image/png'
    ) {
        return true
    }

    const name = String(
        imagePickerFileName(
            file
        ) ||
        ''
    ).toLowerCase()

    return name.endsWith('.png')
}


function isImageProjectFile(
    file
) {
    const mime = String(
        file?.mime_type ||
        ''
    ).toLowerCase()

    if (
        mime.startsWith(
            'image/'
        )
    ) {
        return true
    }

    const name = String(
        imagePickerFileName(
            file
        ) ||
        ''
    ).toLowerCase()

    return /\.(png|jpe?g|gif|webp|svg|avif)$/i.test(
        name
    )
}


function pickerAcceptsProjectFile(
    file
) {
    if (
        imageNodePickerContext.value
    ) {
        return isImageProjectFile(
            file
        )
    }

    return isPngProjectFile(
        file
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
        (a, b) =>
            Number(
                a?.sort_order ||
                0
            ) -
            Number(
                b?.sort_order ||
                0
            )
    )

    const previousById =
        new Map(
            previous
                .filter(item =>
                    isPersistedFolderId(
                        item?.id
                    )
                )
                .map(item => [
                    String(
                        item.id
                    ),
                    item
                ])
        )

    const normalized =
        source.map(
            (
                item,
                index
            ) => {
                const previousItem =
                    previousById.get(
                        String(
                            item.id
                        )
                    ) ||
                    previous[
                        index
                    ] ||
                    null

                return {
                    ...item,
                    type: 'folder',
                    client_key:
                        previousItem?.client_key ||
                        item.client_key ||
                        String(
                            item.id
                        ),
                    parent_client_key:
                        item.parent_id !== null &&
                        item.parent_id !== undefined
                            ? String(
                                item.parent_id
                            )
                            : null,
                    client_visible:
                        item.client_visible ??
                        previousItem?.client_visible ??
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
                    String(
                        item.client_key
                    )
                ]
            )
        )

    return normalized.map(
        item => ({
            ...item,
            parent_client_key:
                item.parent_id !==
                    null &&
                item.parent_id !==
                    undefined
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
        Number(value)

    return (
        Number.isInteger(
            numeric
        ) &&
        numeric > 0
    )
}


function imagePickerStructureItems() {
    return (
        imagePickerItems.value ||
        []
    ).filter(
        item =>
            !item?.__uploaded_file
    )
}


function imagePickerFoldersPayloadForSave() {
    const items =
        imagePickerStructureItems().map(
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
            items
                .filter(item =>
                    item.id !== null
                )
                .map(item => [
                    String(
                        item.id
                    ),
                    String(
                        item.client_key
                    )
                ])
        )

    return items.map(
        item => ({
            ...item,
            parent_client_key:
                item.parent_id !==
                    null &&
                item.parent_id !==
                    undefined
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


async function persistImagePickerStructure() {
    if (!props.projectId) {
        return
    }

    const response =
        await api.put(
            `/projects/${props.projectId}/structure`,
            {
                folders:
                    imagePickerFoldersPayloadForSave()
            }
        )

    const uploadedFiles =
        (
            imagePickerItems.value ||
            []
        ).filter(
            item =>
                item?.__uploaded_file
        )

    const structureItems =
        normalizeProjectFolders(
            response.data?.folders ||
            [],
            imagePickerStructureItems()
        )

    imagePickerItems.value = [
        ...structureItems,
        ...uploadedFiles
    ]
}


function normalizePickerFile(
    file
) {
    return {
        id:
            `project-file-${file.id}`,
        client_key:
            `project-file-${file.id}`,
        type: 'file',
        resource_type: 'file',
        name:
            imagePickerFileName(
                file
            ),
        parent_id:
            file.folder_id ??
            null,
        parent_client_key:
            file.folder_id
                ? String(
                    file.folder_id
                )
                : null,
        mime_type:
            file.mime_type ||
            '',
        open_url:
            file.open_url ||
            '',
        download_url:
            file.download_url ||
            '',
        extension:
            file.extension ||
            '',
        size: Number(
            file.size ||
            0
        ),
        __uploaded_file: true
    }
}


async function loadImagePickerItems() {
    if (
        !props.projectId
    ) {
        imagePickerItems.value = []
        return
    }

    imagePickerLoading.value =
        true
    imagePickerError.value =
        ''

    try {
        const projectResponse =
            await api.get(
                `/projects/${props.projectId}`
            )

        const projectData =
            projectResponse.data?.data ||
            {}

        const structureItems =
            normalizeProjectFolders(
                projectData?.folders ||
                [],
                imagePickerStructureItems()
            )

        const collectedFiles = []
        const queue = [null]
        const visited =
            new Set()

        while (queue.length) {
            const folderId =
                queue.shift()

            const response =
                await api.get(
                    `/projects/${props.projectId}/files`,
                    {
                        params: {
                            folder_id:
                                folderId
                        }
                    }
                )

            const files =
                Array.isArray(
                    response.data?.files
                )
                    ? response.data.files
                    : []

            const folders =
                Array.isArray(
                    response.data?.folders
                )
                    ? response.data.folders
                    : []

            folders.forEach(
                folder => {
                    const id =
                        Number(
                            folder?.id
                        )

                    if (
                        !Number.isFinite(
                            id
                        ) ||
                        visited.has(id)
                    ) {
                        return
                    }

                    visited.add(id)
                    queue.push(id)
                }
            )

            files
                .filter(
                    pickerAcceptsProjectFile
                )
                .forEach(
                    file => {
                        collectedFiles.push(
                            normalizePickerFile(
                                file
                            )
                        )
                    }
                )
        }

        imagePickerItems.value = [
            ...structureItems,
            ...collectedFiles
        ]
    } catch (exception) {
        imagePickerError.value =
            errorMessage(
                exception
            )
    } finally {
        imagePickerLoading.value =
            false
    }
}


function closeImagePickerModal() {
    showImagePickerModal.value =
        false
    sliderPickerContext.value =
        null
    imageNodePickerContext.value =
        false
    imageNodePickerPos.value =
        null
    imagePickerError.value =
        ''
}


function handleImagePickerFolderOpen(
    folder
) {
    imagePickerCurrentFolderId.value =
        folder?.id ??
        null
}


function handleImagePickerStructureUpdate(
    value
) {
    imagePickerItems.value =
        Array.isArray(value)
            ? value
            : []
}


function openProjectFilesForSliderAdd(
    context
) {
    if (
        !props.projectId
    ) {
        return
    }

    sliderPickerContext.value = {
        ...context,
        mode: 'add',
        index: null
    }
    imageNodePickerContext.value =
        false

    imagePickerCurrentFolderId.value =
        null

    showImagePickerModal.value =
        true

    void loadImagePickerItems()
}


function openProjectFilesForSliderReplace(payload) {
    if (
        !props.projectId
    ) {
        return
    }

    sliderPickerContext.value = {
        ...payload,
        mode: 'replace',
        index: Number(
            payload?.index
        )
    }
    imageNodePickerContext.value =
        false

    imagePickerCurrentFolderId.value =
        null

    showImagePickerModal.value =
        true

    void loadImagePickerItems()
}


async function createSliderImageFromProjectFile(file) {
    const openUrl =
        String(
            file?.open_url ||
            ''
        ).trim()

    if (!openUrl) {
        throw new Error(
            'Selected file is missing an open URL.'
        )
    }

    const response =
        await fetch(openUrl)

    if (!response.ok) {
        throw new Error(
            'Failed to read the selected image file.'
        )
    }

    const blob =
        await response.blob()

    const fileName =
        imagePickerFileName(file)

    const imageFile =
        new File(
            [blob],
            fileName,
            {
                type:
                    blob.type ||
                    file.mime_type ||
                    'image/*'
            }
        )

    const preview =
        URL.createObjectURL(imageFile)

    return {
        path: '',
        existing_path: '',
        src: preview,
        preview,
        description: '',
        description_sk: '',
        alt: '',
        alt_sk: '',
        caption: '',
        file: imageFile
    }
}


async function handleImagePickerFileOpen(file) {
    if (!file?.open_url) {
        return
    }

    if (
        !pickerAcceptsProjectFile(file)
    ) {
        imagePickerError.value =
            imageNodePickerContext.value
                ? 'Please select an image file from Project Files.'
                : 'Please select a PNG file from Project Files.'

        return
    }

    if (
        imageNodePickerContext.value
    ) {
        try {
            const instance =
                editor.value

            if (!instance) {
                return
            }

            const imageSrc =
                String(
                    file?.open_url ||
                    ''
                ).trim()

            if (!imageSrc) {
                throw new Error(
                    'Selected file is missing an open URL.'
                )
            }

            const replacePos =
                Number.isInteger(
                    imageNodePickerPos.value
                )
                    ? Number(
                        imageNodePickerPos.value
                    )
                    : null

            if (
                replacePos !== null &&
                replacePos >= 0 &&
                instance.state.doc.nodeAt(
                    replacePos
                )?.type?.name ===
                    'image'
            ) {
                const node =
                    instance.state.doc.nodeAt(
                        replacePos
                    )

                const tr =
                    instance.state.tr.setNodeMarkup(
                        replacePos,
                        undefined,
                        {
                            ...(
                                node?.attrs ||
                                {}
                            ),
                            src:
                                imageSrc,
                            alt:
                                file?.name ||
                                'image',
                            title:
                                '',
                            width:
                                '100%',
                            pendingProjectImage:
                                false
                        }
                    )

                instance.view.dispatch(
                    tr.scrollIntoView()
                )
            } else {
                instance
                    .chain()
                    .focus()
                    .setImage({
                        src:
                            imageSrc,
                        alt:
                            file?.name ||
                            'image',
                        title:
                            '',
                        width:
                            '100%',
                        pendingProjectImage:
                            false
                    })
                    .run()
            }

            closeImagePickerModal()
            markDirty()
        } catch (exception) {
            imagePickerError.value =
                exception instanceof Error
                    ? exception.message
                    : 'Could not attach image from project files.'
        }

        return
    }

    const context =
        sliderPickerContext.value

    if (!context) {
        return
    }

    try {
        const sliderImage =
            await createSliderImageFromProjectFile(file)

        const currentImages =
            context.getCurrentImages?.() ||
            []

        const nextImages =
            Array.isArray(
                currentImages
            )
                ? [...currentImages]
                : []

        if (
            context.mode ===
                'replace' &&
            Number.isInteger(
                context.index
            ) &&
            context.index >= 0 &&
            context.index <
                nextImages.length
        ) {
            nextImages[
                context.index
            ] = {
                ...nextImages[
                    context.index
                ],
                ...sliderImage
            }
        } else {
            nextImages.push(
                sliderImage
            )
        }

        context.setImages?.(
            nextImages
        )

        closeImagePickerModal()

        markDirty()
    } catch (exception) {
        imagePickerError.value =
            exception instanceof Error
                ? exception.message
                : 'Could not attach image from project files.'
    }
}


async function handleImagePickerUpload(
    payload = {}
) {
    if (
        !props.projectId
    ) {
        return
    }

    const files = Array.from(
        payload?.files ||
        []
    ).filter(
        pickerAcceptsProjectFile
    )

    if (!files.length) {
        imagePickerError.value =
            imageNodePickerContext.value
                ? 'Please upload at least one image file.'
                : 'Please upload at least one PNG file.'
        return
    }

    const parent =
        payload?.parent ||
        null

    let folderId =
        null

    if (
        isPersistedFolderId(
            payload?.folderId
        )
    ) {
        folderId =
            Number(
                payload.folderId
            )
    } else if (
        parent?.client_key
    ) {
        const match =
            imagePickerStructureItems().find(
                item =>
                    String(
                        item.client_key
                    ) ===
                    String(
                        parent.client_key
                    )
            )

        if (
            isPersistedFolderId(
                match?.id
            )
        ) {
            folderId =
                Number(
                    match.id
                )
        }
    }

    if (
        payload?.folderId &&
        !folderId
    ) {
        await persistImagePickerStructure()

        const refreshed =
            imagePickerStructureItems().find(
                item =>
                    String(
                        item.client_key
                    ) ===
                    String(
                        payload.folderId
                    )
            )

        if (
            isPersistedFolderId(
                refreshed?.id
            )
        ) {
            folderId =
                Number(
                    refreshed.id
                )
        }
    }

    imagePickerUploading.value =
        true

    try {
        const maxFilesPerRequest =
            20

        const chunks = []

        for (
            let offset = 0;
            offset < files.length;
            offset +=
                maxFilesPerRequest
        ) {
            chunks.push(
                files.slice(
                    offset,
                    offset +
                        maxFilesPerRequest
                )
            )
        }

        for (
            let chunkIndex = 0;
            chunkIndex < chunks.length;
            chunkIndex += 1
        ) {
            const chunk =
                chunks[
                    chunkIndex
                ]

            const chunkOffset =
                chunkIndex *
                maxFilesPerRequest

            const body =
                new FormData()

            chunk.forEach(
                (
                    file,
                    index
                ) => {
                    const sourceIndex =
                        chunkOffset +
                        index

                    const relativePath =
                        String(
                            payload
                                ?.relativePaths?.[
                                sourceIndex
                            ] ||
                            file.name ||
                            `image-${sourceIndex + 1}.png`
                        )

                    body.append(
                        `files[${index}]`,
                        file
                    )

                    body.append(
                        `relative_paths[${index}]`,
                        relativePath
                    )
                }
            )

            if (folderId) {
                body.append(
                    'folder_id',
                    String(
                        folderId
                    )
                )
            }

            body.append(
                'client_visible',
                '1'
            )

            await api.post(
                `/projects/${props.projectId}/files`,
                body
            )
        }

        await loadImagePickerItems()
    } catch (exception) {
        imagePickerError.value =
            errorMessage(
                exception
            )
    } finally {
        imagePickerUploading.value =
            false
    }
}


function handleLinkButton() {
    openLinkModal()
}


function normalizeLinkUrl(value) {
    const raw =
        String(
            value || ''
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
        /^[a-z][a-z\d+.-]*:/i.test(raw)
    ) {
        return raw
    }

    return `https://${raw}`
}


function openLinkModal() {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    savedLinkSelection.value =
        instance.state.selection

    linkHref.value =
        String(
            instance
                .getAttributes(
                    'link'
                )
                ?.href ||
                ''
        )

    linkError.value =
        ''

    showLinkModal.value =
        true
}


function closeLinkModal() {
    showLinkModal.value =
        false

    linkError.value =
        ''
}


function applyLinkFromModal() {
    const instance =
        editor.value

    if (!instance) {
        return
    }

    const href =
        normalizeLinkUrl(
            linkHref.value
        )

    if (!href) {
        linkError.value =
            'Please enter a valid URL.'

        return
    }

    linkError.value =
        ''

    const selection =
        savedLinkSelection.value ||
        instance.state.selection

    instance
        .chain()
        .focus()
        .setTextSelection(
            selection
        )
        .extendMarkRange(
            'link'
        )
        .setLink({
            href
        })
        .run()

    closeLinkModal()
    markDirty()
}


function handleCommandMenuOutsideClick(event) {
    if (
        !commandMenu.value.open ||
        !commandMenuRoot.value ||
        !event.target ||
        !(event.target instanceof Node)
    ) {
        return
    }

    if (
        commandMenuRoot.value.contains(
            event.target
        )
    ) {
        return
    }

    closeMenus()
}


onMounted(() => {
    document.addEventListener(
        'mousedown',
        handleCommandMenuOutsideClick
    )

    window.addEventListener(
        'scroll',
        handleViewportReposition,
        true
    )

    window.addEventListener(
        'resize',
        handleViewportReposition
    )

    nextTick(() => {
        updateBlockToolsUI()
    })
})



function shouldShowTextBubbleMenu({
    editor
}) {
    if (
        !editor ||
        !editor.isEditable
    ) {
        return false
    }

    const {
        selection
    } = editor.state

    /*
     * NodeSelection is used by images, sliders,
     * info blocks and other building elements.
     * Never show the text BubbleMenu for those.
     */
    if (
        selection instanceof NodeSelection
    ) {
        return false
    }

    if (
        selection.empty
    ) {
        return false
    }

    /*
     * BubbleMenu is only for actual text
     * selections inside textblocks.
     */
    const fromParent =
        selection.$from?.parent

    const toParent =
        selection.$to?.parent

    if (
        !fromParent?.isTextblock ||
        !toParent?.isTextblock
    ) {
        return false
    }

    /*
     * Do not show the text toolbar while the
     * selection belongs to a non-text building
     * element such as an image, slider or info block.
     */
    const nonTextNodeNames = new Set([
        'image',
        'slider',
        'info',
        'horizontalRule'
    ])

    for (
        let depth = selection.$from.depth;
        depth > 0;
        depth -= 1
    ) {
        const node =
            selection.$from.node(depth)

        if (
            nonTextNodeNames.has(
                node.type.name
            )
        ) {
            return false
        }
    }

    for (
        let depth = selection.$to.depth;
        depth > 0;
        depth -= 1
    ) {
        const node =
            selection.$to.node(depth)

        if (
            nonTextNodeNames.has(
                node.type.name
            )
        ) {
            return false
        }
    }

    return true
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
            openOnClick: true,
            linkOnPaste: true
        }),

        ResizableImage.configure({
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

        Slider.configure({
            onRequestProjectImageAdd:
                openProjectFilesForSliderAdd,
            onRequestProjectImageReplace:
                openProjectFilesForSliderReplace
        }),

        InfoBlock,

        TableRow,
        TableHeader,
        TableCell
    ],

    editorProps: {
        attributes: {
            class:
                'document-editor-content'
        },

        handleClickOn(
            view,
            _pos,
            node,
            nodePos
        ) {
            if (
                node?.type?.name !==
                    'image'
            ) {
                return false
            }

            const src =
                String(
                    node?.attrs?.src ||
                    ''
                ).trim()

            /*
             * A pending image is only a picker
             * placeholder. Clicking it opens the
             * project file picker.
             */
            if (
                props.editable &&
                (
                    !src ||
                    node?.attrs
                        ?.pendingProjectImage
                )
            ) {
                openProjectFilesForImageNode({
                    replacePos:
                        nodePos
                })

                return true
            }

            /*
             * A real image behaves like every other
             * top-level building element: it receives
             * a real ProseMirror NodeSelection.
             *
             * The block-selection system then adds
             * the single normal editor outline.
             */
            try {
                const selection =
                    NodeSelection.create(
                        view.state.doc,
                        nodePos
                    )

                view.dispatch(
                    view.state.tr.setSelection(
                        selection
                    )
                )

                view.focus()

                return true
            } catch {
                return false
            }
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

        nextTick(() => {
            updateBlockToolsUI()
        })
    },

    onSelectionUpdate() {
        syncSlashMenu()
        updateSelectionUI()

        nextTick(() => {
            updateBlockToolsUI()
        })
    },

    onFocus() {
        updateSelectionUI()

        nextTick(() => {
            updateBlockToolsUI()
        })
    },

    onBlur() {
        if (
            commandMenu.value
                .mode ===
            'slash'
        ) {
            closeMenus()
        }

        /*
         * Give clicks on the block toolbar / resize handle a
         * chance to run before clearing the active block.
         * A real click somewhere else will remove the active
         * NodeSelection and updateBlockToolsUI() on the next
         * selection change.
         */
        window.setTimeout(() => {
            const activeElement =
                document.activeElement

            const editorRoot =
                editor.value?.view?.dom

            if (
                activeElement instanceof Node &&
                editorRoot instanceof HTMLElement &&
                editorRoot.contains(
                    activeElement
                )
            ) {
                return
            }

            if (
                commandMenuRoot.value?.contains(
                    activeElement
                )
            ) {
                return
            }

            blockTools.value.visible =
                false

            clearActiveBlockVisuals()
        }, 0)
    }
})


watch(
    () => props.editable,
    value => {
        editor.value?.setEditable(
            Boolean(value)
        )

        if (!editor.value) {
            return
        }

        editor.value
            .chain()
            .focus()
            .updateAttributes(
                'slider',
                {
                    editable:
                        Boolean(value)
                }
            )
            .updateAttributes(
                'info',
                {
                    editable:
                        Boolean(value)
                }
            )
            .run()

        nextTick(() => {
            updateBlockToolsUI()
        })
    }
)


watch(
    () => props.language,
    value => {
        if (!editor.value) {
            return
        }

        editor.value
            .chain()
            .focus()
            .updateAttributes(
                'slider',
                {
                    language: String(
                        value ||
                        'en'
                    )
                }
            )
            .run()
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
    document.removeEventListener(
        'mousedown',
        handleCommandMenuOutsideClick
    )

    window.removeEventListener(
        'scroll',
        handleViewportReposition,
        true
    )

    window.removeEventListener(
        'resize',
        handleViewportReposition
    )

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
                -top-10
                z-30
                flex
                h-16
                shrink-0
                items-center
                justify-between
                bg-accent
                px-5
                -mx-10
                -mt-10
                text-light
            "
        >

            <div class="flex gap-10">
                <button
                    type="button"
                    class="
                        p
                        hover:text-dark
                    "
                    @click="
                        handleBack
                    "
                >
                    <i class="bi bi-arrow-left" />
                </button>

                <template
                    v-if="
                        editable
                    "
                >
                    <div class="flex gap-4">
                        <button
                            type="button"
                            class="
                                p
                                hover:text-dark
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
                           <i class="bi bi-arrow-counterclockwise" />
                        </button>


                        <button
                            type="button"
                            class="
                                p
                                hover:text-dark

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
                            <i class="bi bi-arrow-clockwise" />
                        </button>
                    </div>

                    <div class="flex gap-4">
                        <button
                            type="button"
                            class="
                                p
                                font-bold
                                hover:text-dark
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
                                p
                                italic
                                hover:text-dark
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
                                p
                                underline
                                hover:text-dark
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
                                p
                                hover:text-dark
                            "
                            title="Add table row"
                            @click="
                                runTableCommand(
                                    'add-row'
                                )
                            "
                        >
                            <i class="bi bi-row-text" />
                        </button>

                        <button
                            type="button"
                            class="
                                p
                                hover:text-dark
                            "
                            title="Remove table row"
                            @click="
                                runTableCommand(
                                    'remove-row'
                                )
                            "
                        >
                            <i class="bi bi-dash-square" />
                        </button>

                        <button
                            type="button"
                            class="
                                p
                                hover:text-dark
                            "
                            title="Add table column"
                            @click="
                                runTableCommand(
                                    'add-column'
                                )
                            "
                        >
                            <i class="bi bi-layout-three-columns" />
                        </button>

                        <button
                            type="button"
                            class="
                                p
                                hover:text-dark
                            "
                            title="Remove table column"
                            @click="
                                runTableCommand(
                                    'remove-column'
                                )
                            "
                        >
                            <i class="bi bi-layout-sidebar" />
                        </button>
                    </div>
                </template>
            </div>


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
                    text-xs
                    font-bold
                    uppercase
                    tracking-[0.14em]
                    text-light
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
                <slot name="header-actions" />

                <div
                    v-if="
                        showSignatureStatus &&
                        requiresSignature
                    "
                    class="
                        px-3
                        py-1
                        font-mono
                        text-[10px]
                        font-bold
                        uppercase
                        tracking-[0.12em]

                    "
                    :class="
                        signatureStatusClass
                    "
                >
                    {{
                        signatureStatusLabel
                    }}
                </div>
            </div>

        </header>


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
                <!-- Editor -->
                <div
                    class="
                        relative
                    "
                >
                    <!-- Contextual plus -->
                    <div
                        v-if="
                            insertHandle?.visible &&
                            insertHandle?.coords &&
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
                                bg-accent
                                text-light
                                leading-none
                                transition-all
                                hover:border-accent
                                hover:bg-light
                                hover:text-accent
                                border
                                border-accent
                            "
                            aria-label="Insert block"
                            @mousedown.prevent
                            @click="
                                openCommandMenu(
                                    'plus'
                                )
                            "
                        >
                            <i class="bi bi-plus-lg" />
                        </button>
                    </div>


                    <EditorContent
                        :editor="
                            editor
                        "
                        :class="[
                            'article-editor',
                            editable
                                ? 'article-editor--editable'
                                : ''
                        ]"
                    />

                    <div
                        v-if="
                            editable &&
                            blockTools?.visible
                        "
                        class="
                            fixed
                            z-40
                            flex
                            items-center
                            gap-1
                            border
                            border-accent
                            bg-light
                            p-1
                        "
                        :style="{
                            top:
                                `${blockTools.top}px`,
                            left:
                                `${blockTools.left}px`
                        }"
                        @mousedown.prevent.stop
                    >
                        <button
                            type="button"
                            class="
                                grid
                                h-7
                                w-7
                                place-items-center
                                text-dark
                                transition-colors
                                hover:bg-accent
                                hover:text-light
                            "
                            title="Move up"
                            @mousedown.prevent
                            @click="
                                moveActiveBlock(
                                    'up'
                                )
                            "
                        >
                            <i class="bi bi-arrow-up" />
                        </button>

                        <button
                            type="button"
                            class="
                                grid
                                h-7
                                w-7
                                place-items-center
                                text-dark
                                transition-colors
                                hover:bg-accent
                                hover:text-light
                            "
                            title="Move down"
                            @mousedown.prevent
                            @click="
                                moveActiveBlock(
                                    'down'
                                )
                            "
                        >
                            <i class="bi bi-arrow-down" />
                        </button>

                        <button
                            type="button"
                            class="
                                grid
                                h-7
                                w-7
                                place-items-center
                                text-dark
                                transition-colors
                                hover:bg-accent
                                hover:text-light
                            "
                            title="Duplicate block"
                            @mousedown.prevent
                            @click="
                                duplicateActiveBlock
                            "
                        >
                            <i class="bi bi-files" />
                        </button>

                        <button
                            type="button"
                            class="
                                grid
                                h-7
                                w-7
                                place-items-center
                                text-dark
                                transition-colors
                                hover:bg-accent
                                hover:text-light
                            "
                            title="Delete block"
                            @mousedown.prevent
                            @click="
                                deleteActiveBlock
                            "
                        >
                            <i class="bi bi-eraser" />
                        </button>
                    </div>
                </div>
            </article>
        </main>


        <!-- Text selection menu -->
        <BubbleMenu
            v-if="
                editor &&
                editable
            "
            :editor="
                editor
            "
            :should-show="
                shouldShowTextBubbleMenu
            "
            :tippy-options="{
                duration: 120,
                placement: 'top',
                interactive: true,
                hideOnClick: true,
                onClickOutside: (instance, event) => {
                    const editorEl =
                        editor?.view?.dom

                    if (
                        event.target instanceof Node &&
                        editorEl &&
                        editorEl.contains(event.target)
                    ) {
                        return
                    }

                    instance.hide()
                }
            }"
        >
            <div
                class="
                    flex
                    items-center
                    border
                    border-accent
                    bg-light
                "
            >
                <button
                    type="button"
                    class="
                        p
                        font-bold
                        px-4
                        py-2
                        hover:text-light
                        hover:bg-accent
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
                        p
                        italic
                        px-4
                        py-2
                        hover:text-light
                        hover:bg-accent
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
                        p
                        underline
                        px-4
                        py-2
                        hover:text-light
                        hover:bg-accent
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
                        p
                        px-4
                        py-2
                        hover:text-light
                        hover:bg-accent
                    "
                    @click="
                        handleLinkButton
                    "
                >
                    Link
                </button>

                <template
                    v-if="
                        editor
                            .isActive(
                                'table'
                            )
                    "
                >
                    <button
                        type="button"
                        class="
                            p
                            px-3
                            py-2
                            hover:text-light
                            hover:bg-accent
                        "
                        title="Add row"
                        @click="
                            runTableCommand(
                                'add-row'
                            )
                        "
                    >
                        +R
                    </button>

                    <button
                        type="button"
                        class="
                            p
                            px-3
                            py-2
                            hover:text-light
                            hover:bg-accent
                        "
                        title="Remove row"
                        @click="
                            runTableCommand(
                                'remove-row'
                            )
                        "
                    >
                        -R
                    </button>

                    <button
                        type="button"
                        class="
                            p
                            px-3
                            py-2
                            hover:text-light
                            hover:bg-accent
                        "
                        title="Add column"
                        @click="
                            runTableCommand(
                                'add-column'
                            )
                        "
                    >
                        +C
                    </button>

                    <button
                        type="button"
                        class="
                            p
                            px-3
                            py-2
                            hover:text-light
                            hover:bg-accent
                        "
                        title="Remove column"
                        @click="
                            runTableCommand(
                                'remove-column'
                            )
                        "
                    >
                        -C
                    </button>
                </template>
            </div>
        </BubbleMenu>


        <!-- Command menu -->
        <div
            v-if="
                commandMenu.open &&
                commandMenu.coords
            "
            ref="commandMenuRoot"
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
                    border-accent
                    bg-light
                "
            >
                <div
                    class="
                        max-h-80
                        overflow-y-auto
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
                            group
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
                                h3
                            "
                        >
                            {{
                                command.label
                            }}
                        </span>

                        <span
                            class="
                                p
                                transition-colors
                                group-hover:text-light
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
        <AdminModal
            :open="
                showLinkModal
            "
            title="Add link"
            subtitle="Enter the URL for the selected text."
            max-width-class="max-w-xl"
            @close="
                closeLinkModal
            "
        >
            <div class="space-y-4">
                <FormField
                    id="document-link-href"
                    type="text"
                    label="Link URL"
                    placeholder="https://example.com"
                    :model-value="linkHref"
                    :error="linkError"
                    @update:model-value="
                        value => {
                            linkHref = String(
                                value ||
                                ''
                            )
                        }
                    "
                />
            </div>

            <template #footer>
                <div
                    class="
                        border-t
                        border-accent
                        p-6
                        flex
                        justify-end
                        gap-3
                    "
                >
                    <Button
                        type="button"
                        text="cancel"
                        align="right"
                        @click="
                            closeLinkModal
                        "
                    />

                    <Button
                        type="button"
                        text="add link"
                        variant="accent"
                        align="right"
                        @click="
                            applyLinkFromModal
                        "
                    />
                </div>
            </template>
        </AdminModal>


        <ProjectFilePickerModal
            v-model:open="
                showImagePickerModal
            "
            :model-value="
                imagePickerItems
            "
            :initial-folder-id="
                imagePickerCurrentFolderId
            "
            :loading="
                imagePickerLoading
            "
            :error="
                imagePickerError
            "
            :title="
                imagePickerTitle
            "
            :subtitle="
                imagePickerSubtitle
            "
            :allow-upload-control="
                true
            "
            :allow-metadata-editing="
                true
            "
            :prevent-deleting-required="
                true
            "
            :disabled="
                imagePickerUploading
            "
            @close="
                closeImagePickerModal
            "
            @update:model-value="
                handleImagePickerStructureUpdate
            "
            @open-folder="
                handleImagePickerFolderOpen
            "
            @open-file="
                handleImagePickerFileOpen
            "
            @upload-files="
                handleImagePickerUpload
            "
        />
    </div>
</template>


<style scoped>
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

/* =========================================================
   EDITOR
   ========================================================= */

.article-editor :deep(.ProseMirror) {
    min-height: 60vh;
    color: rgb(23 23 23);
    font-family: 'Inter', sans-serif;
    font-weight: 300;
    font-size: 1.08rem;
    line-height: 1.4;
    outline: none;
}

.article-editor :deep(.ProseMirror > * + *) {
    margin-top: 2rem;
}

.article-editor :deep(.ProseMirror > *) {
    position: relative;
}

/* =========================================================
   TIGHTER CONTENT SPACING
   ========================================================= */

.article-editor :deep(.ProseMirror h1 + p),
.article-editor :deep(.ProseMirror h2 + p),
.article-editor :deep(.ProseMirror h3 + p),
.article-editor :deep(.ProseMirror h1 + ul),
.article-editor :deep(.ProseMirror h2 + ul),
.article-editor :deep(.ProseMirror h3 + ul),
.article-editor :deep(.ProseMirror h1 + ol),
.article-editor :deep(.ProseMirror h2 + ol),
.article-editor :deep(.ProseMirror h3 + ol),
.article-editor :deep(.ProseMirror h1 + ul[data-type='taskList']),
.article-editor :deep(.ProseMirror h2 + ul[data-type='taskList']),
.article-editor :deep(.ProseMirror h3 + ul[data-type='taskList']),
.article-editor :deep(.ProseMirror p + ul),
.article-editor :deep(.ProseMirror p + ol),
.article-editor :deep(.ProseMirror p + p),
.article-editor :deep(.ProseMirror p + ul[data-type='taskList']),
.article-editor :deep(.ProseMirror ul + p),
.article-editor :deep(.ProseMirror ol + p),
.article-editor :deep(.ProseMirror ul[data-type='taskList'] + p) {
    margin-top: 0.55rem;
}

.article-editor :deep(.ProseMirror p) {
    margin: 0;
}

/* =========================================================
   HEADINGS
   ========================================================= */

.article-editor :deep(.ProseMirror h1),
.article-editor :deep(.ProseMirror h2),
.article-editor :deep(.ProseMirror h3) {
    margin: 2.2rem 0 0.9rem;
    color: var(--color-accent);
    font-family: 'Space Mono', monospace;
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

/* =========================================================
   BLOCKQUOTE
   ========================================================= */

.article-editor :deep(.ProseMirror blockquote) {
    margin: 2rem 0;
    border-left: 3px solid rgb(19 62 180);
    background: rgb(19 62 180 / 0.05);
    padding: 0.8rem 1rem 0.8rem 1.2rem;
    color: rgb(23 23 23 / 0.68);
    font-size: 1.08rem;
    font-style: italic;
}

/* =========================================================
   HORIZONTAL RULE
   ========================================================= */

.article-editor :deep(.ProseMirror hr) {
    margin: 3rem 0;
    border: 0;
    border-top: 1px solid var(--color-accent);
}

/* =========================================================
   LISTS
   ========================================================= */

.article-editor :deep(.ProseMirror ul),
.article-editor :deep(.ProseMirror ol) {
    margin: 1.1rem 0;
    padding-left: 1.75rem;
}

.article-editor :deep(.ProseMirror ul) {
    list-style-type: disc;
}

.article-editor :deep(.ProseMirror ol) {
    list-style-type: decimal;
}

.article-editor :deep(
    .ProseMirror li:not([data-type='taskItem'])
) {
    display: list-item;
    padding-left: 0.25rem;
}

.article-editor :deep(
    .ProseMirror li:not([data-type='taskItem'])
        + li:not([data-type='taskItem'])
) {
    margin-top: 0.12rem;
}

/* =========================================================
   IMAGE
   ========================================================= */

/*
 * The image is rendered by ResizableImageNodeView as a
 * top-level NodeViewWrapper. Its width is controlled by the
 * node's width attribute.
 *
 * The initial width is 100%, matching the full-width slider
 * stage used by the document editor.
 *
 * Do not add an outline here. The generic top-level block
 * selection above supplies the one and only outline.
 */

.article-editor :deep(
    .ProseMirror .document-image-node
) {
    display: block;
    width: 100%;
    max-width: 100%;
    margin: 2rem auto;
}

.article-editor :deep(
    .ProseMirror .document-image-node img
) {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    max-height: 720px;
    margin: 0;
    object-fit: contain;
}

/*
 * Pending image placeholder.
 */

.article-editor :deep(
    .ProseMirror .document-image-node
        img[pendingprojectimage='true']
) {
    min-height: 140px;
    background:
        repeating-linear-gradient(
            -45deg,
            rgb(19 62 180 / 0.06),
            rgb(19 62 180 / 0.06) 10px,
            transparent 10px,
            transparent 20px
        );
    cursor: pointer;
}

/* =========================================================
   LINKS
   ========================================================= */

.article-editor :deep(.ProseMirror a) {
    color: var(--color-accent);
    text-decoration: underline;
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;
}

/* =========================================================
   TABLE
   ========================================================= */

.article-editor :deep(.ProseMirror table) {
    width: 100%;
    margin: 2rem 0;
    table-layout: fixed;
    border-collapse: collapse;
    display: table;
}

.article-editor :deep(.ProseMirror td),
.article-editor :deep(.ProseMirror th) {
    width: 33.33%;
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

/* =========================================================
   TASK LIST / CHECKBOX
   ========================================================= */

.article-editor :deep(
    .ProseMirror ul[data-type='taskList']
) {
    list-style: none !important;
    margin: 1rem 0 !important;
    padding: 0 !important;
}

.article-editor :deep(
    .ProseMirror ul[data-type='taskList'] li
) {
    display: grid !important;
    grid-template-columns: 1rem minmax(0, 1fr) !important;
    align-items: center !important;
    column-gap: 0.32rem !important;
    row-gap: 0 !important;
    list-style: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    padding-left: 0 !important;
}

/* Space between checklist items */

.article-editor :deep(
    .ProseMirror ul[data-type='taskList'] li + li
) {
    margin-top: 0.16rem !important;
}

/* Checkbox wrapper */

.article-editor :deep(
    .ProseMirror ul[data-type='taskList'] li > label
) {
    display: inline-flex !important;
    grid-column: 1 !important;
    flex: 0 0 auto !important;
    width: 1rem !important;
    min-width: 1rem !important;
    height: auto !important;

    align-items: center !important;
    justify-content: center !important;
    align-self: center !important;

    margin: 0 !important;
    padding: 0 !important;

    line-height: 1 !important;
}

/* Actual checkbox */

.article-editor :deep(
    .ProseMirror
        ul[data-type='taskList']
        li
        > label
        > input[type='checkbox']
) {
    display: block !important;

    width: 1rem !important;
    height: 1rem !important;

    margin: 0 !important;
    padding: 0 !important;

    flex: 0 0 auto !important;

    accent-color: var(--color-accent);
}

/* Text wrapper */

.article-editor :deep(
    .ProseMirror ul[data-type='taskList'] li > div
) {
    display: block !important;
    grid-column: 2 !important;

    flex: 1 1 auto !important;
    width: auto !important;
    min-width: 0 !important;

    margin: 0 !important;
    padding: 0 !important;
}

/* Text paragraph */

.article-editor :deep(
    .ProseMirror
        ul[data-type='taskList']
        li
        > div
        > p
) {
    display: block !important;

    width: auto !important;

    margin: 0 !important;
    padding: 0 !important;

    line-height: inherit !important;
}

/* =========================================================
   PLACEHOLDERS
   ========================================================= */

.article-editor :deep(
    .ProseMirror p.is-editor-empty:first-child::before
) {
    float: left;
    height: 0;
    color: rgb(23 23 23 / 0.25);
    content: attr(data-placeholder);
    pointer-events: none;
}

.article-editor :deep(
    .ProseMirror h1.is-empty::before
),
.article-editor :deep(
    .ProseMirror h2.is-empty::before
),
.article-editor :deep(
    .ProseMirror h3.is-empty::before
) {
    float: left;
    height: 0;
    color: rgb(23 23 23 / 0.2);
    content: attr(data-placeholder);
    pointer-events: none;
}

.article-editor :deep(.ProseMirror:focus) {
    outline: none;
}

/* =========================================================
   GENERIC BLOCK SELECTION
   ========================================================= */

/*
 * Every top-level building element uses the same selection
 * system. There is exactly ONE visual outline:
 *
 *     outline
 *
 * Do not add an inset box-shadow here. ProseMirror's
 * .ProseMirror-selectednode is also explicitly neutralized
 * below so NodeViews cannot create a second outline.
 */

.article-editor--editable :deep(.ProseMirror > *) {
    outline: 1px solid transparent;
    outline-offset: 5px;
    transition:
        outline-color 140ms ease;
}

.article-editor--editable :deep(
    .ProseMirror > *:hover
) {
    outline-color:
        rgb(19 62 180 / 0.18);
}

.article-editor--editable :deep(
    .ProseMirror > *.is-editor-active-block
) {
    outline-color:
        rgb(19 62 180 / 0.95);
}

/*
 * The active block class is the only active outline.
 * Tiptap/ProseMirror adds this class to selected NodeViews.
 * It must not create another visual selection.
 */

.article-editor :deep(
    .ProseMirror > .ProseMirror-selectednode
) {
    outline-color: transparent !important;
    box-shadow: none !important;
}

/*
 * The block-selection system still wins over the neutralized
 * ProseMirror node selection.
 */

.article-editor--editable :deep(
    .ProseMirror > .ProseMirror-selectednode.is-editor-active-block
) {
    outline-color:
        rgb(19 62 180 / 0.95) !important;
}

/*
 * Drop targets use the same outline language instead of
 * another inset border.
 */

.article-editor--editable :deep(
    .ProseMirror > *.is-editor-drop-target
) {
    outline-color:
        rgb(19 62 180 / 0.35);
}

/*
 * ResizableImage owns the resize handle, but NOT the outline.
 * Its NodeViewWrapper participates in the same generic block
 * selection system as every other top-level element.
 */

.article-editor :deep(
    .ProseMirror .document-image-node
) {
    position: relative;
}

/* =========================================================
   CUSTOM BLOCKS
   ========================================================= */

.article-editor :deep(
    .ProseMirror .document-custom-block
) {
    margin: 2rem 0;
}

.article-editor :deep(
    .ProseMirror .document-custom-block-toolbar
) {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 0.45rem;
    transition: opacity 160ms ease;
}

.article-editor :deep(
    .ProseMirror .document-custom-block-label
) {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.5rem;
    border: 1px solid rgb(19 62 180 / 0.25);
    background: rgb(19 62 180 / 0.06);
    color: rgb(19 62 180);
    font-family: monospace;
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.article-editor :deep(
    .ProseMirror
        .ProseMirror-selectednode
        .document-custom-block
) {
    box-shadow:
        0 0 0 1px
        rgb(19 62 180 / 0.35);
}

/* =========================================================
   MOBILE
   ========================================================= */

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

    /*
     * Images must still stay inside the editor on small screens.
     */

    .article-editor :deep(.ProseMirror > img) {
        max-width: 100%;
        height: auto;
    }
}
</style>