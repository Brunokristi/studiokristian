<script setup>
import {
    computed,
    onMounted,
    reactive,
    ref
} from 'vue'


import {
    useRoute,
    useRouter
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminConfirmDialog from '../../components/AdminConfirmDialog.vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'


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
                label: 'Team'
            },

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

        return
    }


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


async function submit() {
    if (
        saving.value
    ) {
        return
    }


    saving.value =
        true


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
            await api.post(
                '/coworkers',
                payload
            )
        }


        router.push({
            name:
                'coworkers.index'
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


function cancel() {
    router.push({
        name:
            'coworkers.index'
    })
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


onMounted(
    async () => {
        await Promise.all([
            loadProjects(),
            loadCoworker()
        ])
    }
)
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


        <!-- Header -->
        <AdminPageHeader
            :title="
                pageTitle
            "
            :description="
                editing
                    ? 'Update the coworker and manage their project access.'
                    : 'Create a coworker and assign the projects they can access.'
            "
            :breadcrumbs="
                breadcrumbs
            "
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
            <div
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
                        <div
                            class="
                                space-y-3
                            "
                        >
                            <p
                                class="
                                    h3
                                "
                            >
                                Projects
                            </p>


                            <p
                                class="
                                    p
                                    uppercase
                                    text-dark/40
                                "
                            >
                                Select the projects this
                                coworker can access.
                            </p>


                            <div
                                class="
                                    border-b
                                    border-dark
                                "
                            >
                                <select
                                    id="coworker-projects"
                                    v-model="
                                        form.project_ids
                                    "
                                    multiple
                                    :disabled="
                                        projectsLoading ||
                                        saving
                                    "
                                    class="
                                        block
                                        min-h-48
                                        w-full
                                        resize-none
                                        border-0
                                        bg-transparent
                                        px-0
                                        py-2
                                        text-dark
                                        outline-none
                                        focus:ring-0
                                    "
                                >
                                    <option
                                        v-for="
                                            project in projects
                                        "
                                        :key="
                                            project.id
                                        "
                                        :value="
                                            project.id
                                        "
                                    >
                                        {{
                                            project.name
                                        }}
                                    </option>
                                </select>
                            </div>


                            <p
                                v-if="
                                    errors.project_ids
                                "
                                class="
                                    p
                                    text-red-700
                                "
                            >
                                {{
                                    errors.project_ids[0]
                                }}
                            </p>
                        </div>
                    </section>
                </div>
            </div>


            <!-- Actions -->
            <div
                class="
                    flex
                    flex-col
                    gap-4
                "
            >
                <Button
                    type="button"
                    text="cancel"
                    :disabled="
                        saving
                    "
                    align="left"
                    @click="
                        cancel
                    "
                />


                <Button
                    type="submit"
                    :text="
                        editing
                            ? 'save changes'
                            : 'create coworker'
                    "
                    loading-text="saving"
                    :loading="
                        saving
                    "
                    :disabled="
                        saving
                    "
                    variant="accent"
                    align="left"
                />
            </div>


            <!-- Danger zone -->
            <section
                v-if="
                    editing
                "
                class="
                    space-y-4
                    border-t
                    border-accent
                    pt-8
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