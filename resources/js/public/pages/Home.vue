<script setup lang="ts">
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    watch,
} from 'vue'

import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()

import Button from '../../shared/components/Button.vue'
import GridLayout from '../components/GridLayout.vue'

import {
    useGlobalActions,
} from '../composables/useGlobalActions'

import {
    useSeoMeta,
} from '../composables/useSeoMeta'

const {
    openContacts,
    openRecentProjects,
    openWorkflow,
} = useGlobalActions()

useSeoMeta({
    title: () => t('seo.home.title'),
    description: () => t('seo.home.description'),
})

const bgUrl = '/assets/bg.svg'
const bgUrl2 = '/assets/bg2.svg'
const portraitUrl = '/assets/me.png'


/*
|--------------------------------------------------------------------------
| PROJECT TYPES
|--------------------------------------------------------------------------
*/

type ApiProjectImage = {
    path: string
}

type ApiProject = {
    name: string
    url: string
    summary: string | null
    logo_path: string | null
    images?: ApiProjectImage[]
}

type ProjectGalleryItem = {
    projectName: string
    description: string
    logo: string | null
    link: string
    images: string[]
    currentImage: number
}


/*
|--------------------------------------------------------------------------
| PROJECT GALLERY
|--------------------------------------------------------------------------
*/

const projectGallery =
    ref<ProjectGalleryItem[]>([])

const displayedProjects =
    computed(() => {
        const projects =
            projectGallery.value

        /*
        |--------------------------------------------------------------------------
        | One project = nothing
        |--------------------------------------------------------------------------
        */

        if (projects.length < 2) {
            return []
        }

        /*
        |--------------------------------------------------------------------------
        | Two projects = two
        |--------------------------------------------------------------------------
        */

        if (projects.length === 2) {
            return projects
        }

        /*
        |--------------------------------------------------------------------------
        | Three projects = newest two
        |--------------------------------------------------------------------------
        */

        if (projects.length === 3) {
            return projects.slice(0, 2)
        }

        /*
        |--------------------------------------------------------------------------
        | Four or more = newest four
        |--------------------------------------------------------------------------
        */

        return projects.slice(0, 4)
    })


/*
|--------------------------------------------------------------------------
| SYNCHRONIZED PROJECT GALLERY
|--------------------------------------------------------------------------
*/

const galleryTimer =
    ref<ReturnType<typeof setTimeout> | null>(
        null
    )

const preloadedImages =
    new Set<string>()

const isPreloading =
    ref(false)


/*
|--------------------------------------------------------------------------
| PRELOAD IMAGE
|--------------------------------------------------------------------------
|
| Loads an image into browser memory before
| it is displayed.
|
*/

function preloadImage(
    src: string
): Promise<void> {
    return new Promise((resolve) => {
        if (!src) {
            resolve()
            return
        }

        if (preloadedImages.has(src)) {
            resolve()
            return
        }

        const image =
            new Image()

        image.onload = () => {
            preloadedImages.add(src)
            resolve()
        }

        image.onerror = () => {
            /*
            |--------------------------------------------------------------------------
            | Do not let one failed image stop
            | the entire gallery.
            |--------------------------------------------------------------------------
            */

            resolve()
        }

        image.src = src
    })
}


/*
|--------------------------------------------------------------------------
| GET NEXT IMAGE
|--------------------------------------------------------------------------
*/

function getNextImage(
    project: ProjectGalleryItem
) {
    if (
        project.images.length <= 1
    ) {
        return null
    }

    const nextIndex =
        (
            project.currentImage + 1
        ) %
        project.images.length

    return project.images[
        nextIndex
    ]
}


/*
|--------------------------------------------------------------------------
| PRELOAD NEXT GALLERY IMAGES
|--------------------------------------------------------------------------
|
| Every visible project has its next image
| loaded before the gallery changes.
|
*/

async function preloadNextGalleryImages() {
    const projects =
        displayedProjects.value

    if (!projects.length) {
        return
    }

    const nextImages =
        projects
            .map((project) =>
                getNextImage(project)
            )
            .filter(
                (
                    image
                ): image is string =>
                    Boolean(image)
            )

    if (!nextImages.length) {
        return
    }

    isPreloading.value = true

    await Promise.all(
        nextImages.map((image) =>
            preloadImage(image)
        )
    )

    isPreloading.value = false
}


/*
|--------------------------------------------------------------------------
| ADVANCE GALLERY
|--------------------------------------------------------------------------
|
| All visible project cards change at exactly
| the same time.
|
*/

async function advanceGallery() {
    if (
        !displayedProjects.value.length
    ) {
        scheduleGalleryChange()
        return
    }

    /*
    |--------------------------------------------------------------------------
    | Make absolutely sure the next images
    | are loaded before changing the indexes.
    |--------------------------------------------------------------------------
    */

    await preloadNextGalleryImages()

    /*
    |--------------------------------------------------------------------------
    | Change every visible project at once.
    |--------------------------------------------------------------------------
    */

    displayedProjects.value.forEach(
        (project) => {
            if (
                project.images.length <= 1
            ) {
                return
            }

            project.currentImage =
                (
                    project.currentImage + 1
                ) %
                project.images.length
        }
    )

    /*
    |--------------------------------------------------------------------------
    | Immediately start loading the images
    | that will be needed after this transition.
    |--------------------------------------------------------------------------
    */

    preloadNextGalleryImages()

    /*
    |--------------------------------------------------------------------------
    | Schedule the following synchronized
    | transition.
    |--------------------------------------------------------------------------
    */

    scheduleGalleryChange()
}


/*
|--------------------------------------------------------------------------
| RANDOM GALLERY DELAY
|--------------------------------------------------------------------------
|
| Minimum: 3 seconds
| Maximum: 7 seconds
|--------------------------------------------------------------------------
*/

function scheduleGalleryChange() {
    if (galleryTimer.value) {
        clearTimeout(
            galleryTimer.value
        )
    }

    if (
        !displayedProjects.value.length
    ) {
        return
    }

    const delay =
        3000 +
        Math.random() * 4000

    galleryTimer.value =
        setTimeout(() => {
            advanceGallery()
        }, delay)
}


/*
|--------------------------------------------------------------------------
| CLEAR GALLERY TIMER
|--------------------------------------------------------------------------
*/

function clearGalleryTimer() {
    if (galleryTimer.value) {
        clearTimeout(
            galleryTimer.value
        )

        galleryTimer.value = null
    }
}


/*
|--------------------------------------------------------------------------
| INITIALIZE GALLERY
|--------------------------------------------------------------------------
*/

async function initializeGallery() {
    /*
    |--------------------------------------------------------------------------
    | First preload the currently visible
    | images.
    |--------------------------------------------------------------------------
    */

    const currentImages =
        displayedProjects.value
            .map(
                (project) =>
                    project.images[
                        project.currentImage
                    ]
            )
            .filter(
                (
                    image
                ): image is string =>
                    Boolean(image)
            )

    await Promise.all(
        currentImages.map((image) =>
            preloadImage(image)
        )
    )

    /*
    |--------------------------------------------------------------------------
    | Then preload the NEXT image for
    | every visible project.
    |--------------------------------------------------------------------------
    */

    await preloadNextGalleryImages()

    /*
    |--------------------------------------------------------------------------
    | Now the first transition can happen
    | without waiting for network requests.
    |--------------------------------------------------------------------------
    */

    scheduleGalleryChange()
}


/*
|--------------------------------------------------------------------------
| LOAD PROJECTS
|--------------------------------------------------------------------------
*/

async function loadRecentProjects() {
    clearGalleryTimer()

    try {
        const response = await fetch(
            `/api/projects?locale=${encodeURIComponent(
                locale.value
            )}&limit=4&gallery=1`
        )

        if (!response.ok) {
            throw new Error(
                'Failed to load projects'
            )
        }

        const projects: ApiProject[] =
            await response.json()

        projectGallery.value =
            projects
                .slice(0, 4)
                .map(
                    (project) => ({
                        projectName:
                            project.name,

                        description:
                            project.summary ||
                            '',

                        logo:
                            project.logo_path,

                        link:
                            `/portfolio/${project.url}`,

                        images:
                            (
                                project.images ||
                                []
                            )
                                .map(
                                    (image) =>
                                        image.path
                                )
                                .filter(
                                    (path) =>
                                        Boolean(
                                            path
                                        )
                                ),

                        currentImage: 0,
                    })
                )
                .filter(
                    (project) =>
                        project.images
                            .length > 0
                )

        await initializeGallery()
    } catch (error) {
        console.error(error)

        projectGallery.value = []
    }
}


/*
|--------------------------------------------------------------------------
| SERVICES
|--------------------------------------------------------------------------
*/

type ApiServiceProduct = {
    id: number
    name: string
    slug: string
    description: string | null
}

const serviceCards = ref<
    Array<{
        heading: string
        text: string
        image: string
        bgColor: string
        link: string
    }>
>([])

const isServicesLoading =
    ref(true)


async function loadServices() {
    isServicesLoading.value = true

    try {
        const response = await fetch(
            `/api/services?locale=${encodeURIComponent(
                locale.value
            )}&limit=4`
        )

        if (!response.ok) {
            throw new Error(
                'Failed to load services'
            )
        }

        const serviceProducts:
            ApiServiceProduct[] =
            await response.json()

        serviceCards.value =
            serviceProducts.map(
                (
                    serviceProduct
                ) => ({
                    heading:
                        serviceProduct.name,

                    text:
                        serviceProduct.description ||
                        '',

                    image: '',

                    bgColor: '',

                    link:
                        `/services/${serviceProduct.slug}`,
                })
            )
    } catch (error) {
        console.error(error)

        serviceCards.value = []
    } finally {
        isServicesLoading.value = false
    }
}


/*
|--------------------------------------------------------------------------
| MOUNT
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadRecentProjects()
    loadServices()
})


/*
|--------------------------------------------------------------------------
| LOCALE CHANGE
|--------------------------------------------------------------------------
*/

watch(
    () => locale.value,
    () => {
        loadRecentProjects()
        loadServices()
    }
)


/*
|--------------------------------------------------------------------------
| CLEANUP
|--------------------------------------------------------------------------
*/

onUnmounted(() => {
    clearGalleryTimer()
})
</script>


<template>
    <main
        class="
            py-8
            flex
            flex-col
            gap-[200px]
        "
    >

        <!--
        |--------------------------------------------------------------------------
        | HERO
        |--------------------------------------------------------------------------
        -->

        <section
            class="
                relative
                overflow-hidden
                flex
                justify-center
                min-h-[600px]
            "
            data-theme="dark"
        >
            <img
                :src="bgUrl"
                alt="Abstract background"
                class="
                    block
                    max-w-none
                    h-auto
                    min-h-full
                    object-cover
                "
            />

            <div
                class="
                    absolute
                    inset-0
                    z-10
                    flex
                    flex-col
                    items-center
                    justify-center
                    text-center
                    gap-5
                    p-6
                "
            >
                <h1
                    class="
                        h2
                        text-light
                        max-w-[850px]
                    "
                >
                    {{ t('home.title') }}
                </h1>

                <p
                    class="
                        p
                        uppercase
                        text-light
                        max-w-[700px]
                    "
                >
                    {{ t(
                        'home.description'
                    ) }}
                </p>

                <Button
                    :text="
                        t(
                            'home.callToAction'
                        )
                    "
                    variant="light"
                    @click="openContacts"
                    class="mt-12"
                />
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | ABOUT
        |--------------------------------------------------------------------------
        -->

        <section
            class="
                flex
                flex-col
                gap-16
                p-4
            "
            data-theme="light"
        >
            <div
                class="
                    flex
                    flex-col
                    gap-8
                    items-center
                    justify-center
                    max-w-[900px]
                    mx-auto
                "
            >
                <h2
                    class="
                        h2
                        text-accent
                        text-center
                    "
                >
                    {{ t(
                        'home.about.title'
                    ) }}
                </h2>

                <p
                    class="
                        p
                        text-center
                        uppercase
                    "
                >
                    {{ t(
                        'home.about.text'
                    ) }}
                </p>

                <p
                    class="
                        p
                        text-center
                        uppercase
                    "
                >
                    {{ t(
                        'home.description1'
                    ) }}
                </p>
            </div>

            <div
                class="
                    flex
                    w-full
                    max-w-[700px]
                    mx-auto
                    gap-4
                    justify-center
                    flex-col
                    md:flex-row
                    md:flex-row-reverse
                    items-center
                "
            >
                <img
                    :src="portraitUrl"
                    alt="Bruno Kristián"
                    class="
                        w-[15rem]
                        h-[20rem]
                        shrink-0
                        object-cover
                    "
                />

                <p class="p">
                    {{ t(
                        'home.about.intro'
                    ) }}
                </p>
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | SERVICES
        |--------------------------------------------------------------------------
        -->

        <section
            class="
                flex
                flex-col
                gap-6
            "
            data-theme="light"
        >
            <h2
                class="
                    h2
                    text-accent
                    text-center
                    px-4
                "
            >
                {{ t(
                    'home.services.title'
                ) }}
            </h2>

            <p
                v-if="
                    isServicesLoading
                "
                class="
                    p
                    text-center
                    px-4
                "
            >
                {{ t(
                    'services.loading'
                ) }}
            </p>

            <GridLayout
                v-else-if="
                    serviceCards.length
                "
                :cards="
                    serviceCards
                "
            />

            <Button
                v-if="
                    !isServicesLoading &&
                    serviceCards.length
                "
                :text="
                    t(
                        'home.services.viewAll'
                    )
                "
                @click="
                    $router.push(
                        '/services'
                    )
                "
            />
        </section>


        <!--
        |--------------------------------------------------------------------------
        | PROJECT GALLERY
        |--------------------------------------------------------------------------
        -->

        <section
            class="
                flex
                flex-col
                gap-6
            "
            data-theme="light"
        >
            <h2
                class="
                    h2
                    text-accent
                    text-center
                    px-4
                "
            >
                {{ t(
                    'home.projects.title'
                ) }}
            </h2>

            <div
                v-if="
                    displayedProjects.length
                "
                class="
                    w-full
                    max-w-[32rem]
                    mx-auto
                "
            >
                <div
                    class="
                        grid
                        grid-cols-2
                        gap-px
                        bg-accent
                        border
                        border-accent
                        w-full
                    "
                >

                    <!--
                    |--------------------------------------------------------------------------
                    | PROJECT
                    |--------------------------------------------------------------------------
                    -->

                    <a
                        v-for="(
                            item,
                            index
                        ) in displayedProjects"
                        :key="
                            `${item.link}-${index}`
                        "
                        :href="
                            item.link
                        "
                        class="
                            project-card
                            group
                            relative
                            min-w-0
                            aspect-[3/4]
                            overflow-hidden
                            bg-light
                        "
                        :aria-label="
                            item.projectName
                        "
                    >

                        <!--
                        |--------------------------------------------------------------------------
                        | IMAGE VIEWPORT
                        |--------------------------------------------------------------------------
                        -->

                        <div
                            class="
                                absolute
                                inset-0
                                overflow-hidden
                            "
                        >

                            <!--
                            |--------------------------------------------------------------------------
                            | SLIDING IMAGE
                            |--------------------------------------------------------------------------
                            -->

                            <Transition
                                name="project-slide"
                            >
                                <div
                                    :key="
                                        item.images[
                                            item.currentImage
                                        ]
                                    "
                                    class="
                                        absolute
                                        inset-0
                                        project-slide-image
                                    "
                                >
                                    <img
                                        :src="
                                            item.images[
                                                item.currentImage
                                            ]
                                        "
                                        :alt="
                                            item.projectName
                                        "
                                        class="
                                            w-full
                                            h-full
                                            object-cover
                                            project-image
                                        "
                                    />
                                </div>
                            </Transition>

                        </div>


                        <!--
                        |--------------------------------------------------------------------------
                        | HOVER INFORMATION
                        |--------------------------------------------------------------------------
                        -->

                        <div
                            class="
                                project-card-info
                                absolute
                                inset-0
                                flex
                                flex-col
                                items-center
                                justify-center
                                gap-4
                                p-4
                                bg-accent
                                text-center
                            "
                        >

                            <!-- LOGO -->

                            <img
                                v-if="
                                    item.logo
                                "
                                :src="
                                    item.logo
                                "
                                :alt="
                                    `${item.projectName} logo`
                                "
                                class="
                                    max-w-[6rem]
                                    max-h-[3rem]
                                    w-auto
                                    h-auto
                                    object-contain
                                "
                            />


                            <!-- TITLE + DESCRIPTION -->

                            <div
                                class="
                                    flex
                                    flex-col
                                    items-center
                                    gap-2
                                    w-full
                                "
                            >

                                <!-- TITLE -->

                                <div
                                    class="
                                        h-[3rem]
                                        w-full
                                        flex
                                        items-center
                                        justify-center
                                    "
                                >
                                    <h3
                                        class="
                                            h3
                                            text-light
                                            uppercase
                                            text-center
                                        "
                                    >
                                        {{
                                            item.projectName
                                        }}
                                    </h3>
                                </div>


                                <!-- DESCRIPTION -->

                                <div
                                    class="
                                        h-[5rem]
                                        w-full
                                        flex
                                        items-start
                                        justify-center
                                    "
                                >
                                    <p
                                        v-if="
                                            item.description
                                        "
                                        class="
                                            p
                                            text-light
                                            text-center
                                            max-w-[13rem]
                                        "
                                    >
                                        {{
                                            item.description
                                        }}
                                    </p>
                                </div>

                            </div>
                        </div>

                    </a>
                </div>
            </div>

            <Button
                :text="
                    t(
                        'home.recentProjects'
                    )
                "
                @click="
                    openRecentProjects
                "
            />
        </section>


        <!--
        |--------------------------------------------------------------------------
        | WORKFLOW
        |--------------------------------------------------------------------------
        -->

        <section
            class="
                relative
                overflow-hidden
                flex
                justify-center
            "
            data-theme="dark"
        >
            <img
                :src="bgUrl2"
                alt="Abstract dark background"
                class="
                    block
                    max-w-none
                    h-auto
                "
            />

            <div
                class="
                    absolute
                    inset-0
                    z-10
                    flex
                    flex-col
                    items-center
                    justify-center
                    text-center
                    gap-5
                    p-6
                "
            >
                <h2
                    class="
                        h2
                        text-light
                        max-w-[800px]
                    "
                >
                    {{ t(
                        'home.subtitle2'
                    ) }}
                </h2>

                <p
                    class="
                        p
                        uppercase
                        text-light
                        max-w-[700px]
                    "
                >
                    {{ t(
                        'home.description2'
                    ) }}
                </p>

                <Button
                    :text="
                        t(
                            'home.workflow'
                        )
                    "
                    variant="light"
                    @click="openWorkflow"
                    class="mt-12"
                />
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | FINAL CTA
        |--------------------------------------------------------------------------
        -->

        <section
            class="
                flex
                flex-col
                items-center
                justify-center
                text-center
                gap-5
                px-6
            "
            data-theme="light"
        >
            <h2
                class="
                    h2
                    text-accent
                    max-w-[800px]
                "
            >
                {{ t(
                    'home.final.title'
                ) }}
            </h2>

            <p
                class="
                    p
                    uppercase
                "
            >
                {{ t(
                    'home.final.description'
                ) }}
            </p>

            <Button
                :text="
                    t(
                        'home.final.cta'
                    )
                "
                @click="openContacts"
                class="mt-8"
            />
        </section>

    </main>
</template>


<style scoped>
/*
|--------------------------------------------------------------------------
| PROJECT CARD
|--------------------------------------------------------------------------
*/

.project-card {
    position: relative;
    background: var(--color-light);

    transition:
        background-color
        300ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}


/*
|--------------------------------------------------------------------------
| IMAGE VIEWPORT
|--------------------------------------------------------------------------
*/

.project-card
.project-slide-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}


/*
|--------------------------------------------------------------------------
| PROJECT IMAGE
|--------------------------------------------------------------------------
|
| This handles the hover zoom only.
| The sliding animation itself happens on
| the parent .project-slide-image.
|
*/

.project-image {
    width: 100%;
    height: 100%;
    object-fit: cover;

    transition:
        transform
        500ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}


/*
|--------------------------------------------------------------------------
| HOVER INFORMATION
|--------------------------------------------------------------------------
*/

.project-card-info {
    opacity: 0;

    transition:
        opacity
        300ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}


/*
|--------------------------------------------------------------------------
| DESKTOP HOVER
|--------------------------------------------------------------------------
*/

@media (
    hover: hover
) and (
    pointer: fine
) {
    .project-card:hover {
        background-color:
            var(--color-accent);
    }

    .project-card:hover
    .project-image {
        transform:
            scale(1.03);
    }

    .project-card:hover
    .project-card-info {
        opacity: 1;
    }
}


/*
|--------------------------------------------------------------------------
| INSTAGRAM-STYLE SLIDE
|--------------------------------------------------------------------------
*/

.project-slide-enter-active,
.project-slide-leave-active {
    transition:
        transform
        700ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );
}


/*
|--------------------------------------------------------------------------
| NEW IMAGE STARTS OUTSIDE ON THE RIGHT
|--------------------------------------------------------------------------
*/

.project-slide-enter-from {
    transform:
        translateX(100%);
}


/*
|--------------------------------------------------------------------------
| NEW IMAGE MOVES INTO POSITION
|--------------------------------------------------------------------------
*/

.project-slide-enter-to {
    transform:
        translateX(0);
}


/*
|--------------------------------------------------------------------------
| OLD IMAGE STARTS IN POSITION
|--------------------------------------------------------------------------
*/

.project-slide-leave-from {
    transform:
        translateX(0);
}


/*
|--------------------------------------------------------------------------
| OLD IMAGE LEAVES TO THE LEFT
|--------------------------------------------------------------------------
*/

.project-slide-leave-to {
    transform:
        translateX(-100%);
}


/*
|--------------------------------------------------------------------------
| REDUCED MOTION
|--------------------------------------------------------------------------
*/

@media (
    prefers-reduced-motion: reduce
) {
    .project-card,
    .project-image,
    .project-card-info,
    .project-slide-enter-active,
    .project-slide-leave-active {
        transition: none;
    }
}
</style>
