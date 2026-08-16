<script setup>
import {
    ref,
    computed,
    watch,
    onMounted,
    onUnmounted,
    nextTick
} from 'vue'

import FormField from '@/shared/components/FormField.vue'


const props = defineProps({
    images: {
        type: Array,
        default: () => []
    },

    interval: {
        type: Number,
        default: 4000
    },

    showArrows: {
        type: Boolean,
        default: true
    },

    editable: {
        type: Boolean,
        default: false
    },

    language: {
        type: String,
        default: 'en'
    },

    useProjectFilesPicker: {
        type: Boolean,
        default: false
    }
})


const emit = defineEmits([
    'update:images',
    'add',
    'remove',
    'replace',
    'request-project-image-add',
    'request-project-image-replace'
])


const currentIndex = ref(0)
const displayIndex = ref(0)

const slideDirection = ref('next')

const isLightboxOpen = ref(false)

const fileInput = ref(null)
const replaceInput = ref(null)

const editingIndex = ref(null)

let timer = null


/*
|--------------------------------------------------------------------------
| Slider state
|--------------------------------------------------------------------------
*/

const editorImages = computed(() => {
    if (!props.editable) {
        return props.images
    }

    return [
        ...props.images,
        {
            __add: true
        }
    ]
})


const isAddSlide = computed(() => {
    return (
        props.editable &&
        currentIndex.value === props.images.length
    )
})


const currentImage = computed(() => {
    if (isAddSlide.value) {
        return null
    }

    return (
        props.images[currentIndex.value] || null
    )
})


/*
|--------------------------------------------------------------------------
| Lightbox state
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The lightbox only works with real images.
| The editor's "+ add image" slide never enters the lightbox.
|
*/

const displayImage = computed(() => {
    return (
        props.images[displayIndex.value] || null
    )
})


const lightboxHasMultipleImages = computed(() => {
    return props.images.length > 1
})


const transitionName = computed(() => {
    return slideDirection.value === 'prev'
        ? 'slide-right'
        : 'slide-left'
})


/*
|--------------------------------------------------------------------------
| Main slider navigation
|--------------------------------------------------------------------------
*/

function next() {
    if (!editorImages.value.length) {
        return
    }

    slideDirection.value = 'next'

    currentIndex.value =
        (
            currentIndex.value + 1
        ) %
        editorImages.value.length
}


function prev() {
    if (!editorImages.value.length) {
        return
    }

    slideDirection.value = 'prev'

    currentIndex.value =
        (
            currentIndex.value -
            1 +
            editorImages.value.length
        ) %
        editorImages.value.length
}


/*
|--------------------------------------------------------------------------
| Lightbox navigation
|--------------------------------------------------------------------------
*/

function nextLightbox() {
    if (props.images.length <= 1) {
        return
    }

    slideDirection.value = 'next'

    displayIndex.value =
        (
            displayIndex.value + 1
        ) %
        props.images.length

    currentIndex.value =
        displayIndex.value
}


function prevLightbox() {
    if (props.images.length <= 1) {
        return
    }

    slideDirection.value = 'prev'

    displayIndex.value =
        (
            displayIndex.value -
            1 +
            props.images.length
        ) %
        props.images.length

    currentIndex.value =
        displayIndex.value
}


/*
|--------------------------------------------------------------------------
| Lightbox
|--------------------------------------------------------------------------
*/

function openLightbox(
    index = currentIndex.value
) {
    if (
        !props.images.length ||
        index < 0 ||
        index >= props.images.length
    ) {
        return
    }

    currentIndex.value = index
    displayIndex.value = index

    isLightboxOpen.value = true

    document.body.style.overflow = 'hidden'
}


function closeLightbox() {
    isLightboxOpen.value = false

    document.body.style.overflow = ''
}


function handleKeydown(event) {
    if (!isLightboxOpen.value) {
        return
    }

    if (event.key === 'Escape') {
        closeLightbox()
        return
    }

    if (event.key === 'ArrowRight') {
        nextLightbox()
        return
    }

    if (event.key === 'ArrowLeft') {
        prevLightbox()
    }
}


/*
|--------------------------------------------------------------------------
| Watchers
|--------------------------------------------------------------------------
*/

watch(
    currentIndex,
    () => {
        if (isAddSlide.value) {
            displayIndex.value =
                Math.max(
                    0,
                    props.images.length - 1
                )

            return
        }

        displayIndex.value =
            currentIndex.value
    }
)


watch(
    () => props.images.length,
    length => {
        if (!length) {
            currentIndex.value = 0
            displayIndex.value = 0

            if (isLightboxOpen.value) {
                closeLightbox()
            }

            return
        }

        const maxIndex =
            props.editable
                ? length
                : length - 1

        if (
            currentIndex.value >
            maxIndex
        ) {
            currentIndex.value = maxIndex
        }

        if (
            displayIndex.value >= length
        ) {
            displayIndex.value =
                length - 1
        }
    }
)


/*
|--------------------------------------------------------------------------
| Image helpers
|--------------------------------------------------------------------------
*/

function getImageSource(image) {
    if (!image) {
        return ''
    }

    return (
        image.preview ||
        image.src ||
        image.existing_path ||
        image.path ||
        ''
    )
}


function imageSource(image) {
    return getImageSource(image)
}


function getImageAlt(image) {
    return (
        (props.language === 'sk'
            ? image?.alt_sk
            : image?.alt) ||
        image?.alt ||
        image?.alt_sk ||
        image?.description ||
        image?.description_sk ||
        ''
    )
}


function imageAlt(image) {
    return getImageAlt(image)
}


function getImageCaption(image) {
    return (
        (props.language === 'sk'
            ? image?.description_sk
            : image?.description) ||
        image?.caption ||
        image?.description ||
        image?.description_sk ||
        image?.alt ||
        image?.alt_sk ||
        ''
    )
}


function localizedImageField(
    image,
    englishField,
    slovakField
) {
    if (!image) {
        return ''
    }

    if (props.language === 'sk') {
        return String(
            image?.[slovakField] ||
            ''
        )
    }

    return String(
        image?.[englishField] ||
        ''
    )
}


function imageCaption(image) {
    return getImageCaption(image)
}


/*
|--------------------------------------------------------------------------
| Add / replace
|--------------------------------------------------------------------------
*/

function openAddPicker() {
    if (!props.editable) {
        return
    }

    if (props.useProjectFilesPicker) {
        emit(
            'request-project-image-add'
        )

        return
    }

    fileInput.value?.click()
}


function openFilePickerForAdd() {
    openAddPicker()
}


function openReplacePicker(index) {
    if (!props.editable) {
        return
    }

    if (
        index < 0 ||
        index >= props.images.length
    ) {
        return
    }

    if (props.useProjectFilesPicker) {
        emit(
            'request-project-image-replace',
            {
                index
            }
        )

        return
    }

    editingIndex.value = index

    replaceInput.value?.click()
}


function openFilePickerForReplace(index) {
    openReplacePicker(index)
}


/*
|--------------------------------------------------------------------------
| Add image
|--------------------------------------------------------------------------
*/

function handleAddFile(event) {
    const files =
        Array.from(
            event.target.files || []
        )

    event.target.value = ''

    if (!files.length) {
        return
    }

    const imageFiles =
        files.filter(file =>
            file?.type?.startsWith('image/')
        )

    if (!imageFiles.length) {
        return
    }

    const newImages =
        imageFiles.map(file => {
            const preview =
                URL.createObjectURL(file)

            return {
                path: '',
                existing_path: '',
                src: preview,
                preview,
                description: '',
                description_sk: '',
                alt: '',
                alt_sk: '',
                caption: '',
                file
            }
        })

    const updatedImages = [
        ...props.images,
        ...newImages
    ]

    emit(
        'update:images',
        updatedImages
    )

    emit(
        'add',
        newImages[
            newImages.length - 1
        ]
    )

    nextTick(() => {
        currentIndex.value =
            updatedImages.length - 1

        displayIndex.value =
            updatedImages.length - 1
    })
}


function handleFileChange(event) {
    handleAddFile(event)
}


/*
|--------------------------------------------------------------------------
| Replace image
|--------------------------------------------------------------------------
*/

function handleReplaceFile(event) {
    const file =
        event.target.files?.[0]

    event.target.value = ''

    if (
        !file ||
        editingIndex.value === null
    ) {
        return
    }

    if (
        !file.type.startsWith('image/')
    ) {
        return
    }

    const index =
        editingIndex.value

    const oldImage =
        props.images[index]

    const preview =
        URL.createObjectURL(file)

    const updatedImage = {
        ...oldImage,

        file,

        src: preview,

        preview,

        path: '',

        existing_path: ''
    }

    const updatedImages =
        [...props.images]

    updatedImages[index] =
        updatedImage

    emit(
        'update:images',
        updatedImages
    )

    emit(
        'replace',
        {
            index,
            image: updatedImage
        }
    )

    currentIndex.value = index
    displayIndex.value = index

    editingIndex.value = null
}


/*
|--------------------------------------------------------------------------
| Remove image
|--------------------------------------------------------------------------
*/

function removeImage(index) {
    if (!props.editable) {
        return
    }

    const image =
        props.images[index]

    const updatedImages =
        props.images.filter(
            (_, imageIndex) =>
                imageIndex !== index
        )

    emit(
        'update:images',
        updatedImages
    )

    emit(
        'remove',
        {
            index,
            image
        }
    )

    if (!updatedImages.length) {
        currentIndex.value = 0
        displayIndex.value = 0

        closeLightbox()

        return
    }

    if (
        currentIndex.value >=
        updatedImages.length
    ) {
        currentIndex.value =
            updatedImages.length - 1
    }

    displayIndex.value =
        Math.min(
            displayIndex.value,
            updatedImages.length - 1
        )
}


/*
|--------------------------------------------------------------------------
| Image metadata
|--------------------------------------------------------------------------
*/

function updateImageField(
    index,
    field,
    value
) {
    if (!props.editable) {
        return
    }

    const updatedImages =
        props.images.map(
            (image, imageIndex) => {
                if (
                    imageIndex !== index
                ) {
                    return image
                }

                return {
                    ...image,
                    [field]: value
                }
            }
        )

    emit(
        'update:images',
        updatedImages
    )
}


function updateDescription(value) {
    if (
        currentIndex.value >=
        props.images.length
    ) {
        return
    }

    updateImageField(
        currentIndex.value,
        props.language === 'sk'
            ? 'description_sk'
            : 'description',
        String(value || '')
    )
}


function updateAlt(value) {
    if (
        currentIndex.value >=
        props.images.length
    ) {
        return
    }

    updateImageField(
        currentIndex.value,
        props.language === 'sk'
            ? 'alt_sk'
            : 'alt',
        String(value || '')
    )
}


/*
|--------------------------------------------------------------------------
| Autoplay
|--------------------------------------------------------------------------
*/

function startAutoplay() {
    if (
        props.editable ||
        props.images.length <= 1 ||
        props.interval <= 0
    ) {
        return
    }

    stopAutoplay()

    timer = setInterval(() => {
        next()
    }, props.interval)
}


function stopAutoplay() {
    if (timer) {
        clearInterval(timer)
        timer = null
    }
}


function handleMouseEnter() {
    stopAutoplay()
}


function handleMouseLeave() {
    startAutoplay()
}


/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {
    window.addEventListener(
        'keydown',
        handleKeydown
    )

    startAutoplay()
})


onUnmounted(() => {
    window.removeEventListener(
        'keydown',
        handleKeydown
    )

    stopAutoplay()

    document.body.style.overflow = ''
})
</script>


<template>
    <div
        class="
            flex
            w-full
            flex-col
            items-center
            gap-4
        "
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
    >
        <!-- ===================================================== -->
        <!-- MAIN SLIDER -->
        <!-- ===================================================== -->

        <div
            class="
                flex
                w-full
                items-center
                justify-center
                gap-4
            "
        >
            <!-- Previous -->
            <button
                type="button"
                class="
                    shrink-0
                    cursor-pointer
                    text-dark
                    transition-colors
                    hover:text-accent
                "
                aria-label="Previous image"
                @click="prev"
            >
                <i
                    class="
                        bi
                        bi-arrow-left
                    "
                />
            </button>


            <!-- Image -->
            <div
                class="
                    relative
                    h-[350px]
                    w-[250px]
                    shrink-0
                    overflow-hidden
                    bg-accent/[0.04]
                "
            >
                <!-- Empty / add slide -->
                <button
                    v-if="
                        editable &&
                        (
                            !currentImage ||
                            !imageSource(
                                currentImage
                            )
                        )
                    "
                    type="button"
                    class="
                        group
                        flex
                        h-full
                        w-full
                        cursor-pointer
                        flex-col
                        items-center
                        justify-center
                        gap-3
                        bg-transparent
                        text-accent
                        transition-colors
                        duration-200
                        hover:bg-accent
                        hover:text-light
                    "
                    @click="openFilePickerForAdd"
                >
                    <span
                        class="
                            leading-none
                        "
                    >
                        <i class="bi bi-plus-lg" />
                    </span>
                </button>


                <!-- Public empty state -->
                <div
                    v-else-if="
                        !currentImage ||
                        !imageSource(
                            currentImage
                        )
                    "
                    class="
                        flex
                        h-full
                        w-full
                        items-center
                        justify-center
                        text-sm
                        text-dark/60
                    "
                >
                    No image available
                </div>


                <!-- Image -->
                <img
                    v-else
                    :src="
                        imageSource(
                            currentImage
                        )
                    "
                    :alt="
                        imageAlt(
                            currentImage
                        )
                    "
                    class="
                        h-full
                        w-full
                        object-cover
                    "
                />


                <!-- Image editing overlay -->
                <div
                    v-if="
                        editable &&
                        currentImage &&
                        imageSource(
                            currentImage
                        )
                    "
                    class="
                        absolute
                        inset-x-0
                        bottom-0
                        z-10
                        bg-[#f7f8fd]
                        px-3
                        py-3
                        backdrop-blur-sm
                    "
                >
                    <div class="space-y-3">
                        <FormField
                            id="image-description"
                            type="textarea"
                            :label="
                                props.language === 'sk'
                                    ? 'Popis obrázka'
                                    : 'Image description'
                            "
                            placeholder="Describe the image"
                            :model-value="
                                localizedImageField(
                                    currentImage,
                                    'description',
                                    'description_sk'
                                )
                            "
                            @update:model-value="
                                updateDescription
                            "
                        />

                        <FormField
                            id="image-alt"
                            type="textarea"
                            :label="
                                props.language === 'sk'
                                    ? 'Alternatívny text'
                                    : 'Alt text'
                            "
                            placeholder="Describe the image"
                            :model-value="
                                localizedImageField(
                                    currentImage,
                                    'alt',
                                    'alt_sk'
                                )
                            "
                            @update:model-value="
                                updateAlt
                            "
                        />

                        <div
                            class="
                                flex
                                items-center
                                justify-end
                                pt-2
                                gap-2
                            "
                        >
                            <button
                                type="button"
                                class="
                                    p
                                    text-dark
                                    transition-colors
                                    hover:text-accent
                                "
                                @click="
                                    openFilePickerForReplace(
                                        currentIndex
                                    )
                                "
                            >
                                <i class="bi bi-arrow-repeat" />
                            </button>

                            <button
                                type="button"
                                class="
                                    p
                                    text-dark
                                    transition-colors
                                    hover:text-accent
                                "
                                @click="
                                    removeImage(
                                        currentIndex
                                    )
                                "
                            >
                                <i class="bi bi-eraser" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Next -->
            <button
                type="button"
                class="
                    shrink-0
                    cursor-pointer
                    text-dark
                    transition-colors
                    hover:text-accent
                "
                aria-label="Next image"
                @click="next"
            >
                <i
                    class="
                        bi
                        bi-arrow-right
                    "
                />
            </button>
        </div>


        <!-- ===================================================== -->
        <!-- EXPAND -->
        <!-- ===================================================== -->

        <button
            type="button"
            class="
                cursor-pointer
                text-dark
                transition-colors
                hover:text-accent
            "
            aria-label="Open image fullscreen"
            @click="
                currentImage &&
                imageSource(
                    currentImage
                )
                    ? openLightbox()
                    : editable
                        ? openFilePickerForAdd()
                        : null
            "
        >
            <i
                class="
                    bi
                    bi-arrows-angle-expand
                    adaptive-text
                "
            />
        </button>


        <!-- ===================================================== -->
        <!-- FILE INPUTS -->
        <!-- ===================================================== -->

        <input
            v-if="editable"
            ref="fileInput"
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            @change="handleFileChange"
        />


        <input
            v-if="editable"
            ref="replaceInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleReplaceFile"
        />
    </div>


    <!-- ========================================================= -->
    <!-- LIGHTBOX / TELEPORT -->
    <!-- ========================================================= -->

    <Teleport to="body">
        <transition name="fade">
            <div
                v-if="isLightboxOpen"
                class="
                    fixed
                    inset-0
                    z-[2100]
                    flex
                    items-center
                    justify-center
                    bg-dark/90
                    p-4
                "
                @click.self="closeLightbox"
            >
                <!-- Close -->
                <button
                    type="button"
                    class="
                        absolute
                        right-4
                        top-4
                        z-20
                        cursor-pointer
                        text-2xl
                        text-light
                        transition-colors
                        hover:text-accent
                    "
                    aria-label="Close image preview"
                    @click="closeLightbox"
                >
                    <i
                        class="
                            bi
                            bi-x-lg
                        "
                    />
                </button>


                <!-- Previous -->
                <button
                    v-if="
                        showArrows &&
                        lightboxHasMultipleImages
                    "
                    type="button"
                    class="
                        absolute
                        left-4
                        top-1/2
                        z-20
                        -translate-y-1/2
                        cursor-pointer
                        text-light
                        transition-colors
                        hover:text-accent
                    "
                    aria-label="Previous image"
                    @click="prevLightbox"
                >
                    <i
                        class="
                            bi
                            bi-arrow-left
                            text-3xl
                        "
                    />
                </button>


                <!-- ================================================= -->
                <!-- IMAGE CONTENT -->
                <!-- ================================================= -->

                <div
                    class="
                        relative
                        flex
                        max-h-full
                        max-w-[92vw]
                        flex-col
                        items-center
                        gap-3
                    "
                >
                    <transition
                        :name="transitionName"
                        mode="out-in"
                    >
                        <div
                            v-if="
                                displayImage &&
                                imageSource(
                                    displayImage
                                )
                            "
                            :key="
                                `${displayIndex}-${imageSource(displayImage)}`
                            "
                            class="
                                relative
                                flex
                                max-h-[80vh]
                                max-w-[92vw]
                                items-center
                                justify-center
                            "
                        >
                            <!-- Image -->
                            <img
                                :src="
                                    imageSource(
                                        displayImage
                                    )
                                "
                                :alt="
                                    imageAlt(
                                        displayImage
                                    )
                                "
                                class="
                                    max-h-[80vh]
                                    max-w-[92vw]
                                    object-contain
                                "
                            />
                        </div>
                    </transition>


                    <!-- Caption -->
                    <p
                        v-if="
                            displayImage &&
                            imageCaption(
                                displayImage
                            )
                        "
                        class="
                            text-center
                            text-sm
                            text-light/90
                        "
                    >
                        {{
                            imageCaption(
                                displayImage
                            )
                        }}
                    </p>
                </div>


                <!-- Next -->
                <button
                    v-if="
                        showArrows &&
                        lightboxHasMultipleImages
                    "
                    type="button"
                    class="
                        absolute
                        right-4
                        top-1/2
                        z-20
                        -translate-y-1/2
                        cursor-pointer
                        text-light
                        transition-colors
                        hover:text-accent
                    "
                    aria-label="Next image"
                    @click="nextLightbox"
                >
                    <i
                        class="
                            bi
                            bi-arrow-right
                            text-3xl
                        "
                    />
                </button>
            </div>
        </transition>
    </Teleport>
</template>


<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition:
        opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}


.slide-left-enter-active,
.slide-right-enter-active {
    z-index: 2;

    transition:
        transform 0.35s ease;
}


.slide-left-leave-active,
.slide-right-leave-active {
    z-index: 1;
}


.slide-left-enter-from {
    transform:
        translateX(100%);
}


.slide-left-enter-to {
    transform:
        translateX(0);
}


.slide-right-enter-from {
    transform:
        translateX(-100%);
}


.slide-right-enter-to {
    transform:
        translateX(0);
}
</style>