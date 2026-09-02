```vue
<script setup>
import {
    computed,
    nextTick,
    onMounted,
    ref,
    watch
} from 'vue'


const props = defineProps({
    heading: {
        type: String,
        default: 'New feature'
    },

    text: {
        type: String,
        default: 'Add your feature description here.'
    },

    color: {
        type: String,
        default: 'dark'
    },

    editable: {
        type: Boolean,
        default: false
    },

    index: {
        type: Number,
        default: -1
    },

    draggable: {
        type: Boolean,
        default: true
    },

    opened: {
        type: Boolean,
        default: false
    }
})


const emit = defineEmits([
    'update:heading',
    'update:text',
    'move-up',
    'move-down',
    'remove',
    'drag-start',
    'drag-over',
    'drop',
    'drag-end'
])


const isOpen = ref(props.opened)

const headingElement = ref(null)
const textElement = ref(null)

const isDragging = ref(false)
const isDragOver = ref(false)
const isEditingFocused = ref(false)
const isHovering = ref(false)


const DEFAULT_HEADING =
    'New feature'

const DEFAULT_TEXT =
    'Add your feature description here.'


const iconClass = computed(() => {
    return isOpen.value
        ? 'rotate-180'
        : 'rotate-0'
})


const headingColorClass = computed(() => {
    if (props.color === 'light') {
        return 'text-light'
    }

    if (props.color === 'accent') {
        return 'text-accent'
    }

    return 'text-dark'
})


/*
|--------------------------------------------------------------------------
| Collapse / expand
|--------------------------------------------------------------------------
*/

function toggleOpen() {
    isOpen.value =
        !isOpen.value
}

watch(
    () => props.opened,
    value => {
        isOpen.value = Boolean(value)
    }
)


/*
|--------------------------------------------------------------------------
| Content editing
|--------------------------------------------------------------------------
|
| Do NOT put {{ heading }} or {{ text }} inside contenteditable.
| Vue will otherwise patch the DOM while the browser is editing it,
| which causes the mirrored/duplicated typing behaviour.
|
*/

function handleHeadingInput(event) {
    emit(
        'update:heading',
        event.currentTarget.textContent || ''
    )
}


function handleEditingFocus() {
    isEditingFocused.value =
        true
}


function handleEditingBlur() {
    requestAnimationFrame(() => {
        const active =
            document.activeElement

        const headingActive =
            headingElement.value &&
            active ===
                headingElement.value

        const textActive =
            textElement.value &&
            active ===
                textElement.value

        if (
            headingActive ||
            textActive
        ) {
            return
        }

        isEditingFocused.value =
            false
    })
}


const showEditorControls = computed(() => {
    return props.editable
})


function handleTextInput(event) {
    emit(
        'update:text',
        event.currentTarget.innerText ?? ''
    )
}


function handleHeadingKeydown(event) {
    if (
        event.key !== 'Enter'
    ) {
        return
    }

    event.preventDefault()

    textElement.value?.focus()
}


/*
|--------------------------------------------------------------------------
| Keep contenteditable DOM in sync
|--------------------------------------------------------------------------
*/

async function syncHeading() {
    if (
        !headingElement.value
    ) {
        return
    }

    if (
        document.activeElement ===
        headingElement.value
    ) {
        return
    }

    await nextTick()

    const value =
        props.heading ||
        DEFAULT_HEADING

    if (!headingElement.value) {
        return
    }

    if (
        headingElement.value.textContent !==
        value
    ) {
        headingElement.value.textContent =
            value
    }
}


async function syncText() {
    if (
        !textElement.value
    ) {
        return
    }

    if (
        document.activeElement ===
        textElement.value
    ) {
        return
    }

    await nextTick()

    const value =
        props.text ||
        DEFAULT_TEXT

    if (!textElement.value) {
        return
    }

    if (
        textElement.value.textContent !==
        value
    ) {
        textElement.value.textContent =
            value
    }
}


watch(
    () => props.heading,
    () => {
        syncHeading()
    }
)


watch(
    () => props.text,
    () => {
        syncText()
    }
)


/*
|--------------------------------------------------------------------------
| Initialise editor content
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    await nextTick()

    if (
        props.editable
    ) {
        if (
            !props.heading
        ) {
            emit(
                'update:heading',
                DEFAULT_HEADING
            )
        }

        if (
            !props.text
        ) {
            emit(
                'update:text',
                DEFAULT_TEXT
            )
        }
    }

    syncHeading()
    syncText()
})


/*
|--------------------------------------------------------------------------
| Move buttons
|--------------------------------------------------------------------------
*/

function handleMoveUp() {
    emit('move-up')
}


function handleMoveDown() {
    emit('move-down')
}


function handleRemove() {
    emit('remove')
}


/*
|--------------------------------------------------------------------------
| Drag & drop
|--------------------------------------------------------------------------
*/

function handleDragStart(event) {
    if (
        !props.editable ||
        !props.draggable
    ) {
        event.preventDefault()
        return
    }

    isDragging.value = true

    event.dataTransfer.effectAllowed =
        'move'

    event.dataTransfer.setData(
        'text/plain',
        String(props.index)
    )

    emit(
        'drag-start',
        {
            index: props.index
        }
    )
}


function handleDragOver(event) {
    if (
        !props.editable ||
        !props.draggable
    ) {
        return
    }

    event.preventDefault()

    event.dataTransfer.dropEffect =
        'move'

    isDragOver.value = true

    emit(
        'drag-over',
        {
            index: props.index
        }
    )
}


function handleDragLeave() {
    isDragOver.value = false
}


function handleDrop(event) {
    if (
        !props.editable ||
        !props.draggable
    ) {
        return
    }

    event.preventDefault()

    isDragOver.value = false

    const sourceIndex =
        Number(
            event.dataTransfer.getData(
                'text/plain'
            )
        )

    const targetIndex =
        props.index

    if (
        Number.isNaN(sourceIndex) ||
        sourceIndex < 0 ||
        targetIndex < 0 ||
        sourceIndex === targetIndex
    ) {
        return
    }

    emit(
        'drop',
        {
            from: sourceIndex,
            to: targetIndex
        }
    )
}


function handleDragEnd() {
    isDragging.value = false
    isDragOver.value = false

    emit('drag-end')
}
</script>


<template>
    <!-- ========================================================= -->
    <!-- EDITOR -->
    <!-- ========================================================= -->

    <div
        v-if="editable"
        class="
            relative
            w-full
            border-t
            border-light
            bg-accent
            text-light
            transition-all
            duration-200
        "
        :class="[
            isDragging
                ? 'opacity-40'
                : '',

            isDragOver
                ? 'border-t-2 border-light'
                : ''
        ]"
        :draggable="
            draggable
        "
        @mouseenter="
            isHovering = true
        "
        @mouseleave="
            isHovering = false
        "
        @dragstart="
            handleDragStart
        "
        @dragover="
            handleDragOver
        "
        @dragleave="
            handleDragLeave
        "
        @drop="
            handleDrop
        "
        @dragend="
            handleDragEnd
        "
    >
        <div
            class="
                flex
                w-full
                items-start
                justify-between
                gap-6
                px-4
                py-3
            "
        >
            <!-- Content -->
            <div
                class="
                    min-w-0
                    flex-1
                "
            >
                <!-- Heading -->
                <h3
                    ref="headingElement"
                    contenteditable="true"
                    spellcheck="true"
                    class="
                        h3
                        min-h-[1.5em]
                        cursor-text
                        text-light
                        outline-none
                    "
                    @input="
                        handleHeadingInput
                    "
                    @keydown="
                        handleHeadingKeydown
                    "
                    @focus="
                        handleEditingFocus
                    "
                    @blur="
                        handleEditingBlur
                    "
                    @mousedown.stop
                    @click.stop
                />


                <!-- Text -->
                <transition
                    name="accordion"
                >
                    <div
                        v-show="
                            isOpen
                        "
                        class="
                            accordion-content
                            pr-12
                        "
                    >
                        <p
                            ref="textElement"
                            contenteditable="true"
                            spellcheck="true"
                            class="
                                p
                                mt-3
                                min-h-[1.5em]
                                cursor-text
                                whitespace-pre-wrap
                                text-light
                                outline-none
                            "
                            @input="
                                handleTextInput
                            "
                            @focus="
                                handleEditingFocus
                            "
                            @blur="
                                handleEditingBlur
                            "
                            @mousedown.stop
                            @click.stop
                        />
                    </div>
                </transition>
            </div>


            <!-- Editor controls -->
            <div
                class="
                    flex
                    shrink-0
                    items-center
                    gap-3
                    pt-1
                    transition-opacity
                    duration-150
                "
            >
                <!-- Drag handle -->
                <button
                    v-if="
                        draggable &&
                        showEditorControls
                    "
                    type="button"
                    class="
                        cursor-grab
                        text-light
                        transition-colors
                        active:cursor-grabbing
                    "
                    aria-label="Drag item"
                    draggable="true"
                    @mousedown.stop
                >
                    <i
                        class="
                            bi
                            bi-grip-vertical
                        "
                    />
                </button>

                <!-- Remove -->
                <button
                    v-if="
                        showEditorControls
                    "
                    type="button"
                    class="
                        cursor-pointer
                        text-light
                        transition-colors
                    "
                    aria-label="Remove item"
                    @click="
                        handleRemove
                    "
                >
                    <i
                        class="
                            bi
                            bi-eraser
                        "
                    />
                </button>

                <!-- Accordion arrow -->
                <button
                    type="button"
                    class="
                        shrink-0
                        cursor-pointer
                        text-light
                        transition-transform
                        duration-300
                    "
                    :class="
                        iconClass
                    "
                    aria-label="Collapse or expand"
                    :aria-expanded="
                        isOpen
                    "
                    @click="
                        toggleOpen
                    "
                >
                    <i
                        class="
                            bi
                            bi-arrow-down
                        "
                    />
                </button>
            </div>
        </div>
    </div>


    <!-- ========================================================= -->
    <!-- PUBLIC -->
    <!-- ========================================================= -->

    <div
        v-else
        class="
            w-full
            border-t
            last:border-b
            transition-colors
            duration-300
        "
        :class="[
            isOpen
                ? 'border-light bg-accent text-light'
                : 'border-accent bg-transparent hover:border-light hover:bg-accent hover:text-light',

            !isOpen
                ? headingColorClass
                : ''
        ]"
    >
        <button
            type="button"
            class="
                flex
                w-full
                cursor-pointer
                items-center
                justify-between
                gap-6
                px-4
                py-3
                text-left
            "
            :aria-expanded="
                isOpen
            "
            @click="
                toggleOpen
            "
        >
            <h3 class="h3">
                {{ heading }}
            </h3>


            <span
                class="
                    shrink-0
                    transition-transform
                    duration-300
                "
                :class="
                    iconClass
                "
            >
                <i
                    class="
                        bi
                        bi-arrow-down
                    "
                />
            </span>
        </button>


        <transition
            name="accordion"
        >
            <div
                v-show="
                    isOpen
                "
                class="
                    accordion-content
                    pb-6
                    pl-4
                    pr-12
                "
            >
                <p class="p whitespace-pre-wrap">
                    {{ text }}
                </p>
            </div>
        </transition>
    </div>
</template>


<style scoped>
.accordion-content {
    overflow: hidden;
}

.accordion-enter-active,
.accordion-leave-active {
    transition:
        max-height 0.3s ease,
        opacity 0.3s ease;
}

.accordion-enter-from,
.accordion-leave-to {
    max-height: 0;
    opacity: 0;
}

.accordion-enter-to,
.accordion-leave-from {
    max-height: 300px;
    opacity: 1;
}

[contenteditable='true']:focus {
    outline: none;
}
</style>
```
