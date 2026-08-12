<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import draggable from 'vuedraggable'
import api, { errorMessage, validationErrors } from '../../composables/useAdminApi'
import AdminPageHeader from '../../components/AdminPageHeader.vue'

const props = defineProps({ id: String })
const project = ref(null)
const saving = ref(false)
const error = ref('')
const errors = ref({})

async function load() {
    try {
        project.value = (await api.get(`/projects/${props.id}/portfolio`)).data.project
        if (!project.value.images.length) addImage()
        if (!project.value.features.length) addFeature()
    } catch (exception) { error.value = errorMessage(exception) }
}

onMounted(load)

function addImage() {
    project.value.images.push({ path: '', existing_path: '', description: '', description_sk: '', sort_order: project.value.images.length, file: null })
}

function addFeature() {
    project.value.features.push({ title: '', title_sk: '', description: '', description_sk: '', sort_order: project.value.features.length })
}

async function translate(source, target) {
    if (!project.value[source]) return
    const response = await api.post('/portfolio/translate', { text: project.value[source] })
    project.value[target] = response.data.translated
}

function append(body, key, value) {
    if (value !== null && value !== undefined) body.append(key, value)
}

async function save() {
    saving.value = true; error.value = ''; errors.value = {}
    const body = new FormData()
    body.append('_method', 'PUT')
    for (const key of ['company_id', 'service_product_id', 'portal_status', 'name', 'name_sk', 'url', 'live_url', 'summary', 'summary_sk', 'hex_color']) append(body, key, project.value[key] ?? '')
    append(body, 'existing_logo_path', project.value.logo_path || '')
    if (project.value.logo_file) body.append('logo_file', project.value.logo_file)
    project.value.images.forEach((image, index) => {
        append(body, `images[${index}][path]`, image.path || '')
        append(body, `images[${index}][existing_path]`, image.existing_path || '')
        append(body, `images[${index}][description]`, image.description || '')
        append(body, `images[${index}][description_sk]`, image.description_sk || '')
        append(body, `images[${index}][sort_order]`, index)
        if (image.file) body.append(`images[${index}][file]`, image.file)
    })
    project.value.features.forEach((feature, index) => {
        for (const key of ['title', 'title_sk', 'description', 'description_sk']) append(body, `features[${index}][${key}]`, feature[key] || '')
        append(body, `features[${index}][sort_order]`, index)
    })
    try {
        project.value = (await api.post(`/projects/${props.id}/portfolio`, body)).data.project
    } catch (exception) {
        errors.value = validationErrors(exception); error.value = errorMessage(exception)
    } finally { saving.value = false }
}

async function togglePublishing() {
    const response = await api.put(`/projects/${props.id}/publishing`, { is_published: !project.value.is_published })
    project.value.is_published = response.data.data.is_published
}
</script>

<template>
    <div v-if="project" class="space-y-6">
        <AdminPageHeader :title="project.name" eyebrow="Portfolio content">
            <RouterLink class="admin-button" :to="{ name: 'projects.show', params: { id } }">Project workspace</RouterLink>
            <button class="admin-button" :class="project.is_published ? 'bg-accent' : ''" @click="togglePublishing">{{ project.is_published ? 'Published on website' : 'Show on website' }}</button>
            <button class="admin-button bg-dark text-light" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save content' }}</button>
        </AdminPageHeader>
        <p v-if="error" class="border border-red-700 bg-red-50 p-3 text-red-700">{{ error }}</p>

        <section class="border border-dark bg-light p-5">
            <h2 class="font-mono font-bold uppercase">Project identity</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="admin-field"><label>Name (EN)</label><input v-model="project.name"><p v-if="errors.name" class="admin-error">{{ errors.name[0] }}</p></div>
                <div class="admin-field"><div class="flex justify-between"><label>Name (SK)</label><button class="text-xs underline" @click="translate('name', 'name_sk')">Translate</button></div><input v-model="project.name_sk"></div>
                <div class="admin-field"><label>Public slug</label><input v-model="project.url"><p v-if="errors.url" class="admin-error">{{ errors.url[0] }}</p></div>
                <div class="admin-field"><label>Live website URL</label><input v-model="project.live_url" type="url"></div>
                <div class="admin-field"><label>Brand color</label><div class="flex gap-2"><input v-model="project.hex_color"><input v-model="project.hex_color" class="!w-14 !p-1" type="color"></div></div>
                <div class="admin-field"><label>Logo</label><input type="file" accept="image/*" @change="project.logo_file = $event.target.files[0]"><img v-if="project.logo_path" :src="project.logo_path" class="mt-3 h-16 max-w-full object-contain" alt="Current project logo"></div>
                <div class="admin-field"><label>Summary (EN)</label><textarea v-model="project.summary" rows="5"></textarea></div>
                <div class="admin-field"><div class="flex justify-between"><label>Summary (SK)</label><button class="text-xs underline" @click="translate('summary', 'summary_sk')">Translate</button></div><textarea v-model="project.summary_sk" rows="5"></textarea></div>
            </div>
        </section>

        <section class="border border-dark bg-light p-5">
            <div class="flex items-center justify-between"><h2 class="font-mono font-bold uppercase">Project gallery</h2><button class="admin-button" @click="addImage">Add image</button></div>
            <draggable v-model="project.images" item-key="id" handle=".drag-handle" class="mt-5 grid gap-4 lg:grid-cols-2">
                <template #item="{ element, index }"><article class="border border-dark p-4"><div class="flex justify-between"><button class="drag-handle" aria-label="Reorder image">☰</button><button @click="project.images.splice(index, 1)">Remove</button></div><img v-if="element.existing_path" :src="element.existing_path" class="mt-3 aspect-video w-full object-cover" alt="Portfolio image"><div class="admin-field mt-3"><label>Image</label><input type="file" accept="image/*" @change="element.file = $event.target.files[0]"></div><div class="admin-field mt-3"><label>Description (EN)</label><input v-model="element.description"></div><div class="admin-field mt-3"><label>Description (SK)</label><input v-model="element.description_sk"></div></article></template>
            </draggable>
        </section>

        <section class="border border-dark bg-light p-5">
            <div class="flex items-center justify-between"><h2 class="font-mono font-bold uppercase">Features</h2><button class="admin-button" @click="addFeature">Add feature</button></div>
            <draggable v-model="project.features" item-key="id" handle=".drag-handle" class="mt-5 space-y-4">
                <template #item="{ element, index }"><article class="grid gap-4 border border-dark p-4 md:grid-cols-[32px_1fr_1fr_auto]"><button class="drag-handle">☰</button><div><div class="admin-field"><label>Title (EN)</label><input v-model="element.title"></div><div class="admin-field mt-3"><label>Description (EN)</label><textarea v-model="element.description"></textarea></div></div><div><div class="admin-field"><label>Title (SK)</label><input v-model="element.title_sk"></div><div class="admin-field mt-3"><label>Description (SK)</label><textarea v-model="element.description_sk"></textarea></div></div><button @click="project.features.splice(index, 1)">Remove</button></article></template>
            </draggable>
        </section>

        <footer class="sticky bottom-0 flex justify-end border border-dark bg-light p-4"><button class="admin-button bg-dark text-light" :disabled="saving" @click="save">Save portfolio content</button></footer>
    </div>
    <p v-else class="p-10">Loading…</p>
</template>