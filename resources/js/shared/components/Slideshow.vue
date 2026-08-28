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
        currentIndex.value =
            getWrappedIndex(
                currentIndex.value + 1,
                editorImages.value.length
            )

        isMainAnimating.value = false
        mainAnimationTimer = null
    }, 460)
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
        currentIndex.value =
            getWrappedIndex(
                currentIndex.value - 1,
                editorImages.value.length
            )

        isMainAnimating.value = false
        mainAnimationTimer = null
    }, 460)
}

function clearMainAnimationTimer() {
    if (mainAnimationTimer) {
        clearTimeout(mainAnimationTimer)
        mainAnimationTimer = null
    }
}

/*
|--------------------------------------------------------------------------
| Lightbox
|--------------------------------------------------------------------------
*/

const displayImage = computed(() => {
    return (
        props.images[
            lightboxIndex.value
        ] || null
    )
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
        lightboxIndex.value =
            getWrappedIndex(
                lightboxIndex.value + 1,
                props.images.length
            )

        currentIndex.value =
            lightboxIndex.value

        isLightboxAnimating.value = false
        lightboxAnimationTimer = null
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
        lightboxIndex.value =
            getWrappedIndex(
                lightboxIndex.value - 1,
                props.images.length
            )

        currentIndex.value =
            lightboxIndex.value

        isLightboxAnimating.value = false
        lightboxAnimationTimer = null
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
| Open / close lightbox
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
            lightboxIndex.value >= length
        ) {
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

    const imageFiles = files.filter(
        file =>
            file?.type?.startsWith('image/')
    )

    if (!imageFiles.length) {
        return
    }

    const newImages = imageFiles.map(file => {
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

    const index = editingIndex.value

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

    const updatedImages = [
        ...props.images
    ]

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

    const updatedImages =
        props.images.map(
            (image, imageIndex) => {
                if (
                    imageIndex !==
                    index
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

    clearMainAnimationTimer()
    clearLightboxAnimationTimer()

    document.body.style.overflow = ''
})
</script>

<template>

    <!-- ========================================================= -->
    <!-- MAIN SLIDER -->
    <!-- ========================================================= -->

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

        <div
            class="
                flex
                w-full
                items-center
                justify-center
                gap-4
            "
        >

            <!-- PREVIOUS -->

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


            <!-- IMAGE -->

            <div
                class="
                    relative
                    aspect-[3/4]
                    h-[350px]
                    w-auto
                    shrink-0
                    overflow-hidden
                "
            >

                <!-- ADD -->

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
                        flex
                        h-full
                        w-full
                        cursor-pointer
                        items-center
                        justify-center
                        bg-accent/[0.04]
                        text-accent
                        transition-colors
                        duration-200
                        hover:bg-accent
                        hover:text-light
                    "
                    @click="openFilePickerForAdd"
                >
                    <i class="bi bi-plus-lg"></i>
                </button>


                <!-- EMPTY -->

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


                <!-- IMAGES -->

                <template v-else>

                    <!-- PREVIOUS -->

                    <div
                        v-if="
                            mainPreviousImage &&
                            imageSource(
                                mainPreviousImage
                            )
                        "
                        class="
                            slideshow-image
                            slideshow-image-previous
                        "
                        :class="{
                            'slideshow-image-previous-active':
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


                    <!-- NEXT -->

                    <div
                        v-if="
                            mainNextImage &&
                            imageSource(
                                mainNextImage
                            )
                        "
                        class="
                            slideshow-image
                            slideshow-image-next
                        "
                        :class="{
                            'slideshow-image-next-active':
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


                    <!-- CURRENT -->

                    <div
                        class="
                            slideshow-image
                            slideshow-image-current
                        "
                        :class="{
                            'slideshow-current-next':
                                isMainAnimating &&
                                mainDirection === 'next',

                            'slideshow-current-prev':
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


                <!-- EDITING -->

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
                                <i
                                    class="
                                        bi
                                        bi-arrow-repeat
                                    "
                                ></i>
                            </button>

                            <button
                                type="button"
                                class="
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
                                <i
                                    class="
                                        bi
                                        bi-eraser
                                    "
                                ></i>
                            </button>
                        </div>

                    </div>
                </div>

            </div>


            <!-- NEXT -->

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


        <!-- EXPAND -->

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


        <!-- FILE INPUTS -->

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
    <!-- TELEPORT -->
    <!-- ========================================================= -->

    <Teleport to="body">

        <Transition name="slideshow-teleport">

            <div
                v-if="isLightboxOpen"
                class="
                    fixed
                    inset-0
                    z-[2100]
                    flex
                    h-screen
                    w-screen
                    flex-col
                    items-center
                    justify-center
                    overflow-hidden
                    bg-black
                "
            >

                <!-- ================================================= -->
                <!-- CAROUSEL -->
                <!-- ================================================= -->

                <div
                    class="
                        flex
                        min-h-0
                        w-full
                        flex-1
                        items-center
                        justify-center
                    "
                >

                    <!-- PREVIOUS -->

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
                            z-50
                            flex
                            h-10
                            w-10
                            -translate-y-1/2
                            cursor-pointer
                            items-center
                            justify-center
                            text-white
                            transition
                            duration-200
                            hover:scale-105
                            hover:text-accent
                            active:scale-95
                            sm:left-8
                        "
                        aria-label="Previous image"
                        @click="prevLightbox"
                    >
                        <i
                            class="
                                bi
                                bi-arrow-left
                                text-lg
                            "
                        ></i>
                    </button>


                    <!-- ================================================= -->
                    <!-- IMAGE -->
                    <!-- ================================================= -->

                    <div
                        class="
                            relative
                            aspect-[3/4]
                            h-[520px]
                            w-[390px]
                            max-h-[60vh]
                            max-w-[75vw]
                            overflow-hidden
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
                                lightbox-image
                                lightbox-image-previous
                            "
                            :class="{
                                'lightbox-image-previous-active':
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
                                lightbox-image
                                lightbox-image-next
                            "
                            :class="{
                                'lightbox-image-next-active':
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
                                lightbox-image
                                lightbox-image-current
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


                    <!-- NEXT -->

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
                            z-50
                            flex
                            h-10
                            w-10
                            -translate-y-1/2
                            cursor-pointer
                            items-center
                            justify-center
                            text-white
                            transition
                            duration-200
                            hover:scale-105
                            hover:text-accent
                            active:scale-95
                            sm:right-8
                        "
                        aria-label="Next image"
                        @click="nextLightbox"
                    >
                        <i
                            class="
                                bi
                                bi-arrow-right
                                text-lg
                            "
                        ></i>
                    </button>

                </div>


                <!-- ================================================= -->
                <!-- DESCRIPTION + CLOSE -->
                <!-- ================================================= -->

                <div
                    class="
                        z-50
                        flex
                        w-full
                        shrink-0
                        flex-col
                        items-center
                        gap-6
                        px-6
                        pb-6
                    "
                >

                    <!-- DESCRIPTION -->

                    <p
                        v-if="
                            displayImage &&
                            imageCaption(
                                displayImage
                            )
                        "
                        class="
                            m-0
                            max-w-2xl
                            text-center
                            p
                            text-light
                        "
                    >
                        {{
                            imageCaption(
                                displayImage
                            )
                        }}
                    </p>


                    <!-- CLOSE -->

                    <button
                        type="button"
                        class="
                            flex
                            cursor-pointer
                            items-center
                            justify-center
                            text-white
                            transition
                            duration-200
                            hover:scale-105
                            hover:text-accent
                            active:scale-95
                        "
                        aria-label="Close image preview"
                        title="Close"
                        @click="closeLightbox"
                    >
                        <i
                            class="
                                bi
                                bi-arrows-angle-contract
                                text-xl
                            "
                        ></i>
                    </button>

                </div>

            </div>

        </Transition>

    </Teleport>
</template>

<style scoped>
/*
|--------------------------------------------------------------------------
| MAIN SLIDER
|--------------------------------------------------------------------------
*/

.slideshow-image {
    position: absolute;
    inset: 0;

    width: 100%;
    height: 100%;

    overflow: hidden;

    will-change: transform;
    backface-visibility: hidden;
}

.slideshow-image img {
    display: block;

    width: 100%;
    height: 100%;

    object-fit: cover;

    user-select: none;
    pointer-events: none;
}

.slideshow-image-current {
    z-index: 10;

    transform: translateX(0);
}

.slideshow-image-next {
    z-index: 5;

    transform: translateX(100%);
}

.slideshow-image-previous {
    z-index: 5;

    transform: translateX(-100%);
}


/*
|--------------------------------------------------------------------------
| MAIN NEXT
|--------------------------------------------------------------------------
*/

.slideshow-current-next {
    animation:
        slideshowCurrentNext
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

.slideshow-image-next-active {
    animation:
        slideshowNextEnter
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

@keyframes slideshowCurrentNext {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-100%);
    }
}

@keyframes slideshowNextEnter {
    from {
        transform: translateX(100%);
    }

    to {
        transform: translateX(0);
    }
}


/*
|--------------------------------------------------------------------------
| MAIN PREVIOUS
|--------------------------------------------------------------------------
*/

.slideshow-current-prev {
    animation:
        slideshowCurrentPrev
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

.slideshow-image-previous-active {
    animation:
        slideshowPreviousEnter
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

@keyframes slideshowCurrentPrev {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(100%);
    }
}

@keyframes slideshowPreviousEnter {
    from {
        transform: translateX(-100%);
    }

    to {
        transform: translateX(0);
    }
}


/*
|--------------------------------------------------------------------------
| LIGHTBOX
|--------------------------------------------------------------------------
*/

.lightbox-image {
    position: absolute;
    inset: 0;

    width: 100%;
    height: 100%;

    overflow: hidden;

    will-change: transform;
    backface-visibility: hidden;
}

.lightbox-image img {
    display: block;

    width: 100%;
    height: 100%;

    object-fit: cover;

    user-select: none;
    pointer-events: none;
}

.lightbox-image-current {
    z-index: 10;

    transform: translateX(0);
}

.lightbox-image-next {
    z-index: 5;

    transform: translateX(100%);
}

.lightbox-image-previous {
    z-index: 5;

    transform: translateX(-100%);
}


/*
|--------------------------------------------------------------------------
| LIGHTBOX NEXT
|--------------------------------------------------------------------------
*/

.lightbox-current-next {
    animation:
        lightboxCurrentNext
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

.lightbox-image-next-active {
    animation:
        lightboxNextEnter
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

@keyframes lightboxCurrentNext {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-100%);
    }
}

@keyframes lightboxNextEnter {
    from {
        transform: translateX(100%);
    }

    to {
        transform: translateX(0);
    }
}


/*
|--------------------------------------------------------------------------
| LIGHTBOX PREVIOUS
|--------------------------------------------------------------------------
*/

.lightbox-current-prev {
    animation:
        lightboxCurrentPrev
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

.lightbox-image-previous-active {
    animation:
        lightboxPreviousEnter
        460ms
        cubic-bezier(0.16, 1, 0.3, 1)
        forwards;
}

@keyframes lightboxCurrentPrev {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(100%);
    }
}

@keyframes lightboxPreviousEnter {
    from {
        transform: translateX(-100%);
    }

    to {
        transform: translateX(0);
    }
}


/*
|--------------------------------------------------------------------------
| TELEPORT FADE
|--------------------------------------------------------------------------
*/

.slideshow-teleport-enter-active,
.slideshow-teleport-leave-active {
    transition: opacity 350ms ease;
}

.slideshow-teleport-enter-from,
.slideshow-teleport-leave-to {
    opacity: 0;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 640px) {
    .slideshow-teleport {
        padding: 0;
    }
}


/*
|--------------------------------------------------------------------------
| REDUCED MOTION
|--------------------------------------------------------------------------
*/

@media (prefers-reduced-motion: reduce) {
    .slideshow-current-next,
    .slideshow-current-prev,
    .slideshow-image-next-active,
    .slideshow-image-previous-active,
    .lightbox-current-next,
    .lightbox-current-prev,
    .lightbox-image-next-active,
    .lightbox-image-previous-active {
        animation: none;
    }

    .slideshow-teleport-enter-active,
    .slideshow-teleport-leave-active {
        transition: none;
    }
}
</style>