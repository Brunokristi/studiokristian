<script setup>
import {
    computed,
    ref
} from 'vue'

import {
    NodeViewWrapper
} from '@tiptap/vue-3'

import Info from '@shared/components/Info.vue'


const props = defineProps({
    node: {
        type: Object,
        required: true
    },

    selected: {
        type: Boolean,
        default: false
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


const heading = computed(() => {
    return String(
        props.node?.attrs?.heading ||
        'New information'
    )
})


const text = computed(() => {
    return String(
        props.node?.attrs?.text ||
        'Add information here.'
    )
})


const items = computed(() => {
    const source =
        Array.isArray(
            props.node?.attrs?.items
        ) &&
        props.node.attrs.items.length
            ? props.node.attrs.items
            : [
                {
                    heading:
                        heading.value,
                    text:
                        text.value
                }
            ]

    return source.map(
        item => ({
            heading: String(
                item?.heading ||
                'New information'
            ),
            text: String(
                item?.text ||
                'Add information here.'
            )
        })
    )
})


const editable = computed(() => {
    return Boolean(
        props.node?.attrs?.editable ?? true
    )
})


function updateHeading(value) {
    updateItemHeading(
        0,
        value
    )
}


function updateText(value) {
    updateItemText(
        0,
        value
    )
}


function updateItems(nextItems) {
    props.updateAttributes({
        items: nextItems,
        heading: String(
            nextItems?.[0]?.heading ||
            'New information'
        ),
        text: String(
            nextItems?.[0]?.text ||
            'Add information here.'
        )
    })
}


function updateItemHeading(index, value) {
    const nextItems =
        [...items.value]

    if (!nextItems[index]) {
        return
    }

    nextItems[index] = {
        ...nextItems[index],
        heading: String(
            value || ''
        )
    }

    updateItems(nextItems)
}


function updateItemText(index, value) {
    const nextItems =
        [...items.value]

    if (!nextItems[index]) {
        return
    }

    nextItems[index] = {
        ...nextItems[index],
        text: String(
            value || ''
        )
    }

    updateItems(nextItems)
}


function addInfoItem() {
    updateItems([
        ...items.value,
        {
            heading:
                'New information',
            text:
                'Add information here.'
        }
    ])
}


function removeInfoItem(index) {
    if (
        items.value.length <= 1
    ) {
        return
    }

    updateItems(
        items.value.filter(
            (_, itemIndex) =>
                itemIndex !== index
        )
    )
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
                Info
            </span>
        </div>

        <div class="space-y-3">
            <Info
                v-for="(
                    item,
                    itemIndex
                ) in items"
                :key="itemIndex"
                :heading="item.heading"
                :text="item.text"
                :editable="editable"
                :draggable="false"
                @update:heading="
                    updateItemHeading(
                        itemIndex,
                        $event
                    )
                "
                @update:text="
                    updateItemText(
                        itemIndex,
                        $event
                    )
                "
                @remove="
                    removeInfoItem(
                        itemIndex
                    )
                "
            />

            <button
                v-if="editable"
                type="button"
                class="
                    inline-flex
                    items-center
                    gap-2
                    border
                    border-accent
                    px-3
                    py-2
                    font-mono
                    text-xs
                    font-bold
                    uppercase
                    text-accent
                    transition-colors
                    hover:bg-accent
                    hover:text-light
                "
                @click="addInfoItem"
            >
                <i class="bi bi-plus-lg" />
                Add info block
            </button>
        </div>
    </NodeViewWrapper>
</template>
