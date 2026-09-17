    <script setup>
    import {
        computed,
        ref
    } from 'vue'

    import {
        useRouter
    } from 'vue-router'

    import api, {
        errorMessage
    } from '../../composables/useAdminApi'

    import { useAdminPageHeader } from '../../composables/useAdminPageHeader'
    import { useProjectContext } from '../../composables/useProjectContext'

    import AdminConfirmDialog from '@shared/components/ConfirmDialog.vue'
    import Button from '@shared/components/Button.vue'
    import Toast from '@shared/components/Toast.vue'

    const router =
        useRouter()

    const {
        project
    } = useProjectContext()

    const busy =
        ref(false)

    const showConfirm =
        ref(false)

    const requestError =
        ref('')

    const showErrorToast =
        ref(false)

    async function destroyProject() {
        if (
            !project.value?.id ||
            busy.value
        ) {
            return
        }

        busy.value =
            true

        try {
            await api.delete(
                `/projects/${project.value.id}`
            )

            await router.push({
                name: 'projects.index'
            })
        } catch (exception) {
            requestError.value =
                errorMessage(exception)

            showErrorToast.value =
                true
        } finally {
            busy.value =
                false
        }
    }

    useAdminPageHeader({
        title: computed(() => project.value?.name || 'Project'),
        eyebrow: computed(() => project.value?.project_code || 'Project'),
        description: 'Destructive project actions.',
        breadcrumbs: computed(() => [
            {
                label: 'Projects',
                to: { name: 'projects.index' }
            },
            {
                label: project.value?.name || 'Project'
            },
            {
                label: 'Danger zone'
            }
        ])
    })
    </script>

    <template>
        <section class="w-full space-y-8">
            <Toast
                v-model="showErrorToast"
                heading="Something went wrong"
                :text="requestError"
                :duration="5000"
            />

            <div>
                <h2 class="h2 text-left text-accent">
                    Danger zone
                </h2>

                <p class="p mt-3 max-w-2xl text-dark/60">
                    Deleting this project permanently removes its workspace. This action cannot be undone.
                </p>
            </div>

            <Button
                type="button"
                text="delete project"
                align="left"
                :loading="busy"
                @click="showConfirm = true"
            />

            <AdminConfirmDialog
                :open="showConfirm"
                title="Delete project?"
                text="This action cannot be undone."
                confirm-text="Delete project"
                :busy="busy"
                @close="showConfirm = false"
                @confirm="destroyProject"
            />
        </section>
    </template>