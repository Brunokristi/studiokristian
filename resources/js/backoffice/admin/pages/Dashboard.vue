<script setup>
import {
    computed,
    nextTick,
    onMounted,
    ref
} from 'vue'


import {
    RouterLink,
    useRouter
} from 'vue-router'


import api, {
    errorMessage
} from '../composables/useAdminApi'

import AdminStatusBadge from '@admin/components/AdminStatusBadge.vue'

import Button from '@shared/components/Button.vue'
import Toast from '@shared/components/Toast.vue'


const router =
    useRouter()


const data =
    ref({
        counts: {},
        recent_projects: [],
        recent_clients: []
    })


const loading =
    ref(true)


const error =
    ref('')


const showErrorToast =
    ref(false)


const summary =
    computed(() => [
        {
            key: 'active_clients',
            label: 'Active clients'
        },

        {
            key: 'active_projects',
            label: 'Active projects'
        },

        {
            key: 'active_service_products',
            label: 'Active products'
        },

        {
            key: 'portal_contacts',
            label: 'Portal contacts'
        }
    ])


function showError(
    message
) {
    error.value =
        message


    showErrorToast.value =
        false


    nextTick(() => {
        showErrorToast.value =
            true
    })
}


function createServiceProduct() {
    router.push({
        name:
            'service-products.index',

        query: {
            create: 1
        }
    })
}


function createProject() {
    router.push({
        name:
            'projects.create'
    })
}


function createClient() {
    router.push({
        name:
            'clients.create'
    })
}


onMounted(
    async () => {
        try {
            const response =
                await api.get(
                    '/dashboard'
                )


            data.value =
                response.data
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
)
</script>


<template>
    <div
        class="
            w-full
            space-y-14
        "
    >
        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="error"
            :duration="5000"
        />


        <!-- Header -->
        <header
            class="
                space-y-8
                border-b
                border-accent
                pb-8
            "
        >
            <div
                class="
                    max-w-3xl
                    space-y-3
                "
            >
                <p
                    class="
                        h3
                        text-accent
                    "
                >
                    Admin dashboard
                </p>


                <h1
                    class="
                        h2
                        text-left
                    "
                >
                    Client Portal
                </h1>


                <p
                    class="
                        p
                        max-w-2xl
                        uppercase
                    "
                >
                    Companies, contacts, projects and the services you sell.
                </p>
            </div>


            <!-- Actions -->
            <div
                class="
                    grid
                    gap-6
                    sm:grid-cols-3
                    lg:max-w-3xl
                "
            >
                <Button
                    text="new service product"
                    align="left"
                    @click="
                        createServiceProduct
                    "
                />


                <Button
                    text="new project"
                    align="left"
                    @click="
                        createProject
                    "
                />


                <Button
                    text="new client"
                    variant="accent"
                    align="left"
                    @click="
                        createClient
                    "
                />
            </div>
        </header>


        <!-- Summary -->
        <section
            aria-label="Summary"
            class="
                space-y-4
            "
        >
            <h2 class="h3">
                Overview
            </h2>


            <div
                class="
                    grid
                    grid-cols-2
                    gap-px
                    border
                    border-accent
                    bg-accent
                    lg:grid-cols-4
                "
            >
                <article
                    v-for="
                        item
                        in summary
                    "
                    :key="
                        item.key
                    "
                    class="
                        flex
                        min-h-36
                        flex-col
                        justify-between
                        bg-light
                        p-5
                        sm:min-h-44
                        sm:p-6
                    "
                >
                    <p
                        class="
                            h3
                            text-dark
                        "
                    >
                        {{ item.label }}
                    </p>


                    <p
                        class="
                            font-mono
                            text-4xl
                            font-bold
                            leading-none
                            text-accent
                            sm:text-5xl
                        "
                    >
                        {{
                            loading
                                ? '—'
                                : data.counts[
                                    item.key
                                ] ?? 0
                        }}
                    </p>
                </article>
            </div>
        </section>


        <!-- Recent content -->
        <div
            class="
                grid
                gap-12
                xl:grid-cols-2
                xl:gap-px
                xl:border
                xl:border-accent
                xl:bg-accent
            "
        >
            <!-- Projects -->
            <section
                class="
                    bg-light
                    xl:p-6
                "
            >
                <header
                    class="
                        mb-4
                        flex
                        items-end
                        justify-between
                        gap-6
                    "
                >
                    <h2 class="h3">
                        Recent projects
                    </h2>


                    <RouterLink
                        :to="{
                            name:
                                'projects.index'
                        }"
                        class="
                            group
                            flex
                            shrink-0
                            items-center
                            gap-2
                            font-mono
                            text-xs
                            font-bold
                            lowercase
                            text-dark
                            transition-colors
                            hover:text-accent
                        "
                    >
                        view all

                        <i
                            class="
                                bi
                                bi-arrow-right
                                transition-transform
                                duration-200
                                group-hover:translate-x-1
                            "
                        />
                    </RouterLink>
                </header>


                <div
                    class="
                        border-y
                        border-accent
                    "
                >
                    <RouterLink
                        v-for="
                            project
                            in data.recent_projects
                        "
                        :key="
                            project.id
                        "
                        :to="{
                            name:
                                'projects.show',

                            params: {
                                id:
                                    project.id
                            }
                        }"
                        class="
                            group
                            grid
                            grid-cols-[minmax(0,1fr)_auto]
                            items-center
                            gap-5
                            border-b
                            border-accent
                            px-1
                            py-5
                            transition-colors
                            last:border-b-0
                            hover:bg-accent
                            hover:px-4
                            hover:text-light
                        "
                    >
                        <div
                            class="
                                min-w-0
                            "
                        >
                            <h3
                                class="
                                    h3
                                    truncate
                                "
                            >
                                {{ project.name }}
                            </h3>


                            <p
                                class="
                                    p
                                    mt-2
                                    truncate
                                    uppercase
                                    opacity-60
                                "
                            >
                                {{
                                    project.company
                                        ?.name ||
                                    'No client'
                                }}

                                <template
                                    v-if="
                                        project.service_product
                                            ?.name
                                    "
                                >
                                    ·
                                    {{
                                        project.service_product
                                            .name
                                    }}
                                </template>
                            </p>
                        </div>


                        <AdminStatusBadge
                            :status="
                                project.status
                            "
                        />
                    </RouterLink>


                    <div
                        v-if="
                            loading
                        "
                        class="
                            space-y-5
                            py-5
                        "
                    >
                        <div
                            v-for="
                                index
                                in 3
                            "
                            :key="
                                index
                            "
                            class="
                                animate-pulse
                            "
                        >
                            <div
                                class="
                                    h-3
                                    w-1/3
                                    bg-dark/10
                                "
                            />

                            <div
                                class="
                                    mt-3
                                    h-2
                                    w-1/2
                                    bg-dark/5
                                "
                            />
                        </div>
                    </div>


                    <p
                        v-else-if="
                            !data
                                .recent_projects
                                .length
                        "
                        class="
                            p
                            py-10
                            text-center
                            uppercase
                            text-dark/40
                        "
                    >
                        No projects yet.
                    </p>
                </div>
            </section>


            <!-- Clients -->
            <section
                class="
                    bg-light
                    xl:p-6
                "
            >
                <header
                    class="
                        mb-4
                        flex
                        items-end
                        justify-between
                        gap-6
                    "
                >
                    <h2 class="h3">
                        Recent clients
                    </h2>


                    <RouterLink
                        :to="{
                            name:
                                'clients.index'
                        }"
                        class="
                            group
                            flex
                            shrink-0
                            items-center
                            gap-2
                            font-mono
                            text-xs
                            font-bold
                            lowercase
                            text-dark
                            transition-colors
                            hover:text-accent
                        "
                    >
                        view all

                        <i
                            class="
                                bi
                                bi-arrow-right
                                transition-transform
                                duration-200
                                group-hover:translate-x-1
                            "
                        />
                    </RouterLink>
                </header>


                <div
                    class="
                        border-y
                        border-accent
                    "
                >
                    <RouterLink
                        v-for="
                            client
                            in data.recent_clients
                        "
                        :key="
                            client.id
                        "
                        :to="{
                            name:
                                'clients.show',

                            params: {
                                id:
                                    client.id
                            }
                        }"
                        class="
                            group
                            grid
                            grid-cols-[minmax(0,1fr)_auto]
                            items-center
                            gap-5
                            border-b
                            border-accent
                            px-1
                            py-5
                            transition-colors
                            last:border-b-0
                            hover:bg-accent
                            hover:px-4
                            hover:text-light
                        "
                    >
                        <div
                            class="
                                min-w-0
                            "
                        >
                            <h3
                                class="
                                    h3
                                    truncate
                                "
                            >
                                {{
                                    client.display_label
                                }}
                            </h3>


                            <p
                                class="
                                    p
                                    mt-2
                                    uppercase
                                    opacity-60
                                "
                            >
                                {{
                                    client.contacts_count
                                }}
                                {{
                                    client.contacts_count === 1
                                        ? 'contact'
                                        : 'contacts'
                                }}

                                ·

                                {{
                                    client.projects_count
                                }}
                                {{
                                    client.projects_count === 1
                                        ? 'project'
                                        : 'projects'
                                }}
                            </p>
                        </div>


                        <AdminStatusBadge
                            :status="
                                client.status
                            "
                        />
                    </RouterLink>


                    <div
                        v-if="
                            loading
                        "
                        class="
                            space-y-5
                            py-5
                        "
                    >
                        <div
                            v-for="
                                index
                                in 3
                            "
                            :key="
                                index
                            "
                            class="
                                animate-pulse
                            "
                        >
                            <div
                                class="
                                    h-3
                                    w-1/3
                                    bg-dark/10
                                "
                            />

                            <div
                                class="
                                    mt-3
                                    h-2
                                    w-1/2
                                    bg-dark/5
                                "
                            />
                        </div>
                    </div>


                    <p
                        v-else-if="
                            !data
                                .recent_clients
                                .length
                        "
                        class="
                            p
                            py-10
                            text-center
                            uppercase
                            text-dark/40
                        "
                    >
                        No clients yet.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>