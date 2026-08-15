<script setup>
import {
    computed,
    onMounted,
    ref
} from 'vue'


import {
    RouterLink
} from 'vue-router'


import draggable from 'vuedraggable'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import AdminPageHeader from '../../components/AdminPageHeader.vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'
import Slideshow from '@shared/components/Slideshow.vue'
import Info from '@shared/components/Info.vue'


const props =
    defineProps({
        id: {
            type: String,
            required: true
        }
    })


const project =
    ref(null)


const loading =
    ref(true)


const saving =
    ref(false)


const savingPublishing =
    ref(false)


const error =
    ref('')


const errors =
    ref({})


const showErrorToast =
    ref(false)


const showSavedToast =
    ref(false)


const language =
    ref('en')


const uploadingImageIndex =
    ref(null)


const previewImages =
    computed(() => {
        if (
            !project.value
        ) {
            return []
        }


        return (
            project.value.images ||
            []
        )
            .map(
                (
                    image,
                    index
                ) => ({
                    src:
                        image.preview ||
                        image.existing_path ||
                        image.path ||
                        '',

                    alt:
                        language.value === 'sk'
                            ? (
                                image.description_sk ||
                                `Projektový obrázok ${index + 1}`
                            )
                            : (
                                image.description ||
                                `Project image ${index + 1}`
                            ),

                    caption:
                        language.value === 'sk'
                            ? (
                                image.description_sk ||
                                `Obrázok ${index + 1}`
                            )
                            : (
                                image.description ||
                                `Image ${index + 1}`
                            )
                })
            )
            .filter(
                image =>
                    image.src
            )
    })


const previewItems =
    computed(() => {
        if (
            !project.value
        ) {
            return []
        }


        return (
            project.value.features ||
            []
        ).map(
            feature => ({
                heading:
                    language.value === 'sk'
                        ? feature.title_sk || ''
                        : feature.title || '',

                text:
                    language.value === 'sk'
                        ? feature.description_sk || ''
                        : feature.description || ''
            })
        )
    })


const previewName =
    computed(() => {
        if (
            !project.value
        ) {
            return ''
        }


        return language.value === 'sk'
            ? project.value.name_sk || ''
            : project.value.name || ''
    })


const languageLabel =
    computed(() => {
        return language.value === 'sk'
            ? 'Slovenčina'
            : 'English'
    })


function createClientKey(
    prefix
) {
    return `${prefix}-${Date.now()}-${Math.random()
        .toString(36)
        .slice(2)}`
}


function normalizeProject(
    value
) {
    if (
        !value
    ) {
        return value
    }


    value.images =
        (
            value.images ||
            []
        ).map(
            image => ({
                ...image,

                client_key:
                    image.client_key ||
                    image.id ||
                    createClientKey(
                        'image'
                    ),

                file:
                    image.file ||
                    null,

                preview:
                    image.preview ||
                    ''
            })
        )


    value.features =
        (
            value.features ||
            []
        ).map(
            feature => ({
                ...feature,

                client_key:
                    feature.client_key ||
                    feature.id ||
                    createClientKey(
                        'feature'
                    )
            })
        )


    return value
}


function showError(
    message
) {
    error.value =
        message


    showErrorToast.value =
        false


    requestAnimationFrame(() => {
        showErrorToast.value =
            true
    })
}


async function load() {
    loading.value =
        true


    error.value =
        ''


    try {
        const response =
            await api.get(
                `/projects/${props.id}/portfolio`
            )


        project.value =
            normalizeProject(
                response.data.project
            )


        if (
            !project.value.images.length
        ) {
            addImage()
        }


        if (
            !project.value.features.length
        ) {
            addFeature()
        }
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        loading.value =
            false
    }
}


function addImage() {
    if (
        !project.value
    ) {
        return
    }


    project.value.images.push({
        client_key:
            createClientKey(
                'image'
            ),

        path: '',

        existing_path: '',

        description: '',

        description_sk: '',

        sort_order:
            project.value.images.length,

        file: null,

        preview: ''
    })
}


function removeImage(
    index
) {
    if (
        !project.value
    ) {
        return
    }


    project.value.images.splice(
        index,
        1
    )
}


function addFeature() {
    if (
        !project.value
    ) {
        return
    }


    project.value.features.push({
        client_key:
            createClientKey(
                'feature'
            ),

        title: '',

        title_sk: '',

        description: '',

        description_sk: '',

        sort_order:
            project.value.features.length
    })
}


function removeFeature(
    index
) {
    if (
        !project.value
    ) {
        return
    }


    project.value.features.splice(
        index,
        1
    )
}


function updateProjectName(
    value
) {
    if (
        !project.value
    ) {
        return
    }


    if (
        language.value === 'sk'
    ) {
        project.value.name_sk =
            value

        return
    }


    project.value.name =
        value
}


function updateImageDescription(
    image,
    value
) {
    if (
        language.value === 'sk'
    ) {
        image.description_sk =
            value

        return
    }


    image.description =
        value
}


function updateFeatureTitle(
    feature,
    value
) {
    if (
        language.value === 'sk'
    ) {
        feature.title_sk =
            value

        return
    }


    feature.title =
        value
}


function updateFeatureDescription(
    feature,
    value
) {
    if (
        language.value === 'sk'
    ) {
        feature.description_sk =
            value

        return
    }


    feature.description =
        value
}


function handleImageChange(
    image,
    event,
    index
) {
    const file =
        event.target.files?.[0]


    if (
        !file
    ) {
        return
    }


    image.file =
        file


    uploadingImageIndex.value =
        index


    const reader =
        new FileReader()


    reader.onload =
        () => {
            image.preview =
                reader.result


            uploadingImageIndex.value =
                null
        }


    reader.onerror =
        () => {
            uploadingImageIndex.value =
                null


            showError(
                'The image could not be loaded.'
            )
        }


    reader.readAsDataURL(
        file
    )
}


async function translate(
    source,
    target
) {
    if (
        !project.value ||
        !project.value[source]
    ) {
        return
    }


    try {
        const response =
            await api.post(
                '/portfolio/translate',
                {
                    text:
                        project.value[
                            source
                        ]
                }
            )


        project.value[target] =
            response.data.translated
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


async function translateFeature(
    feature,
    source,
    target
) {
    if (
        !feature[source]
    ) {
        return
    }


    try {
        const response =
            await api.post(
                '/portfolio/translate',
                {
                    text:
                        feature[source]
                }
            )


        feature[target] =
            response.data.translated
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


async function translateImage(
    image,
    source,
    target
) {
    if (
        !image[source]
    ) {
        return
    }


    try {
        const response =
            await api.post(
                '/portfolio/translate',
                {
                    text:
                        image[source]
                }
            )


        image[target] =
            response.data.translated
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


async function translateFeatureContent(
    feature
) {
    await translateFeature(
        feature,
        'title',
        'title_sk'
    )


    await translateFeature(
        feature,
        'description',
        'description_sk'
    )
}


function append(
    body,
    key,
    value
) {
    if (
        value !== null &&
        value !== undefined
    ) {
        body.append(
            key,
            value
        )
    }
}


async function save() {
    if (
        !project.value ||
        saving.value
    ) {
        return
    }


    saving.value =
        true


    error.value =
        ''


    errors.value =
        {}


    const body =
        new FormData()


    body.append(
        '_method',
        'PUT'
    )


    for (
        const key of [
            'company_id',
            'service_product_id',
            'portal_status',
            'name',
            'name_sk',
            'url',
            'live_url',
            'summary',
            'summary_sk',
            'hex_color'
        ]
    ) {
        append(
            body,
            key,
            project.value[key] ?? ''
        )
    }


    append(
        body,
        'existing_logo_path',
        project.value.logo_path ||
            ''
    )


    if (
        project.value.logo_file
    ) {
        body.append(
            'logo_file',
            project.value.logo_file
        )
    }


    project.value.images.forEach(
        (
            image,
            index
        ) => {
            append(
                body,
                `images[${index}][path]`,
                image.path ||
                    ''
            )


            append(
                body,
                `images[${index}][existing_path]`,
                image.existing_path ||
                    ''
            )


            append(
                body,
                `images[${index}][description]`,
                image.description ||
                    ''
            )


            append(
                body,
                `images[${index}][description_sk]`,
                image.description_sk ||
                    ''
            )


            append(
                body,
                `images[${index}][sort_order]`,
                index
            )


            if (
                image.file
            ) {
                body.append(
                    `images[${index}][file]`,
                    image.file
                )
            }
        }
    )


    project.value.features.forEach(
        (
            feature,
            index
        ) => {
            for (
                const key of [
                    'title',
                    'title_sk',
                    'description',
                    'description_sk'
                ]
            ) {
                append(
                    body,
                    `features[${index}][${key}]`,
                    feature[key] ||
                        ''
                )
            }


            append(
                body,
                `features[${index}][sort_order]`,
                index
            )
        }
    )


    try {
        const response =
            await api.post(
                `/projects/${props.id}/portfolio`,
                body
            )


        project.value =
            normalizeProject(
                response.data.project
            )


        showSavedToast.value =
            false


        requestAnimationFrame(() => {
            showSavedToast.value =
                true
        })
    } catch (
        exception
    ) {
        errors.value =
            validationErrors(
                exception
            )


        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        saving.value =
            false
    }
}


async function togglePublishing() {
    if (
        !project.value ||
        savingPublishing.value
    ) {
        return
    }


    savingPublishing.value =
        true


    try {
        const response =
            await api.put(
                `/projects/${props.id}/publishing`,
                {
                    is_published:
                        !project.value.is_published
                }
            )


        project.value.is_published =
            response.data.data.is_published
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        savingPublishing.value =
            false
    }
}


function openLiveWebsite() {
    if (
        !project.value?.live_url
    ) {
        return
    }


    window.open(
        project.value.live_url,
        '_blank'
    )
}


onMounted(
    load
)
</script>


<template>
    <div
        v-if="
            loading
        "
        class="
            flex
            min-h-[60vh]
            items-center
            justify-center
        "
    >
        <p
            class="
                p
                uppercase
                text-dark/40
            "
        >
            Loading portfolio...
        </p>
    </div>


    <div
        v-else-if="
            project
        "
        class="
            w-full
            space-y-12
            lg:space-y-16
        "
    >
        <!-- Toasts -->
        <Toast
            v-model="
                showErrorToast
            "
            heading="Something went wrong"
            :text="
                error
            "
            :duration="5000"
        />


        <Toast
            v-model="
                showSavedToast
            "
            heading="Portfolio saved"
            text="Your portfolio content has been saved."
            :duration="3000"
        />


        <!-- Header -->
        <AdminPageHeader
            :title="
                project.name
            "
            eyebrow="Portfolio editor"
            description="Edit the portfolio while seeing how it will appear to visitors."
            :breadcrumbs="[
                {
                    label: 'Portfolio',
                    to: {
                        name: 'portfolio.index'
                    }
                },

                {
                    label:
                        project.name
                }
            ]"
        >
            <div
                class="
                    flex
                    flex-wrap
                    items-center
                    gap-6
                "
            >
                <RouterLink
                    :to="{
                        name:
                            'projects.show',
                        params: {
                            id:
                                props.id
                        }
                    }"
                    class="
                        font-mono
                        text-xs
                        font-bold
                        uppercase
                        text-dark
                        transition-colors
                        hover:text-accent
                    "
                >
                    Project workspace
                </RouterLink>


                <Button
                    type="button"
                    :text="
                        project.is_published
                            ? 'published'
                            : 'show on website'
                    "
                    :variant="
                        project.is_published
                            ? 'accent'
                            : 'dark'
                    "
                    :loading="
                        savingPublishing
                    "
                    :disabled="
                        savingPublishing
                    "
                    align="left"
                    @click="
                        togglePublishing
                    "
                />


                <Button
                    type="button"
                    text="save changes"
                    variant="accent"
                    :loading="
                        saving
                    "
                    loading-text="saving"
                    :disabled="
                        saving
                    "
                    align="left"
                    @click="
                        save
                    "
                />
            </div>
        </AdminPageHeader>


        <!-- Language selector -->
        <div
            class="
                flex
                flex-col
                gap-4
                border-b
                border-accent
                pb-4
                sm:flex-row
                sm:items-center
                sm:justify-between
            "
        >
            <div>
                <p
                    class="
                        p
                        uppercase
                        text-dark/40
                    "
                >
                    Preview language
                </p>


                <p
                    class="
                        h3
                        mt-1
                        text-accent
                    "
                >
                    {{
                        languageLabel
                    }}
                </p>
            </div>


            <div
                class="
                    flex
                    gap-1
                "
            >
                <button
                    type="button"
                    class="
                        px-4
                        py-2
                        font-mono
                        text-xs
                        font-bold
                        uppercase
                        transition-colors
                    "
                    :class="
                        language === 'en'
                            ? 'bg-accent text-light'
                            : 'text-dark hover:text-accent'
                    "
                    @click="
                        language = 'en'
                    "
                >
                    EN
                </button>


                <button
                    type="button"
                    class="
                        px-4
                        py-2
                        font-mono
                        text-xs
                        font-bold
                        uppercase
                        transition-colors
                    "
                    :class="
                        language === 'sk'
                            ? 'bg-accent text-light'
                            : 'text-dark hover:text-accent'
                    "
                    @click="
                        language = 'sk'
                    "
                >
                    SK
                </button>
            </div>
        </div>


        <!-- Portfolio preview -->
        <main
            class="
                mx-auto
                w-full
                max-w-5xl
                bg-light
                py-5
            "
        >
            <!-- Gallery -->
            <section>
                <Slideshow
                    :images="
                        previewImages
                    "
                    :autoplay="
                        false
                    "
                    :interval="
                        5000
                    "
                />


                <!-- Gallery controls -->
                <div
                    class="
                        mt-5
                        border
                        border-accent
                        bg-light
                        p-5
                    "
                >
                    <div
                        class="
                            flex
                            flex-col
                            gap-4
                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                        "
                    >
                        <div>
                            <p
                                class="
                                    h3
                                    text-accent
                                "
                            >
                                Project gallery
                            </p>


                            <p
                                class="
                                    p
                                    mt-1
                                    text-dark/40
                                "
                            >
                                Add, remove and reorder the images shown above.
                            </p>
                        </div>


                        <Button
                            type="button"
                            text="add image"
                            variant="accent"
                            align="left"
                            @click="
                                addImage
                            "
                        />
                    </div>


                    <draggable
                        v-model="
                            project.images
                        "
                        item-key="client_key"
                        handle=".drag-handle"
                        class="
                            mt-8
                            space-y-8
                        "
                    >
                        <template
                            #item="{
                                element,
                                index
                            }"
                        >
                            <article
                                class="
                                    border-t
                                    border-accent/20
                                    pt-6
                                "
                            >
                                <div
                                    class="
                                        flex
                                        gap-4
                                    "
                                >
                                    <button
                                        type="button"
                                        class="
                                            drag-handle
                                            shrink-0
                                            cursor-grab
                                            pt-1
                                            font-mono
                                            text-lg
                                            leading-none
                                            text-dark/30
                                            transition-colors
                                            hover:text-accent
                                        "
                                        aria-label="Reorder image"
                                    >
                                        ⋮⋮
                                    </button>


                                    <div
                                        class="
                                            min-w-0
                                            flex-1
                                        "
                                    >
                                        <div
                                            class="
                                                grid
                                                gap-6
                                                lg:grid-cols-2
                                            "
                                        >
                                            <!-- Image -->
                                            <div>
                                                <div
                                                    v-if="
                                                        element.preview ||
                                                        element.existing_path ||
                                                        element.path
                                                    "
                                                    class="
                                                        aspect-video
                                                        overflow-hidden
                                                        bg-accent/[0.04]
                                                    "
                                                >
                                                    <img
                                                        :src="
                                                            element.preview ||
                                                            element.existing_path ||
                                                            element.path
                                                        "
                                                        :alt="
                                                            language === 'sk'
                                                                ? element.description_sk
                                                                : element.description
                                                        "
                                                        class="
                                                            h-full
                                                            w-full
                                                            object-cover
                                                        "
                                                    >
                                                </div>


                                                <div
                                                    v-else
                                                    class="
                                                        flex
                                                        aspect-video
                                                        items-center
                                                        justify-center
                                                        border
                                                        border-accent/20
                                                        bg-accent/[0.03]
                                                    "
                                                >
                                                    <p
                                                        class="
                                                            p
                                                            uppercase
                                                            text-dark/30
                                                        "
                                                    >
                                                        No image selected
                                                    </p>
                                                </div>


                                                <label
                                                    class="
                                                        mt-4
                                                        inline-flex
                                                        cursor-pointer
                                                        border-b
                                                        border-dark
                                                        pb-1
                                                        font-mono
                                                        text-xs
                                                        font-bold
                                                        uppercase
                                                        transition-colors
                                                        hover:border-accent
                                                        hover:text-accent
                                                    "
                                                >
                                                    {{
                                                        uploadingImageIndex === index
                                                            ? 'loading image...'
                                                            : 'choose image'
                                                    }}


                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        class="sr-only"
                                                        @change="
                                                            handleImageChange(
                                                                element,
                                                                $event,
                                                                index
                                                            )
                                                        "
                                                    >
                                                </label>
                                            </div>


                                            <!-- Description -->
                                            <div
                                                class="
                                                    flex
                                                    flex-col
                                                    justify-center
                                                    gap-5
                                                "
                                            >
                                                <FormField
                                                    :id="
                                                        `image-description-${index}-${language}`
                                                    "
                                                    :model-value="
                                                        language === 'sk'
                                                            ? element.description_sk
                                                            : element.description
                                                    "
                                                    name="description"
                                                    type="text"
                                                    label="Image description"
                                                    placeholder="Describe the image"
                                                    @update:model-value="
                                                        updateImageDescription(
                                                            element,
                                                            $event
                                                        )
                                                    "
                                                />


                                                <Button
                                                    v-if="
                                                        language === 'sk' &&
                                                        element.description
                                                    "
                                                    type="button"
                                                    text="translate from English"
                                                    align="left"
                                                    @click="
                                                        translateImage(
                                                            element,
                                                            'description',
                                                            'description_sk'
                                                        )
                                                    "
                                                />
                                            </div>
                                        </div>
                                    </div>


                                    <button
                                        type="button"
                                        class="
                                            shrink-0
                                            self-start
                                            font-mono
                                            text-xs
                                            font-bold
                                            uppercase
                                            text-dark/40
                                            transition-colors
                                            hover:text-red-600
                                        "
                                        @click="
                                            removeImage(
                                                index
                                            )
                                        "
                                    >
                                        remove
                                    </button>
                                </div>
                            </article>
                        </template>
                    </draggable>
                </div>
            </section>


            <!-- Project title -->
            <section
                class="
                    mt-20
                "
            >
                <h2
                    class="
                        h2
                        text-accent
                    "
                >
                    {{
                        previewName ||
                        'Untitled project'
                    }}
                </h2>


                <div
                    class="
                        mt-8
                        border
                        border-accent
                        bg-light
                        p-5
                    "
                >
                    <div
                        class="
                            flex
                            flex-col
                            gap-4
                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                        "
                    >
                        <p
                            class="
                                h3
                                text-accent
                            "
                        >
                            Project title
                        </p>


                        <Button
                            v-if="
                                language === 'sk' &&
                                project.name
                            "
                            type="button"
                            text="translate from English"
                            align="left"
                            @click="
                                translate(
                                    'name',
                                    'name_sk'
                                )
                            "
                        />
                    </div>


                    <FormField
                        :id="
                            `project-name-${language}`
                        "
                        :model-value="
                            language === 'sk'
                                ? project.name_sk
                                : project.name
                        "
                        name="name"
                        type="text"
                        :label="
                            language === 'sk'
                                ? 'Slovak title'
                                : 'English title'
                        "
                        :placeholder="
                            language === 'sk'
                                ? 'Project title in Slovak'
                                : 'Project title in English'
                        "
                        class="
                            mt-6
                        "
                        @update:model-value="
                            updateProjectName
                        "
                    />
                </div>
            </section>


            <!-- Features preview -->
            <section
                class="
                    mt-20
                "
            >
                <div
                    class="
                        space-y-8
                    "
                >
                    <Info
                        v-for="
                            (
                                item,
                                index
                            ) in previewItems"
                        :key="
                            index
                        "
                        :heading="
                            item.heading
                        "
                        :text="
                            item.text
                        "
                    />
                </div>


                <!-- Features editor -->
                <div
                    class="
                        mt-10
                        border
                        border-accent
                        bg-light
                        p-5
                    "
                >
                    <div
                        class="
                            flex
                            flex-col
                            gap-4
                            sm:flex-row
                            sm:items-center
                            sm:justify-between
                        "
                    >
                        <div>
                            <p
                                class="
                                    h3
                                    text-accent
                                "
                            >
                                Project information
                            </p>


                            <p
                                class="
                                    p
                                    mt-1
                                    text-dark/40
                                "
                            >
                                These sections appear directly below the project title.
                            </p>
                        </div>


                        <Button
                            type="button"
                            text="add section"
                            variant="accent"
                            align="left"
                            @click="
                                addFeature
                            "
                        />
                    </div>


                    <draggable
                        v-model="
                            project.features
                        "
                        item-key="client_key"
                        handle=".feature-drag-handle"
                        class="
                            mt-8
                            space-y-8
                        "
                    >
                        <template
                            #item="{
                                element,
                                index
                            }"
                        >
                            <article
                                class="
                                    border-t
                                    border-accent/20
                                    pt-6
                                "
                            >
                                <div
                                    class="
                                        flex
                                        gap-4
                                    "
                                >
                                    <button
                                        type="button"
                                        class="
                                            feature-drag-handle
                                            shrink-0
                                            cursor-grab
                                            pt-1
                                            font-mono
                                            text-lg
                                            leading-none
                                            text-dark/30
                                            transition-colors
                                            hover:text-accent
                                        "
                                        aria-label="Reorder section"
                                    >
                                        ⋮⋮
                                    </button>


                                    <div
                                        class="
                                            min-w-0
                                            flex-1
                                            space-y-6
                                        "
                                    >
                                        <FormField
                                            :id="
                                                `feature-title-${index}-${language}`
                                            "
                                            :model-value="
                                                language === 'sk'
                                                    ? element.title_sk
                                                    : element.title
                                            "
                                            name="title"
                                            type="text"
                                            label="Heading"
                                            :placeholder="
                                                language === 'sk'
                                                    ? 'Section heading in Slovak'
                                                    : 'Section heading in English'
                                            "
                                            @update:model-value="
                                                updateFeatureTitle(
                                                    element,
                                                    $event
                                                )
                                            "
                                        />


                                        <FormField
                                            :id="
                                                `feature-description-${index}-${language}`
                                            "
                                            :model-value="
                                                language === 'sk'
                                                    ? element.description_sk
                                                    : element.description
                                            "
                                            name="description"
                                            type="textarea"
                                            label="Text"
                                            placeholder="Describe this part of the project"
                                            @update:model-value="
                                                updateFeatureDescription(
                                                    element,
                                                    $event
                                                )
                                            "
                                        />


                                        <Button
                                            v-if="
                                                language === 'sk' &&
                                                (
                                                    element.title ||
                                                    element.description
                                                )
                                            "
                                            type="button"
                                            text="translate from English"
                                            align="left"
                                            @click="
                                                translateFeatureContent(
                                                    element
                                                )
                                            "
                                        />
                                    </div>


                                    <button
                                        type="button"
                                        class="
                                            shrink-0
                                            self-start
                                            font-mono
                                            text-xs
                                            font-bold
                                            uppercase
                                            text-dark/40
                                            transition-colors
                                            hover:text-red-600
                                        "
                                        @click="
                                            removeFeature(
                                                index
                                            )
                                        "
                                    >
                                        remove
                                    </button>
                                </div>
                            </article>
                        </template>
                    </draggable>
                </div>
            </section>


            <!-- Live website -->
            <section
                v-if="
                    project.live_url
                "
                class="
                    mt-20
                "
            >
                <Button
                    :text="
                        language === 'sk'
                            ? 'zobraziť web'
                            : 'view live website'
                    "
                    variant="dark"
                    @click="
                        openLiveWebsite
                    "
                />
            </section>
        </main>


        <!-- SEO -->
        <section
            class="
                border-t
                border-accent
                pt-10
            "
        >
            <div
                class="
                    max-w-4xl
                "
            >
                <h2
                    class="
                        h2
                        text-accent
                    "
                >
                    SEO
                </h2>


                <p
                    class="
                        p
                        mt-2
                        text-dark/50
                    "
                >
                    These descriptions are used by search engines and are not displayed as part of the portfolio page.
                </p>


                <div
                    class="
                        mt-8
                        grid
                        gap-8
                        md:grid-cols-2
                    "
                >
                    <FormField
                        id="portfolio-summary-en"
                        v-model="
                            project.summary
                        "
                        name="summary"
                        type="textarea"
                        label="SEO description (EN)"
                        placeholder="Short description for search engines"
                    />


                    <div
                        class="
                            space-y-4
                        "
                    >
                        <FormField
                            id="portfolio-summary-sk"
                            v-model="
                                project.summary_sk
                            "
                            name="summary_sk"
                            type="textarea"
                            label="SEO description (SK)"
                            placeholder="Short description for search engines"
                        />


                        <Button
                            type="button"
                            text="translate from English"
                            align="left"
                            @click="
                                translate(
                                    'summary',
                                    'summary_sk'
                                )
                            "
                        />
                    </div>
                </div>
            </div>
        </section>


        <!-- Save bar -->
        <div
            class="
                sticky
                bottom-0
                z-20
                flex
                justify-end
                border-t
                border-accent
                bg-light/95
                px-4
                py-4
                backdrop-blur
                sm:px-6
            "
        >
            <Button
                type="button"
                text="save portfolio"
                variant="accent"
                :loading="
                    saving
                "
                loading-text="saving"
                :disabled="
                    saving
                "
                align="right"
                @click="
                    save
                "
            />
        </div>
    </div>


    <div
        v-else
        class="
            flex
            min-h-[60vh]
            items-center
            justify-center
        "
    >
        <p
            class="
                p
                uppercase
                text-dark/40
            "
        >
            Portfolio project could not be loaded.
        </p>
    </div>
</template>