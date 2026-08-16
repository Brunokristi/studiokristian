<script setup>
import {
    computed,
    ref
} from 'vue'

import {
    NodeViewWrapper
} from '@tiptap/vue-3'

import Slideshow from '@shared/components/Slideshow.vue'


const props = defineProps({
    node: {
        type: Object,
        required: true
    },

    selected: {
        type: Boolean,
        default: false
    },

    extension: {
        type: Object,
        required: true
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


const hover = ref(false)


const images = computed(() => {
    return Array.isArray(
        props.node?.attrs?.images
    )
        ? props.node.attrs.images
        : []
})


const language = computed(() => {
    return String(
        props.node?.attrs?.language ||
        'en'
    )
})


const editable = computed(() => {
    return Boolean(
        props.node?.attrs?.editable ?? true
    )
})


const useProjectFilesPicker = computed(() => {
    return Boolean(
        props.extension.options?.onRequestProjectImageAdd &&
        props.extension.options?.onRequestProjectImageReplace
    )
})


function updateImages(value) {
    props.updateAttributes({
        images: Array.isArray(value)
            ? value
            : []
    })
}


function requestAddFromProjectFiles() {
    props.extension.options?.onRequestProjectImageAdd?.({
        getCurrentImages: () => images.value,

        setImages: nextImages => {
            updateImages(nextImages)
        },

        updateNodeAttributes: attributes => {
            props.updateAttributes(attributes)
        }
    })
}


function requestReplaceFromProjectFiles(payload = {}) {
    props.extension.options?.onRequestProjectImageReplace?.({
        index: Number(payload?.index),

        getCurrentImages: () => images.value,

        setImages: nextImages => {
            updateImages(nextImages)
        },

        updateNodeAttributes: attributes => {
            props.updateAttributes(attributes)
        }
    })
}


function focusNode() {
    if (!props.editor?.view) {
        return
    }

    props.editor.view.focus()
}
</script>


<template>
    <NodeViewWrapper
        as="div"
        class="document-custom-block"
        :class="[
            selected
                ? 'ring-1 ring-accent/40'
                : ''
        ]"
        @mouseenter="hover = true"
        @mouseleave="hover = false"
        @mousedown="focusNode"
    >
        <div
            v-if="editable"
            class="document-custom-block-toolbar"
            :class="[
                selected || hover
                    ? 'opacity-100'
                    : 'opacity-0'
            ]"
        >
            <span
                class="document-custom-block-label"
            >
                <i class="bi bi-grip-vertical" />
                Slider
            </span>
        </div>

        <Slideshow
            :images="images"
            :editable="editable"
            :language="language"
            :show-arrows="true"
            :use-project-files-picker="useProjectFilesPicker"
            @update:images="updateImages"
            @request-project-image-add="requestAddFromProjectFiles"
            @request-project-image-replace="requestReplaceFromProjectFiles"
        />
    </NodeViewWrapper>
</template>
