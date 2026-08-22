<script setup>
import Modal from '@/shared/components/Modal.vue'
import FileStructure from './FileStructure.vue'


const props = defineProps({
    open: {
        type: Boolean,
        default: false
    },

    modelValue: {
        type: Array,
        default: () => []
    },

    initialFolderId: {
        type: [String, Number, null],
        default: null
    },

    loading: {
        type: Boolean,
        default: false
    },

    error: {
        type: String,
        default: ''
    },

    title: {
        type: String,
        default: 'Project files'
    },

    subtitle: {
        type: String,
        default: 'Select an image from Project Files.'
    },

    allowUploadControl: {
        type: Boolean,
        default: true
    },

    allowMetadataEditing: {
        type: Boolean,
        default: false
    },

    preventDeletingRequired: {
        type: Boolean,
        default: true
    },

    disabled: {
        type: Boolean,
        default: false
    }
})


const emit = defineEmits([
    'update:open',
    'update:modelValue',
    'open-folder',
    'open-file',
    'upload-files',
    'close'
])


function closeModal() {
    emit(
        'update:open',
        false
    )

    emit(
        'close'
    )
}


function updateItems(value) {
    emit(
        'update:modelValue',
        value
    )
}
</script>


<template>
    <Modal
        :open="open"
        :title="title"
        :subtitle="subtitle"
        max-width-class="max-w-5xl"
        max-height-class="max-h-[85vh]"
        panel-class="flex flex-col border border-accent bg-light shadow-xl"
        body-class="min-h-0 flex-1 overflow-hidden"
        close-label="Close project files"
        @close="
            closeModal
        "
    >
        <div
            v-if="loading"
            class="
                p-6
            "
        >
            <p
                class="
                    p
                    uppercase
                    text-dark/40
                "
            >
                Loading project files...
            </p>
        </div>

        <div
            v-else-if="error"
            class="
                p-6
            "
        >
            <p
                class="
                    p
                    text-red-600
                "
            >
                {{ error }}
            </p>
        </div>

        <div
            v-else
            class="
                min-h-0
                flex-1
                overflow-hidden
            "
        >
            <FileStructure
                :model-value="
                    modelValue
                "
                :initial-folder-id="
                    initialFolderId
                "
                :allow-upload-control="
                    allowUploadControl
                "
                :allow-metadata-editing="
                    allowMetadataEditing
                "
                :prevent-deleting-required="
                    preventDeletingRequired
                "
                :disabled="
                    disabled
                "
                :show-image-previews="
                    true
                "
                @update:model-value="
                    updateItems
                "
                @open-folder="
                    emit(
                        'open-folder',
                        $event
                    )
                "
                @open-file="
                    emit(
                        'open-file',
                        $event
                    )
                "
                @upload-files="
                    emit(
                        'upload-files',
                        $event
                    )
                "
            />
        </div>
    </Modal>
</template>
