<script setup>
import {
    nextTick,
    onMounted,
    ref,
    watch
} from 'vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'


const props =
    defineProps({
        template: {
            type: Object,
            default: null
        },

        loading: {
            type: Boolean,
            default: false
        },

        saving: {
            type: Boolean,
            default: false
        }
    })


const emit =
    defineEmits([
        'close',
        'save'
    ])


const title =
    ref('')


const content =
    ref('')


const editor =
    ref(null)


const saved =
    ref(false)


watch(
    () => props.template,
    value => {
        title.value =
            value?.name ||
            'Untitled document'


        content.value =
            value?.content ||
            ''
    },
    {
        immediate: true
    }
)


function focusEditor() {
    nextTick(() => {
        editor.value?.focus()
    })
}


function insertText(
    text
) {
    const element =
        editor.value


    if (
        !element
    ) {
        return
    }


    element.focus()


    const selection =
        window.getSelection()


    if (
        !selection ||
        !selection.rangeCount
    ) {
        content.value +=
            text

        return
    }


    const range =
        selection.getRangeAt(
            0
        )


    range.deleteContents()


    const node =
        document.createTextNode(
            text
        )


    range.insertNode(
        node
    )


    range.setStartAfter(
        node
    )


    range.collapse(
        true
    )


    selection.removeAllRanges()


    selection.addRange(
        range
    )


    syncContent()
}


function format(
    command
) {
    editor.value?.focus()


    document.execCommand(
        command,
        false
    )


    syncContent()
}


function insertVariable() {
    insertText(
        '{{ client.name }}'
    )
}


function insertHeading() {
    editor.value?.focus()


    document.execCommand(
        'formatBlock',
        false,
        'h2'
    )


    syncContent()
}


function insertParagraph() {
    editor.value?.focus()


    document.execCommand(
        'formatBlock',
        false,
        'p'
    )


    syncContent()
}


function syncContent() {
    if (
        editor.value
    ) {
        content.value =
            editor.value.innerHTML
    }
}


function save() {
    emit(
        'save',
        {
            ...(props.template || {}),

            name:
                title.value.trim() ||
                'Untitled document',

            content:
                content.value
        }
    )
}


function close() {
    emit(
        'close'
    )
}


onMounted(
    focusEditor
)
</script>


<template>
    <div
        class="
            fixed
            inset-0
            z-[60]
            flex
            flex-col
            bg-light
        "
    >
        <!-- Header -->
        <header
            class="
                flex
                min-h-16
                shrink-0
                items-center
                justify-between
                gap-5
                border-b
                border-accent
                px-4
                sm:px-6
            "
        >
            <div
                class="
                    flex
                    min-w-0
                    flex-1
                    items-center
                    gap-5
                "
            >
                <button
                    type="button"
                    class="
                        shrink-0
                        font-mono
                        text-xs
                        font-bold
                        uppercase
                        text-dark/50
                        transition-colors
                        hover:text-accent
                    "
                    @click="
                        close
                    "
                >
                    ← back
                </button>


                <span
                    class="
                        hidden
                        h-5
                        w-px
                        bg-accent
                        sm:block
                    "
                />


                <div
                    class="
                        min-w-0
                        flex-1
                        max-w-xl
                    "
                >
                    <input
                        v-model="
                            title
                        "
                        type="text"
                        class="
                            w-full
                            border-0
                            bg-transparent
                            font-mono
                            text-sm
                            font-bold
                            outline-none
                            placeholder:text-dark/30
                        "
                        placeholder="Document title"
                    >
                </div>
            </div>


            <div
                class="
                    flex
                    shrink-0
                    items-center
                    gap-4
                "
            >
                <span
                    v-if="
                        saved
                    "
                    class="
                        hidden
                        font-mono
                        text-xs
                        uppercase
                        text-dark/40
                        sm:block
                    "
                >
                    Saved
                </span>


                <Button
                    type="button"
                    text="save"
                    variant="accent"
                    align="left"
                    :loading="
                        saving
                    "
                    :disabled="
                        saving
                    "
                    @click="
                        save
                    "
                />
            </div>
        </header>


        <!-- Toolbar -->
        <div
            class="
                flex
                shrink-0
                items-center
                gap-1
                overflow-x-auto
                border-b
                border-accent/20
                px-4
                py-3
                sm:px-6
            "
        >
            <button
                type="button"
                class="
                    px-3
                    py-2
                    font-mono
                    text-xs
                    font-bold
                    hover:bg-accent
                    hover:text-light
                "
                @click="
                    format(
                        'bold'
                    )
                "
            >
                Bold
            </button>


            <button
                type="button"
                class="
                    px-3
                    py-2
                    font-mono
                    text-xs
                    font-bold
                    italic
                    hover:bg-accent
                    hover:text-light
                "
                @click="
                    format(
                        'italic'
                    )
                "
            >
                Italic
            </button>


            <button
                type="button"
                class="
                    px-3
                    py-2
                    font-mono
                    text-xs
                    font-bold
                    hover:bg-accent
                    hover:text-light
                "
                @click="
                    insertHeading
                "
            >
                Heading
            </button>


            <button
                type="button"
                class="
                    px-3
                    py-2
                    font-mono
                    text-xs
                    font-bold
                    hover:bg-accent
                    hover:text-light
                "
                @click="
                    insertParagraph
                "
            >
                Text
            </button>


            <button
                type="button"
                class="
                    px-3
                    py-2
                    font-mono
                    text-xs
                    font-bold
                    hover:bg-accent
                    hover:text-light
                "
                @click="
                    format(
                        'insertUnorderedList'
                    )
                "
            >
                List
            </button>


            <span
                class="
                    mx-2
                    h-5
                    w-px
                    bg-accent/20
                "
            />


            <button
                type="button"
                class="
                    px-3
                    py-2
                    font-mono
                    text-xs
                    font-bold
                    text-accent
                    hover:bg-accent
                    hover:text-light
                "
                @click="
                    insertVariable
                "
            >
                + Variable
            </button>
        </div>


        <!-- Editor -->
        <main
            class="
                min-h-0
                flex-1
                overflow-y-auto
                bg-neutral-50
                px-4
                py-8
                sm:px-8
                sm:py-12
            "
        >
            <div
                class="
                    mx-auto
                    w-full
                    max-w-3xl
                "
            >
                <div
                    ref="editor"
                    contenteditable="true"
                    spellcheck="true"
                    class="
                        min-h-[70vh]
                        bg-light
                        px-6
                        py-10
                        text-base
                        leading-8
                        shadow-sm
                        outline-none
                        sm:px-16
                        sm:py-16
                    "
                    @input="
                        syncContent
                    "
                    @keydown.ctrl.s.prevent="
                        save
                    "
                    @keydown.meta.s.prevent="
                        save
                    "
                    v-html="
                        content
                    "
                />
            </div>
        </main>


        <!-- Variable hint -->
        <div
            class="
                hidden
                border-t
                border-accent/20
                bg-light
                px-6
                py-3
                text-center
                sm:block
            "
        >
            <p
                class="
                    font-mono
                    text-[10px]
                    uppercase
                    text-dark/40
                "
            >
                Use variables to automatically insert client, project and service information.
            </p>
        </div>
    </div>
</template>