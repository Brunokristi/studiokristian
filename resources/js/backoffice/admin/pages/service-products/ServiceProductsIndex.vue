<script setup>
import {
    computed,
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


import {
    useServerTable
} from '../../composables/useServerTable'


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminDataTable from '../../components/AdminDataTable.vue'
import AdminPagination from '../../components/AdminPagination.vue'
import AdminConfirmDialog from '../../components/AdminConfirmDialog.vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'


const route =
    useRoute()


const router =
    useRouter()


const columns = [
    {
        key: 'name',
        label: 'Product',
        sortable: true
    },


    {
        key: 'slug',
        label: 'Slug',
        sortable: true
    },


    {
        key: 'projects_count',
        label: 'Projects'
    },


    {
        key: 'active',
        label: 'Status',
        sortable: true
    },


    {
        key: 'updated_at',
        label: 'Updated',
        sortable: true
    }
]


const {
    rows,
    meta,
    loading,
    error,
    state,
    sortBy,
    load
} =
    useServerTable(
        '/service-products',
        {
            active: '',
            sort: 'sort_order',
            direction: 'asc'
        }
    )


const panel =
    ref(false)


const editing =
    ref(null)


const saving =
    ref(false)


const errors =
    ref({})


const requestError =
    ref('')


const deactivateTarget =
    ref(null)


const showErrorToast =
    ref(false)


const showSuccessToast =
    ref(false)


const blank =
    () => ({
        name: '',
        slug: '',
        description: '',
        active: true
    })


const form =
    reactive(
        blank()
    )


const statusOptions =
    computed(() => [
        {
            label: 'All statuses',
            value: ''
        },


        {
            label: 'Active',
            value: '1'
        },


        {
            label: 'Inactive',
            value: '0'
        }
    ])


const filterValues =
    computed({
        get() {
            return {
                active:
                    state.active ??
                    ''
            }
        },


        set(values) {
            state.active =
                values?.active ??
                ''


            state.page =
                1
        }
    })


function open(
    item = null
) {
    if (
        item?.id
    ) {
        router.push({
            name:
                'service-products.show',

            params: {
                id:
                    item.id
            }
        })

        return
    }


    editing.value =
        null


    Object.assign(
        form,
        blank()
    )


    errors.value =
        {}


    requestError.value =
        ''


    panel.value =
        true
}


function close() {
    panel.value =
        false


    editing.value =
        null


    Object.assign(
        form,
        blank()
    )


    errors.value =
        {}


    requestError.value =
        ''
}


function showError(
    message
) {
    requestError.value =
        message


    showErrorToast.value =
        false


    showErrorToast.value =
        true
}


async function save() {
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
        if (
            editing.value
        ) {
            await api.put(
                `/service-products/${editing.value}`,
                {
                    ...form,
                    active:
                        Boolean(
                            form.active
                        )
                }
            )
        } else {
            await api.post(
                '/service-products',
                {
                    ...form,
                    active:
                        Boolean(
                            form.active
                        )
                }
            )
        }


        close()


        showSuccessToast.value =
            false


        showSuccessToast.value =
            true


        await load()
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


async function deactivate() {
    if (
        !deactivateTarget.value ||
        saving.value
    ) {
        return
    }


    saving.value =
        true


    try {
        await api.post(
            `/service-products/${deactivateTarget.value.id}/deactivate`
        )


        deactivateTarget.value =
            null


        await load()
    } catch (
        exception
    ) {
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


function createProduct() {
    router.push({ name: 'service-products.create' })
}


function formatDate(
    value
) {
    if (
        !value
    ) {
        return '—'
    }


    const date =
        new Date(
            value
        )


    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return '—'
    }


    return date.toLocaleDateString(
        'en-GB',
        {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }
    )
}


onMounted(() => {
    if (
        route.query.create
    ) {
        open()
    }
})


watch(
    () =>
        route.query.create,

    value => {
        if (
            value
        ) {
            open()
        }
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
        <!-- Toasts -->
        <Toast
            v-model="
                showErrorToast
            "
            heading="Something went wrong"
            :text="
                requestError ||
                error ||
                ''
            "
            :duration="5000"
        />


        <Toast
            v-model="
                showSuccessToast
            "
            heading="Service product saved"
            text="The service product has been saved."
            :duration="3000"
        />


        <!-- Page header -->
        <AdminPageHeader
            title="Services"
            description="Reusable services assigned to client projects."
            :breadcrumbs="[
                {
                    label: 'Services'
                }
            ]"
        />


        <!-- Service products table -->
        <AdminDataTable
            v-model:search="
                state.search
            "
            :filter-values="
                filterValues
            "
            @update:filterValues="
                values => filterValues = values
            "
            title="Provided services"
            search-placeholder="Search service products"
            :columns="
                columns
            "
            :rows="
                rows
            "
            :loading="
                loading
            "
            :filters="[
                {
                    key: 'active',
                    label: 'Status',
                    type: 'select',
                    options: statusOptions
                }
            ]"
            :sort="
                state.sort
            "
            :direction="
                state.direction
            "
            empty-title="No service products yet."
            empty-text="Add a reusable service before creating projects."
            add-label=" "
            @sort="
                sortBy
            "
            @row-click="
                open
            "
            @add="
                createProduct
            "
        >
            <!-- Product -->
            <template
                #cell-name="{
                    row
                }"
            >
                <div
                    class="
                        min-w-0
                    "
                >
                    <p
                        class="
                            p
                            font-medium
                            uppercase
                        "
                    >
                        {{
                            row.name
                        }}
                    </p>


                    <p
                        v-if="
                            row.description
                        "
                        class="
                            p
                            mt-1
                            max-w-md
                            truncate
                            uppercase
                            text-dark/40
                        "
                    >
                        {{
                            row.description
                        }}
                    </p>
                </div>
            </template>


            <!-- Slug -->
            <template
                #cell-slug="{
                    value
                }"
            >
                <span
                    class="
                        p
                        uppercase
                        text-dark/60
                    "
                >
                    {{
                        value ||
                        '—'
                    }}
                </span>
            </template>


            <!-- Projects -->
            <template
                #cell-projects_count="{
                    value
                }"
            >
                <span class="p">
                    {{
                        value ??
                        0
                    }}
                </span>
            </template>


            <!-- Status -->
            <template
                #cell-active="{
                    value
                }"
            >
                <Tag
                    :text="
                        value
                            ? 'active'
                            : 'inactive'
                    "
                />
            </template>


            <!-- Order -->
            <template
                #cell-sort_order="{
                    value
                }"
            >
                <span class="p">
                    {{
                        value ??
                        0
                    }}
                </span>
            </template>


            <!-- Updated -->
            <template
                #cell-updated_at="{
                    value
                }"
            >
                <span class="p">
                    {{
                        formatDate(
                            value
                        )
                    }}
                </span>
            </template>


            <!-- Empty action -->
            <template
                #empty-action
            >
                <Button
                    text="create service product"
                    variant="accent"
                    align="center"
                    @click="
                        createProduct
                    "
                />
            </template>
        </AdminDataTable>


        <!-- Pagination -->
        <AdminPagination
            :meta="
                meta
            "
            @change="
                page =>
                    state.page =
                        page
            "
        />


        <!-- Create / Edit panel -->
        <div
            v-if="
                panel
            "
            class="
                fixed
                inset-0
                z-40
                overflow-y-auto
                bg-dark/55
                p-4
                sm:p-6
            "
            @click.self="
                close
            "
        >
            <form
                class="
                    ml-auto
                    min-h-full
                    w-full
                    max-w-xl
                    bg-light
                    p-6
                    sm:p-8
                "
                @submit.prevent="
                    save
                "
            >
                <!-- Panel header -->
                <div
                    class="
                        border-b
                        border-accent
                        pb-6
                    "
                >
                    <div
                        class="
                            flex
                            items-start
                            justify-between
                            gap-6
                        "
                    >
                        <div>
                            <p
                                class="
                                    h3
                                    text-accent
                                "
                            >
                                Service product
                            </p>


                            <h2
                                class="
                                    h2
                                    mt-2
                                "
                            >
                                {{
                                    editing
                                        ? 'Edit product'
                                        : 'New product'
                                }}
                            </h2>
                        </div>


                        <button
                            type="button"
                            class="
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-dark
                                transition-colors
                                hover:text-accent
                            "
                            @click="
                                close
                            "
                        >
                            Close
                        </button>
                    </div>
                </div>


                <!-- Error -->
                <p
                    v-if="
                        requestError
                    "
                    class="
                        p
                        mt-6
                        text-red-700
                    "
                >
                    {{
                        requestError
                    }}
                </p>


                <!-- Form -->
                <div
                    class="
                        mt-8
                        space-y-8
                    "
                >
                    <FormField
                        id="product-name"
                        v-model="
                            form.name
                        "
                        name="name"
                        type="text"
                        label="Name"
                        placeholder="Website development"
                        :error="
                            errors.name?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="product-slug"
                        v-model="
                            form.slug
                        "
                        name="slug"
                        type="text"
                        label="Slug"
                        placeholder="Generated from name when blank"
                        :error="
                            errors.slug?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="product-description"
                        v-model="
                            form.description
                        "
                        name="description"
                        type="textarea"
                        label="Description"
                        placeholder="Describe this service."
                        :error="
                            errors.description?.[0] ||
                            ''
                        "
                    />


                    <FormField
                        id="product-active"
                        v-model="
                            form.active
                        "
                        name="active"
                        type="select"
                        label="Status"
                        :options="[
                            {
                                label: 'Active',
                                value: true
                            },
                            {
                                label: 'Inactive',
                                value: false
                            }
                        ]"
                        :error="
                            errors.active?.[0] ||
                            ''
                        "
                    />
                </div>


                <!-- Actions -->
                <div
                    class="
                        mt-10
                        flex
                        flex-col
                        gap-6
                        border-t
                        border-accent
                        pt-8
                        sm:flex-row
                        sm:items-end
                        sm:justify-between
                    "
                >
                    <div>
                        <Button
                            v-if="
                                editing &&
                                form.active
                            "
                            type="button"
                            text="deactivate"
                            :disabled="
                                saving
                            "
                            align="left"
                            @click="
                                deactivateTarget = {
                                    id:
                                        editing,
                                    name:
                                        form.name
                                }
                            "
                        />
                    </div>


                    <div
                        class="
                            flex
                            flex-col
                            gap-4
                            sm:flex-row
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
                                close
                            "
                        />


                        <Button
                            type="submit"
                            :text="
                                editing
                                    ? 'save changes'
                                    : 'create service product'
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
                            hover-variant="accent-dark"
                        />
                    </div>
                </div>
            </form>
        </div>


        <!-- Deactivate confirmation -->
        <AdminConfirmDialog
            :open="
                Boolean(
                    deactivateTarget
                )
            "
            title="Deactivate product?"
            :text="
                `${deactivateTarget?.name || 'This product'} will remain on existing projects but cannot be selected for new ones.`
            "
            confirm-label="Deactivate"
            :busy="
                saving
            "
            @close="
                deactivateTarget = null
            "
            @confirm="
                deactivate
            "
        />
    </div>
</template>