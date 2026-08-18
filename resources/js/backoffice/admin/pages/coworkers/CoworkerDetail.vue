<script setup>
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch
} from 'vue'


import {
    useRoute,
    useRouter
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'
import useAutosavePolicy from '../../composables/useAutosavePolicy'


import AdminConfirmDialog from '@shared/components/ConfirmDialog.vue'


import Button from '@shared/components/Button.vue'
import { useAdminPageHeader } from '../../composables/useAdminPageHeader'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'


const {
    setLastSavedAt,
    setStatus
} =
    useAutosavePolicy()


const props =
    defineProps({
        id: {
            type: String,
            default: ''
        }
    })


const route =
    useRoute()


const router =
    useRouter()


const loading =
    ref(
        Boolean(
            props.id
        )
    )


const saving =
    ref(false)


const deleting =
    ref(false)


const showDeleteConfirm =
    ref(false)


const errors =
    ref({})


const requestError =
    ref('')


const projects =
    ref([])


const projectsLoading =
    ref(false)


const autosaveTimer =
    ref(null)


const suppressAutosave =
    ref(false)


const lastSavedSnapshot =
    ref('')


const showErrorToast =
    ref(false)


const form =
    reactive({
        name: '',
        email: '',
        project_ids: []
    })


const editing =
    computed(() =>
        Boolean(
            props.id
        )
    )


const pageTitle =
    computed(() => {
        if (
            !editing.value
        ) {
            return 'New coworker'
        }


        return (
            form.name ||
            'Coworker'
        )
    })


const breadcrumbs =
    computed(() => {
        const items = [
            {
                label: 'Coworkers',
                to: {
                    name:
                        'coworkers.index'
                }
            }
        ]


        items.push({
            label:
                editing.value
                    ? pageTitle.value
                    : 'New coworker'
        })


        return items
    })


const projectOptions =
    computed(() =>
        projects.value.map(
            project => ({
                label:
                    project.name,

                value:
                    project.id
            })
        )
    )


function showError(
    message
) {
    requestError.value =
        message


    showErrorToast.value =
        false


    requestAnimationFrame(() => {
        showErrorToast.value =
            true
    })
}


function getAutosaveSnapshot() {
    return JSON.stringify({
        name:
            String(
                form.name || ''
            ).trim(),

        email:
            String(
                form.email || ''
            ).trim().toLowerCase(),

        project_ids:
            [...form.project_ids]
                .map(value => Number(value))
                .filter(Number.isFinite)
                .sort((a, b) => a - b)
    })
}


function canAutosave() {
    const email =
        String(
            form.email || ''
        ).trim()


    const hasValidEmail =
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            email
        )


    return Boolean(
        String(form.name || '').trim() &&
        hasValidEmail
    )
}


async function loadProjects() {
    projectsLoading.value =
        true


    try {
        const response =
            await api.get(
                '/projects'
            )


        projects.value =
            response.data.data ||
            []
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        projectsLoading.value =
            false
    }
}


async function loadCoworker() {
    if (
        !props.id
    ) {
        loading.value =
            false

        lastSavedSnapshot.value =
            getAutosaveSnapshot()

        return
    }


    suppressAutosave.value =
        true


    try {
        const response =
            await api.get(
                `/coworkers/${props.id}`
            )


        const coworker =
            response.data.data ||
            response.data


        Object.assign(
            form,
            {
                name:
                    coworker.name ||
                    '',

                email:
                    coworker.email ||
                    '',

                project_ids:
                    (
                        coworker.projects ||
                        []
                    ).map(
                        project =>
                            project.id
                    )
            }
        )


        lastSavedSnapshot.value =
            getAutosaveSnapshot()
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


        suppressAutosave.value =
            false
    }
}


async function saveCoworker() {
    if (
        saving.value
    ) {
        return
    }


    if (
        !canAutosave()
    ) {
        return
    }


    suppressAutosave.value =
        true

    saving.value =
        true


    setStatus(
        'saving'
    )


    errors.value =
        {}


    requestError.value =
        ''


    try {
        const payload = {
            name:
                form.name,

            email:
                form.email,

            project_ids:
                form.project_ids
        }


        if (
            editing.value
        ) {
            await api.put(
                `/coworkers/${props.id}`,
                payload
            )
        } else {
            const response =
                await api.post(
                '/coworkers',
                payload
            )


            const createdId =
                response?.data?.data?.id ||
                response?.data?.id ||
                null


            if (
                createdId
            ) {
                router.replace({
                    name:
                        'coworkers.edit',

                    params: {
                        id:
                            createdId
                    }
                })


                lastSavedSnapshot.value =
                    getAutosaveSnapshot()


                setLastSavedAt()


                return
            }
        }


        lastSavedSnapshot.value =
            getAutosaveSnapshot()


        setLastSavedAt()
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


        setStatus(
            'idle'
        )


        suppressAutosave.value =
            false
    }
}


function deleteCoworker() {
    if (
        !editing.value ||
        !props.id ||
        deleting.value
    ) {
        return
    }


    showDeleteConfirm.value =
        true
}


async function confirmDeleteCoworker() {
    if (
        !editing.value ||
        !props.id ||
        deleting.value
    ) {
        return
    }


    deleting.value =
        true


    requestError.value =
        ''


    try {
        await api.delete(
            `/coworkers/${props.id}`
        )


        showDeleteConfirm.value =
            false


        router.push({
            name:
                'coworkers.index'
        })
    } catch (
        exception
    ) {
        showDeleteConfirm.value =
            false


        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        deleting.value =
            false
    }
}


function closeDeleteConfirm() {
    if (
        deleting.value
    ) {
        return
    }


    showDeleteConfirm.value =
        false
}


function scheduleAutosave() {
    if (
        suppressAutosave.value ||
        loading.value ||
        saving.value ||
        !canAutosave()
    ) {
        return
    }


    const snapshot =
        getAutosaveSnapshot()


    if (
        lastSavedSnapshot.value &&
        snapshot ===
        lastSavedSnapshot.value
    ) {
        return
    }


    if (
        autosaveTimer.value
    ) {
        clearTimeout(
            autosaveTimer.value
        )
    }


    autosaveTimer.value =
        setTimeout(() => {
            if (
                !saving.value &&
                canAutosave()
            ) {
                void saveCoworker()
            }
        }, 600)
}


watch(
    () => ({
        ...form
    }),
    () => {
        scheduleAutosave()
    },
    {
        deep: true
    }
)


onMounted(
    async () => {
        await Promise.all([
            loadProjects(),
            loadCoworker()
        ])
    }
)


onBeforeUnmount(
    () => {
        if (
            autosaveTimer.value
        ) {
            clearTimeout(
                autosaveTimer.value
            )
        }
    }
)
useAdminPageHeader({
    title: pageTitle,
    description: computed(() =>
        editing.value
            ? 'Update the coworker and manage their project access.'
            : 'Create a coworker and assign the projects they can access. Changes save automatically.'
    ),
    breadcrumbs
})
</script>


<template>
    <div
        class="
            w-full
            space-y-12
            lg:space-y-14
        "
    >
        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="requestError"
            :duration="5000"
        />


        <!-- Loading -->
        <div
            v-if="
                loading
            "
            class="
                border-t
                border-accent
                pt-6
            "
        >
            <p
                class="
                    p
                    uppercase
                    text-dark/40
                "
            >
                Loading coworker...
            </p>
        </div>


        <!-- Form -->
        <form
            v-else
            class="
                space-y-16
            "
            @submit.prevent="
                submit
            "
        >
            <!-- Information -->
            <section
                class="
                    space-y-8
                "
            >
                <h2
                    class="
                        h2
                        text-left
                        text-accent
                    "
                >
                    Coworker information
                </h2>


                <div
                    class="
                        grid
                        grid-cols-1
                        gap-8
                        md:grid-cols-2
                        md:gap-20
                    "
                >
                    <!-- Basic information -->
                    <section
                        class="
                            space-y-8
                        "
                    >
                        <FormField
                            id="coworker-name"
                            v-model="
                                form.name
                            "
                            name="name"
                            type="text"
                            label="Name"
                            placeholder="Full name"
                            autocomplete="name"
                            required
                            :error="
                                errors.name?.[0] ||
                                ''
                            "
                        />


                        <FormField
                            id="coworker-email"
                            v-model="
                                form.email
                            "
                            name="email"
                            type="email"
                            label="Email"
                            placeholder="name@company.com"
                            autocomplete="email"
                            required
                            :error="
                                errors.email?.[0] ||
                                ''
                            "
                        />
                    </section>


                    <!-- Project access -->
                    <section
                        class="
                            space-y-8
                        "
                    >
                        <FormField
                            id="coworker-projects"
                            v-model="
                                form.project_ids
                            "
                            name="project_ids"
                            type="select"
                            label="Projects"
                            placeholder="Select projects"
                            multiple
                            :options="
                                projectOptions
                            "
                            :loading="
                                projectsLoading
                            "
                            :disabled="
                                projectsLoading ||
                                saving
                            "
                            :error="
                                errors.project_ids?.[0] ||
                                ''
                            "
                        />
                    </section>
                </div>
            </section>


            <!-- Danger zone -->
            <section
                v-if="
                    editing
                "
                class="
                    space-y-4
                "
            >
                <h2
                    class="
                        h2
                        text-left
                        text-accent
                    "
                >
                    Danger zone
                </h2>


                <Button
                    type="button"
                    text="delete coworker"
                    :loading="
                        deleting
                    "
                    :disabled="
                        deleting
                    "
                    align="left"
                    @click="
                        deleteCoworker
                    "
                />
            </section>
        </form>


        <!-- Delete confirmation -->
        <AdminConfirmDialog
            :open="
                showDeleteConfirm
            "
            title="Delete coworker?"
            :text="
                `This will permanently delete ${pageTitle}. This action cannot be undone.`
            "
            confirm-label="Delete coworker"
            :busy="
                deleting
            "
            @close="
                closeDeleteConfirm
            "
            @confirm="
                confirmDeleteCoworker
            "
        />
    </div>
</template>