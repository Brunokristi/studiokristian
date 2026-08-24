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

const DocumentLink = Link.extend({
    addAttributes() {
        return {
            ...this.parent?.(),

            description: {
                default: null,
                parseHTML: element => {
                    const value =
                        element.getAttribute(
                            'data-link-description'
                        )

                    return value || null
                },
                renderHTML: attributes => {
                    if (!attributes.description) {
                        return {}
                    }

                    return {
                        'data-link-description':
                            String(
                                attributes.description
                            )
                    }
                }
            }
        }
    }
})


const TextAlign = Extension.create({
    name: 'textAlign',

    addGlobalAttributes() {
        return [
            {
                types: [
                    'paragraph',
                    'heading'
                ],
                attributes: {
                    textAlign: {
                        default: null,
                        parseHTML: element =>
                            element.style.textAlign ||
                            element.getAttribute(
                                'data-text-align'
                            ) ||
                            null,
                        renderHTML: attributes => {
                            if (!attributes.textAlign) {
                                return {}
                            }

                            return {
                                style:
                                    `text-align: ${attributes.textAlign}`,
                                'data-text-align':
                                    attributes.textAlign
                            }
                        }
                    }
                }
            }
        ]
    },

    addCommands() {
        return {
            setTextAlign: alignment => ({
                state,
                dispatch
            }) => {
                const {
                    tr,
                    selection,
                    schema
                } = state

                const allowedTypes = [
                    schema.nodes.paragraph,
                    schema.nodes.heading
                ].filter(Boolean)

                const positions = []

                if (selection.empty) {
                    for (
                        let depth = selection.$from.depth;
                        depth > 0;
                        depth -= 1
                    ) {
                        const node =
                            selection.$from.node(depth)

                        if (
                            allowedTypes.includes(
                                node.type
                            )
                        ) {
                            positions.push({
                                node,
                                position:
                                    selection.$from.before(
                                        depth
                                    )
                            })

                            break
                        }
                    }
                } else {
                    state.doc.nodesBetween(
                        selection.from,
                        selection.to,
                        (node, position) => {
                            if (
                                allowedTypes.includes(
                                    node.type
                                )
                            ) {
                                positions.push({
                                    node,
                                    position
                                })
                            }
                        }
                    )
                }

                positions.forEach(
                    ({ node, position }) => {
                        tr.setNodeMarkup(
                            position,
                            undefined,
                            {
                                ...node.attrs,
                                textAlign:
                                    alignment
                            }
                        )
                    }
                )

                if (dispatch) {
                    dispatch(tr)
                }

                return true
            }
        }
    }
})

import {
    Table,
    TableRow,
    TableHeader,
    TableCell
} from '@tiptap/extension-table'
import {
    NodeSelection,
    Plugin,
    PluginKey,
    Selection,
    TextSelection
} from '@tiptap/pm/state'
import {
    Decoration,
    DecorationSet
} from '@tiptap/pm/view'
import { Extension } from '@tiptap/core'

import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import HorizontalRule from '@tiptap/extension-horizontal-rule'

import useAutosavePolicy from '../../backoffice/admin/composables/useAutosavePolicy.js'
import api, {
    errorMessage
} from '../../backoffice/admin/composables/useAdminApi.js'
import InfoBlock from '../../backoffice/components/document-editor/extensions/Info.js'
import ResizableImage from '../../backoffice/components/document-editor/extensions/ResizableImage.js'

import Modal from '@shared/components/Modal.vue'
import FilePickerModal from './FilePickerModal.vue'
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

    clientMode: {
        type: Boolean,
        default: false
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
const linkDescription = ref('')
const linkError = ref('')
const savedLinkSelection = ref(null)

const linkPreview = ref({
    visible: false,
    href: '',
    description: '',
    top: 20,
    left: 20
})

const showImagePickerModal = ref(false)
const imagePickerLoading = ref(false)
const imagePickerError = ref('')
const imagePickerItems = ref([])
const imagePickerCurrentFolderId = ref(null)
const imagePickerLoadedFolders = ref(
    new Set()
)
const imagePickerStructureLoaded = ref(false)
const imagePickerUploading = ref(false)

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

const blockToolsRoot = ref(null)

const turnIntoMenu = ref({
    visible: false
})

const tableTools = ref({
    visible: false,
    top: 0,
    left: 0
})

const tableControls = [
    {
        command: 'add-row',
        title: 'Add row below',
        icon: 'bi bi-plus-square'
    },
    {
        command: 'remove-row',
        title: 'Remove row',
        icon: 'bi bi-dash-square'
    },
    {
        command: 'add-column',
        title: 'Add column after',
        icon: 'bi bi-layout-three-columns'
    },
    {
        command: 'remove-column',
        title: 'Remove column',
        icon: 'bi bi-layout-sidebar'
    },
]


const AUTOSAVE_DELAY = 800
const COMMAND_MENU_WIDTH = 320
const COMMAND_MENU_MAX_HEIGHT = 320


const imagePickerTitle = computed(() => {
    return 'Project files'
})


const imagePickerSubtitle = computed(() => {
    return 'Select an image file from project files.'
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


function normalizeTableWidths(
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
        (
            node.type === 'tableCell' ||
            node.type === 'tableHeader'
        )
    ) {
        normalized.attrs = {
            ...node.attrs,
            colwidth: null
        }
    }

    if (
        Array.isArray(
            node.content
        )
    ) {
        normalized.content =
            node.content.map(
                child =>
                    normalizeTableWidths(
                        child
                    )
            )
    }

    return normalized
}


function normalizeClientImageUrls(
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
        node.type === 'image' &&
        node.attrs &&
        typeof node.attrs === 'object'
    ) {
        const attrs = {
            ...node.attrs
        }

        let fileId =
            attrs.projectFileId

        if (
            typeof fileId === 'string'
        ) {
            fileId =
                fileId
                    .replace(
                        'project-file-',
                        ''
                    )
                    .trim()
        }

        const numericFileId =
            Number(fileId)

        if (
            !Number.isInteger(
                numericFileId
            ) ||
            numericFileId <= 0
        ) {
            const src =
                String(
                    attrs.src ||
                    ''
                ).trim()

            /*
             * Recognize every project-file open URL
             * format that may exist in documents:
             *
             * /admin/client-portal/api/projects/13/files/175/open
             * /projects/13/files/175/open
             * /client/files/175/open
             */
            const match =
                src.match(
                    /\/files\/(\d+)\/open(?:[/?#]|$)/
                )

            if (match) {
                fileId =
                    match[1]
            }
        }

        const resolvedFileId =
            Number(fileId)

        if (
            Number.isInteger(
                resolvedFileId
            ) &&
            resolvedFileId > 0
        ) {
            attrs.src =
                `/client/files/${resolvedFileId}/open`

            attrs.projectFileId =
                resolvedFileId
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
                normalizeClientImageUrls
            )
    }

    return normalized
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
                const normalized =
                    props.clientMode
                        ? normalizeClientImageUrls(
                            parsed
                        )
                        : parsed

                return normalizeTableWidths(
                    normalized
                )
            }

            if (
                parsed?.doc?.type ===
                'doc'
            ) {
                const normalized =
                    props.clientMode
                        ? normalizeClientImageUrls(
                            parsed.doc
                        )
                        : parsed.doc

                return normalizeTableWidths(
                    normalized
                )
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
        const normalized =
            props.clientMode
                ? normalizeClientImageUrls(
                    source
                )
                : source

        return normalizeTableWidths(
            normalized
        )
    }

    if (
        source?.doc?.type ===
        'doc'
    ) {
        const normalized =
            props.clientMode
                ? normalizeClientImageUrls(
                    source.doc
                )
                : source.doc

        return normalizeTableWidths(
            normalized
        )
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

    let blockPos =
        Number(
            selection.from || 0
        )

    if (
        selection.$from?.depth > 0
    ) {
        blockPos =
            selection.$from.before(
                1
            )
    }

    const safePos = Math.max(
        0,
        Math.min(
            blockPos,
            doc.content.size
        )
    )

    const resolved =
        doc.resolve(
            safePos
        )

    const topLevelIndex =
        resolved.index(
            0
        )

    if (
        Number.isInteger(
            topLevelIndex
        ) &&
        topLevelIndex >= 0 &&
        topLevelIndex <
            doc.childCount
    ) {
        return topLevelIndex
    }

    return -1
}


/*
 * Marks the top-level block that currently holds the
 * selection. A decoration is used so the outline survives
 * every ProseMirror re-render.
 */
const ActiveBlockHighlight = Extension.create({
    name: 'activeBlockHighlight',

    addProseMirrorPlugins() {
        const editorInstance =
            this.editor

        return [
            new Plugin({
                key:
                    new PluginKey(
                        'activeBlockHighlight'
                    ),

                props: {
                    decorations(state) {
                        if (
                            !editorInstance
                                ?.isEditable
                        ) {
                            return null
                        }

                        const {
                            doc,
                            selection
                        } = state

                        const index =
                            getActiveTopLevelIndex(
                                selection,
                                doc
                            )

                        if (index < 0) {
                            return null
                        }

                        const from =
                            getTopLevelBlockStartPos(
                                doc,
                                index
                            )

                        return DecorationSet.create(
                            doc,
                            [
                                Decoration.node(
                                    from,
                                    from +
                                        doc.child(
                                            index
                                        ).nodeSize,
                                    {
                                        class:
                                            'is-editor-active-block'
                                    }
                                )
                            ]
                        )
                    }
                }
            })
        ]
    }
})


function getEditorView(
    instance = editor.value
) {
    if (
        !instance ||
        instance.isDestroyed
    ) {
        return null
    }

    try {
        return instance.view
    } catch {
        return null
    }
}


function getEditorDom(
    instance = editor.value
) {
    const view =
        getEditorView(
            instance
        )

    if (!view) {
        return null
    }

    try {
        return view.dom
    } catch {
        return null
    }
}


function clearActiveBlockVisuals() {
    const root =
        getEditorDom()

    if (
        !(root instanceof HTMLElement)
    ) {
        return
    }

    root
        .querySelectorAll(
            '.is-editor-drop-target'
        )
        .forEach(node => {
            node.classList.remove(
                'is-editor-drop-target'
            )
        })
}


function updateBlockToolsUI() {
    const instance =
        editor.value

    tableTools.value.visible =
        false

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
        getEditorDom(
            instance
        )

    if (
        !(root instanceof HTMLElement)
    ) {
        blockTools.value.visible =
            false

        return
    }

    if (
        !doc.childCount
    ) {
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

    if (
        turnIntoMenu.value.visible &&
        blockTools.value.index !== index
    ) {
        closeTurnIntoMenu()
    }

    /*
     * Remove the previous active state first.
     */
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

    const rect =
        blockDom.getBoundingClientRect()

    if (
        rect.width <= 0 ||
        rect.height <= 0
    ) {
        blockTools.value.visible =
            false

        return
    }

    const toolbarGap = 8

    blockTools.value = {
        visible: true,

        top:
            rect.top -
            5,

        // Start the toolbar outside the element so it cannot cover its content.
        left:
            rect.right +
            toolbarGap,

        index
    }

    updateTableToolsUI(
        blockDom
    )
}


function updateTableToolsUI(
    blockDom
) {
    const instance =
        editor.value

    if (
        !instance ||
        !props.editable ||
        !instance.isActive('table')
    ) {
        tableTools.value.visible =
            false

        return
    }

    const tableDom =
        blockDom instanceof HTMLElement
            ? (
                blockDom.tagName === 'TABLE'
                    ? blockDom
                    : blockDom.querySelector('table')
            )
            : null

    if (
        !(tableDom instanceof HTMLElement)
    ) {
        tableTools.value.visible =
            false

        return
    }

    const tableRect =
        tableDom.getBoundingClientRect()

    tableTools.value = {
        visible: true,
        top:
            tableRect.top +
            40,
        left:
            tableRect.right +
            8
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
            const view =
                getEditorView(
                    instance
                )

            if (!view) {
                return
            }

            const coords =
                view.coordsAtPos(
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


function extractPlainText(
    node
) {
    if (!node) {
        return ''
    }

    if (node.type === 'text') {
        return node.text || ''
    }

    if (
        !Array.isArray(
            node.content
        )
    ) {
        return ''
    }

    return node.content
        .map(
            extractPlainText
        )
        .join('')
}


function getBlockItemTexts(
    node
) {
    if (
        node?.type === 'bulletList' ||
        node?.type === 'orderedList' ||
        node?.type === 'taskList'
    ) {
        const items =
            (node.content || []).map(
                item =>
                    extractPlainText(
                        item
                    )
            )

        return items.length
            ? items
            : ['']
    }

    return [
        extractPlainText(node)
    ]
}


function buildListNode(
    kind,
    items
) {
    if (kind === 'taskList') {
        return {
            type: 'taskList',
            content: items.map(
                text => ({
                    type: 'taskItem',
                    attrs: {
                        checked: false
                    },
                    content:
                        createTextNode(
                            text
                        )
                })
            )
        }
    }

    return listNodes(
        kind,
        items
    )
}


function buildTextNodes(
    builder,
    items
) {
    return items.map(
        text => builder(text)
    )
}


const turnIntoOptions = [
    {
        id: 'paragraph',
        label: 'Text',
        build: items =>
            buildTextNodes(
                paragraphNode,
                items
            )
    },
    {
        id: 'heading1',
        label: 'Heading',
        build: items =>
            buildTextNodes(
                text =>
                    headingNode(
                        1,
                        text
                    ),
                items
            )
    },
    {
        id: 'heading2',
        label: 'Subheading',
        build: items =>
            buildTextNodes(
                text =>
                    headingNode(
                        2,
                        text
                    ),
                items
            )
    },
    {
        id: 'quote',
        label: 'Quote',
        build: items =>
            buildTextNodes(
                blockquoteNode,
                items
            )
    },
    {
        id: 'bulletList',
        label: 'Bullet list',
        build: items => [
            buildListNode(
                'bulletList',
                items
            )
        ]
    },
    {
        id: 'orderedList',
        label: 'Numbered list',
        build: items => [
            buildListNode(
                'orderedList',
                items
            )
        ]
    },
    {
        id: 'taskList',
        label: 'Checklist',
        build: items => [
            buildListNode(
                'taskList',
                items
            )
        ]
    }
]


function toggleTurnIntoMenu() {
    turnIntoMenu.value.visible =
        !turnIntoMenu.value.visible
}


function closeTurnIntoMenu() {
    turnIntoMenu.value.visible =
        false
}


function turnActiveBlockInto(
    option
) {
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

    const items =
        getBlockItemTexts(
            content[index]
        )

    const newNodes =
        option.build(items)

    content.splice(
        index,
        1,
        ...(
            newNodes.length
                ? newNodes
                : [paragraphNode('')]
        )
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
    closeTurnIntoMenu()

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

    const root =
        getEditorDom(
            instance
        )

    const index =
        getActiveTopLevelIndex(
            instance.state.selection,
            instance.state.doc
        )

    const blockDom =
        root instanceof HTMLElement &&
        index >= 0
            ? root.children[index]
            : null

    if (
        blockDom instanceof HTMLElement
    ) {
        const rect =
            blockDom.getBoundingClientRect()

        insertHandle.value.visible =
            true

        insertHandle.value.coords = {
            top:
                rect.top -
                5,

            left:
                rect.left -
                46
        }

        return
    }

    try {
        const view =
            getEditorView(
                instance
            )

        if (!view) {
            return
        }

        const coords =
            view.coordsAtPos(
                selection.from
            )

        insertHandle.value.visible =
            true

        insertHandle.value.coords = {
            top:
                coords.top +
                (coords.bottom - coords.top) / 2 -
                16,

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
        const view =
            getEditorView(
                instance
            )

        if (!view) {
            return
        }

        const coords =
            view.coordsAtPos(
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
        const view =
            getEditorView(
                instance
            )

        if (!view) {
            return
        }

        const coords =
            view.coordsAtPos(
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
    } else if (
        command ===
        'remove-row'
    ) {
        chain
            .deleteRow()
            .run()
    } else if (
        command ===
        'add-column'
    ) {
        chain
            .addColumnAfter()
            .run()
    } else if (
        command ===
        'remove-column'
    ) {
        chain
            .deleteColumn()
            .run()
    } else if (
        command ===
        'delete-table'
    ) {
        chain
            .deleteTable()
            .run()
    }

    markDirty()

    nextTick(() => {
        updateBlockToolsUI()
    })
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
    imagePickerCurrentFolderId.value =
        null

    imagePickerItems.value =
        []

    imagePickerLoadedFolders.value =
        new Set()

    imagePickerStructureLoaded.value =
        false

    showImagePickerModal.value =
        true

    void loadImagePickerItems(
        null
    )
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
    return isImageProjectFile(
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
                .filter(
                    item =>
                        isPersistedFolderId(
                            item?.id
                        )
                )
                .map(
                    item => [
                        String(
                            item.id
                        ),
                        item
                    ]
                )
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
                            item?.id
                        )
                    ) ||
                    previous[
                        index
                    ] ||
                    null

                const type =
                    item?.type ===
                        'file'
                        ? 'file'
                        : 'folder'

                return {
                    ...item,

                    type,

                    resource_type:
                        type === 'file'
                            ? (
                                item?.resource_type ||
                                previousItem?.resource_type ||
                                'document'
                            )
                            : null,

                    client_key:
                        previousItem?.client_key ||
                        item?.client_key ||
                        String(
                            item?.id
                        ),

                    parent_client_key:
                        item?.parent_id !== null &&
                        item?.parent_id !== undefined
                            ? String(
                                item.parent_id
                            )
                            : null,

                    client_visible:
                        item?.client_visible ??
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


function isBrowserImageFile(
    file
) {
    if (
        !file
    ) {
        return false
    }

    const mime =
        String(
            file.type ||
            ''
        ).toLowerCase()

    if (
        mime.startsWith(
            'image/'
        )
    ) {
        return true
    }

    const name =
        String(
            file.name ||
            ''
        ).toLowerCase()

    return /\.(png|jpe?g|gif|webp|svg|avif|bmp|ico|tiff?)$/i.test(
        name
    )
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
        thumbnail_url:
            file.thumbnail_url ||
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


async function loadImagePickerItems(
    folderId = null,
    force = false
) {
    if (
        !props.projectId
    ) {
        imagePickerItems.value =
            []

        return
    }

    const cacheKey =
        folderId === null
            ? 'root'
            : String(
                folderId
            )

    /*
     * Load the folder/document structure once.
     * This is metadata only; it does not download file contents.
     */
    if (
        !imagePickerStructureLoaded.value
    ) {
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

        const uploadedFiles =
            (
                imagePickerItems.value ||
                []
            ).filter(
                item =>
                    item?.__uploaded_file
            )

        imagePickerItems.value = [
            ...structureItems,
            ...uploadedFiles
        ]

        imagePickerStructureLoaded.value =
            true
    }

    if (
        !force &&
        imagePickerLoadedFolders.value.has(
            cacheKey
        )
    ) {
        imagePickerCurrentFolderId.value =
            folderId

        return
    }

    imagePickerLoading.value =
        true

    imagePickerError.value =
        ''

    try {
        const response =
            await api.get(
                `/projects/${props.projectId}/files`,
                folderId === null
                    ? {}
                    : {
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

        const imageFiles =
            files
                .filter(
                    pickerAcceptsProjectFile
                )
                .map(
                    normalizePickerFile
                )

        const parentKey =
            String(
                folderId ??
                ''
            )

        const retainedItems =
            (
                imagePickerItems.value ||
                []
            ).filter(
                item => {
                    if (
                        !item?.__uploaded_file
                    ) {
                        return true
                    }

                    return (
                        String(
                            item.parent_id ??
                            ''
                        ) !==
                        parentKey
                    )
                }
            )

        imagePickerItems.value = [
            ...retainedItems,
            ...imageFiles
        ]

        imagePickerLoadedFolders.value.add(
            cacheKey
        )

        imagePickerCurrentFolderId.value =
            folderId
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
    imageNodePickerContext.value =
        false
    imageNodePickerPos.value =
        null
    imagePickerError.value =
        ''
}


async function handleImagePickerFolderOpen(
    folder
) {
    const folderId =
        folder?.id ??
        null

    imagePickerCurrentFolderId.value =
        folderId

    await loadImagePickerItems(
        folderId
    )
}


function handleImagePickerStructureUpdate(
    value
) {
    imagePickerItems.value =
        Array.isArray(value)
            ? value
            : []
}


async function handleImagePickerFileOpen(file) {
    if (!file?.open_url) {
        return
    }

    if (
        !pickerAcceptsProjectFile(file)
    ) {
        imagePickerError.value =
            'Please select an image file from Project Files.'

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

            const projectFileId =
                Number(
                    String(
                        file.id ||
                        ''
                    ).replace(
                        'project-file-',
                        ''
                    )
                )

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
                                false,
                            projectFileId
                        }
                    )

                const view =
                    getEditorView(
                        instance
                    )

                if (!view) {
                    return
                }

                view.dispatch(
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
                            false,
                        projectFileId
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
        isBrowserImageFile
    )

    if (!files.length) {
        imagePickerError.value =
            'Please upload at least one image file.'
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

        imagePickerLoadedFolders.value.delete(
            imagePickerCurrentFolderId.value ===
                null
                ? 'root'
                : String(
                    imagePickerCurrentFolderId.value
                )
        )

        await loadImagePickerItems(
            imagePickerCurrentFolderId.value
        )
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

    const activeLink =
        instance
            .getAttributes(
                'link'
            ) || {}

    linkHref.value =
        String(
            activeLink.href ||
                ''
        )

    linkDescription.value =
        String(
            activeLink.description ||
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


function hideLinkPreview() {
    linkPreview.value = {
        visible: false,
        href: '',
        description: '',
        top: 0,
        left: 0
    }
}


function showLinkPreviewForEvent(event) {
    const target = event.target

    const anchor =
        target instanceof Element
            ? target.closest('a[href]')
            : null

    if (!(anchor instanceof HTMLElement)) {
        hideLinkPreview()
        return
    }

    const href =
        String(
            anchor.getAttribute('href') || ''
        )

    if (!href) {
        hideLinkPreview()
        return
    }

    const description =
        String(
            anchor.dataset.linkDescription ||
            anchor.getAttribute(
                'data-link-description'
            ) ||
            ''
        )

    const rect =
        anchor.getBoundingClientRect()

    linkPreview.value = {
        visible: true,
        href,
        description,

        /*
         * Position the popup at the TOP of the
         * linked text. The CSS will then move the
         * entire popup above this point.
         */
        top: rect.top - 8,

        left:
            rect.left +
            rect.width / 2
    }
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
            href,
            description:
                linkDescription.value
                    .trim()
        })
        .run()

    closeLinkModal()
    markDirty()
}


function handleCommandMenuOutsideClick(event) {
    if (
        turnIntoMenu.value.visible &&
        blockToolsRoot.value &&
        event.target instanceof Node &&
        !blockToolsRoot.value.contains(
            event.target
        )
    ) {
        closeTurnIntoMenu()
    }

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
        window.requestAnimationFrame(() => {
            updateBlockToolsUI()
        })
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
     * NodeSelection is used by images, info blocks
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
     * element such as an image or info block.
     */
    const nonTextNodeNames = new Set([
        'image',
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

        TextAlign,

        DocumentLink.configure({
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
            resizable: false
        }),

        InfoBlock,

        ActiveBlockHighlight,

        TableRow,
        TableHeader,
        TableCell
    ],

    editorProps: {
        attributes: {
            class:
                'document-editor-content'
        },

        handleDOMEvents: {
            mouseover(view, event) {
                showLinkPreviewForEvent(
                    event
                )

                return false
            },

            mouseleave(_view, event) {
                if (
                    event.target instanceof Element &&
                    event.target.closest('a[href]')
                ) {
                    hideLinkPreview()
                }

                return false
            }
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

    onCreate() {
        nextTick(() => {
            window.requestAnimationFrame(() => {
                updateBlockToolsUI()
            })
        })
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
                getEditorDom()

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

            tableTools.value.visible =
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

    const instance =
        editor.value

    if (
        instance &&
        !instance.isDestroyed
    ) {
        instance.destroy()
    }
})
</script>


<template>
    <div
        class="
            fixed
            inset-0
            z-[100]
            flex
            h-screen
            w-full
            flex-col
            overflow-hidden
            bg-light
            text-dark
        "
    >
        <!-- Top bar -->
        <header
            class="
                z-30
                flex
                h-16
                shrink-0
                items-center
                justify-between
                bg-accent
                px-5
                text-light
            "
        >

            <div class="flex gap-10">
                <button
                    type="button"
                    class="
                        p
                        @mousedown.prevent
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
                        ref="blockToolsRoot"
                        class="
                            fixed
                            z-40
                            flex
                            items-center
                            gap-1
                            border
                            border-accent
                            bg-light
                            p-0
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
                                h-8
                                w-8
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
                                h-8
                                w-8
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
                                h-8
                                w-8
                                place-items-center
                                text-dark
                                transition-colors
                                hover:bg-accent
                                hover:text-light
                            "
                            title="Turn into"
                            @mousedown.prevent
                            @click="
                                toggleTurnIntoMenu
                            "
                        >
                            <i class="bi bi-arrow-repeat" />
                        </button>

                        <button
                            type="button"
                            class="
                                grid
                                h-8
                                w-8
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
                                h-8
                                w-8
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

                        <div
                            v-if="
                                turnIntoMenu.visible
                            "
                            class="
                                absolute
                                left-0
                                top-full
                                z-50
                                mt-1
                                w-44
                                border
                                border-accent
                                bg-light
                            "
                            @mousedown.prevent.stop
                        >
                            <button
                                v-for="
                                    option in turnIntoOptions
                                "
                                :key="option.id"
                                type="button"
                                class="
                                    block
                                    w-full
                                    px-3
                                    py-2
                                    text-left
                                    text-sm
                                    transition-colors
                                    hover:bg-accent
                                    hover:text-light
                                "
                                @mousedown.prevent
                                @click="
                                    turnActiveBlockInto(
                                        option
                                    )
                                "
                            >
                                {{
                                    option.label
                                }}
                            </button>
                        </div>
                    </div>

                    <!-- Table controls -->
                    <div
                        v-if="
                            editable &&
                            tableTools?.visible
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
                            p-0
                        "
                        :style="{
                            top:
                                `${tableTools.top}px`,
                            left:
                                `${tableTools.left}px`
                        }"
                        @mousedown.prevent.stop
                    >
                        <button
                            v-for="
                                control in tableControls
                            "
                            :key="control.command"
                            type="button"
                            class="
                                grid
                                h-8
                                w-8
                                place-items-center
                                text-dark
                                transition-colors
                                hover:bg-accent
                                hover:text-light
                            "
                            :title="control.title"
                            @mousedown.prevent
                            @click="
                                runTableCommand(
                                    control.command
                                )
                            "
                        >
                            <i :class="control.icon" />
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
                placement: 'right-start',
                offset: [40, 8],
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
                    @mousedown.prevent
                    @click="
                        handleLinkButton
                    "
                >
                    Link
                </button>

                <button
                    type="button"
                    class="
                        grid
                        h-8
                        w-8
                        place-items-center
                        text-dark
                        transition-colors
                        hover:bg-accent
                        hover:text-light
                    "
                    title="Align left"
                    aria-label="Align left"
                    :class="{
                        'bg-accent text-light': editor.isActive({
                            textAlign: 'left'
                        })
                    }"
                    @mousedown.prevent
                    @click="
                        editor
                            .chain()
                            .focus()
                            .setTextAlign('left')
                            .run()
                    "
                >
                    <i class="bi bi-text-left" />
                </button>

                <button
                    type="button"
                    class="
                        grid
                        h-8
                        w-8
                        place-items-center
                        text-dark
                        transition-colors
                        hover:bg-accent
                        hover:text-light
                    "
                    title="Align center"
                    aria-label="Align center"
                    :class="{
                        'bg-accent text-light': editor.isActive({
                            textAlign: 'center'
                        })
                    }"
                    @mousedown.prevent
                    @click="
                        editor
                            .chain()
                            .focus()
                            .setTextAlign('center')
                            .run()
                    "
                >
                    <i class="bi bi-text-center" />
                </button>

                <button
                    type="button"
                    class="
                        grid
                        h-8
                        w-8
                        place-items-center
                        text-dark
                        transition-colors
                        hover:bg-accent
                        hover:text-light
                    "
                    title="Align right"
                    aria-label="Align right"
                    :class="{
                        'bg-accent text-light': editor.isActive({
                            textAlign: 'right'
                        })
                    }"
                    @mousedown.prevent
                    @click="
                        editor
                            .chain()
                            .focus()
                            .setTextAlign('right')
                            .run()
                    "
                >
                    <i class="bi bi-text-right" />
                </button>

                <button
                    type="button"
                    class="
                        grid
                        h-8
                        w-8
                        place-items-center
                        text-dark
                        transition-colors
                        hover:bg-accent
                        hover:text-light
                    "
                    title="Justify text"
                    aria-label="Justify text"
                    :class="{
                        'bg-accent text-light': editor.isActive({
                            textAlign: 'justify'
                        })
                    }"
                    @mousedown.prevent
                    @click="
                        editor
                            .chain()
                            .focus()
                            .setTextAlign('justify')
                            .run()
                    "
                >
                    <i class="bi bi-justify" />
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


        <div
            v-if="linkPreview.visible"
            class="
                fixed
                z-50
                w-72
                max-w-[calc(100vw-2rem)]
                -translate-x-1/2
                -translate-y-full
                border
                border-accent
                bg-light
                p-3
            "
            :style="{
                top: `${linkPreview.top}px`,
                left: `${linkPreview.left}px`
            }"
        >
            <div
                class="
                    mb-2
                    max-w-full
                    break-words
                    p
                    uppercase
                "
            >
                {{
                    linkPreview.description ||
                        'Open link'
                }}
            </div>

            <Button
                type="button"
                text="continue to site"
                variant="accent"
                align="left"
                @click="
                    window.open(
                        linkPreview.href,
                        '_blank',
                        'noopener,noreferrer'
                    )
                "
            />
        </div>

        <Modal
            :open="
                showLinkModal
            "
            title="Add link"
            subtitle="Enter the URL and optional description for the selected text."
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

                <FormField
                    id="document-link-description"
                    type="text"
                    label="Link description"
                    placeholder="Open the project page"
                    :model-value="linkDescription"
                    @update:model-value="
                        value => {
                            linkDescription = String(
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
                        p-6
                        flex
                        flex-col
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
        </Modal>

        <FilePickerModal
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
   HEADINGS
   ========================================================= */

/*
 * `:not(.document-custom-block *)` keeps editor typography out of
 * node views, so shared components keep their own styling.
 */

.article-editor :deep(
    .ProseMirror h1:not(.document-custom-block *)
),
.article-editor :deep(
    .ProseMirror h2:not(.document-custom-block *)
),
.article-editor :deep(
    .ProseMirror h3:not(.document-custom-block *)
) {
    margin: 2.2rem 0 0.9rem;
    color: var(--color-accent);
    font-family: 'Space Mono', monospace;
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1.12;
}

.article-editor :deep(
    .ProseMirror h1:not(.document-custom-block *)
) {
    font-size: clamp(2rem, 4vw, 3.1rem);
}

.article-editor :deep(
    .ProseMirror h2:not(.document-custom-block *)
) {
    font-size: clamp(1.55rem, 3vw, 2.2rem);
}

.article-editor :deep(
    .ProseMirror h3:not(.document-custom-block *)
) {
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
    border-top: 1px solid rgb(19 62 180);
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
 * The image NodeView owns the resize handle.
 *
 * It does NOT own the selection outline.
 *
 * The parent .ProseMirror > * selection system below
 * supplies the same outline as every other building block.
 */

.article-editor :deep(
    .ProseMirror .document-image-node
) {
    position: relative;

    display: block;

    width: 100%;
    max-width: 100%;

    margin: 2rem auto;

    border: 0 !important;
    box-shadow: none !important;
}

.article-editor :deep(
    .ProseMirror .document-image-node img
) {
    display: block;

    width: 100%;
    max-width: 100%;

    height: auto;

    margin: 0;
    padding: 0;

    border: 0 !important;
    outline: none !important;
    box-shadow: none !important;

    object-fit: contain;

    user-select: none;
    -webkit-user-drag: none;
}

/*
 * Pending project image.
 */

.article-editor :deep(
    .ProseMirror
        .document-image-node
        img[pendingprojectimage='true']
) {
    min-height: 140px;

    border: 1px dashed
        rgb(19 62 180 / 0.45) !important;

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
    width: auto !important;
    min-width: 0 !important;
    border: 1px solid rgb(19 62 180);
    padding: 0.75rem;
    vertical-align: top;
    text-align: left;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.article-editor :deep(.ProseMirror th) {
    background: rgb(19 62 180 / 0.05);
    font-weight: 400;
}

.article-editor :deep(.ProseMirror .selectedCell) {
    background: rgb(19 62 180 / 0.05);
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
    grid-template-columns:
        1rem
        minmax(0, 1fr) !important;

    align-items: center !important;

    column-gap: 1rem !important;
    row-gap: 0 !important;

    list-style: none !important;

    width: 100% !important;

    margin: 0 !important;
    padding: 0 !important;
    padding-left: 0 !important;
}

.article-editor :deep(
    .ProseMirror
        ul[data-type='taskList']
        li
        + li
) {
    margin-top: 0.16rem !important;
}

/* Checkbox wrapper */

.article-editor :deep(
    .ProseMirror
        ul[data-type='taskList']
        li
        > label
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
    .ProseMirror
        ul[data-type='taskList']
        li
        > div
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
    .ProseMirror
        p.is-editor-empty:first-child::before
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
 * Every top-level editor block shares the same base behavior.
 *
 * Hovered inactive blocks get the subtle outline.
 * Active blocks receive a single strong blue outline from the
 * editor's actual active-block source of truth.
 */

.article-editor--editable :deep(
    .ProseMirror > *
) {
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
        rgb(19 62 180 / 0.95) !important;
    box-shadow: none !important;
}

.article-editor :deep(
    .ProseMirror > .ProseMirror-selectednode
) {
    box-shadow:
        none !important;
}

.article-editor--editable :deep(
    .ProseMirror > *.is-editor-drop-target
) {
    outline:
        1px solid
        rgb(19 62 180 / 0.35);
    outline-offset: 5px;
    box-shadow:
        none !important;
}


/* =========================================================
   IMAGE
   ========================================================= */

.article-editor :deep(
    .ProseMirror
        .document-image-node
) {
    position: relative;

    display: block;

    width: 100%;
    max-width: 100%;
}


/*
 * The image itself never creates a selection outline.
 *
 * The NodeViewWrapper is the editor block.
 */

.article-editor :deep(
    .ProseMirror
        .document-image-node
        img
) {
    border: 0 !important;

    outline: none !important;

    box-shadow:
        none !important;
}


/* =========================================================
   CUSTOM BLOCK
   ========================================================= */

.article-editor :deep(
    .ProseMirror
        .document-custom-block
) {
    margin: 2rem 0;
}


/*
 * IMPORTANT:
 *
 * Remove the old custom-block selection shadow.
 *
 * The custom block now uses the exact same active outline
 * as paragraphs, headings, lists, images, etc.
 */

.article-editor :deep(
    .ProseMirror
        .ProseMirror-selectednode
        .document-custom-block
) {
    box-shadow:
        none !important;
}
/*
 * IMPORTANT:
 *
 * Do not give custom blocks their own selection box.
 *
 * The generic .is-editor-active-block outline is the
 * single outline.
 */

.article-editor :deep(
    .ProseMirror
        .ProseMirror-selectednode
        .document-custom-block
) {
    box-shadow:
        none !important;
}

/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 640px) {
    .article-editor :deep(
        .ProseMirror
    ) {
        min-height: 55vh;

        font-size: 1rem;

        line-height: 1.8;
    }

    .article-editor :deep(
        .ProseMirror h1:not(.document-custom-block *)
    ) {
        font-size: 2rem;
    }

    .article-editor :deep(
        .ProseMirror h2:not(.document-custom-block *)
    ) {
        font-size: 1.55rem;
    }

    .article-editor :deep(
        .ProseMirror blockquote
    ) {
        padding-left: 1rem;

        font-size: 1.05rem;
    }

    .article-editor :deep(
        .ProseMirror td
    ),
    .article-editor :deep(
        .ProseMirror th
    ) {
        min-width: 100px;

        padding: 0.55rem;
    }

    .article-editor :deep(
        .ProseMirror
            .document-image-node
    ) {
        max-width: 100%;
    }

    .article-editor :deep(
        .ProseMirror
            .document-image-node
            img
    ) {
        max-width: 100%;
    }
}
</style>