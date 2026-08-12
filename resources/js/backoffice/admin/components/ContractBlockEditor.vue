<script setup>
import { onMounted, ref } from 'vue'
import draggable from 'vuedraggable'
import api from '../composables/useAdminApi'

const props = defineProps({
    modelValue: { type: Object, default: () => ({ blocks: [] }) },
    fields: { type: Array, default: () => [] },
    disabled: Boolean,
})
const emit = defineEmits(['update:modelValue'])
const clauses = ref([])

onMounted(async () => {
    clauses.value = (await api.get('/contract-clauses')).data
})

const blocks = () => props.modelValue?.blocks || []
const set = items => emit('update:modelValue', { blocks: items })

function add(type) {
    set([
        ...blocks(),
        {
            type,
            content: type === 'page_break' ? '' : 'New block',
            ...(type === 'conditional'
                ? { conditions: [{ field: props.fields[0]?.key || '', operator: 'checked' }], blocks: [] }
                : {}),
        },
    ])
}

function remove(index) {
    const copy = [...blocks()]
    copy.splice(index, 1)
    set(copy)
}

function duplicate(index) {
    const copy = [...blocks()]
    copy.splice(index + 1, 0, structuredClone(copy[index]))
    set(copy)
}

function insert(clause) {
    const version = clause.versions?.[0]
    if (version) set([...blocks(), ...structuredClone(version.content.blocks || [])])
}
</script>

<template>
    <div class="space-y-4">
        <draggable :model-value="blocks()" item-key="content" handle=".drag-handle" :disabled="disabled" @update:model-value="set">
            <template #item="{ element, index }">
                <article class="mb-3 border border-dark bg-light p-4">
                    <div class="flex items-center gap-3">
                        <button type="button" class="drag-handle" aria-label="Drag block">☰</button>
                        <select v-model="element.type" :disabled="disabled" class="border p-2">
                            <option v-for="type in ['heading', 'paragraph', 'clause', 'information', 'bullet_list', 'numbered_list', 'conditional', 'page_break']" :key="type">{{ type }}</option>
                        </select>
                        <span class="flex-1"></span>
                        <button v-if="!disabled" type="button" @click="duplicate(index)">Duplicate</button>
                        <button v-if="!disabled" type="button" @click="remove(index)">Delete</button>
                    </div>
                    <textarea v-if="!['page_break', 'conditional'].includes(element.type)" v-model="element.content" :disabled="disabled" class="mt-3 w-full border p-3" rows="4"></textarea>
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
                            :model-value="{ blocks: element.blocks || [] }"
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
