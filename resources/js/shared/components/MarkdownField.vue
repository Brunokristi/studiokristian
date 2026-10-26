<script setup>
import { nextTick, ref, watch } from 'vue'
import MarkdownContent from '@shared/components/MarkdownContent.vue'


const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    id: {
        type: String,
        required: true
    },
    name: {
        type: String,
        default: ''
    },
    label: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: ''
    },
    required: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    }
})


const emit = defineEmits(['update:modelValue'])

const textareaRef = ref(null)
const mode = ref('write')

const toolbar = [
    { key: 'heading', label: 'H', title: 'Heading', icon: null },
    { key: 'bold', label: 'B', title: 'Bold', icon: 'bi-type-bold' },
    { key: 'italic', label: 'I', title: 'Italic', icon: 'bi-type-italic' },
    { key: 'bullets', label: '•', title: 'Bullet list', icon: 'bi-list-ul' },
    { key: 'numbers', label: '1.', title: 'Numbered list', icon: 'bi-list-ol' },
    { key: 'checklist', label: '☐', title: 'Checklist', icon: 'bi-check2-square' },
    { key: 'link', label: '🔗', title: 'Link', icon: 'bi-link-45deg' }
]


function resize() {
    nextTick(() => {
        const el = textareaRef.value

        if (!el) {
            return
        }

        el.style.height = 'auto'
        el.style.height = `${Math.max(el.scrollHeight, 96)}px`
    })
}


watch(() => props.modelValue, resize, { immediate: true })
watch(mode, value => {
    if (value === 'write') {
        resize()
    }
})


function update(value, selectionStart, selectionEnd) {
    emit('update:modelValue', value)

    nextTick(() => {
        const el = textareaRef.value

        if (!el) {
            return
        }

        el.focus()
        el.setSelectionRange(selectionStart, selectionEnd)
    })
}


function wrapSelection(before, after = before, fallback = 'text') {
    const el = textareaRef.value
    const value = props.modelValue || ''
    const start = el?.selectionStart ?? value.length
    const end = el?.selectionEnd ?? value.length
    const selected = value.slice(start, end) || fallback

    update(
        value.slice(0, start) + before + selected + after + value.slice(end),
        start + before.length,
        start + before.length + selected.length
    )
}


function prefixLines(prefixFor) {
    const el = textareaRef.value
    const value = props.modelValue || ''
    const start = el?.selectionStart ?? value.length
    const end = el?.selectionEnd ?? value.length
    const lineStart = value.lastIndexOf('\n', start - 1) + 1
    const lineEndIndex = value.indexOf('\n', end)
    const lineEnd = lineEndIndex === -1 ? value.length : lineEndIndex

    const lines = value.slice(lineStart, lineEnd).split('\n')
    const replaced = lines
        .map((line, index) => prefixFor(index) + line)
        .join('\n')

    update(
        value.slice(0, lineStart) + replaced + value.slice(lineEnd),
        lineStart,
        lineStart + replaced.length
    )
}


function applyFormat(key) {
    if (props.disabled) {
        return
    }

    mode.value = 'write'

    nextTick(() => {
        switch (key) {
            case 'heading':
                return prefixLines(() => '## ')
            case 'bold':
                return wrapSelection('**')
            case 'italic':
                return wrapSelection('_')
            case 'bullets':
                return prefixLines(() => '- ')
            case 'numbers':
                return prefixLines(index => `${index + 1}. `)
            case 'checklist':
                return prefixLines(() => '- [ ] ')
            case 'link':
                return wrapSelection('[', '](https://)', 'link text')
        }
    })
}


// Continue lists when pressing Enter at the end of a list item.
function handleKeydown(event) {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
        return
    }

    const el = event.target
    const value = props.modelValue || ''
    const start = el.selectionStart

    if (start !== el.selectionEnd) {
        return
    }

    const lineStart = value.lastIndexOf('\n', start - 1) + 1
    const line = value.slice(lineStart, start)
    const match = line.match(/^(\s*)([-*+] \[[ xX]\] |[-*+] |(\d+)([.)]) )/)

    if (!match) {
        return
    }

    event.preventDefault()

    // An empty list item ends the list.
    if (line.trim() === match[2].trim()) {
        update(value.slice(0, lineStart) + value.slice(start), lineStart, lineStart)
        return
    }

    let marker = match[2]

    if (match[3]) {
        marker = `${Number(match[3]) + 1}${match[4]} `
    } else if (/\[[ xX]\]/.test(marker)) {
        marker = marker.replace(/\[[xX]\]/, '[ ]')
    }

    const insert = `\n${match[1]}${marker}`

    update(
        value.slice(0, start) + insert + value.slice(start),
        start + insert.length,
        start + insert.length
    )
}
</script>


<template>
    <div>
        <div
            class="mb-2 flex flex-wrap items-center justify-between gap-3"
        >
            <label
                v-if="label"
                :for="id"
                class="p uppercase"
            >
                {{ label }}

                <span
                    v-if="required"
                    class="text-accent"
                    aria-hidden="true"
                >
                    *
                </span>
            </label>

            <div
                class="flex items-center gap-3 text-[10px] uppercase"
                role="tablist"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="mode === 'write'"
                    :class="mode === 'write' ? 'text-accent' : 'text-dark/40 hover:text-dark'"
                    @click="mode = 'write'"
                >
                    Write
                </button>

                <button
                    type="button"
                    role="tab"
                    :aria-selected="mode === 'preview'"
                    :class="mode === 'preview' ? 'text-accent' : 'text-dark/40 hover:text-dark'"
                    @click="mode = 'preview'"
                >
                    Preview
                </button>
            </div>
        </div>

        <textarea
            v-show="mode === 'write'"
            :id="id"
            ref="textareaRef"
            :name="name"
            :value="modelValue"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled"
            rows="4"
            class="p block w-full resize-none appearance-none overflow-hidden border-0 border-b border-dark bg-transparent px-0 py-0 font-mono leading-6 text-dark outline-none transition-colors duration-200 placeholder:text-dark/30 focus:border-accent focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50"
            :class="{ 'border-red-600': error }"
            @input="emit('update:modelValue', $event.target.value)"
            @keydown="handleKeydown"
        />

        <div
            v-if="mode === 'preview'"
            class="min-h-24 border-b border-dark pb-2"
        >
            <MarkdownContent
                v-if="modelValue?.trim()"
                :source="modelValue"
            />

            <p
                v-else
                class="p text-dark/30"
            >
                Nothing to preview yet.
            </p>
        </div>

                <div
            class="mb-2 flex flex-wrap gap-4"
            role="toolbar"
            :aria-label="`${label || 'Text'} formatting`"
        >
            <button
                v-for="item in toolbar"
                :key="item.key"
                type="button"
                :title="item.title"
                :aria-label="item.title"
                :disabled="disabled"
                class="flex h-7 items-center justify-center font-mono text-xs font-bold text-dark transition-colors hover:border-accent hover:text-accent disabled:opacity-40"
                @mousedown.prevent
                @click="applyFormat(item.key)"
            >
                <i
                    v-if="item.icon"
                    :class="['bi', item.icon]"
                    aria-hidden="true"
                />

                <span v-else>
                    {{ item.label }}
                </span>
            </button>
        </div>

        <p
            v-if="error"
            class="p mt-2 text-red-600"
        >
            {{ error }}
        </p>
    </div>
</template>
