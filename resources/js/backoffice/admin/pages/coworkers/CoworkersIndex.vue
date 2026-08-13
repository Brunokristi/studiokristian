<script setup>
import {
    computed,
    nextTick,
    onMounted,
    ref
} from 'vue'


import {
    RouterLink
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminDataTable from '../../components/AdminDataTable.vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Toast from '@shared/components/Toast.vue'
import Tag from '@shared/components/Tag.vue'


const list =
    ref([])


const projects =
    ref([])


const loading =
    ref(true)


const saving =
    ref(false)


const error =
    ref('')


const errors =
    ref({})


const search =
    ref('')


const showToast =
    ref(false)


const toastHeading =
    ref('')


const toastText =
    ref('')


const form =
    ref({
        name: '',
        email: '',
        project_ids: []
    })


const columns = [
    {
        key: 'name',
        label: 'Name',
        sortable: true
    },
    {
        key: 'email',
        label: 'Email',
        sortable: true
    },
    {
        key: 'projects',
        label: 'Projects'
    }
]


const filteredCoworkers =
    computed(() => {
        const query =
            search.value
                .trim()
                .toLowerCase()


        if (!query) {
            return list.value
        }


        return list.value.filter(
            person => {
                const name =
                    String(
                        person.name || ''
                    ).toLowerCase()


                const email =
                    String(
                        person.email || ''
                    ).toLowerCase()


                const projectNames =
                    (
                        person.projects ||
                        []
                    )
                        .map(
                            project =>
                                project.name || ''
                        )
                        .join(' ')
                        .toLowerCase()


                return (
                    name.includes(query) ||
                    email.includes(query) ||
                    projectNames.includes(query)
                )
            }
        )
    })


function resetForm() {
    form.value = {
        name: '',
        email: '',
        project_ids: []
    }


    errors.value = {}
}


function showError(message) {
    toastHeading.value =
        'Something went wrong'


    toastText.value =
        message


    showToast.value =
        false


    nextTick(() => {
        showToast.value =
            true
    })
}


function showSuccess(message) {
    toastHeading.value =
        'Coworker created'


    toastText.value =
        message


    showToast.value =
        false


    nextTick(() => {
        showToast.value =
            true
    })
}


async function load() {
    loading.value =
        true


    try {
        const [
            coworkersResponse,
            projectsResponse
        ] = await Promise.all([
            api.get('/coworkers'),
            api.get('/projects')
        ])


        list.value =
            coworkersResponse.data


        projects.value =
            projectsResponse.data.data || []
    } catch (exception) {
        error.value =
            errorMessage(
                exception
            )


        showError(
            error.value
        )
    } finally {
        loading.value =
            false
    }
}


async function createCoworker() {
    if (saving.value) {
        return
    }


    saving.value =
        true


    errors.value =
        {}


    error.value =
        ''


    try {
        await api.post(
            '/coworkers',
            {
                name:
                    form.value.name,

                email:
                    form.value.email,

                project_ids:
                    form.value.project_ids
            }
        )


        resetForm()


        await load()


        showSuccess(
            'The coworker has been added to your team.'
        )
    } catch (exception) {
        errors.value =
            validationErrors(
                exception
            )


        error.value =
            errorMessage(
                exception
            )


        showError(
            error.value
        )
    } finally {
        saving.value =
            false
    }
}


onMounted(
    load
)
</script>


<template>
    <div
        class="
            w-full
            space-y-10
            lg:space-y-14
        "
    >
        <Toast
            v-model="showToast"
            :heading="toastHeading"
            :text="toastText"
            :duration="5000"
        />


        <AdminPageHeader
            title="Coworkers"
            :breadcrumbs="[
                {
                    label: 'Team'
                },
                {
                    label: 'Coworkers'
                }
            ]"
            description="Manage staff members and the client projects they can access."
        />


        <div
            class="
                grid
                gap-10
                xl:grid-cols-[360px_minmax(0,1fr)]
                xl:items-start
            "
        >
            <!-- Add coworker -->
            <section
                class="
                    border
                    border-accent
                    bg-light
                    p-5
                    sm:p-6
                "
            >
                <div
                    class="
                        border-b
                        border-accent
                        pb-4
                    "
                >
                    <h2 class="h3">
                        Add coworker
                    </h2>

                    <p
                        class="
                            p
                            mt-2
                            uppercase
                            text-dark/50
                        "
                    >
                        Create a staff account and
                        assign project access.
                    </p>
                </div>


                <form
                    class="
                        mt-6
                        space-y-6
                    "
                    @submit.prevent="createCoworker"
                >
                    <FormField
                        id="coworker-name"
                        v-model="form.name"
                        label="Name"
                        placeholder="Full name"
                        autocomplete="name"
                        :error="
                            errors.name
                                ? errors.name[0]
                                : ''
                        "
                        required
                        autofocus
                    />


                    <FormField
                        id="coworker-email"
                        v-model="form.email"
                        type="email"
                        label="Email"
                        placeholder="name@company.com"
                        autocomplete="email"
                        :error="
                            errors.email
                                ? errors.email[0]
                                : ''
                        "
                        required
                    />


                    <div
                        class="
                            space-y-2
                        "
                    >
                        <label
                            for="coworker-projects"
                            class="h3"
                        >
                            Projects
                        </label>


                        <select
                            id="coworker-projects"
                            v-model="form.project_ids"
                            multiple
                            class="
                                block
                                min-h-36
                                w-full
                                rounded-none
                                border
                                border-dark
                                bg-light
                                px-3
                                py-0
                                text-sm
                                text-dark
                                outline-none
                                focus:border-accent
                                focus:ring-1
                                focus:ring-accent
                            "
                        >
                            <option
                                v-for="project in projects"
                                :key="project.id"
                                :value="project.id"
                            >
                                {{ project.name }}
                            </option>
                        </select>


                        <p
                            v-if="
                                errors.project_ids
                            "
                            class="
                                text-xs
                                text-red-700
                            "
                        >
                            {{
                                errors.project_ids[0]
                            }}
                        </p>


                        <p
                            v-else
                            class="
                                p
                                text-dark/50
                            "
                        >
                            Select one or more projects.
                        </p>
                    </div>


                    <Button
                        type="submit"
                        text="save coworker"
                        loading-text="saving"
                        :loading="saving"
                        :disabled="saving"
                        :lowercase="true"
                    />
                </form>
            </section>


            <!-- Team -->
            <section
                class="
                    min-w-0
                    space-y-5
                "
            >
                <div
                    class="
                        flex
                        flex-col
                        gap-5
                        sm:flex-row
                        sm:items-end
                        sm:justify-between
                    "
                >
                    <div>
                        <h2 class="h3">
                            Team
                        </h2>

                        <p
                            class="
                                p
                                mt-2
                                uppercase
                                text-dark/50
                            "
                        >
                            Staff members with access
                            to the client portal.
                        </p>
                    </div>


                    <div
                        class="
                            w-full
                            sm:w-64
                        "
                    >
                        <FormField
                            id="coworker-search"
                            v-model="search"
                            type="search"
                            label="Search"
                            placeholder="Name, email or project"
                        />
                    </div>
                </div>


                <AdminDataTable
                    :columns="columns"
                    :rows="filteredCoworkers"
                    :loading="loading"
                    empty-title="No coworkers yet."
                    empty-text="Create your first coworker using the form."
                >
                    <template #cell-name="{ row }">
                        <strong
                            class="
                                font-sans
                                text-sm
                                font-medium
                            "
                        >
                            {{ row.name }}
                        </strong>
                    </template>


                    <template #cell-email="{ row }">
                        <span
                            class="
                                text-sm
                                text-dark/60
                            "
                        >
                            {{ row.email }}
                        </span>
                    </template>


                    <template #cell-projects="{ row }">
                        <div
                            v-if="
                                row.projects &&
                                row.projects.length
                            "
                            class="
                                flex
                                flex-wrap
                                gap-2
                            "
                        >
                            <RouterLink
                                v-for="
                                    project in row.projects
                                "
                                :key="
                                    project.id
                                "
                                :to="{
                                    name: 'projects.show',
                                    params: {
                                        id: project.id
                                    }
                                }"
                                class="
                                    transition-opacity
                                    hover:opacity-60
                                "
                            >
                                <Tag
                                    :text="
                                        project.name
                                    "
                                />
                            </RouterLink>
                        </div>


                        <span
                            v-else
                            class="
                                text-sm
                                text-dark/40
                            "
                        >
                            No projects
                        </span>
                    </template>
                </AdminDataTable>
            </section>
        </div>
    </div>
</template>