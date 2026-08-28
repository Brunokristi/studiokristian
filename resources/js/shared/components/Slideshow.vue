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

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const currentIndex = ref(0)

const isLightboxOpen = ref(false)

const isMainAnimating = ref(false)
const mainDirection = ref('next')

const isLightboxAnimating = ref(false)
const lightboxDirection = ref('next')

const lightboxIndex = ref(0)

const fileInput = ref(null)
const replaceInput = ref(null)

const editingIndex = ref(null)

let timer = null
let mainAnimationTimer = null
let lightboxAnimationTimer = null

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

    return props.images[currentIndex.value] || null
})

/*
|--------------------------------------------------------------------------
| Index helpers
|--------------------------------------------------------------------------
*/

function getWrappedIndex(index, length) {
    if (!length) {
        return 0
    }

    return (
        (index % length) +
        length
    ) % length
}

/*
|--------------------------------------------------------------------------
| Main carousel
|--------------------------------------------------------------------------
*/

const mainPreviousImage = computed(() => {
    const images = editorImages.value

    if (images.length <= 1) {
        return null
    }

    return images[
        getWrappedIndex(
            currentIndex.value - 1,
            images.length
        )
    ]
})

const mainNextImage = computed(() => {
    const images = editorImages.value

    if (images.length <= 1) {
        return null
    }

    return images[
        getWrappedIndex(
            currentIndex.value + 1,
            images.length
        )
    ]
})

function next() {
    if (
        editorImages.value.length <= 1 ||
        isMainAnimating.value
    ) {
        return
    }

    mainDirection.value = 'next'
    isMainAnimating.value = true

    clearMainAnimationTimer()

    mainAnimationTimer = window.setTimeout(() => {
        currentIndex.value = getWrappedIndex(
            currentIndex.value + 1,
            editorImages.value.length
        )

        isMainAnimating.value = false
    }, 420)
}

function prev() {
    if (
        editorImages.value.length <= 1 ||
        isMainAnimating.value
    ) {
        return
    }

    mainDirection.value = 'prev'
    isMainAnimating.value = true

    clearMainAnimationTimer()

    mainAnimationTimer = window.setTimeout(() => {
        currentIndex.value = getWrappedIndex(
            currentIndex.value - 1,
            editorImages.value.length
        )

        isMainAnimating.value = false
    }, 420)
}

function clearMainAnimationTimer() {
    if (mainAnimationTimer) {
        clearTimeout(mainAnimationTimer)
        mainAnimationTimer = null
    }
}

/*
|--------------------------------------------------------------------------
| Lightbox / teleport
|--------------------------------------------------------------------------
*/

const displayImage = computed(() => {
    return props.images[lightboxIndex.value] || null
})

const lightboxHasMultipleImages = computed(() => {
    return props.images.length > 1
})

const lightboxPreviousImage = computed(() => {
    if (props.images.length <= 1) {
        return null
    }

    return props.images[
        getWrappedIndex(
            lightboxIndex.value - 1,
            props.images.length
        )
    ]
})

const lightboxNextImage = computed(() => {
    if (props.images.length <= 1) {
        return null
    }

    return props.images[
        getWrappedIndex(
            lightboxIndex.value + 1,
            props.images.length
        )
    ]
})

function nextLightbox() {
    if (
        props.images.length <= 1 ||
        isLightboxAnimating.value
    ) {
        return
    }

    lightboxDirection.value = 'next'
    isLightboxAnimating.value = true

    clearLightboxAnimationTimer()

    lightboxAnimationTimer = window.setTimeout(() => {
        lightboxIndex.value = getWrappedIndex(
            lightboxIndex.value + 1,
            props.images.length
        )

        currentIndex.value = lightboxIndex.value

        isLightboxAnimating.value = false
    }, 460)
}

function prevLightbox() {
    if (
        props.images.length <= 1 ||
        isLightboxAnimating.value
    ) {
        return
    }

    lightboxDirection.value = 'prev'
    isLightboxAnimating.value = true

    clearLightboxAnimationTimer()

    lightboxAnimationTimer = window.setTimeout(() => {
        lightboxIndex.value = getWrappedIndex(
            lightboxIndex.value - 1,
            props.images.length
        )

        currentIndex.value = lightboxIndex.value

        isLightboxAnimating.value = false
    }, 460)
}

function clearLightboxAnimationTimer() {
    if (lightboxAnimationTimer) {
        clearTimeout(lightboxAnimationTimer)
        lightboxAnimationTimer = null
    }
}

/*
|--------------------------------------------------------------------------
| Open / close teleport
|--------------------------------------------------------------------------
*/

function openLightbox(index = currentIndex.value) {
    if (
        !props.images.length ||
        index < 0 ||
        index >= props.images.length
    ) {
        return
    }

    currentIndex.value = index
    lightboxIndex.value = index

    isLightboxAnimating.value = false
    isLightboxOpen.value = true

    document.body.style.overflow = 'hidden'
}

function closeLightbox() {
    isLightboxOpen.value = false
    isLightboxAnimating.value = false

    document.body.style.overflow = ''
}

/*
|--------------------------------------------------------------------------
| Keyboard controls
|--------------------------------------------------------------------------
*/

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
    () => props.images.length,
    length => {
        if (!length) {
            currentIndex.value = 0
            lightboxIndex.value = 0

            if (isLightboxOpen.value) {
                closeLightbox()
            }

            return
        }

        const maxIndex = props.editable
            ? length
            : length - 1

        if (currentIndex.value > maxIndex) {
            currentIndex.value = maxIndex
        }

        if (lightboxIndex.value >= length) {
            lightboxIndex.value = length - 1
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
        (
            props.language === 'sk'
                ? image?.alt_sk
                : image?.alt
        ) ||
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
        (
            props.language === 'sk'
                ? image?.description_sk
                : image?.description
        ) ||
        image?.caption ||
        image?.description ||
        image?.description_sk ||
        image?.alt ||
        image?.alt_sk ||
        ''
    )
}

function imageCaption(image) {
    return getImageCaption(image)
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
            image?.[slovakField] || ''
        )
    }

    return String(
        image?.[englishField] || ''
    )
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
        emit('request-project-image-add')
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
    const files = Array.from(
        event.target.files || []
    )

    event.target.value = ''

    if (!files.length) {
        return
    }

    const imageFiles = files.filter(file =>
        file?.type?.startsWith('image/')
    )

    if (!imageFiles.length) {
        return
    }

    const newImages = imageFiles.map(file => {
        const preview = URL.createObjectURL(file)

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
        newImages[newImages.length - 1]
    )

    nextTick(() => {
        currentIndex.value =
            updatedImages.length - 1

        lightboxIndex.value =
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
    const file = event.target.files?.[0]

    event.target.value = ''

    if (
        !file ||
        editingIndex.value === null
    ) {
        return
    }

    if (!file.type.startsWith('image/')) {
        return
    }

    const index = editingIndex.value
    const oldImage = props.images[index]

    const preview = URL.createObjectURL(file)

    const updatedImage = {
        ...oldImage,
        file,
        src: preview,
        preview,
        path: '',
        existing_path: ''
    }

    const updatedImages = [
        ...props.images
    ]

    updatedImages[index] = updatedImage

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
    lightboxIndex.value = index

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

    const image = props.images[index]

    const updatedImages = props.images.filter(
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
        lightboxIndex.value = 0

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

    if (
        lightboxIndex.value >=
        updatedImages.length
    ) {
        lightboxIndex.value =
            updatedImages.length - 1
    }
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

    const updatedImages = props.images.map(
        (image, imageIndex) => {
            if (imageIndex !== index) {
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

    clearMainAnimationTimer()
    clearLightboxAnimationTimer()

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
                <i class="bi bi-arrow-left"></i>
            </button>

            <!-- ================================================= -->
            <!-- IMAGE STACK -->
            <!-- ================================================= -->

            <div
                class="
                    relative
                    h-[350px]
                    w-[250px]
                    shrink-0
                "
            >
                <!-- ADD IMAGE -->
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
                        bg-accent/[0.04]
                        text-accent
                        transition-colors
                        duration-200
                        hover:bg-accent
                        hover:text-light
                    "
                    @click="openFilePickerForAdd"
                >
                    <span class="leading-none">
                        <i class="bi bi-plus-lg"></i>
                    </span>
                </button>

                <!-- EMPTY PUBLIC STATE -->
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
                        bg-accent/[0.04]
                        text-sm
                        text-dark/60
                    "
                >
                    No image available
                </div>

                <!-- IMAGE STACK -->
                <template v-else>
                    <!-- Previous -->
                    <div
                        v-if="
                            mainPreviousImage &&
                            imageSource(
                                mainPreviousImage
                            )
                        "
                        class="
                            carousel-card
                            carousel-card-previous
                        "
                        :class="{
                            'carousel-main-previous':
                                isMainAnimating &&
                                mainDirection === 'prev'
                        }"
                    >
                        <img
                            :src="
                                imageSource(
                                    mainPreviousImage
                                )
                            "
                            :alt="
                                imageAlt(
                                    mainPreviousImage
                                )
                            "
                        />
                    </div>

                    <!-- Next -->
                    <div
                        v-if="
                            mainNextImage &&
                            imageSource(
                                mainNextImage
                            )
                        "
                        class="
                            carousel-card
                            carousel-card-next
                        "
                        :class="{
                            'carousel-main-next':
                                isMainAnimating &&
                                mainDirection === 'next'
                        }"
                    >
                        <img
                            :src="
                                imageSource(
                                    mainNextImage
                                )
                            "
                            :alt="
                                imageAlt(
                                    mainNextImage
                                )
                            "
                        />
                    </div>

                    <!-- Current -->
                    <div
                        class="
                            carousel-card
                            carousel-card-current
                        "
                        :class="{
                            'carousel-main-current-next':
                                isMainAnimating &&
                                mainDirection === 'next',

                            'carousel-main-current-prev':
                                isMainAnimating &&
                                mainDirection === 'prev'
                        }"
                    >
                        <img
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
                        />
                    </div>
                </template>

                <!-- EDITING OVERLAY -->
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
                        z-30
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
                                gap-2
                                pt-2
                            "
                        >
                            <button
                                type="button"
                                class="
                                    p
                                    cursor-pointer
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
                                <i class="bi bi-arrow-repeat"></i>
                            </button>

                            <button
                                type="button"
                                class="
                                    p
                                    cursor-pointer
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
                                <i class="bi bi-eraser"></i>
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
                <i class="bi bi-arrow-right"></i>
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
            ></i>
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
    <!-- FULLSCREEN TELEPORT -->
    <!-- ========================================================= -->

    <Teleport to="body">
        <Transition name="slideshow-teleport">
            <div
                v-if="isLightboxOpen"
                class="
                    slideshow-teleport
                "
            >
                <!-- BACKGROUND -->
                <div
                    class="
                        slideshow-teleport-background
                    "
                    @click.self="closeLightbox"
                ></div>

                <!-- CLOSE -->
                <button
                    type="button"
                    class="
                        slideshow-close
                    "
                    aria-label="Close image preview"
                    title="Close"
                    @click="closeLightbox"
                >
                    <span
                        class="
                            slideshow-close-icon
                        "
                    >
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <!-- PREVIOUS -->
                <button
                    v-if="
                        showArrows &&
                        lightboxHasMultipleImages
                    "
                    type="button"
                    class="
                        slideshow-arrow
                        slideshow-arrow-prev
                    "
                    aria-label="Previous image"
                    @click="prevLightbox"
                >
                    <i
                        class="
                            bi
                            bi-arrow-left
                        "
                    ></i>
                </button>

                <!-- ================================================= -->
                <!-- CENTER CONTENT -->
                <!-- ================================================= -->

                <div
                    class="
                        slideshow-teleport-content
                    "
                >
                    <div
                        class="
                            lightbox-carousel
                        "
                    >
                        <!-- PREVIOUS -->
                        <div
                            v-if="
                                lightboxPreviousImage &&
                                imageSource(
                                    lightboxPreviousImage
                                )
                            "
                            class="
                                lightbox-card
                                lightbox-card-previous
                            "
                            :class="{
                                'lightbox-previous-prev':
                                    isLightboxAnimating &&
                                    lightboxDirection === 'prev'
                            }"
                        >
                            <img
                                :src="
                                    imageSource(
                                        lightboxPreviousImage
                                    )
                                "
                                :alt="
                                    imageAlt(
                                        lightboxPreviousImage
                                    )
                                "
                            />
                        </div>

                        <!-- NEXT -->
                        <div
                            v-if="
                                lightboxNextImage &&
                                imageSource(
                                    lightboxNextImage
                                )
                            "
                            class="
                                lightbox-card
                                lightbox-card-next
                            "
                            :class="{
                                'lightbox-next-next':
                                    isLightboxAnimating &&
                                    lightboxDirection === 'next'
                            }"
                        >
                            <img
                                :src="
                                    imageSource(
                                        lightboxNextImage
                                    )
                                "
                                :alt="
                                    imageAlt(
                                        lightboxNextImage
                                    )
                                "
                            />
                        </div>

                        <!-- CURRENT -->
                        <div
                            v-if="
                                displayImage &&
                                imageSource(
                                    displayImage
                                )
                            "
                            class="
                                lightbox-card
                                lightbox-card-current
                            "
                            :class="{
                                'lightbox-current-next':
                                    isLightboxAnimating &&
                                    lightboxDirection === 'next',

                                'lightbox-current-prev':
                                    isLightboxAnimating &&
                                    lightboxDirection === 'prev'
                            }"
                        >
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
                            />
                        </div>
                    </div>

                    <!-- CAPTION -->
                    <p
                        v-if="
                            displayImage &&
                            imageCaption(
                                displayImage
                            )
                        "
                        class="
                            slideshow-caption
                        "
                    >
                        {{
                            imageCaption(
                                displayImage
                            )
                        }}
                    </p>
                </div>

                <!-- NEXT -->
                <button
                    v-if="
                        showArrows &&
                        lightboxHasMultipleImages
                    "
                    type="button"
                    class="
                        slideshow-arrow
                        slideshow-arrow-next
                    "
                    aria-label="Next image"
                    @click="nextLightbox"
                >
                    <i
                        class="
                            bi
                            bi-arrow-right
                        "
                    ></i>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/*
|--------------------------------------------------------------------------
| Main carousel
|--------------------------------------------------------------------------
*/

.carousel-card {
    position: absolute;

    inset: 0;

    width: 100%;
    height: 100%;

    overflow: hidden;

    background: #f7f8fd;

    transform-origin: center bottom;

    will-change:
        transform,
        opacity;
}

.carousel-card img {
    display: block;

    width: 100%;
    height: 100%;

    object-fit: cover;

    user-select: none;
    pointer-events: none;
}

.carousel-card-previous {
    z-index: 1;

    transform:
        translateY(-10px)
        scale(0.94);

    opacity: 0.5;
}

.carousel-card-next {
    z-index: 2;

    transform:
        translateY(10px)
        scale(0.94);

    opacity: 0.7;
}

.carousel-card-current {
    z-index: 10;

    transform:
        translateY(0)
        scale(1);

    opacity: 1;

    transition:
        transform 420ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        ),
        opacity 420ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}

/*
|--------------------------------------------------------------------------
| Main - next
|--------------------------------------------------------------------------
*/

.carousel-main-current-next {
    transform:
        translateY(-110%)
        scale(0.96);

    opacity: 0;
}

.carousel-main-next {
    animation:
        mainNextCard
        420ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        )
        forwards;
}

@keyframes mainNextCard {
    from {
        transform:
            translateY(10px)
            scale(0.94);

        opacity: 0.7;
    }

    to {
        transform:
            translateY(0)
            scale(1);

        opacity: 1;
    }
}

/*
|--------------------------------------------------------------------------
| Main - previous
|--------------------------------------------------------------------------
*/

.carousel-main-current-prev {
    transform:
        translateY(110%)
        scale(0.96);

    opacity: 0;
}

.carousel-main-previous {
    animation:
        mainPreviousCard
        420ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        )
        forwards;
}

@keyframes mainPreviousCard {
    from {
        transform:
            translateY(-10px)
            scale(0.94);

        opacity: 0.5;
    }

    to {
        transform:
            translateY(0)
            scale(1);

        opacity: 1;
    }
}

/*
|--------------------------------------------------------------------------
| FULLSCREEN TELEPORT
|--------------------------------------------------------------------------
*/

.slideshow-teleport {
    position: fixed;

    inset: 0;

    z-index: 2100;

    width: 100%;
    height: 100%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #000;

    overflow: hidden;
}

.slideshow-teleport-background {
    position: absolute;

    inset: 0;

    z-index: 1;

    background: #000;
}

/*
|--------------------------------------------------------------------------
| Center content
|--------------------------------------------------------------------------
*/

.slideshow-teleport-content {
    position: relative;

    z-index: 10;

    width: min(
        92vw,
        900px
    );

    display: flex;
    flex-direction: column;
    align-items: center;

    gap: 16px;
}

/*
|--------------------------------------------------------------------------
| Lightbox carousel
|--------------------------------------------------------------------------
*/

.lightbox-carousel {
    position: relative;

    width: 100%;
    height: 78vh;

    overflow: visible;
}

/*
|--------------------------------------------------------------------------
| Lightbox cards
|--------------------------------------------------------------------------
*/

.lightbox-card {
    position: absolute;

    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    will-change:
        transform,
        opacity;
}

.lightbox-card img {
    display: block;

    max-width: 100%;
    max-height: 100%;

    object-fit: contain;

    user-select: none;
    pointer-events: none;
}

/*
|--------------------------------------------------------------------------
| Previous image underneath
|--------------------------------------------------------------------------
*/

.lightbox-card-previous {
    z-index: 1;

    transform:
        translateY(-28px)
        scale(0.92);

    opacity: 0.35;

    filter: brightness(0.65);
}

/*
|--------------------------------------------------------------------------
| Next image underneath
|--------------------------------------------------------------------------
*/

.lightbox-card-next {
    z-index: 2;

    transform:
        translateY(28px)
        scale(0.92);

    opacity: 0.45;

    filter: brightness(0.7);
}

/*
|--------------------------------------------------------------------------
| Current image
|--------------------------------------------------------------------------
*/

.lightbox-card-current {
    z-index: 10;

    transform:
        translateY(0)
        scale(1);

    opacity: 1;

    transition:
        transform 460ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        ),
        opacity 460ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}

/*
|--------------------------------------------------------------------------
| Next animation
|--------------------------------------------------------------------------
*/

.lightbox-current-next {
    transform:
        translateY(-110%)
        scale(0.96);

    opacity: 0;
}

.lightbox-next-next {
    animation:
        lightboxNextCard
        460ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        )
        forwards;
}

@keyframes lightboxNextCard {
    from {
        transform:
            translateY(28px)
            scale(0.92);

        opacity: 0.45;
    }

    to {
        transform:
            translateY(0)
            scale(1);

        opacity: 1;
    }
}

/*
|--------------------------------------------------------------------------
| Previous animation
|--------------------------------------------------------------------------
*/

.lightbox-current-prev {
    transform:
        translateY(110%)
        scale(0.96);

    opacity: 0;
}

.lightbox-previous-prev {
    animation:
        lightboxPreviousCard
        460ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        )
        forwards;
}

@keyframes lightboxPreviousCard {
    from {
        transform:
            translateY(-28px)
            scale(0.92);

        opacity: 0.35;
    }

    to {
        transform:
            translateY(0)
            scale(1);

        opacity: 1;
    }
}

/*
|--------------------------------------------------------------------------
| CLOSE BUTTON
|--------------------------------------------------------------------------
|
| Same 24px control and 18px two-line geometry as the
| navigation menu/X.
|
*/

.slideshow-close {
    position: absolute;

    top: 24px;
    right: 24px;

    z-index: 100;

    width: 24px;
    height: 24px;

    padding: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    color: white;

    background: transparent;
    border: 0;

    cursor: pointer;

    transition:
        transform 220ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        ),
        color 220ms ease;
}

.slideshow-close:hover {
    color: var(--color-accent);

    transform: scale(1.05);
}

.slideshow-close:active {
    transform: scale(0.95);
}

.slideshow-close-icon {
    position: relative;

    width: 18px;
    height: 14px;

    display: block;

    pointer-events: none;
}

.slideshow-close-icon span {
    position: absolute;

    left: 0;
    top: 6.5px;

    width: 18px;
    height: 1px;

    background: currentColor;

    transform-origin: center;
}

.slideshow-close-icon span:first-child {
    transform: rotate(45deg);
}

.slideshow-close-icon span:last-child {
    transform: rotate(-45deg);
}

/*
|--------------------------------------------------------------------------
| Arrows
|--------------------------------------------------------------------------
*/

.slideshow-arrow {
    position: absolute;

    top: 50%;

    z-index: 50;

    width: 24px;
    height: 24px;

    padding: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    color: white;

    background: transparent;
    border: 0;

    cursor: pointer;

    transform: translateY(-50%);

    transition:
        transform 220ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        ),
        color 220ms ease;
}

.slideshow-arrow:hover {
    color: var(--color-accent);

    transform:
        translateY(-50%)
        scale(1.05);
}

.slideshow-arrow:active {
    transform:
        translateY(-50%)
        scale(0.95);
}

.slideshow-arrow-prev {
    left: 24px;
}

.slideshow-arrow-next {
    right: 24px;
}

/*
|--------------------------------------------------------------------------
| Caption
|--------------------------------------------------------------------------
*/

.slideshow-caption {
    position: relative;

    z-index: 40;

    max-width: 90%;

    margin: 0;

    text-align: center;

    font-size: 0.875rem;
    line-height: 1.4;

    color: rgba(
        255,
        255,
        255,
        0.9
    );
}

/*
|--------------------------------------------------------------------------
| Teleport entrance
|--------------------------------------------------------------------------
*/

.slideshow-teleport-enter-active,
.slideshow-teleport-leave-active {
    transition:
        opacity 350ms ease;
}

.slideshow-teleport-enter-from,
.slideshow-teleport-leave-to {
    opacity: 0;
}

/*
|--------------------------------------------------------------------------
| Reduced motion
|--------------------------------------------------------------------------
*/

@media (
    prefers-reduced-motion: reduce
) {
    .carousel-card,
    .carousel-card-current,
    .lightbox-card,
    .lightbox-card-current,
    .slideshow-close,
    .slideshow-arrow {
        transition: none;
    }

    .carousel-main-next,
    .carousel-main-previous,
    .lightbox-next-next,
    .lightbox-previous-prev {
        animation: none;
    }
}
</style>