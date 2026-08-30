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

import AdminConfirmDialog from '../../../../shared/components/ConfirmDialog.vue'

import FileStructure
    from '../../../components/FileStructure.vue'

import DocumentEditor
    from '../../../components/DocumentEditor.vue'

import Button from '@shared/components/Button.vue'

import FormField
    from '@shared/components/FormField.vue'

import LanguageToggle
    from '@shared/components/LanguageToggle.vue'

import Toast
    from '@shared/components/Toast.vue'

import useAutosavePolicy
    from '../../composables/useAutosavePolicy'

import {
    useAdminPageHeader
} from '../../composables/useAdminPageHeader'


const {
    setStatus,
    setLastSavedAt
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


const deleting =
    ref(false)


const showDeleteConfirm =
    ref(false)


const errors =
    ref({})


const requestError =
    ref('')


const showErrorToast =
    ref(false)


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


const documentBlocks =
    ref([])


const documentSaveRevision =
    ref(0)


const documentSavedRevision =
    ref(0)


const documentSaveError =
    ref('')


const documentSaveInFlight =
    ref(false)


const templateCurrentFolderId =
    ref(null)


const queuedDocumentPayload =
    ref(null)


const queuedTemplateSave =
    ref(false)


const templateSaveTimer =
    ref(null)


const language =
    ref('en')


const productForm =
    reactive({

        name: '',

        name_sk: '',

        description: '',

        description_sk: '',

        active: true

    })


const productName =
    computed({

        get() {

            return language.value === 'sk'
                ? productForm.name_sk
                : productForm.name

        },

        set(value) {

            if (
                language.value === 'sk'
            ) {

                productForm.name_sk =
                    value

            } else {

                productForm.name =
                    value

            }

        }

    })


const productDescription =
    computed({

        get() {

            return language.value === 'sk'
                ? productForm.description_sk
                : productForm.description

        },

        set(value) {

            if (
                language.value === 'sk'
            ) {

                productForm.description_sk =
                    value

            } else {

                productForm.description =
                    value

            }

        }

    })


/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
*/

const services =
    ref([])


const selectedServiceIds =
    ref([])


const servicesLoading =
    ref(false)


const serviceCreating =
    ref(false)


const serviceDeletingId =
    ref(null)


const serviceSearch =
    ref('')


const serviceErrors =
    ref({})


const serviceOptions =
    computed(() => {

        const query =
            String(
                serviceSearch.value || ''
            ).trim()


        const existing =
            services.value
                .filter(service => {

                    if (!query) {
                        return true
                    }

                    return String(
                        service?.name || ''
                    )
                        .toLowerCase()
                        .includes(
                            query.toLowerCase()
                        )

                })
                .map(service => ({

                    label:
                        language.value === 'sk'
                            ? (
                                service.name_sk ||
                                service.name
                            )
                            : service.name,

                    value:
                        service.id,

                    existing:
                        true

                }))


        if (!query) {

            return existing

        }


        const exactMatch =
            services.value.some(
                service =>
                    String(
                        service?.name || ''
                    )
                        .trim()
                        .toLowerCase() ===
                    query.toLowerCase()
            )


        if (exactMatch) {

            return existing

        }


        return [
            ...existing,

            {
                label:
                    `Create "${query}"`,

                value:
                    '__create__',

                create:
                    true,

                name:
                    query
            }
        ]

    })


const hasPersistedProduct =
    computed(() => {

        return Boolean(
            props.id ||
            createdProductId.value ||
            data.value?.product?.id
        )

    })


function currentProductId() {

    return (
        props.id ||
        createdProductId.value ||
        data.value?.product?.id ||
        null
    )

}


async function loadServices(
    explicitProductId = null
) {

    const productId =
        explicitProductId ||
        currentProductId()


    if (!productId) {

        services.value = []

        selectedServiceIds.value = []

        return

    }


    servicesLoading.value =
        true


    try {

        const response =
            await api.get(
                `/service-products/${productId}/services`
            )


        services.value =
            Array.isArray(
                response.data?.data
            )
                ? response.data.data
                : Array.isArray(
                    response.data
                )
                    ? response.data
                    : []


        const loadedIds =
            services.value.map(
                service =>
                    service.id
            )


        /*
         * Only initialise the selection from the server when
         * there is no local selection yet. A reload must never
         * wipe out the services the user has already selected.
         */

        if (
            selectedServiceIds.value.length === 0
        ) {

            selectedServiceIds.value = [
                ...loadedIds
            ]

        } else {

            const availableIds =
                new Set(
                    loadedIds.map(
                        id =>
                            String(id)
                    )
                )

            selectedServiceIds.value =
                selectedServiceIds.value.filter(
                    id =>
                        availableIds.has(
                            String(id)
                        )
                )

        }


    } catch (exception) {

        showError(
            errorMessage(exception)
        )

    } finally {

        servicesLoading.value =
            false

    }

}


function searchServices(
    query
) {

    serviceSearch.value =
        String(
            query || ''
        )

}


async function handleSelectedServiceIdsUpdate(
    value
) {

    /*
     * Some FormField implementations emit the typed autocomplete
     * text through update:modelValue. Never let that text replace
     * the selected service IDs.
     */
    if (!Array.isArray(value)) {
        return
    }


    const previousSelection = [
        ...selectedServiceIds.value
    ]


    const nextSelection = [
        ...value
    ]


    /*
     * First update the UI so the pill disappears immediately.
     */
    selectedServiceIds.value =
        nextSelection


    /*
     * Detect which service IDs were removed.
     *
     * The × button in FormField removes the ID from the
     * modelValue array. This is therefore the reliable place
     * to persist that deletion.
     */
    const removedIds =
        previousSelection.filter(
            previousId =>
                !nextSelection.some(
                    nextId =>
                        String(nextId) ===
                        String(previousId)
                )
        )


    if (!removedIds.length) {
        return
    }


    for (const serviceId of removedIds) {

        serviceDeletingId.value =
            serviceId


        try {

            await api.delete(
                `/services/${serviceId}`
            )


            /*
             * Remove the deleted service from the available
             * autocomplete options as well.
             */
            services.value =
                services.value.filter(
                    service =>
                        String(
                            service.id
                        ) !==
                        String(
                            serviceId
                        )
                )


        } catch (exception) {

            /*
             * If the server deletion failed, restore the pill.
             */
            if (
                !selectedServiceIds.value.some(
                    id =>
                        String(id) ===
                        String(serviceId)
                )
            ) {

                selectedServiceIds.value = [
                    ...selectedServiceIds.value,
                    serviceId
                ]

            }


            showError(
                errorMessage(
                    exception
                )
            )

        } finally {

            serviceDeletingId.value =
                null

        }

    }

}


async function handleServiceSelect(
    option
) {
    if (
        !option ||
        serviceCreating.value
    ) {
        return
    }

    /*
     * Existing service
     */
    if (
        option.existing
    ) {
        const alreadySelected =
            selectedServiceIds.value.some(
                id =>
                    String(id) ===
                    String(option.value)
            )

        if (
            !alreadySelected
        ) {
            selectedServiceIds.value = [
                ...selectedServiceIds.value,
                option.value
            ]
        }

        serviceSearch.value = ''

        return
    }

    /*
     * Create new service
     */
    if (
        option.value !== '__create__' ||
        !option.name
    ) {
        return
    }

    const productId =
        currentProductId()

    if (!productId) {
        showError(
            'Save the service product before adding services.'
        )

        return
    }

    const name =
        String(
            option.name || ''
        ).trim()

    if (!name) {
        return
    }

    /*
     * IMPORTANT:
     *
     * Take a snapshot of the currently selected
     * services BEFORE doing anything with the API.
     */
    const existingSelectedIds = [
        ...selectedServiceIds.value
    ]

    /*
     * Check whether a service with this name
     * already exists.
     */
    const existingService =
        services.value.find(
            service =>
                String(
                    service?.name || ''
                )
                    .trim()
                    .toLowerCase() ===
                name.toLowerCase()
        )

    if (
        existingService?.id
    ) {

        if (
            !existingSelectedIds.some(
                id =>
                    String(id) ===
                    String(existingService.id)
            )
        ) {

            selectedServiceIds.value = [
                ...existingSelectedIds,
                existingService.id
            ]

        }

        serviceSearch.value = ''

        return
    }

    serviceCreating.value =
        true

    serviceErrors.value =
        {}

    try {

        const response =
            await api.post(
                `/service-products/${productId}/services`,
                {
                    name,

                    name_sk: '',

                    description: '',

                    description_sk: '',

                    active: true
                }
            )

        const created =
            response.data?.data ||
            response.data?.service ||
            response.data

        if (
            created?.id
        ) {

            /*
             * Add the newly created service to
             * the local service collection.
             *
             * DO NOT replace the collection.
             */
            services.value = [
                ...services.value,
                created
            ]

            /*
             * Add the new service to the existing
             * selection.
             *
             * DO NOT replace the previous IDs.
             */
            selectedServiceIds.value = [
                ...existingSelectedIds,
                created.id
            ]

        }

        /*
         * Only clear the search field.
         *
         * This does NOT touch the selected IDs.
         */
        serviceSearch.value = ''

    } catch (exception) {

        /*
         * If creation fails, restore exactly
         * what was selected before.
         */
        selectedServiceIds.value = [
            ...existingSelectedIds
        ]

        serviceErrors.value =
            validationErrors(
                exception
            )

        showError(
            Object.values(
                serviceErrors.value
            ).flat()[0] ||
            errorMessage(exception)
        )

    } finally {

        serviceCreating.value =
            false

    }
}

function removeSelectedService(
    serviceId
) {

    selectedServiceIds.value =
        selectedServiceIds.value.filter(
            id =>
                String(id) !==
                String(serviceId)
        )

}


function changeLanguage(
    value
) {

    language.value =
        value === 'sk'
            ? 'sk'
            : 'en'

}


/*
|--------------------------------------------------------------------------
| General product state
|--------------------------------------------------------------------------
*/

const isCreateMode =
    computed(() =>
        !props.id &&
        !createdProductId.value
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
                label:
                    'Service Products',

                to: {
                    name:
                        'service-products.index'
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
    computed(() =>
        true
    )


const folders =
    computed(() =>
        data.value?.template?.folders ||
        []
    )


function isPersistedFolderId(
    value
) {

    if (
        value === null ||
        value === undefined
    ) {

        return false

    }


    const numeric =
        Number(value)


    return Number.isInteger(
        numeric
    ) && numeric > 0

}


function normalizeTemplateFolders(
    serverFolders,
    previousFolders = []
) {

    const source =
        Array.isArray(
            serverFolders
        )
            ? serverFolders
            : []


    const flattened = []


    const walk = (
        items,
        parentId = null,
        parentClientKey = null
    ) => {

        items.forEach(item => {

            const normalized = {

                ...item,

                parent_id:
                    parentId !== null
                        ? parentId
                        : item.parent_id ?? null,

                parent_client_key:
                    parentClientKey ||
                    item.parent_client_key ||
                    null,

                client_key:
                    String(
                        item.client_key ||
                        item.id
                    ),

                client_visible:
                    item.client_visible ??
                    true,

                children:
                    undefined

            }


            delete normalized.children


            flattened.push(
                normalized
            )


            if (
                Array.isArray(
                    item.children
                ) &&
                item.children.length
            ) {

                walk(
                    item.children,
                    item.id,
                    normalized.client_key
                )

            }

        })

    }


    walk(source)


    if (
        !flattened.length &&
        previousFolders.length
    ) {

        return previousFolders.map(
            item => ({

                ...item,

                client_key:
                    String(
                        item.client_key ||
                        item.id
                    ),

                client_visible:
                    item.client_visible ??
                    true

            })
        )

    }


    return flattened.sort(
        (a, b) =>
            Number(
                a?.sort_order || 0
            ) -
            Number(
                b?.sort_order || 0
            )
    )

}


function refreshOpenDocumentIdFromClientKey() {

    if (
        !documentTemplate.value?.client_key ||
        !data.value?.template?.folders
    ) {

        return

    }


    const match =
        data.value.template.folders.find(
            item =>
                String(
                    item.client_key
                ) ===
                String(
                    documentTemplate.value.client_key
                )
        )


    if (!match?.id) {

        return

    }


    documentTemplate.value = {

        ...documentTemplate.value,

        id:
            match.id,

        name:
            match.name ||
            documentTemplate.value.name,

        title:
            match.name ||
            documentTemplate.value.title

    }

}


async function resolveDocumentFolderId(
    payload
) {

    if (
        !payload?.client_key
    ) {

        return payload

    }


    const findByClientKey = () =>
        data.value?.template?.folders?.find(
            item =>
                String(
                    item.client_key
                ) ===
                String(
                    payload.client_key
                )
        ) || null


    const direct =
        findByClientKey()


    if (
        direct?.id &&
        isPersistedFolderId(
            direct.id
        )
    ) {

        return {

            ...payload,

            id:
                direct.id,

            title:
                String(
                    payload.title ||
                    direct.name ||
                    'Untitled document'
                )

        }

    }


    await saveTemplate()


    const remapped =
        findByClientKey()


    if (
        remapped?.id &&
        isPersistedFolderId(
            remapped.id
        )
    ) {

        return {

            ...payload,

            id:
                remapped.id,

            title:
                String(
                    payload.title ||
                    remapped.name ||
                    'Untitled document'
                )

        }

    }


    return payload

}


function blankProduct() {

    return {

        name: '',

        name_sk: '',

        description: '',

        description_sk: '',

        active: true

    }

}


function blankTemplate() {

    return {

        id: null,

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

            name_sk:
                product?.name_sk ||
                '',

            description:
                product?.description ||
                '',

            description_sk:
                product?.description_sk ||
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
            productForm.name,

        name_sk:
            productForm.name_sk,

        description:
            productForm.description,

        description_sk:
            productForm.description_sk,

        active:
            productForm.active

    })

}


async function load(
    explicitId = null
) {

    const productId =
        explicitId ||
        props.id ||
        createdProductId.value


    loading.value =
        true


    suppressAutosave.value =
        true


    try {

        if (!productId) {

            data.value = {

                product:
                    blankProduct(),

                template:
                    blankTemplate(),

                readiness: {

                    ready:
                        false,

                    missing: [
                        'service_product'
                    ]

                }

            }


            prepareProduct(
                data.value.product
            )


            services.value =
                []

            selectedServiceIds.value =
                []


            return

        }


        const [
            templateResponse,
            productResponse
        ] =
            await Promise.all([

                api.get(
                    `/service-products/${productId}/template`
                ),

                api.get(
                    `/service-products/${productId}`
                )

            ])


        const productFromApi =
            productResponse.data?.data ||
            productResponse.data?.product ||
            productResponse.data


        const templateResponseData =
            templateResponse.data ||
            {}


        const serverFolders =
            Array.isArray(
                templateResponseData.folders
            )
                ? templateResponseData.folders
                : []


        data.value = {

            product:
                productFromApi ||
                {
                    id:
                        productId,

                    ...blankProduct()
                },

            template: {

                id:
                    productId,

                folders:
                    normalizeTemplateFolders(
                        serverFolders,
                        serverFolders
                    )

            }

        }


        prepareProduct(
            data.value.product
        )


        await loadServices(
            data.value.product?.id
        )


        lastSavedSnapshot.value =
            getAutosaveSnapshot()


    } catch (exception) {

        showError(
            errorMessage(exception)
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


    const productId =
        props.id ||
        createdProductId.value ||
        data.value?.product?.id


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
                productForm.name,

            name_sk:
                productForm.name_sk,

            description:
                productForm.description,

            description_sk:
                productForm.description_sk,

            active:
                Boolean(
                    productForm.active
                )

        }


        if (
            isCreateMode.value
        ) {

            const response =
                await api.post(
                    '/service-products',
                    payload
                )


            const createdId =
                response.data?.data?.id


            if (!createdId) {

                return

            }


            createdProductId.value =
                createdId


            data.value = {

                product: {

                    ...(data.value?.product || {}),

                    id:
                        createdId,

                    ...payload

                },

                template:
                    blankTemplate()

            }


            await load(
                createdId
            )


            await loadServices(
                createdId
            )


            if (
                String(
                    props.id || ''
                ) !==
                String(
                    createdId
                )
            ) {

                await router.replace({

                    name:
                        'service-products.show',

                    params: {

                        id:
                            String(
                                createdId
                            )

                    }

                }).catch(
                    () => {}
                )

            }


            return

        }


        if (!productId) {

            return

        }


        const response =
            await api.put(
                `/service-products/${productId}`,
                payload
            )


        data.value.product = {

            ...(data.value.product || {}),

            ...(response.data?.data || {})

        }


        lastSavedSnapshot.value =
            getAutosaveSnapshot()


    } catch (exception) {

        errors.value =
            validationErrors(
                exception
            )


        const fieldError =
            Object.values(
                errors.value
            ).flat()[0]


        showError(
            fieldError ||
            errorMessage(exception)
        )


    } finally {

        saving.value =
            false


        setStatus(
            'idle'
        )


        setLastSavedAt(
            new Date()
        )


        suppressAutosave.value =
            false

    }

}


function createBlueprint(
    explicitId = null
) {

    const productId =
        explicitId ||
        props.id ||
        createdProductId.value


    if (!productId) {

        return

    }


    void load(
        productId
    )

}


/*
|--------------------------------------------------------------------------
| Document editor
|--------------------------------------------------------------------------
*/

function openDocumentTemplate(
    file
) {

    if (!file?.id) {

        return

    }


    const envelope =
        readDocumentEnvelope(
            file.content || ''
        )


    documentTemplate.value = {

        id:
            file.id,

        client_key:
            file.client_key ||
            String(
                file.id
            ),

        name:
            file.name ||
            envelope.title ||
            'Untitled document',

        title:
            file.name ||
            envelope.title ||
            'Untitled document',

        subtitle:
            file.subtitle ||
            envelope.subtitle ||
            '',

        content:
            file.content ||
            ''

    }


    documentBlocks.value =
        envelope.doc


    documentSaveRevision.value =
        Number(
            file.document_revision ||
            0
        )


    documentSavedRevision.value =
        documentSaveRevision.value


    documentSaveError.value =
        ''


    documentEditorOpen.value =
        true


    window.scrollTo({

        top:
            0,

        behavior:
            'smooth'

    })

}


function readDocumentEnvelope(
    content
) {

    try {

        const parsed =
            JSON.parse(
                String(
                    content || ''
                )
            )


        if (
            parsed &&
            typeof parsed ===
                'object' &&
            !Array.isArray(
                parsed
            )
        ) {

            return {

                title:
                    String(
                        parsed.title ||
                        ''
                    ),

                subtitle:
                    String(
                        parsed.subtitle ||
                        ''
                    ),

                doc:
                    parsed.doc ||
                    parsed

            }

        }

    } catch {

        // Legacy content is handled by the editor's own loader.

    }


    return {

        title:
            '',

        subtitle:
            '',

        doc:
            content ||
            ''

    }

}


function updateDocumentBlocks(
    value
) {

    documentBlocks.value =
        value

}


async function saveDocumentTemplate(
    template
) {

    const payload =
        template ||
        documentTemplate.value


    if (
        !payload?.id ||
        !data.value?.template
    ) {

        return

    }


    const resolved =
        await resolveDocumentFolderId({

            id:
                payload.id,

            client_key:
                payload.client_key ||
                documentTemplate.value?.client_key ||
                null,

            revision:
                Number(
                    payload.revision ||
                    0
                ),

            title:
                payload.title ||
                payload.name ||
                documentTemplate.value?.title ||
                'Untitled document',

            subtitle:
                payload.subtitle ||
                '',

            document_schema:
                payload.document_schema ||
                documentBlocks.value ||
                {}

        })


    if (
        !isPersistedFolderId(
            resolved?.id
        )
    ) {

        documentSaveError.value =
            'Document file could not be persisted yet. Please wait a moment and try again.'


        showError(
            documentSaveError.value
        )


        return

    }


    const nextRevision =
        Math.max(

            documentSaveRevision.value +
                1,

            Number(
                resolved.revision ||
                0
            )

        )


    documentSaveRevision.value =
        nextRevision


    queuedDocumentPayload.value = {

        id:
            resolved.id,

        client_key:
            resolved.client_key,

        revision:
            nextRevision,

        title:
            String(
                resolved.title ||
                'Untitled document'
            ).trim() ||
            'Untitled document',

        subtitle:
            String(
                resolved.subtitle ||
                ''
            ),

        document_schema:
            resolved.document_schema ||
            {}

    }


    void flushDocumentSaveQueue()

}


async function flushDocumentSaveQueue() {

    if (
        documentSaveInFlight.value ||
        !queuedDocumentPayload.value
    ) {

        return

    }


    documentSaveInFlight.value =
        true


    documentSaveError.value =
        ''


    while (
        queuedDocumentPayload.value
    ) {

        const payload =
            queuedDocumentPayload.value


        queuedDocumentPayload.value =
            null


        try {

            await persistDocumentTemplate(
                payload
            )

        } catch (exception) {

            if (
                exception?.response?.status ===
                    404 &&
                payload?.client_key
            ) {

                const remapped =
                    await resolveDocumentFolderId(
                        payload
                    )


                if (
                    isPersistedFolderId(
                        remapped?.id
                    ) &&
                    String(
                        remapped.id
                    ) !==
                    String(
                        payload.id
                    )
                ) {

                    queuedDocumentPayload.value = {

                        ...payload,

                        id:
                            remapped.id

                    }


                    continue

                }

            }


            const stale =
                exception?.response?.status ===
                    409
                    ? exception?.response?.data ||
                      {}
                    : null


            const expectedRevision =
                Number(
                    stale?.expected_revision
                )


            if (
                stale &&
                Number.isFinite(
                    expectedRevision
                ) &&
                expectedRevision >= 1
            ) {

                const savedRevision =
                    Number(
                        stale.saved_revision ||
                        0
                    )


                documentSavedRevision.value =
                    Math.max(
                        documentSavedRevision.value,
                        savedRevision
                    )


                documentSaveRevision.value =
                    savedRevision


                queuedDocumentPayload.value = {

                    ...payload,

                    revision:
                        expectedRevision

                }


                continue

            }


            queuedDocumentPayload.value =
                payload


            documentSaveError.value =
                errorMessage(
                    exception
                )


            showError(
                documentSaveError.value
            )


            break

        }

    }


    documentSaveInFlight.value =
        false

}


async function persistDocumentTemplate(
    payload
) {

    const response =
        await api.put(

            `/service-products/${data.value.product.id}/template-folders/${payload.id}/document`,

            {

                title:
                    payload.title,

                subtitle:
                    payload.subtitle,

                document_schema:
                    payload.document_schema,

                revision:
                    payload.revision

            }

        )


    const saved =
        response.data ||
        {}


    const savedRevision =
        Number(
            saved.saved_revision ||
            payload.revision
        )


    const savedContent =
        String(
            saved.content ||
            ''
        )


    documentSaveRevision.value =
        Math.max(
            documentSaveRevision.value,
            payload.revision
        )


    documentSavedRevision.value =
        Math.max(
            documentSavedRevision.value,
            savedRevision
        )


    documentSaveError.value =
        ''


    documentTemplate.value = {

        ...documentTemplate.value,

        id:
            payload.id,

        client_key:
            payload.client_key ||
            documentTemplate.value?.client_key ||
            String(
                payload.id
            ),

        name:
            String(
                saved.title ||
                payload.title ||
                'Untitled document'
            ),

        title:
            String(
                saved.title ||
                payload.title ||
                'Untitled document'
            ),

        subtitle:
            String(
                saved.subtitle ||
                payload.subtitle ||
                ''
            ),

        content:
            savedContent,

        document_revision:
            savedRevision

    }


    documentBlocks.value =
        saved.document_schema ||
        payload.document_schema


    if (
        data.value?.template?.folders
    ) {

        data.value.template.folders =
            data.value.template.folders.map(
                item =>
                    String(
                        item.id
                    ) ===
                    String(
                        payload.id
                    )
                        ? {

                            ...item,

                            name:
                                String(
                                    saved.title ||
                                    payload.title ||
                                    item.name
                                ),

                            template_name:
                                String(
                                    saved.title ||
                                    payload.title ||
                                    item.template_name ||
                                    item.name
                                ),

                            content:
                                savedContent,

                            document_revision:
                                savedRevision

                        }
                        : item
            )

    }


    lastSavedSnapshot.value =
        getAutosaveSnapshot()

}


async function handleDocumentBack(
    meta = null
) {

    const requiredRevision =
        Number(
            meta?.waitForRevision ||
            0
        )


    if (
        requiredRevision > 0
    ) {

        await flushDocumentSaveQueue()


        if (
            documentSavedRevision.value <
                requiredRevision ||
            documentSaveError.value
        ) {

            return

        }

    }


    documentEditorOpen.value =
        false

}


function updateDocumentTitle(
    value
) {

    if (
        !documentTemplate.value
    ) {

        return

    }


    const title =
        String(
            value || ''
        ).trim()


    documentTemplate.value = {

        ...documentTemplate.value,

        name:
            title,

        title

    }

}


function updateDocumentSubtitle(
    value
) {

    if (
        !documentTemplate.value
    ) {

        return

    }


    documentTemplate.value = {

        ...documentTemplate.value,

        subtitle:
            String(
                value || ''
            )

    }

}


/*
|--------------------------------------------------------------------------
| Template / file structure
|--------------------------------------------------------------------------
*/

function updateFolders(
    value
) {

    if (
        !data.value?.template
    ) {

        return

    }


    data.value.template.folders =
        value


    if (
        data.value?.template?.id
    ) {

        scheduleTemplateSave()

    }

}


function normalizeOpenUrl(
    value
) {

    const raw =
        String(
            value ||
            ''
        ).trim()


    if (!raw) {

        return ''

    }


    if (
        raw.startsWith('/') ||
        raw.startsWith('#')
    ) {

        return raw

    }


    if (
        /^[a-z][a-z\d+.-]*:/i.test(
            raw
        )
    ) {

        return raw

    }


    return `https://${raw}`

}


function normalizeExternalUrlForSave(
    value
) {

    const raw =
        String(
            value ||
            ''
        ).trim()


    if (!raw) {

        return ''

    }


    if (
        /^[a-z][a-z\d+.-]*:/i.test(
            raw
        )
    ) {

        return raw

    }


    return `https://${raw}`

}


function handleTemplateFileOpen(
    file
) {

    const openUrl =
        normalizeOpenUrl(
            file?.open_url ||
            file?.url ||
            ''
        )


    if (openUrl) {

        window.open(
            openUrl,
            '_blank',
            'noopener,noreferrer'
        )


        return

    }


    showError(
        'This template file entry has no storage-backed file. Upload/open/download must be executed in a real Project Files workspace with persisted file records.'
    )

}


function handleTemplateFileDownload(
    file
) {

    const downloadUrl =
        String(
            file?.download_url ||
            ''
        )


    if (downloadUrl) {

        const link =
            document.createElement(
                'a'
            )


        link.href =
            downloadUrl


        link.download =
            String(
                file?.name ||
                'download'
            )


        document.body.appendChild(
            link
        )


        link.click()


        document.body.removeChild(
            link
        )


        return

    }


    showError(
        'This template file entry has no storage-backed binary to download. Use a Project Files workspace to upload and retrieve real files.'
    )

}


async function handleTemplateFileUpload(
    payload = {}
) {

    const productId =
        data.value?.product?.id ||
        props.id ||
        createdProductId.value


    if (!productId) {

        showError(
            'Save the service product before uploading files.'
        )


        return

    }


    const files =
        Array.from(
            payload?.files ||
            []
        )


    if (!files.length) {

        return

    }


    const parent =
        payload?.parent ||
        null


    let folderId =
        null


    let parentClientKey =
        null


    if (
        isPersistedFolderId(
            payload?.folderId
        )
    ) {

        folderId =
            Number(
                payload.folderId
            )

    }


    if (
        !folderId &&
        isPersistedFolderId(
            parent?.id
        )
    ) {

        folderId =
            Number(
                parent.id
            )

    }


    if (
        !folderId &&
        parent?.client_key
    ) {

        parentClientKey =
            String(
                parent.client_key
            )


        await saveTemplate()


        const persistedParent =
            (
                data.value?.template?.folders ||
                []
            ).find(
                item =>
                    String(
                        item.client_key
                    ) ===
                    parentClientKey
            )


        if (
            isPersistedFolderId(
                persistedParent?.id
            )
        ) {

            folderId =
                Number(
                    persistedParent.id
                )

        }

    }


    const maxFilesPerRequest =
        20


    try {

        for (
            let offset = 0;
            offset < files.length;
            offset += maxFilesPerRequest
        ) {

            const chunk =
                files.slice(
                    offset,
                    offset +
                        maxFilesPerRequest
                )


            const body =
                new FormData()


            chunk.forEach(
                (
                    file,
                    index
                ) => {

                    const sourceIndex =
                        offset +
                        index


                    const relativePath =
                        String(

                            payload
                                ?.relativePaths?.[
                                    sourceIndex
                                ] ||

                            file.webkitRelativePath ||

                            file.name

                        )


                    body.append(
                        'files[]',
                        file
                    )


                    body.append(
                        `relative_paths[${index}]`,
                        relativePath
                    )

                }
            )


            if (folderId) {

                body.append(
                    'folder_id',
                    String(
                        folderId
                    )
                )

            } else if (
                parentClientKey
            ) {

                body.append(
                    'parent_client_key',
                    parentClientKey
                )

            }


            body.append(
                'client_visible',
                '1'
            )


            await api.post(
                `/service-products/${productId}/template/files`,
                body
            )

        }


        await load(
            productId
        )


    } catch (exception) {

        showError(
            errorMessage(
                exception
            )
        )

    }

}


function handleTemplateFolderOpen(
    folder
) {

    templateCurrentFolderId.value =
        folder?.id ||
        null

}


function scheduleTemplateSave(
    delay = 250
) {

    if (
        !data.value?.template
    ) {

        return

    }


    if (
        templateSaveTimer.value
    ) {

        clearTimeout(
            templateSaveTimer.value
        )

    }


    templateSaveTimer.value =
        setTimeout(
            () => {

                templateSaveTimer.value =
                    null

                void saveTemplate()

            },
            delay
        )

}


function templateFoldersPayloadForSave() {

    const items =
        (
            data.value?.template?.folders ||
            []
        )
            .map(
                item => ({

                    ...item,

                    id:
                        isPersistedFolderId(
                            item.id
                        )
                            ? Number(
                                item.id
                            )
                            : null,

                    url:
                        item.resource_type ===
                            'link'
                            ? normalizeExternalUrlForSave(
                                item.url
                            )
                            : null,

                    client_key:
                        String(
                            item.client_key ||
                            item.id
                        ),

                    parent_client_key:
                        item.parent_client_key ??
                        null,

                    client_visible:
                        item.client_visible ??
                        true

                })
            )


    const keyById =
        new Map(
            items.map(
                item => [
                    String(
                        item.id
                    ),
                    String(
                        item.client_key
                    )
                ]
            )
        )


    return items.map(
        item => ({

            ...item,

            parent_client_key:
                item.parent_id !== null &&
                item.parent_id !== undefined

                    ? (

                        keyById.get(
                            String(
                                item.parent_id
                            )
                        ) ||

                        String(
                            item.parent_client_key ||
                            item.parent_id
                        )

                    )

                    : null

        })
    )

}


async function saveTemplate() {

    if (
        !data.value?.template
    ) {

        return

    }


    if (
        saving.value
    ) {

        queuedTemplateSave.value =
            true

        return

    }


    const productId =
        data.value?.product?.id ||
        props.id ||
        createdProductId.value


    if (!productId) {

        return

    }


    saving.value =
        true


    setStatus(
        'saving'
    )


    try {

        const currentFolders =
            (
                data.value.template.folders ||
                []
            )
                .map(
                    item => ({
                        ...item
                    })
                )


        const response =
            await api.put(
                `/service-products/${productId}/template`,
                {

                    folders:
                        templateFoldersPayloadForSave()

                }
            )


        const serverFolders =
            response.data?.folders ||
            []


        data.value.template = {

            ...data.value.template,

            id:
                productId,

            folders:
                normalizeTemplateFolders(
                    serverFolders,
                    currentFolders
                )

        }


        refreshOpenDocumentIdFromClientKey()


        lastSavedSnapshot.value =
            getAutosaveSnapshot()


    } catch (exception) {

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


        setLastSavedAt(
            new Date()
        )


        if (
            queuedTemplateSave.value
        ) {

            queuedTemplateSave.value =
                false


            scheduleTemplateSave(
                100
            )

        }

    }

}


/*
|--------------------------------------------------------------------------
| Autosave
|--------------------------------------------------------------------------
*/

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
        documentEditorOpen.value ||
        inputHasFocus.value
    ) {

        return

    }


    if (
        isCreateMode.value &&
        !String(
            productForm.name ||
            ''
        ).trim()
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
        setTimeout(
            () => {

                if (
                    !saving.value
                ) {

                    saveProduct()

                }

            },
            400
        )

}


function cancel() {

    router.push({

        name:
            'service-products.index'

    })

}


function deleteProduct() {

    if (
        isCreateMode.value ||
        !props.id ||
        deleting.value
    ) {

        return

    }


    showDeleteConfirm.value =
        true

}


async function confirmDeleteProduct() {

    if (
        isCreateMode.value ||
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
            `/service-products/${props.id}`
        )


        showDeleteConfirm.value =
            false


        router.push({

            name:
                'service-products.index'

        })


    } catch (exception) {

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


watch(

    () => [

        productForm.name,

        productForm.name_sk,

        productForm.description,

        productForm.description_sk,

        productForm.active

    ],

    () => {

        scheduleAutosave()

    },

    {
        deep:
            true
    }

)


onMounted(() => {

    load()

})


useAdminPageHeader({

    title:
        pageTitle,

    description:
        computed(() =>
            isCreateMode.value
                ? 'Create a reusable service for your client projects.'
                : 'Define what this service includes and how its projects are structured.'
        ),

    breadcrumbs

})

</script>


<template>

    <div
        v-if="data"
    >

        <Teleport
            v-if="documentEditorOpen"
            to="body"
        >

            <DocumentEditor
                :model-value="
                    documentBlocks
                "

                :template="
                    documentTemplate
                "

                :title="
                    documentTemplate?.name || ''
                "

                :subtitle="
                    documentTemplate?.subtitle || ''
                "

                :editable="
                    editable
                "

                :saving="
                    documentSaveInFlight
                "

                :save-revision="
                    documentSaveRevision
                "

                :saved-revision="
                    documentSavedRevision
                "

                :save-error="
                    documentSaveError
                "

                @update:title="
                    updateDocumentTitle
                "

                @update:subtitle="
                    updateDocumentSubtitle
                "

                @update:model-value="
                    updateDocumentBlocks
                "

                @back="
                    handleDocumentBack
                "

                @save="
                    saveDocumentTemplate
                "

            />

        </Teleport>


        <div
            v-else
            class="
                w-full
                space-y-16
                lg:space-y-20
            "
        >

            <LanguageToggle
                :model-value="language"
                class="
                    fixed
                    top-11
                    right-1
                    z-50
                "
                @update:model-value="
                    changeLanguage
                "
            />


            <Toast
                v-model="
                    showErrorToast
                "

                heading="Something went wrong"

                :text="
                    requestError
                "

                :duration="
                    5000
                "
            />


            <Teleport
                to="#admin-page-header-actions"
            >

                <Button
                    v-if="
                        !isCreateMode &&
                        data.template &&
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
                        createBlueprint
                    "
                />

            </Teleport>


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
                                productName
                            "

                            name="name"

                            type="text"

                            :label="
                                language === 'sk'
                                    ? 'Názov'
                                    : 'Name'
                            "

                            :placeholder="
                                language === 'sk'
                                    ? 'Názov služby'
                                    : 'Website development'
                            "

                            :disabled="
                                saving
                            "

                            :error="
                                errors.name?.[0] ||
                                errors.slug?.[0] ||
                                ''
                            "

                            @focus="
                                handleFieldFocus
                            "

                            @blur="
                                handleFieldBlur
                            "
                        />


                        <FormField
                            id="service-description"

                            v-model="
                                productDescription
                            "

                            name="description"

                            type="textarea"

                            :label="
                                language === 'sk'
                                    ? 'Popis'
                                    : 'Description'
                            "

                            :placeholder="
                                language === 'sk'
                                    ? 'Popíšte, čo táto služba zahŕňa.'
                                    : 'Describe what this service includes.'
                            "

                            :disabled="
                                saving
                            "

                            :error="
                                errors.description?.[0] ||
                                ''
                            "

                            @focus="
                                handleFieldFocus
                            "

                            @blur="
                                handleFieldBlur
                            "
                        />

                    </section>


                    <section
                        class="
                            flex
                            flex-col
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
                                    label:
                                        'Active',

                                    value:
                                        true
                                },

                                {
                                    label:
                                        'Inactive',

                                    value:
                                        false
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

                    </section>

                </form>

            </section>


            <!-- Services -->

            <section
                class="
                    space-y-8
                "
            >

                <div
                    class="
                        flex
                        flex-col
                        gap-2
                    "
                >

                    <h2
                        class="
                            h2
                            text-accent
                            text-left
                        "
                    >
                        Services
                    </h2>

                    <p
                        class="
                            p
                            text-dark/50
                        "
                    >
                        Add the individual services that belong to this service product.
                    </p>

                </div>


                <div
                    v-if="!hasPersistedProduct"
                    class="
                        border-t
                        border-accent
                        pt-5
                    "
                >

                    <p
                        class="
                            p
                            text-dark/45
                        "
                    >
                        Save the service product first to add services.
                    </p>

                </div>


                <div
                    v-else
                    class="
                        space-y-6
                    "
                >

                    <FormField
                        id="service-product-service-search"

                        :model-value="
                            selectedServiceIds
                        "

                        name="service"

                        type="autocomplete"

                        multiple

                        label="Services"

                        placeholder="Start typing a service"

                        :options="
                            serviceOptions
                        "

                        :loading="
                            servicesLoading ||
                            serviceCreating
                        "

                        :disabled="
                            !editable ||
                            serviceCreating
                        "

                        @search="
                            searchServices
                        "

                        @update:model-value="
                            handleSelectedServiceIdsUpdate
                        "

                        @select="
                            handleServiceSelect
                        "
                    />


                    <div
                        v-if="servicesLoading"
                        class="
                            border-t
                            border-accent
                            pt-5
                        "
                    >

                        <p
                            class="
                                p
                                uppercase
                                text-dark/40
                            "
                        >
                            Loading services...
                        </p>

                    </div>


                    <p
                        v-else-if="
                            !services.length
                        "
                        class="
                            p
                            text-dark/45
                        "
                    >
                        No services have been added yet.
                    </p>

                </div>

            </section>


            <!-- File structure -->

            <section
                v-if="
                    !isCreateMode &&
                    data.template
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


                <FileStructure
                    :model-value="
                        folders
                    "

                    :initial-folder-id="
                        templateCurrentFolderId
                    "

                    :allow-upload-control="
                        editable
                    "

                    :disabled="
                        !editable
                    "

                    :template-delete-url="
                        data?.product?.id
                            ? `/service-products/${data.product.id}/template-folders`
                            : null
                    "

                    @update:model-value="
                        updateFolders
                    "

                    @open-document="
                        openDocumentTemplate
                    "

                    @open-folder="
                        handleTemplateFolderOpen
                    "

                    @open-file="
                        handleTemplateFileOpen
                    "

                    @download-file="
                        handleTemplateFileDownload
                    "

                    @upload-files="
                        handleTemplateFileUpload
                    "
                />

            </section>


            <!-- Danger zone -->

            <section
                v-if="!isCreateMode"
                class="
                    space-y-4
                "
            >

                <h3
                    class="
                        h2
                        text-accent
                        text-left
                    "
                >
                    Danger zone
                </h3>


                <Button
                    type="button"

                    :text="'Delete service'"

                    :loading-text="'Deleting...'"

                    :loading="
                        deleting
                    "

                    :disabled="
                        deleting
                    "

                    :lowercase="
                        true
                    "

                    @click.prevent="
                        deleteProduct
                    "

                    align="left"
                />

            </section>


            <AdminConfirmDialog
                :open="
                    showDeleteConfirm
                "

                title="Delete service?"

                :text="
                    `This will permanently delete ${pageTitle}. This action cannot be undone.`
                "

                confirm-label="Delete service"

                :busy="
                    deleting
                "

                @close="
                    closeDeleteConfirm
                "

                @confirm="
                    confirmDeleteProduct
                "
            />

        </div>

    </div>


    <div
        v-else-if="loading"
        class="
            p
            uppercase
            text-dark/40
        "
    >
        Loading service...
    </div>

</template>