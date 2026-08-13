<script setup>
import {
    computed,
    onMounted,
    reactive,
    ref,
    watch
} from 'vue'


import {
    useRouter
} from 'vue-router'


import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'


import AdminPageHeader from '../../components/AdminPageHeader.vue'


import ServiceFileStructure
    from '../../components/ServiceFileStructure.vue'

import DocumentEditor
    from '../../components/DocumentEditor.vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Tag from '@shared/components/Tag.vue'
import useAutosavePolicy from '../../composables/useAutosavePolicy'


const {
    enabled: autosaveEnabled
} =
    useAutosavePolicy()


const props =
    defineProps({
        id: {
            type: String,
            default: ''
        },

        create: {
            type: Boolean,
            default: false
        }
    })


const router =
    useRouter()


const data =
    ref(null)


const createdProductId =
    ref(null)


const loading =
    ref(true)


const saving =
    ref(false)


const errors =
    ref({})


const requestError =
    ref('')


const autosaveTimer =
    ref(null)


const suppressAutosave =
    ref(false)


const inputHasFocus =
    ref(false)


const lastSavedSnapshot =
    ref('')


const documentEditorOpen =
    ref(false)


const documentTemplate =
    ref(null)


const documentTemplateLoading =
    ref(false)


const productForm =
    reactive({
        name: '',
        description: '',
        active: true
    })


const isCreateMode =
    computed(() =>
        Boolean(
            props.create
        ) ||
        (!props.id && !createdProductId.value)
    )


const pageTitle =
    computed(() =>
        productForm.name ||
        data.value?.product?.name ||
        'New service'
    )


const breadcrumbs =
    computed(() => {
        const items = [
            {
                label: 'Service Products',
                to: {
                    name: 'service-products.index'
                }
            }
        ]


        items.push({
            label:
                productForm.name ||
                data.value?.product?.name ||
                'New service'
        })


        return items
    })


const editable =
    computed(() => {
        if (isCreateMode.value) {
            return true
        }


        return (
            data.value?.version?.status ===
            'draft'
        )
    })


const folders =
    computed(() =>
        data.value?.version?.folders ||
        []
    )


function blankProduct() {
    return {
        name: '',
        description: '',
        active: true
    }
}


function blankBlueprint() {
    return {
        id: null,
        version: '1.0',
        status: 'draft',
        folders: []
    }
}


function prepareProduct(
    product
) {
    Object.assign(
        productForm,
        {
            name:
                product?.name ||
                '',

            description:
                product?.description ||
                '',

            active:
                product?.active ??
                true
        }
    )
}


function showError(
    message
) {
    requestError.value =
        message
}


function getAutosaveSnapshot() {
    return JSON.stringify({
        name:
            productForm.name,

        description:
            productForm.description,

        active:
            productForm.active,

        folders:
            data.value?.version?.folders || []
    })
}


async function load() {
    loading.value =
        true

    suppressAutosave.value =
        true

    try {
        if (
            isCreateMode.value
        ) {
            data.value = {
                product:
                    blankProduct(),

                version:
                    blankBlueprint(),

                readiness: {
                    ready: false,
                    missing: [
                        'service_product'
                    ]
                }
            }


            prepareProduct(
                data.value.product
            )


            return
        }


        const response =
            await api.get(
                `/service-products/${props.id}/blueprint`
            )


        data.value =
            response.data


        prepareProduct(
            data.value.product
        )


        if (
            data.value.version
        ) {
            data.value.version.folders =
                (data.value.version.folders || [])
                    .map(
                        item => ({
                            ...item,
                            client_key:
                                item.client_key ||
                                String(
                                    item.id
                                ),
                            parent_client_key:
                                item.parent_client_key ??
                                (item.parent_id !== null &&
                                item.parent_id !== undefined
                                    ? String(
                                        item.parent_id
                                    )
                                    : null),
                            client_visible:
                                item.client_visible ??
                                true
                        })
                    )
        }


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


async function saveProduct() {
    if (
        saving.value
    ) {
        return
    }


    suppressAutosave.value =
        true

    saving.value =
        true


    errors.value =
        {}


    requestError.value =
        ''


    try {
        const payload = {
            name:
                productForm.name,

            description:
                productForm.description,

            active:
                Boolean(
                    productForm.active
                )
        }


        let productResponse


        if (
            isCreateMode.value
        ) {
            productResponse =
                await api.post(
                    '/service-products',
                    payload
                )


            const createdId =
                productResponse.data?.id


            if (
                createdId
            ) {
                createdProductId.value =
                    createdId

                data.value =
                    data.value || {
                        product: {},
                        version: blankBlueprint()
                    }

                data.value.product = {
                    ...(data.value.product || {}),
                    id: createdId,
                    name:
                        productForm.name,
                    description:
                        productForm.description,
                    active:
                        Boolean(
                            productForm.active
                        )
                }

                lastSavedSnapshot.value =
                    getAutosaveSnapshot()

                return
            }


            return
        }


        productResponse =
            await api.put(
                `/service-products/${props.id || createdProductId.value}`,
                payload
            )


        if (
            data.value
        ) {
            data.value.product = {
                ...(data.value.product || {}),
                ...productResponse.data
            }
        }


        if (
            !productForm.name &&
            productResponse.data?.name
        ) {
            productForm.name =
                productResponse.data.name
        }


        if (
            !productForm.description &&
            productResponse.data?.description
        ) {
            productForm.description =
                productResponse.data.description
        }


        if (
            data.value?.version?.id
        ) {
            const blueprintResponse =
                await api.put(
                    `/blueprint-versions/${data.value.version.id}`,
                    {
                        fields:
                            data.value.version.fields ||
                            [],

                        folders:
                            (data.value.version.folders || []).map(
                                item => ({
                                    ...item,
                                    client_key:
                                        item.client_key ||
                                        String(
                                            item.id
                                        ),
                                    parent_client_key:
                                        item.parent_client_key ??
                                        (item.parent_id !== null &&
                                        item.parent_id !== undefined
                                            ? String(
                                                item.parent_id
                                            )
                                            : null),
                                    client_visible:
                                        item.client_visible ??
                                        true
                                })
                            )
                    }
                )


            if (
                blueprintResponse?.data
            ) {
                data.value.version =
                    {
                        ...data.value.version,
                        ...blueprintResponse.data
                    }
            }
        }


        lastSavedSnapshot.value =
            getAutosaveSnapshot()
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

        suppressAutosave.value =
            false
    }
}


async function createBlueprint() {
    if (
        !props.id ||
        saving.value
    ) {
        return
    }


    saving.value =
        true


    try {
        await api.post(
            `/service-products/${props.id}/blueprint`,
            {
                name:
                    `${productForm.name} Blueprint`,

                version:
                    '1.0'
            }
        )


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


async function createDraft() {
    if (
        !props.id ||
        saving.value
    ) {
        return
    }


    saving.value =
        true


    try {
        const currentVersion =
            data.value?.version?.version ||
            '1.0'


        const parts =
            String(
                currentVersion
            )
                .split('.')
                .map(
                    Number
                )


        const nextVersion =
            `${parts[0] || 1}.${(parts[1] || 0) + 1}`


        await api.post(
            `/service-products/${props.id}/blueprint/drafts`,
            {
                version:
                    nextVersion
            }
        )


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


function openDocumentTemplate(
    file
) {
    if (
        !file?.id
    ) {
        return
    }


    documentTemplate.value =
        {
            id: file.id,
            name:
                file.name ||
                'Untitled document',
            content:
                file.content ||
                ''
        }


    documentEditorOpen.value =
        true
}


function saveDocumentTemplate(
    template
) {
    if (
        !data.value?.version
    ) {
        return
    }


    data.value.version.folders =
        data.value.version.folders.map(
            item =>
                String(item.id) ===
                String(template.id)
                    ? {
                        ...item,
                        name:
                            template.name ||
                            item.name,
                        content:
                            template.content ||
                            '',
                        template_name:
                            template.name ||
                            item.template_name ||
                            item.name
                    }
                    : item
        )


    documentTemplate.value =
        null

    documentEditorOpen.value =
        false

    scheduleAutosave()
}


function updateFolders(
    value
) {
    if (
        !data.value?.version
    ) {
        return
    }


    data.value.version.folders =
        value

    scheduleAutosave()
}


async function saveBlueprint() {
    if (
        !data.value?.version ||
        saving.value
    ) {
        return
    }


    saving.value =
        true


    try {
        await api.put(
            `/blueprint-versions/${data.value.version.id}`,
            {
                fields:
                    data.value.version.fields ||
                    [],

                folders:
                    (data.value.version.folders || []).map(
                        item => ({
                            ...item,
                            client_key:
                                item.client_key ||
                                String(
                                    item.id
                                ),
                            parent_client_key:
                                item.parent_client_key ??
                                (item.parent_id !== null &&
                                item.parent_id !== undefined
                                    ? String(
                                        item.parent_id
                                    )
                                    : null),
                            client_visible:
                                item.client_visible ??
                                true
                        })
                    )
            }
        )


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


async function publishBlueprint() {
    if (
        !data.value?.version ||
        saving.value
    ) {
        return
    }


    saving.value =
        true


    try {
        await api.post(
            `/blueprint-versions/${data.value.version.id}/publish`,
            {
                change_summary:
                    'Updated service configuration'
            }
        )


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


function handleFieldFocus() {
    inputHasFocus.value =
        true
}


function handleFieldBlur() {
    inputHasFocus.value =
        false

    scheduleAutosave()
}


function scheduleAutosave() {
    if (
        suppressAutosave.value ||
        inputHasFocus.value ||
        !autosaveEnabled.value
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
                !saving.value
            ) {
                saveProduct()
            }
        }, 400)
}


function cancel() {
    router.push({
        name:
            'service-products.index'
    })
}


function formatStatus(
    value
) {
    return String(
        value || ''
    )
        .replaceAll(
            '_',
            ' '
        )
}

watch(
    () => [
        productForm.name,
        productForm.description,
        productForm.active
    ],
    () => {
        scheduleAutosave()
    },
    {
        deep: true
    }
)


watch(
    () => data.value?.version?.folders,
    () => {
        scheduleAutosave()
    },
    {
        deep: true
    }
)


onMounted(() => {
    load()
})
</script>


<template>
    <div
        v-if="data"
        class="
            w-full
            space-y-16
            lg:space-y-20
        "
    >
        <!-- Header -->
        <AdminPageHeader
            :title="
                pageTitle
            "
            :description="
                isCreateMode
                    ? 'Create a reusable service for your client projects.'
                    : 'Define what this service includes and how its projects are structured.'
            "
            :breadcrumbs="
                breadcrumbs
            "
        >
            <Button
                v-if="
                    !isCreateMode &&
                    !data.version
                "
                type="button"
                text="create structure"
                variant="accent"
                align="left"
                :loading="
                    saving
                "
                :disabled="
                    saving
                "
                @click="
                    createBlueprint
                "
            />


            <Button
                v-if="
                    !isCreateMode &&
                    data.version &&
                    !editable
                "
                type="button"
                text="create draft"
                variant="accent"
                align="left"
                :loading="
                    saving
                "
                :disabled="
                    saving
                "
                @click="
                    createDraft
                "
            />
        </AdminPageHeader>


        <!-- Basic information -->
        <section
            class="
                space-y-6
            "
        >
            <h2
                class="
                    h2
                    text-accent
                    text-left
                "
            >
                Basic information
            </h2>


            <form
                class="
                    grid
                    grid-cols-1
                    gap-8
                    md:grid-cols-2
                    md:gap-x-20
                    md:gap-y-10
                "
                @submit.prevent="
                    saveProduct
                "
            >
                <section
                    class="
                        space-y-8
                    "
                >
                    <FormField
                        id="service-name"
                        v-model="
                            productForm.name
                        "
                        name="name"
                        type="text"
                        label="Name"
                        placeholder="Website development"
                        :disabled="
                            saving
                        "
                        :error="
                            errors.name?.[0] ||
                            ''
                        "
                        @focus="handleFieldFocus"
                        @blur="handleFieldBlur"
                    />


                    <FormField
                        id="service-description"
                        v-model="
                            productForm.description
                        "
                        name="description"
                        type="textarea"
                        label="Description"
                        placeholder="Describe what this service includes."
                        :disabled="
                            saving
                        "
                        :error="
                            errors.description?.[0] ||
                            ''
                        "
                        @focus="handleFieldFocus"
                        @blur="handleFieldBlur"
                    />
                </section>


                <section
                        class="
                            flex
                            flex-col
                            space-y-8

                        "
                    >
                        <div
                            class="
                                space-y-8
                            "
                        >
                            <FormField
                                id="service-status"
                                v-model="
                                    productForm.active
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
                                :disabled="
                                    saving
                                "
                                :error="
                                    errors.active?.[0] ||
                                    ''
                                "
                            />
                        </div>

                        <div
                            class="
                                flex
                                flex-col
                                gap-4
                            "
                        />
                </section>
            </form>
        </section>


        <!-- File structure -->
        <section
            v-if="
                !isCreateMode &&
                data.version
            "
            class="
                space-y-8
            "
        >
            <div>
                <h2
                    class="
                        h2
                        text-accent
                        text-left
                    "
                >
                    Project structure
                </h2>
            </div>


            <ServiceFileStructure
                :model-value="
                    folders
                "
                :disabled="
                    !editable
                "
                @update:model-value="
                    updateFolders
                "
                @open-document="
                    openDocumentTemplate
                "
            />
        </section>


        <!-- Document editor -->
        <DocumentEditor
            v-if="
                documentEditorOpen
            "
            :template="
                documentTemplate
            "
            :loading="
                documentTemplateLoading
            "
            :saving="
                saving
            "
            @close="
                documentEditorOpen = false
            "
            @save="
                saveDocumentTemplate
            "
        />
    </div>


    <div
        v-else-if="
            loading
        "
        class="
            p
            uppercase
            text-dark/40
        "
    >
        Loading service...
    </div>
</template>