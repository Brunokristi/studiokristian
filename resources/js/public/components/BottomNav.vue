<script setup>
import { ref, onMounted, onUnmounted, nextTick, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import Toast from '@shared/components/Toast.vue'
import router from '@public/router'

const isDarkBackground = ref(false)
const { locale } = useI18n({ useScope: 'global' })
const route = useRoute()

const showToast = ref(false)
const toastHeading = ref('Language changed')
const toastText = ref('')

const localeCode = computed(() => (locale.value === 'en' ? 'EN' : 'SK'))

const isOnNavPage = computed(() => route.name === 'nav')

const returnToPath = computed(() => {
    const from = route.query.from

    return typeof from === 'string' && from
        ? from
        : '/'
})

let sections = []

const toggleLanguage = () => {
    locale.value = locale.value === 'en' ? 'sk' : 'en'
    localStorage.setItem('locale', locale.value)

    const languageName =
        locale.value === 'en'
            ? 'English'
            : 'Slovak'

    toastText.value =
        `Language changed to ${languageName}.`

    showToast.value = false

    nextTick(() => {
        showToast.value = true
    })
}

const updateThemeFromNavPosition = () => {
    const probeX = Math.max(
        window.innerWidth - 40,
        0
    )

    const probeY = Math.max(
        window.innerHeight - 40,
        0
    )

    const elementsAtPoint =
        document.elementsFromPoint(
            probeX,
            probeY
        )

    const sectionFromPoint = elementsAtPoint
        .map((element) =>
            element.closest?.('[data-theme]')
        )
        .find(Boolean)

    if (sectionFromPoint) {
        const theme =
            sectionFromPoint.dataset.theme ||
            'light'

        isDarkBackground.value =
            theme === 'dark'

        return
    }

    sections = Array.from(
        document.querySelectorAll(
            '[data-theme]'
        )
    )

    const activeSection = sections.find(
        (section) => {
            const rect =
                section.getBoundingClientRect()

            return (
                rect.left <= probeX &&
                rect.right >= probeX &&
                rect.top <= probeY &&
                rect.bottom >= probeY
            )
        }
    )

    const theme =
        activeSection?.dataset.theme ||
        'light'

    isDarkBackground.value =
        theme === 'dark'
}

function openNavigation() {
    if (isOnNavPage.value) {
        router.push(returnToPath.value)

        return
    }

    router.push({
        name: 'nav',
        query: {
            from: route.fullPath,
        },
    })
}

onMounted(() => {
    sections = Array.from(
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
            passive: true,
        }
    )

    window.addEventListener(
        'resize',
        updateThemeFromNavPosition
    )
})

watch(
    () => route.fullPath,
    () => {
        nextTick(() => {
            sections = Array.from(
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
})
</script>

<template>
    <Toast
        v-model="showToast"
        :heading="toastHeading"
        :text="toastText"
        :duration="4000"
    />

    <nav
        class="fixed bottom-6 right-6 flex flex-col gap-2 transition-colors duration-500 z-[1000]"
        :class="
            isDarkBackground
                ? 'text-white'
                : 'text-black'
        "
    >
        <!-- NAVIGATION -->
        <button
            class="nav-control"
            type="button"
            @click="openNavigation"
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
                class="menu-icon"
                :class="{
                    'menu-icon-open': isOnNavPage,
                }"
            >
                <span class="menu-line"></span>
                <span class="menu-line"></span>
            </span>
        </button>

        <!-- LANGUAGE -->
        <button
            class="nav-control"
            type="button"
            @click="toggleLanguage"
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
            <span class="language-icon">
                {{ localeCode }}
            </span>
        </button>
    </nav>
</template>

<style scoped>
.nav-control {
    position: relative;

    width: 24px;
    height: 24px;

    padding: 0;
    margin: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    color: inherit;

    background: transparent;
    border: 0;

    cursor: pointer;

    transition:
        transform 220ms
        cubic-bezier(0.16, 1, 0.3, 1);
}

.nav-control:hover {
    transform: scale(1.08);
}

.nav-control:active {
    transform: scale(0.94);
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
        cubic-bezier(0.16, 1, 0.3, 1);
}

.menu-line {
    position: absolute;

    left: 0;

    width: 18px;
    height: 1px;

    background: currentColor;

    transform-origin: center;

    transition:
        transform 350ms
        cubic-bezier(0.16, 1, 0.3, 1),
        top 350ms
        cubic-bezier(0.16, 1, 0.3, 1);
}

.menu-line:first-child {
    top: 2px;
}

.menu-line:last-child {
    top: 10px;
}

.menu-icon-open .menu-line:first-child {
    top: 6px;

    transform: rotate(45deg);
}

.menu-icon-open .menu-line:last-child {
    top: 6px;

    transform: rotate(-45deg);
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

    letter-spacing: 0.08em;
}

/*
|--------------------------------------------------------------------------
| Reduced motion
|--------------------------------------------------------------------------
*/

@media (prefers-reduced-motion: reduce) {
    .nav-control,
    .menu-icon,
    .menu-line {
        transition: none;
    }
}
</style>