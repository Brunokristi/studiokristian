<script setup>
import {
    computed,
    onMounted,
    ref,
    watch
} from 'vue'
import draggable from 'vuedraggable'
import api from '../composables/useAdminApi'

const props = defineProps({
    modelValue: { type: Object, default: () => ({ version: 1, blocks: [] }) },
    fields: { type: Array, default: () => [] },
    disabled: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const clauses = ref([])
const selectedBlockId = ref(null)
const openInsertMenuId = ref(null)
const isHydrating = ref(false)

onMounted(async () => {
    clauses.value = (await api.get('/contract-clauses')).data
})

const documentModel = ref(normalizeDocument(props.modelValue))


const blocks = computed(() => documentModel.value.blocks)


watch(
    () => props.modelValue,
    value => {
        isHydrating.value = true
        documentModel.value = normalizeDocument(value)
        selectedBlockId.value =
            selectedBlockId.value && documentModel.value.blocks.some(block => block.id === selectedBlockId.value)
                ? selectedBlockId.value
                : documentModel.value.blocks[0]?.id || null

        queueMicrotask(() => {
            isHydrating.value = false
        })
    },
    {
        immediate: true,
        deep: true
    }
)


watch(
    documentModel,
    value => {
        if (isHydrating.value) {
            return
        }

        emit('update:modelValue', normalizeDocument(value))
    },
    {
        deep: true
    }
)


function newId() {
    return `block_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`
}


function normalizeBlock(block) {
    const normalized = {
        id:
            typeof block?.id === 'string' && block.id.trim()
                ? block.id.trim()
                : newId(),

        type:
            block?.type ||
            'paragraph',

        content:
            block?.content ||
            '',

        level:
            block?.level ||
            2,

        items:
            Array.isArray(block?.items)
                ? block.items.map(item => String(item || '').trim()).filter(Boolean)
                : [],

        rows:
            Array.isArray(block?.rows)
                ? block.rows.map(row => Array.isArray(row) ? row.map(cell => String(cell || '')) : [])
                : [],

        conditions:
            Array.isArray(block?.conditions)
                ? block.conditions.map(condition => ({
                    field: String(condition?.field || ''),
                    operator: condition?.operator || 'checked',
                    value: condition?.value ?? null,
                }))
                : [],

        mode:
            block?.mode ||
            'all',

        blocks:
            Array.isArray(block?.blocks)
                ? block.blocks.map(child => normalizeBlock(child))
                : [],

        key:
            block?.key ||
            '',

        data:
            block?.data && typeof block.data === 'object' && !Array.isArray(block.data)
                ? structuredClone(block.data)
                : {},

        meta:
            block?.meta && typeof block.meta === 'object' && !Array.isArray(block.meta)
                ? structuredClone(block.meta)
                : {}
    }

    if (
        normalized.type === 'conditional' &&
        !normalized.conditions.length
    ) {
        normalized.conditions = [
            {
                field: props.fields[0]?.key || '',
                operator: 'checked',
                value: null,
            }
        ]
    }

    if (
        (normalized.type === 'bullet_list' || normalized.type === 'numbered_list') &&
        !normalized.items.length &&
        normalized.content
    ) {
        normalized.items = String(normalized.content)
            .split(/\r?\n+/)
            .map(item => item.trim())
            .filter(Boolean)
    }

    if (normalized.type === 'page_break') {
        normalized.content = ''
    }

    return normalized
}


function normalizeDocument(value) {
    const blocks = Array.isArray(value?.blocks)
        ? value.blocks.map(normalizeBlock)
        : []

    return {
        version: Number(value?.version) || 1,
        blocks: blocks.length ? blocks : [normalizeBlock({ type: 'paragraph' })]
    }
}


function cloneBlock(block, freshId = false) {
    const cloned = normalizeBlock(block)

    if (freshId) {
        cloned.id = newId()
    }

    cloned.blocks = (cloned.blocks || []).map(child => cloneBlock(child, freshId))

    return cloned
}


function syncDocument(nextBlocks) {
    documentModel.value = {
        ...documentModel.value,
        blocks: nextBlocks.map(normalizeBlock)
    }
}


function setActive(blockId) {
    selectedBlockId.value = blockId
}


function blockIndex(blockId) {
    return blocks.value.findIndex(block => block.id === blockId)
}


function insertAt(index, type = 'paragraph') {
    const next = [...blocks.value]
    const block = normalizeBlock({
        type,
        ...(type === 'conditional'
            ? { conditions: [{ field: props.fields[0]?.key || '', operator: 'checked' }], blocks: [] }
            : {}),
    })

    next.splice(index, 0, block)
    syncDocument(next)
    setActive(block.id)
    openInsertMenuId.value = null
}


function add(type) {
    const index =
        selectedBlockId.value !== null && blockIndex(selectedBlockId.value) !== -1
            ? blockIndex(selectedBlockId.value) + 1
            : blocks.value.length

    insertAt(index, type)
}


function insertAfter(blockId, type = 'paragraph') {
    const index = blockIndex(blockId)

    if (index === -1) {
        insertAt(blocks.value.length, type)
        return
    }

    insertAt(index + 1, type)
}

function remove(index) {
    const copy = [...blocks.value]
    copy.splice(index, 1)

    syncDocument(copy.length ? copy : [normalizeBlock({ type: 'paragraph' })])
    selectedBlockId.value = copy[Math.min(index, copy.length - 1)]?.id || null
}

function duplicate(index) {
    const copy = [...blocks.value]
    const block = cloneBlock(copy[index], true)
    copy.splice(index + 1, 0, block)
    syncDocument(copy)
    selectedBlockId.value = block.id
}

function insert(clause) {
    const version = clause.versions?.[0]
    if (version) {
        const copy = [...blocks.value]
        const insertIndex =
            selectedBlockId.value !== null && blockIndex(selectedBlockId.value) !== -1
                ? blockIndex(selectedBlockId.value) + 1
                : copy.length

        const additions = (version.content?.blocks || []).map(block => cloneBlock(block, true))
        copy.splice(insertIndex, 0, ...additions)
        syncDocument(copy)
        selectedBlockId.value = additions[0]?.id || selectedBlockId.value
    }
}


function toggleInsertMenu(blockId) {
    openInsertMenuId.value =
        openInsertMenuId.value === blockId
            ? null
            : blockId
}


function updateType(element, type) {
    element.type = type

    if (type === 'conditional') {
        element.conditions = element.conditions?.length
            ? element.conditions
            : [{ field: props.fields[0]?.key || '', operator: 'checked' }]
        element.blocks = Array.isArray(element.blocks) ? element.blocks : []
    }

    if (type === 'bullet_list' || type === 'numbered_list') {
        element.items = Array.isArray(element.items) ? element.items : String(element.content || '').split(/\r?\n+/).map(item => item.trim()).filter(Boolean)
        element.content = element.items.join('\n')
    }

    if (type === 'page_break') {
        element.content = ''
    }
}


function listText(element) {
    return Array.isArray(element.items) && element.items.length
        ? element.items.join('\n')
        : String(element.content || '')
}


function updateListText(element, value) {
    const items = String(value || '')
        .split(/\r?\n+/)
        .map(item => item.trim())
        .filter(Boolean)

    element.items = items
    element.content = items.join('\n')
}
</script>

<template>
    <div class="space-y-4">
        <draggable :model-value="blocks" item-key="id" handle=".drag-handle" :disabled="disabled" @update:model-value="syncDocument">
            <template #item="{ element, index }">
                <article class="mb-3 border border-dark bg-light p-4" @click="setActive(element.id)">
                    <div class="flex items-center gap-3">
                        <button type="button" class="drag-handle" aria-label="Drag block">☰</button>
                        <button type="button" class="text-sm uppercase" @click.stop="toggleInsertMenu(element.id)">+</button>
                        <select :value="element.type" :disabled="disabled" class="border p-2" @change="updateType(element, $event.target.value)">
                            <option v-for="type in ['heading', 'paragraph', 'clause', 'information', 'bullet_list', 'numbered_list', 'conditional', 'page_break']" :key="type">{{ type }}</option>
                        </select>
                        <span class="flex-1"></span>
                        <button v-if="!disabled" type="button" @click="duplicate(index)">Duplicate</button>
                        <button v-if="!disabled" type="button" @click="remove(index)">Delete</button>
                    </div>
                    <div v-if="openInsertMenuId === element.id" class="mt-3 flex flex-wrap gap-2">
                        <button v-for="type in ['heading', 'paragraph', 'clause', 'conditional', 'bullet_list', 'page_break']" :key="type" type="button" class="border px-3 py-1 text-sm" @click.stop="insertAfter(element.id, type)">+ {{ type }}</button>
                    </div>
                    <textarea v-if="!['page_break', 'conditional'].includes(element.type)" v-model="element.content" :disabled="disabled" class="mt-3 w-full border p-3" rows="4"></textarea>
                    <textarea v-if="['bullet_list', 'numbered_list'].includes(element.type)" :value="listText(element)" :disabled="disabled" class="mt-3 w-full border p-3" rows="4" placeholder="One item per line" @input="updateListText(element, $event.target.value)"></textarea>
                    <div v-if="element.type === 'conditional'" class="mt-3 grid gap-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="admin-field">
                                <label>Field</label>
                                <select v-model="element.conditions[0].field" :disabled="disabled">
                                    <option v-for="field in fields" :key="field.key" :value="field.key">{{ field.label }}</option>
                                </select>
                            </div>
                            <div class="admin-field">
                                <label>Condition</label>
                                <select v-model="element.conditions[0].operator" :disabled="disabled">
                                    <option value="checked">is checked</option>
                                    <option value="unchecked">is unchecked</option>
                                    <option value="equals">equals</option>
                                    <option value="not_equals">does not equal</option>
                                </select>
                            </div>
                        </div>
                        <ContractBlockEditor
                            :model-value="{ version: 1, blocks: element.blocks || [] }"
                            :fields="fields"
                            :disabled="disabled"
                            @update:model-value="element.blocks = $event.blocks"
                        />
                    </div>
                </article>
            </template>
        </draggable>
        <div v-if="!disabled" class="flex flex-wrap gap-2">
            <button v-for="type in ['heading', 'paragraph', 'clause', 'conditional', 'bullet_list', 'page_break']" :key="type" type="button" class="admin-button" @click="add(type)">+ {{ type }}</button>
        </div>
        <details class="border border-dark bg-light p-4">
            <summary class="font-mono text-sm font-bold uppercase">Clause library</summary>
            <button v-for="clause in clauses" :key="clause.id" type="button" class="mt-3 mr-2 border p-3 text-left" :disabled="disabled" @click="insert(clause)">
                {{ clause.name }}
                <small class="block">{{ clause.category }}</small>
            </button>
            <p v-if="!clauses.length" class="mt-3 text-sm">No published clauses yet.</p>
        </details>
    </div>
</template>
