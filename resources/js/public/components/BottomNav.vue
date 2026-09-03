<script setup>
import {
    ref,
    onMounted,
    onUnmounted,
    nextTick,
    computed,
    watch
} from 'vue'

import {
    useI18n
} from 'vue-i18n'

import {
    useRoute
} from 'vue-router'

import Toast
    from '@shared/components/Toast.vue'

import Modal
    from '@shared/components/Modal.vue'

import FormField
    from '@shared/components/FormField.vue'

import Button
    from '@shared/components/Button.vue'

import Tag
    from '@shared/components/Tag.vue'

import router
    from '@public/router'


const isDarkBackground =
    ref(false)


const {
    locale,
    t
} =
    useI18n({
        useScope:
            'global'
    })


const route =
    useRoute()


const showToast =
    ref(false)


const toastHeading =
    ref('Language changed')


const toastText =
    ref('')


const localeCode =
    computed(() =>
        locale.value === 'en'
            ? 'EN'
            : 'SK'
    )


const isOnNavPage =
    computed(() =>
        route.name === 'nav'
    )


const returnToPath =
    computed(() => {

        const from =
            route.query.from

        return typeof from === 'string' &&
            from
            ? from
            : '/'
    })


/*
|--------------------------------------------------------------------------
| Consultation
|--------------------------------------------------------------------------
*/

const consultationOpen =
    ref(false)


const consultationSubmitting =
    ref(false)


const consultationSubmitted =
    ref(false)


const consultationForm =
    ref({

        name: '',

        service: '',

        contactMethod: '',

        email: '',

        phone: '',

        instagram: '',

        message: '',

        website: ''

    })


const servicesLoading =
    ref(false)


const services =
    ref([])


const loadServices =
    async () => {

        servicesLoading.value =
            true

        try {

            const response =
                await fetch(
                    `/api/services?locale=${locale.value}`,
                    {
                        headers: {
                            Accept:
                                'application/json'
                        }
                    }
                )

            if (
                !response.ok
            ) {

                throw new Error(
                    'Failed to load services.'
                )

            }

            const serviceProducts =
                await response.json()

            services.value =
                serviceProducts.map(
                    serviceProduct => ({

                        label:
                            serviceProduct.name,

                        value:
                            String(
                                serviceProduct.id
                            )

                    })
                )

        } catch (
            error
        ) {

            console.error(
                'Loading services failed:',
                error
            )

            services.value =
                []

        } finally {

            servicesLoading.value =
                false

        }

    }


const serviceOptions =
    computed(() => [

        ...services.value,

        {

            label:
                t(
                    'consultationForm.serviceOther'
                ),

            value:
                'other'

        }

    ])


const contactMethodOptions =
    computed(() => [

        {

            label:
                t(
                    'consultationForm.contactMethodCall'
                ),

            value:
                'call'

        },

        {

            label:
                t(
                    'consultationForm.contactMethodMessage'
                ),

            value:
                'message'

        },

        {

            label:
                t(
                    'consultationForm.contactMethodEmail'
                ),

            value:
                'email'

        },

        {

            label:
                t(
                    'consultationForm.contactMethodInstagram'
                ),

            value:
                'instagram'

        },

        {

            label:
                t(
                    'consultationForm.contactMethodWhatsapp'
                ),

            value:
                'whatsapp'

        }

    ])


const resetConsultationForm =
    () => {

        consultationForm.value = {

            name: '',

            service: '',

            contactMethod: '',

            email: '',

            phone: '',

            instagram: '',

            message: '',

            website: ''

        }

        consultationSubmitted.value =
            false

    }


const openConsultation =
    () => {

        resetConsultationForm()

        consultationOpen.value =
            true

        if (
            services.value.length === 0
        ) {

            loadServices()

        }

    }


const closeConsultation =
    () => {

        if (
            consultationSubmitting.value
        ) {

            return

        }

        consultationOpen.value =
            false

    }


const submitConsultation =
    async () => {

        if (
            consultationForm.value.website
        ) {

            return

        }

        consultationSubmitting.value =
            true

        try {

            const response =
                await fetch(
                    '/api/contact',
                    {

                        method:
                            'POST',

                        headers: {

                            'Content-Type':
                                'application/json',

                            Accept:
                                'application/json'

                        },

                        body:
                            JSON.stringify({

                                ...consultationForm.value,

                                locale:
                                    locale.value

                            })

                    }
                )

            if (
                !response.ok
            ) {

                throw new Error(
                    'Contact request failed.'
                )

            }

            if (
                consultationForm.value
                    .contactMethod ===
                'email'
            ) {

                consultationSubmitted.value =
                    true

            } else {

                consultationOpen.value =
                    false

                toastHeading.value =
                    t(
                        'consultationForm.toastHeading'
                    )

                toastText.value =
                    t(
                        'consultationForm.toastText'
                    )

                showToast.value =
                    false

                nextTick(() => {

                    showToast.value =
                        true

                })

            }

        } catch (
            error
        ) {

            console.error(
                'Consultation submission failed:',
                error
            )

            toastHeading.value =
                t(
                    'consultationForm.errorHeading'
                )

            toastText.value =
                t(
                    'consultationForm.errorText'
                )

            showToast.value =
                false

            nextTick(() => {

                showToast.value =
                    true

            })

        } finally {

            consultationSubmitting.value =
                false

        }

    }


/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
*/

const toggleLanguage =
    () => {

        locale.value =
            locale.value === 'en'
                ? 'sk'
                : 'en'

        localStorage.setItem(
            'locale',
            locale.value
        )

        const languageName =
            locale.value === 'en'
                ? 'English'
                : 'Slovak'

        toastText.value =
            `Language changed to ${languageName}.`

        showToast.value =
            false

        nextTick(() => {

            showToast.value =
                true

        })

    }


/*
|--------------------------------------------------------------------------
| Theme detection
|--------------------------------------------------------------------------
*/

let sections = []


const updateThemeFromNavPosition =
    () => {

        const probeX =
            Math.max(
                window.innerWidth - 40,
                0
            )

        const probeY =
            Math.max(
                window.innerHeight - 40,
                0
            )

        const elementsAtPoint =
            document.elementsFromPoint(
                probeX,
                probeY
            )

        const sectionFromPoint =
            elementsAtPoint
                .map(element =>
                    element.closest?.(
                        '[data-theme]'
                    )
                )
                .find(Boolean)

        if (
            sectionFromPoint
        ) {

            const theme =
                sectionFromPoint.dataset.theme ||
                'light'

            isDarkBackground.value =
                theme === 'dark'

            return

        }

        sections =
            Array.from(
                document.querySelectorAll(
                    '[data-theme]'
                )
            )

        const activeSection =
            sections.find(
                section => {

                    const rect =
                        section.getBoundingClientRect()

                    return (

                        rect.left <=
                            probeX &&

                        rect.right >=
                            probeX &&

                        rect.top <=
                            probeY &&

                        rect.bottom >=
                            probeY

                    )

                }
            )

        const theme =
            activeSection?.dataset.theme ||
            'light'

        isDarkBackground.value =
            theme === 'dark'

    }


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

function openNavigation() {

    if (
        isOnNavPage.value
    ) {

        router.push(
            returnToPath.value
        )

        return

    }

    router.push({

        name:
            'nav',

        query: {

            from:
                route.fullPath

        }

    })

}


/*
|--------------------------------------------------------------------------
| Keyboard
|--------------------------------------------------------------------------
*/

const handleKeydown =
    event => {

        if (

            event.key ===
                'Escape' &&

            consultationOpen.value

        ) {

            closeConsultation()

        }

    }


/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {

    sections =
        Array.from(
            document.querySelectorAll(
                '[data-theme]'
            )
        )

    nextTick(() => {

        requestAnimationFrame(
            updateThemeFromNavPosition
        )

    })

    window.addEventListener(
        'scroll',
        updateThemeFromNavPosition,
        {
            passive:
                true
        }
    )

    window.addEventListener(
        'resize',
        updateThemeFromNavPosition
    )

    window.addEventListener(
        'keydown',
        handleKeydown
    )

})


watch(
    () => route.fullPath,
    () => {

        nextTick(() => {

            sections =
                Array.from(
                    document.querySelectorAll(
                        '[data-theme]'
                    )
                )

            requestAnimationFrame(
                updateThemeFromNavPosition
            )

        })

    }
)


/*
|--------------------------------------------------------------------------
| Recompute theme detection after language changes
|--------------------------------------------------------------------------
|
| Translated copy has a different length, which reflows the page and
| shifts [data-theme] section boundaries without a route change. Without
| this, the scroll-based nav theme can stay stale until the next scroll.
*/

watch(
    locale,
    () => {

        nextTick(() => {

            sections =
                Array.from(
                    document.querySelectorAll(
                        '[data-theme]'
                    )
                )

            requestAnimationFrame(
                updateThemeFromNavPosition
            )

        })

    }
)


onUnmounted(() => {

    window.removeEventListener(
        'scroll',
        updateThemeFromNavPosition
    )

    window.removeEventListener(
        'resize',
        updateThemeFromNavPosition
    )

    window.removeEventListener(
        'keydown',
        handleKeydown
    )

})

</script>


<template>

    <!--
    |--------------------------------------------------------------------------
    | Toast
    |--------------------------------------------------------------------------
    -->

    <Toast
        v-model="
            showToast
        "

        :heading="
            toastHeading
        "

        :text="
            toastText
        "

        :duration="
            4000
        "
    />


    <!--
    |--------------------------------------------------------------------------
    | Navigation controls
    |--------------------------------------------------------------------------
    -->

    <nav
        class="
            fixed
            bottom-6
            right-6
            z-[1000]
            flex
            flex-col
            items-end
            gap-2
            transition-colors
            duration-500
        "

        :class="
            isDarkBackground
                ? 'text-white'
                : 'text-black'
        "
    >

        <!--
        |--------------------------------------------------------------------------
        | Navigation
        |--------------------------------------------------------------------------
        -->

        <button
            class="
                nav-control
            "

            type="button"

            @click="
                openNavigation
            "

            :title="
                isOnNavPage
                    ? 'Close navigation'
                    : 'Open navigation'
            "

            :aria-label="
                isOnNavPage
                    ? 'Close navigation'
                    : 'Open navigation'
            "
        >

            <span
                class="
                    menu-icon
                "

                :class="{
                    'menu-icon-open':
                        isOnNavPage
                }"
            >

                <span
                    class="
                        menu-line
                    "
                ></span>

                <span
                    class="
                        menu-line
                    "
                ></span>

            </span>

        </button>


        <!--
        |--------------------------------------------------------------------------
        | Language
        |--------------------------------------------------------------------------
        -->

        <button
            class="
                nav-control
            "

            type="button"

            @click="
                toggleLanguage
            "

            :title="
                locale === 'en'
                    ? 'Switch to Slovak'
                    : 'Switch to English'
            "

            :aria-label="
                locale === 'en'
                    ? 'Switch to Slovak'
                    : 'Switch to English'
            "
        >

            <span
                class="
                    language-icon
                "
            >

                {{
                    localeCode
                }}

            </span>

        </button>


        <!--
        |--------------------------------------------------------------------------
        | Free consultation
        |--------------------------------------------------------------------------
        -->

        <button
            type="button"
            class="
                p 
                uppercase
                px-2
                py-1
                gap-2
                flex
                items-center
                transition-transform
                duration-200
                ease-out
                hover:scale-[1.01]
            "
            @click="
                openConsultation
            "
            :aria-label="
                t('consultationForm.title')
            "
        >
            {{ t('consultationForm.title') }}
            <i class="bi bi-arrow-right"></i>
        </button>

    </nav>


    <!--
    |--------------------------------------------------------------------------
    | Consultation modal
    |--------------------------------------------------------------------------
    -->

    <Modal
        :open="
            consultationOpen
        "

        :title="
            consultationSubmitted
                ? t('consultationForm.successTitle')
                : t('consultationForm.title')
        "

        :subtitle="
            consultationSubmitted
                ? t('consultationForm.successText')
                : t('consultationForm.subtitle')
        "

        close-label="Close consultation"

        max-width-class="
            max-w-2xl
        "

        panel-class="
            overflow-y-auto
            border
            border-accent
            bg-light
            shadow-xl
        "

        body-class="
            p-0
        "

        @close="
            closeConsultation
        "
    >

        <!--
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        -->

        <div
            v-if="
                consultationSubmitted
            "

            class="
                flex
                flex-col
                gap-8
                bg-light
                p-5
                sm:p-6
            "
        >

            <div
                class="
                    flex
                    h-12
                    w-12
                    items-center
                    justify-center
                    border
                    border-accent
                    font-mono
                    text-sm
                "
            >

                ✓

            </div>


            <p
                class="
                    p
                    uppercase
                    text-dark/60
                "
            >

                {{
                    t(
                        'consultationForm.successText'
                    )
                }}

            </p>


            <Button
                type="button"

                :text="
                    t('consultationForm.close')
                "

                variant="accent"

                hover-variant="dark"

                align="right"

                @click="
                    closeConsultation
                "
            />

        </div>


        <!--
        |--------------------------------------------------------------------------
        | Form
        |--------------------------------------------------------------------------
        -->

        <form
            v-else

            class="
                space-y-8
                bg-light
                p-5
                sm:p-6
            "

            @submit.prevent="
                submitConsultation
            "
        >

            <!-- NAME -->

            <FormField
                id="
                    consultation-name
                "

                v-model="
                    consultationForm.name
                "

                name="name"

                type="text"

                :label="
                    t('consultationForm.name')
                "

                required
            />


            <!-- SERVICE -->

            <FormField
                id="
                    consultation-service
                "

                v-model="
                    consultationForm.service
                "

                name="service"

                type="select"

                :label="
                    t('consultationForm.service')
                "

                :options="
                    serviceOptions
                "

                :loading="
                    servicesLoading
                "

                required
            />


            <!-- CONTACT METHOD -->

            <FormField
                id="
                    consultation-contact-method
                "

                v-model="
                    consultationForm.contactMethod
                "

                name="contact_method"

                type="select"

                :label="
                    t('consultationForm.contactMethod')
                "

                :options="
                    contactMethodOptions
                "

                required
            />


            <!-- EMAIL -->

            <FormField
                v-if="
                    consultationForm.contactMethod ===
                    'email'
                "

                id="
                    consultation-email
                "

                v-model="
                    consultationForm.email
                "

                name="email"

                type="email"

                :label="
                    t('consultationForm.email')
                "

                required
            />


            <!-- PHONE -->

            <FormField
                v-if="
                    [
                        'call',
                        'message',
                        'whatsapp'
                    ].includes(
                        consultationForm.contactMethod
                    )
                "

                id="
                    consultation-phone
                "

                v-model="
                    consultationForm.phone
                "

                name="phone"

                type="text"

                :label="
                    t('consultationForm.phone')
                "

                required
            />


            <!-- INSTAGRAM -->

            <FormField
                v-if="
                    consultationForm.contactMethod ===
                    'instagram'
                "

                id="
                    consultation-instagram
                "

                v-model="
                    consultationForm.instagram
                "

                name="instagram"

                type="text"

                :label="
                    t('consultationForm.instagram')
                "
            />


            <!-- MESSAGE -->

            <FormField
                id="
                    consultation-message
                "

                v-model="
                    consultationForm.message
                "

                name="message"

                type="textarea"

                :label="
                    t('consultationForm.message')
                "

                :placeholder="
                    t(
                        'consultationForm.messagePlaceholder'
                    )
                "
            />


            <!--
            |--------------------------------------------------------------------------
            | Honeypot
            |--------------------------------------------------------------------------
            -->

            <div
                class="
                    absolute
                    -left-[9999px]
                    h-px
                    w-px
                    overflow-hidden
                "

                aria-hidden="true"
            >

                <label
                    for="
                        consultation-website
                    "
                >

                    Website

                </label>

                <input
                    id="
                        consultation-website
                    "

                    v-model="
                        consultationForm.website
                    "

                    type="text"

                    name="website"

                    tabindex="-1"

                    autocomplete="off"
                />

            </div>


            <!-- SUBMIT -->

            <div
                class="
                    flex
                    justify-end
                    pt-2
                "
            >

                <Button
                    type="submit"

                    :text="
                        consultationSubmitting
                            ? t(
                                'consultationForm.submitting'
                            )
                            : t(
                                'consultationForm.submit'
                            )
                    "

                    variant="accent"

                    hover-variant="dark"

                    align="right"

                    :loading="
                        consultationSubmitting
                    "

                    :disabled="
                        consultationSubmitting
                    "
                />

            </div>

        </form>

    </Modal>

</template>


<style scoped>

/*
|--------------------------------------------------------------------------
| Navigation control
|--------------------------------------------------------------------------
*/

.nav-control {

    color: inherit;

    background: transparent;

    border: 0;

    cursor: pointer;

}


.nav-control {

    position: relative;

    width: 24px;

    height: 24px;

    padding: 0;

    margin: 0;

    display: flex;

    align-items: center;

    justify-content: center;

    transition:
        transform 220ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );

}


.nav-control:hover {

    transform:
        scale(1.08);

}


.nav-control:active {

    transform:
        scale(0.94);

}


/*
|--------------------------------------------------------------------------
| Menu icon
|--------------------------------------------------------------------------
*/

.menu-icon {

    position: relative;

    width: 18px;

    height: 14px;

    display: block;

    transition:
        transform 350ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );

}


.menu-line {

    position: absolute;

    left: 0;

    width: 18px;

    height: 1px;

    background:
        currentColor;

    transform-origin:
        center;

    transition:
        transform 350ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        ),

        top 350ms
        cubic-bezier(
            0.16,
            1,
            0.3,
            1
        );

}


.menu-line:first-child {

    top: 2px;

}


.menu-line:last-child {

    top: 10px;

}


.menu-icon-open
    .menu-line:first-child {

    top: 6px;

    transform:
        rotate(45deg);

}


.menu-icon-open
    .menu-line:last-child {

    top: 6px;

    transform:
        rotate(-45deg);

}


/*
|--------------------------------------------------------------------------
| Language
|--------------------------------------------------------------------------
*/

.language-icon {

    width: 18px;

    height: 18px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: inherit;

    font-family: inherit;

    font-size: 14px;

    font-weight: 300;

    line-height: 1;

    letter-spacing:
        0.08em;

}


/*
|--------------------------------------------------------------------------
| Reduced motion
|--------------------------------------------------------------------------
*/

@media (
    prefers-reduced-motion: reduce
) {

    .nav-control,
    .consultation-tag,
    .menu-icon,
    .menu-line {

        animation: none;

        transition: none;

    }

}

</style>