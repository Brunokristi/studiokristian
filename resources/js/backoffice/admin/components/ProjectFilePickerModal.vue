<script setup>
import ServiceFileStructure from './ServiceFileStructure.vue'


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
        default: 'Double-click a file to select it.'
    },

    allowUploadControl: {
        type: Boolean,
        default: true
    },

    allowMetadataEditing: {
        type: Boolean,
        default: true
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
    <div
        v-if="open"
        class="
            fixed
            inset-0
            z-40
            flex
            items-center
            justify-center
            bg-dark/60
            p-4
        "
        @click.self="
            closeModal
        "
    >
        <div
            class="
                flex
                max-h-[85vh]
                w-full
                max-w-5xl
                flex-col
                overflow-hidden
                border
                border-accent
                bg-light
            "
        >
            <div
                class="
                    flex
                    items-center
                    justify-between
                    gap-4
                    border-b
                    border-accent
                    px-5
                    py-4
                "
            >
                <div>
                    <p
                        class="
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            text-dark/40
                        "
                    >
                        {{ title }}
                    </p>

                    <p
                        class="
                            p
                            mt-1
                        "
                    >
                        {{ subtitle }}
                    </p>
                </div>

                <button
                    type="button"
                    class="
                        font-mono
                        text-xs
                        font-bold
                        uppercase
                        text-dark
                        transition-colors
                        hover:text-accent
                    "
                    @click="
                        closeModal
                    "
                >
                    close
                </button>
            </div>

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
                <ServiceFileStructure
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
        </div>
    </div>
</template>
