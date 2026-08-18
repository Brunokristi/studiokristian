<script setup>
import {
    computed,
    onBeforeUnmount,
    ref
} from 'vue'

import {
    NodeSelection
} from '@tiptap/pm/state'

import {
    NodeViewWrapper
} from '@tiptap/vue-3'


const props = defineProps({
    node: {
        type: Object,
        required: true
    },

    selected: {
        type: Boolean,
        default: false
    },

    getPos: {
        type: Function,
        required: false,
        default: null
    },

    updateAttributes: {
        type: Function,
        required: true
    },

    editor: {
        type: Object,
        required: true
    }
})


const wrapperRef = ref(null)
const imageRef = ref(null)

const isResizing = ref(false)
const isHovered = ref(false)

const liveHeightPx = ref(null)

let resizeStartY = 0
let resizeStartHeight = 0


const editable = computed(() => {
    return Boolean(
        props.editor?.isEditable
    )
})


const isPending = computed(() => {
    return Boolean(
        props.node?.attrs
            ?.pendingProjectImage
    )
})


const savedHeight = computed(() => {
    const value =
        props.node?.attrs?.height

    if (
        value === null ||
        value === undefined ||
        value === ''
    ) {
        return null
    }

    const numeric =
        Number.parseFloat(
            String(value)
        )

    if (
        !Number.isFinite(numeric) ||
        numeric <= 0
    ) {
        return null
    }

    return numeric
})


const currentHeight = computed(() => {
    if (
        isResizing.value &&
        liveHeightPx.value !== null
    ) {
        return `${Math.round(
            liveHeightPx.value
        )}px`
    }

    if (
        savedHeight.value !== null
    ) {
        return `${Math.round(
            savedHeight.value
        )}px`
    }

    return 'auto'
})


const wrapperStyle = computed(() => {
    const height =
        currentHeight.value

    return {
        width: '100%',
        maxWidth: '100%',
        height:
            height === 'auto'
                ? undefined
                : height
    }
})


const imageStyle = computed(() => {
    const height =
        currentHeight.value

    return {
        width: '100%',
        maxWidth: '100%',
        height:
            height === 'auto'
                ? 'auto'
                : height,
        display: 'block',
        margin: '0 auto',
        objectFit: 'contain'
    }
})


const showResizeHandle = computed(() => {
    return (
        editable.value &&
        !isPending.value &&
        (
            props.selected ||
            isResizing.value
        )
    )
})


function selectNode() {
    if (
        !editable.value ||
        isPending.value ||
        props.selected ||
        !props.editor?.view ||
        typeof props.getPos !== 'function'
    ) {
        return
    }

    const pos =
        Number(props.getPos())

    if (
        !Number.isInteger(pos)
    ) {
        return
    }

    const selection =
        NodeSelection.create(
            props.editor.view.state.doc,
            pos
        )

    props.editor.view.dispatch(
        props.editor.view.state.tr.setSelection(
            selection
        )
    )
}


function getNaturalImageHeight() {
    const image =
        imageRef.value

    if (
        !(image instanceof HTMLImageElement)
    ) {
        return 0
    }

    /*
     * If the image has a saved height,
     * use the rendered height.
     */
    if (
        savedHeight.value !== null
    ) {
        return savedHeight.value
    }

    const renderedWidth =
        image.getBoundingClientRect()
            .width

    const naturalWidth =
        image.naturalWidth

    const naturalHeight =
        image.naturalHeight

    if (
        renderedWidth > 0 &&
        naturalWidth > 0 &&
        naturalHeight > 0
    ) {
        return (
            renderedWidth *
            naturalHeight /
            naturalWidth
        )
    }

    return image.getBoundingClientRect()
        .height
}


function startResize(event) {
    if (
        !editable.value ||
        isPending.value
    ) {
        return
    }

    event.preventDefault()
    event.stopPropagation()

    const image =
        imageRef.value

    if (
        !(image instanceof HTMLElement)
    ) {
        return
    }

    const currentHeight =
        getNaturalImageHeight()

    if (
        currentHeight <= 0
    ) {
        return
    }

    resizeStartY =
        Number(event.clientY)

    resizeStartHeight =
        currentHeight

    liveHeightPx.value =
        currentHeight

    isResizing.value = true

    document.addEventListener(
        'pointermove',
        handlePointerMove
    )

    document.addEventListener(
        'pointerup',
        finishResize
    )

    document.addEventListener(
        'pointercancel',
        finishResize
    )
}


function handlePointerMove(event) {
    if (!isResizing.value) {
        return
    }

    const deltaY =
        Number(event.clientY) -
        resizeStartY

    const minHeight = 120

    /*
     * There is intentionally NO maximum
     * based on the editor width.
     *
     * Width is fixed.
     * Height can grow vertically.
     */
    const maxHeight = 2400

    const nextHeight =
        Math.min(
            maxHeight,
            Math.max(
                minHeight,
                resizeStartHeight +
                    deltaY
            )
        )

    liveHeightPx.value =
        nextHeight
}


function finishResize() {
    if (!isResizing.value) {
        return
    }

    if (
        liveHeightPx.value !== null
    ) {
        const nextHeight =
            `${Math.round(
                liveHeightPx.value
            )}px`

        props.updateAttributes({
            width: '100%',
            height: nextHeight
        })
    }

    liveHeightPx.value = null

    stopResize()
}


function stopResize() {
    document.removeEventListener(
        'pointermove',
        handlePointerMove
    )

    document.removeEventListener(
        'pointerup',
        finishResize
    )

    document.removeEventListener(
        'pointercancel',
        finishResize
    )

    isResizing.value = false
}


function handleMouseEnter() {
    isHovered.value = true
}


function handleMouseLeave() {
    if (!isResizing.value) {
        isHovered.value = false
    }
}


onBeforeUnmount(() => {
    stopResize()
})
</script>


<template>
    <NodeViewWrapper
        ref="wrapperRef"
        as="figure"
        class="document-image-node"
        :class="{
            'document-image-node--resizing':
                isResizing,
            'is-editor-active-block':
                selected
        }"
        :style="wrapperStyle"
        @click.stop="selectNode"
        @mouseenter="
            handleMouseEnter
        "
        @mouseleave="
            handleMouseLeave
        "
        @focusin="
            handleMouseEnter
        "
        @focusout="
            handleMouseLeave
        "
    >
        <div
            class="
                document-image-node__content
            "
        >
            <img
                ref="imageRef"
                :src="
                    String(
                        node?.attrs?.src ||
                        ''
                    )
                "
                :alt="
                    String(
                        node?.attrs?.alt ||
                        ''
                    )
                "
                :title="
                    String(
                        node?.attrs?.title ||
                        ''
                    )
                "
                :style="
                    imageStyle
                "
                :pendingprojectimage="
                    isPending
                        ? 'true'
                        : null
                "
                draggable="false"
            >

            <button
                v-if="
                    showResizeHandle
                "
                type="button"
                class="
                    document-image-node__resize-handle
                "
                aria-label="
                    Resize image height
                "
                title="
                    Drag to change image height
                "
                @pointerdown.stop.prevent="
                    startResize
                "
            >
                <span
                    class="
                        document-image-node__resize-handle-line
                    "
                />
            </button>
        </div>
    </NodeViewWrapper>
</template>


<style scoped>
/* =========================================================
   IMAGE NODE

   Width is ALWAYS the full document width.

   Do not put outline styles here.
   The editor's normal block selection supplies the
   single outline.
   ========================================================= */

.document-image-node {
    position: relative;

    display: block;

    width: 100%;
    max-width: 100%;

    margin: 2rem 0;

    padding: 0;

    box-sizing: border-box;
    overflow: visible;
}


/* =========================================================
   IMAGE CONTENT
   ========================================================= */

.document-image-node__content {
    position: relative;

    display: block;

    width: 100%;
    max-width: 100%;

    margin: 0;
    padding: 0;

    box-sizing: border-box;
}


.document-image-node img {
    display: block;

    width: 100%;
    max-width: 100%;

    margin: 0 auto;
    padding: 0;

    border: 0;

    object-fit: contain;

    user-select: none;
    -webkit-user-drag: none;
}


/* =========================================================
   PENDING IMAGE
   ========================================================= */

.document-image-node
    img[pendingprojectimage='true'] {
    min-height: 140px;

    background:
        repeating-linear-gradient(
            -45deg,
            rgb(19 62 180 / 0.06),
            rgb(19 62 180 / 0.06) 10px,
            transparent 10px,
            transparent 20px
        );
}


/* =========================================================
   HEIGHT RESIZE HANDLE
   ========================================================= */

/*
 * Bottom-center handle.
 *
 * It controls HEIGHT only.
 */

.document-image-node__resize-handle {
    position: absolute;

    left: 50%;
    bottom: -10px;

    width: 8px;
    height: 8px;

    margin: 0;
    padding: 0;

    transform: translateX(-50%);

    border: 2px solid
        var(--color-accent);

    border-radius: 50%;

    background:
        rgb(19 62 180);

    box-shadow: none;

    cursor: ns-resize;

    z-index: 50;

    appearance: none;

    box-sizing: border-box;

    touch-action: none;

    pointer-events: auto;

    transition:
        transform 120ms ease,
        background-color 120ms ease,
        box-shadow 120ms ease;
}


.document-image-node__resize-handle-line {
    display: block;

    width: 0;
    height: 0;

    margin: 0 auto;
}


/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 640px) {
    .document-image-node {
        width: 100%;
        max-width: 100%;
    }

    .document-image-node__resize-handle {
        bottom: -6px;
    }
}
</style>