    <script setup>
    import {
        computed,
        onBeforeUnmount,
        reactive,
        ref,
        watch
    } from 'vue'

    import api, {
        errorMessage,
        validationErrors
    } from '../../composables/useAdminApi'

    import useAutosavePolicy from '../../composables/useAutosavePolicy'
    import { useAdminPageHeader } from '../../composables/useAdminPageHeader'
    import { useProjectContext } from '../../composables/useProjectContext'

    import FormField from '@shared/components/FormField.vue'
    import Toast from '@shared/components/Toast.vue'

    const {
        project,
        setProject
    } = useProjectContext()

    const {
        enabled: autosaveEnabled,
        setLastSavedAt,
        setStatus
    } = useAutosavePolicy()

    const lookups =
        ref({
            service_products: []
        })

    const errors =
        ref({})

    const requestError =
        ref('')

    const showErrorToast =
        ref(false)

    const saving =
        ref(false)

    const suppressAutosave =
        ref(true)

    const autosaveTimer =
        ref(null)

    const lastSavedSnapshot =
        ref('')

    const form =
        reactive({
            company_id: '',
            service_product_id: '',
            name: '',
            summary: '',
            internal_notes: '',
            portal_status: 'draft',
            is_saas: false,
            started_at: '',
            completed_at: '',
            contact_ids: [],
            coworker_ids: []
        })

    const shellUser = (() => {
        try {
            return JSON.parse(
                document.querySelector('#client-portal-admin-user')?.textContent ||
                '{}'
            )
        } catch {
            return {}
        }
    })()

    const canManage =
        computed(() =>
            Boolean(
                project.value?.current_user?.is_admin ??
                shellUser?.is_admin
            )
        )

    const statusOptions = [
        { label: 'Draft', value: 'draft' },
        { label: 'Active', value: 'active' },
        { label: 'On hold', value: 'on_hold' },
        { label: 'Completed', value: 'completed' },
        { label: 'Archived', value: 'archived' }
    ]

    const serviceOptions =
        computed(() =>
            (lookups.value.service_products || []).map(
                product => ({
                    label: product.active
                        ? product.name
                        : `${product.name} (inactive)`,
                    value: String(product.id),
                    disabled: !product.active &&
                        String(product.id) !==
                        String(form.service_product_id)
                })
            )
        )

    function payload() {
        return {
            company_id: form.company_id,
            service_product_id: form.service_product_id,
            name: form.name,
            summary: form.summary,
            internal_notes: form.internal_notes,
            portal_status: form.portal_status,
            is_saas: form.is_saas,
            started_at: form.started_at,
            completed_at: form.completed_at,
            contact_ids: form.contact_ids,
            coworker_ids: form.coworker_ids
        }
    }

    function snapshot() {
        return JSON.stringify(
            payload()
        )
    }

    function applyProject(value) {
        if (!value) {
            return
        }

        suppressAutosave.value =
            true

        Object.assign(
            form,
            {
                company_id: String(value.company_id || ''),
                service_product_id: String(value.service_product_id || ''),
                name: value.name || '',
                summary: value.summary || '',
                internal_notes: value.internal_notes || '',
                portal_status: value.status || 'draft',
                is_saas: Boolean(value.is_saas),
                started_at: value.started_at || '',
                completed_at: value.completed_at || '',
                contact_ids: (value.contacts || []).map(contact => contact.id),
                coworker_ids: (value.coworkers || []).map(coworker => coworker.id)
            }
        )

        lastSavedSnapshot.value =
            snapshot()

        queueMicrotask(() => {
            suppressAutosave.value =
                false
        })
    }

    async function loadLookups() {
        if (!canManage.value) {
            return
        }

        try {
            lookups.value =
                (await api.get('/lookups')).data ||
                lookups.value
        } catch (exception) {
            showError(
                errorMessage(exception)
            )
        }
    }

    function showError(message) {
        requestError.value =
            message

        showErrorToast.value =
            false

        requestAnimationFrame(() => {
            showErrorToast.value =
                true
        })
    }

    async function save() {
        if (
            !canManage.value ||
            saving.value ||
            !form.company_id ||
            !form.service_product_id ||
            !String(form.name).trim()
        ) {
            return
        }

        saving.value =
            true

        suppressAutosave.value =
            true

        errors.value =
            {}

        setStatus('saving')

        try {
            const response =
                await api.put(
                    `/projects/${project.value.id}`,
                    payload()
                )

            const updated =
                response.data?.data ||
                project.value

            setProject(updated)
            applyProject(updated)
            setLastSavedAt()
        } catch (exception) {
            errors.value =
                validationErrors(exception)

            showError(
                errorMessage(exception)
            )
        } finally {
            saving.value =
                false

            suppressAutosave.value =
                false

            setStatus('idle')
        }
    }

    watch(
        project,
        value => {
            applyProject(value)
            void loadLookups()
        },
        {
            immediate: true
        }
    )

    watch(
        form,
        () => {
            if (
                suppressAutosave.value ||
                !autosaveEnabled.value ||
                snapshot() === lastSavedSnapshot.value
            ) {
                return
            }

            clearTimeout(
                autosaveTimer.value
            )

            autosaveTimer.value =
                setTimeout(
                    () => void save(),
                    600
                )
        },
        {
            deep: true
        }
    )

    onBeforeUnmount(() => {
        clearTimeout(
            autosaveTimer.value
        )
    })

    useAdminPageHeader({
        title: computed(() => form.name || 'Project'),
        eyebrow: computed(() => project.value?.project_code || 'Project'),
        description: computed(() => form.summary || 'Project workspace and delivery.'),
        breadcrumbs: computed(() => [
            {
                label: 'Projects',
                to: { name: 'projects.index' }
            },
            {
                label: form.name || 'Project'
            }
        ])
    })
    </script>

    <template>
        <div class="w-full space-y-14">
            <Toast
                v-model="showErrorToast"
                heading="Something went wrong"
                :text="requestError"
                :duration="5000"
            />

            <section
                v-if="canManage"
                class="space-y-14"
            >
                <h2 class="h2 text-accent">
                    Project information
                </h2>

                <div class="grid grid-cols-1 gap-8 md:grid-cols-2 md:gap-20">
                    <section class="space-y-8">
                        <FormField
                            id="project-name"
                            v-model="form.name"
                            name="name"
                            type="text"
                            label="Project name"
                            placeholder="Project name"
                            required
                            :error="errors.name?.[0] || ''"
                        />

                        <FormField
                            id="project-service"
                            v-model="form.service_product_id"
                            name="service_product_id"
                            type="select"
                            label="Service product"
                            :options="serviceOptions"
                            required
                            :error="errors.service_product_id?.[0] || ''"
                        />

                        <FormField
                            id="project-status"
                            v-model="form.portal_status"
                            name="portal_status"
                            type="select"
                            label="Status"
                            :options="statusOptions"
                            :error="errors.portal_status?.[0] || ''"
                        />

                        <FormField
                            id="project-is-saas"
                            v-model="form.is_saas"
                            type="toggle"
                            label="Is SaaS"
                            :error="errors.is_saas?.[0] || ''"
                        />

                        <div class="grid gap-7 sm:grid-cols-2">
                            <FormField
                                id="project-started"
                                v-model="form.started_at"
                                name="started_at"
                                type="date"
                                label="Started"
                                :error="errors.started_at?.[0] || ''"
                            />

                            <FormField
                                id="project-completed"
                                v-model="form.completed_at"
                                name="completed_at"
                                type="date"
                                label="Completed"
                                :error="errors.completed_at?.[0] || ''"
                            />
                        </div>
                    </section>

                    <section class="space-y-8">
                        <FormField
                            id="project-summary"
                            v-model="form.summary"
                            name="summary"
                            type="textarea"
                            label="Summary"
                            placeholder="Brief description of the project"
                            :error="errors.summary?.[0] || ''"
                        />

                        <FormField
                            id="project-notes"
                            v-model="form.internal_notes"
                            name="internal_notes"
                            type="textarea"
                            label="Internal notes"
                            placeholder="Visible only to your team"
                            :error="errors.internal_notes?.[0] || ''"
                        />
                    </section>
                </div>
            </section>

            <section
                v-if="project?.todo_signatures?.length"
                class="space-y-4"
            >
                <h2 class="h2 text-left text-accent">
                    Actions required
                </h2>

                <p class="p uppercase">
                    {{ project.todo_signatures.length }} client signature request(s) are waiting in Files.
                </p>
            </section>
        </div>
    </template>