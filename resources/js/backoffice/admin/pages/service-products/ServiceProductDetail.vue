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
import FormField from '@shared/components/FormField.vue'
import LanguageToggle from '@shared/components/LanguageToggle.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'
import useAutosavePolicy from '../../composables/useAutosavePolicy'
import { useAdminPageHeader } from '../../composables/useAdminPageHeader'


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


const blueprintBackfillAttempted =
    ref(false)


const documentEditorOpen =
    ref(false)


const documentTemplate =
    ref(null)


const documentTemplateLoading =
    ref(false)


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


const blueprintCurrentFolderId =
    ref(null)


const queuedDocumentPayload =
    ref(null)


const queuedBlueprintSave =
    ref(false)


const blueprintSaveTimer =
    ref(null)


const language = ref('en')

const productForm =
    reactive({
        name: '',
        name_sk: '',
        description: '',
        description_sk: '',
        active: true
    })


const productName = computed({
    get() {
        return language.value === 'sk'
            ? productForm.name_sk
            : productForm.name
    },
    set(value) {
        if (language.value === 'sk') {
            productForm.name_sk = value
        } else {
            productForm.name = value
        }
    }
})

const productDescription = computed({
    get() {
        return language.value === 'sk'
            ? productForm.description_sk
            : productForm.description
    },
    set(value) {
        if (language.value === 'sk') {
            productForm.description_sk = value
        } else {
            productForm.description = value
        }
    }
})

/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
*/

const services = ref([])
const servicesLoading = ref(false)
const serviceCreating = ref(false)
const serviceDeletingId = ref(null)
const serviceSearch = ref('')
const serviceErrors = ref({})
const serviceSavingId = ref(null)

const serviceOptions = computed(() => {
    const query = String(
        serviceSearch.value || ''
    ).trim()

    const existing = services.value
        .filter(service => {
            if (!query) {
                return true
            }

            return String(
                service?.name || ''
            )
                .toLowerCase()
                .includes(query.toLowerCase())
        })
        .map(service => ({
            label:
                language.value === 'sk'
                    ? (service.name_sk || service.name)
                    : service.name,
            value: service.id,
            existing: true
        }))

    if (!query) {
        return existing
    }

    const exactMatch = services.value.some(
        service =>
            String(
                service?.name || ''
            ).trim().toLowerCase() ===
            query.toLowerCase()
    )

    if (exactMatch) {
        return existing
    }

    return [
        ...existing,
        {
            label: `Create "${query}"`,
            value: '__create__',
            create: true,
            name: query
        }
    ]
})

const hasPersistedProduct = computed(() => {
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
        return
    }

    servicesLoading.value = true

    try {
        const response = await api.get(
            `/service-products/${productId}/services`
        )

        services.value =
            Array.isArray(response.data?.data)
                ? response.data.data
                : Array.isArray(response.data)
                    ? response.data
                    : []
    } catch (exception) {
        showError(
            errorMessage(exception)
        )
    } finally {
        servicesLoading.value = false
    }
}

function searchServices(query) {
    serviceSearch.value = String(
        query || ''
    )
}

async function handleServiceSelect(option) {
    if (
        !option ||
        serviceCreating.value
    ) {
        return
    }

    if (option.existing) {
        serviceSearch.value = ''
        return
    }

    if (
        option.value !== '__create__' ||
        !option.name
    ) {
        return
    }

    const productId = currentProductId()

    if (!productId) {
        showError(
            'Save the service product before adding services.'
        )
        return
    }

    const name = String(
        option.name || ''
    ).trim()

    if (!name) {
        return
    }

    const duplicate = services.value.some(
        service =>
            String(
                service?.name || ''
            ).trim().toLowerCase() ===
            name.toLowerCase()
    )

    if (duplicate) {
        serviceSearch.value = ''
        return
    }

    serviceCreating.value = true
    serviceErrors.value = {}

    try {
        const response = await api.post(
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

        if (created?.id) {
            services.value = [
                ...services.value,
                created
            ].sort(
                (a, b) =>
                    Number(a?.sort_order || 0) -
                    Number(b?.sort_order || 0)
            )
        } else {
            await loadServices(productId)
        }

        serviceSearch.value = ''
    } catch (exception) {
        serviceErrors.value =
            validationErrors(exception)

        showError(
            Object.values(
                serviceErrors.value
            ).flat()[0] ||
            errorMessage(exception)
        )
    } finally {
        serviceCreating.value = false
    }
}

function serviceField(service, field) {
    if (!service) {
        return ''
    }

    if (language.value === 'sk') {
        return String(service[`${field}_sk`] || '')
    }

    return String(service[field] || '')
}

function setServiceField(service, field, value) {
    if (!service || !editable.value) {
        return
    }

    const key =
        language.value === 'sk'
            ? `${field}_sk`
            : field

    service[key] = String(value || '')
}

async function saveService(service) {
    if (
        !service?.id ||
        !editable.value ||
        serviceSavingId.value
    ) {
        return
    }

    serviceSavingId.value = service.id

    try {
        const response = await api.put(
            `/services/${service.id}`,
            {
                name: service.name || '',
                name_sk: service.name_sk || '',
                description: service.description || '',
                description_sk: service.description_sk || '',
                active: service.active ?? true
            }
        )

        const saved =
            response.data?.data ||
            response.data?.service ||
            response.data

        services.value = services.value.map(item =>
            String(item.id) === String(service.id)
                ? { ...item, ...saved }
                : item
        )
    } catch (exception) {
        serviceErrors.value = validationErrors(exception)

        showError(
            Object.values(serviceErrors.value).flat()[0] ||
            errorMessage(exception)
        )
    } finally {
        serviceSavingId.value = null
    }
}

function serviceDisplayName(service) {
    if (!service) {
        return ''
    }

    return language.value === 'sk'
        ? (
            service.name_sk ||
            service.name ||
            ''
        )
        : (
            service.name ||
            ''
        )
}


function changeLanguage(value) {
    language.value = value === 'sk' ? 'sk' : 'en'
}

async function removeService(service) {
    if (
        !service?.id ||
        serviceDeletingId.value ||
        !editable.value
    ) {
        return
    }

    serviceDeletingId.value = service.id

    try {
        await api.delete(
            `/services/${service.id}`
        )

        services.value =
            services.value.filter(
                item =>
                    String(item.id) !==
                    String(service.id)
            )
    } catch (exception) {
        showError(
            errorMessage(exception)
        )
    } finally {
        serviceDeletingId.value = null
    }
}


const isCreateMode =
    computed(() =>
        !props.id && !createdProductId.value
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


function normalizeVersionFolders(
    serverFolders,
    previousFolders = []
) {
    const source =
        Array.isArray(serverFolders)
            ? [...serverFolders]
            : []


    const previous =
        Array.isArray(previousFolders)
            ? [...previousFolders]
            : []


    source.sort(
        (a, b) =>
            Number(
                a?.sort_order || 0
            ) -
            Number(
                b?.sort_order || 0
            )
    )


    previous.sort(
        (a, b) =>
            Number(
                a?.sort_order || 0
            ) -
            Number(
                b?.sort_order || 0
            )
    )


    const normalized =
        source.map(
            (item, index) => {
                const previousItem =
                    previous[index] ||
                    null

                const clientKey =
                    previousItem?.client_key ||
                    String(item.id)

                return {
                    ...item,
                    client_key:
                        clientKey,
                    parent_client_key:
                        null,
                    client_visible:
                        item.client_visible ??
                        true
                }
            }
        )


    const idToClientKey =
        new Map(
            normalized.map(
                item => [
                    String(item.id),
                    item.client_key
                ]
            )
        )


    return normalized.map(
        item => ({
            ...item,
            parent_client_key:
                item.parent_id !== null &&
                item.parent_id !== undefined
                    ? (
                        idToClientKey.get(
                            String(
                                item.parent_id
                            )
                        ) ||
                        String(
                            item.parent_id
                        )
                    )
                    : null
        })
    )
}


function refreshOpenDocumentIdFromClientKey() {
    if (
        !documentTemplate.value?.client_key ||
        !data.value?.version?.folders
    ) {
        return
    }

    const match =
        data.value.version.folders.find(
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
        id: match.id,
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

    const findByClientKey =
        () =>
            data.value?.version?.folders?.find(
                item =>
                    String(
                        item.client_key
                    ) ===
                    String(
                        payload.client_key
                    )
            ) ||
            null


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
            id: direct.id,
            title:
                String(
                    payload.title ||
                    direct.name ||
                    'Untitled document'
                )
        }
    }


    if (
        !data.value?.version?.id
    ) {
        return payload
    }


    await saveBlueprint()


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
            id: remapped.id,
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
            productForm.active,

        folders:
            data.value?.version?.folders || []
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
        if (
            !productId
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

            services.value = []

            return
        }


        const [blueprintResponse, productResponse] =
            await Promise.all([
                api.get(
                    `/service-products/${productId}/blueprint`
                ),
                api.get(
                    `/service-products/${productId}`
                )
            ])


        data.value =
            blueprintResponse.data


        const productFromApi =
            productResponse.data?.data ||
            productResponse.data?.product ||
            productResponse.data


        if (productFromApi?.id) {
            data.value.product = {
                ...(data.value.product || {}),
                ...productFromApi
            }
        }


        prepareProduct(
            data.value.product
        )


        await loadServices(
            data.value.product?.id
        )


        if (
            data.value.version
        ) {
            data.value.version.folders =
                normalizeVersionFolders(
                    data.value.version.folders || [],
                    data.value.version.folders || []
                )
        } else if (
            data.value.product?.id &&
            !blueprintBackfillAttempted.value
        ) {
            // Existing product created before structure was persisted — backfill it now.
            blueprintBackfillAttempted.value =
                true

            void createBlueprint(
                data.value.product.id
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

    setStatus('saving')

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
                productResponse.data?.data?.id


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

                lastSavedSnapshot.value =
                    getAutosaveSnapshot()

                // Auto-generate the project structure before navigating so it always exists.
                await createBlueprint(
                    createdId
                )

                await loadServices(
                    createdId
                )

                if (
                    String(
                        props.id ||
                        ''
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
                ...(productResponse.data?.data || {})
            }
        }


        if (
            !productForm.name &&
            productResponse.data?.data?.name
        ) {
            productForm.name =
                productResponse.data.data.name
        }


        if (
            !productForm.description &&
            productResponse.data?.data?.description
        ) {
            productForm.description =
                productResponse.data.data.description
        }


        if (
            data.value?.version?.id
        ) {
            const currentFolders =
                (data.value.version.folders || [])
                    .map(
                        item => ({
                            ...item
                        })
                    )

            const blueprintResponse =
                await api.put(
                    `/blueprint-versions/${data.value.version.id}`,
                    {
                        fields:
                            data.value.version.fields ||
                            [],

                        folders:
                            foldersPayloadForSave()
                    }
                )


            if (
                blueprintResponse?.data
            ) {
                data.value.version =
                    {
                        ...data.value.version,
                        ...blueprintResponse.data,
                        folders:
                            normalizeVersionFolders(
                                blueprintResponse.data.folders || [],
                                currentFolders
                            )
                    }

                refreshOpenDocumentIdFromClientKey()
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


        const fieldError =
            Object.values(
                errors.value
            ).flat()[0]

        showError(
            fieldError ||
            errorMessage(
                exception
            )
        )
    } finally {
        saving.value =
            false

        setStatus('idle')
        setLastSavedAt(new Date())

        suppressAutosave.value =
            false
    }
}


async function createBlueprint(
    explicitId = null
) {
    const productId =
        explicitId ||
        props.id ||
        createdProductId.value

    if (
        !productId
    ) {
        return
    }


    saving.value =
        true


    try {
        await api.post(
            `/service-products/${productId}/blueprint`,
            {
                name:
                    `${productForm.name} Blueprint`,

                version:
                    '1.0'
            }
        )


        await load(
            productId
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


    const envelope =
        readDocumentEnvelope(
            file.content || ''
        )


    documentTemplate.value =
        {
            id: file.id,
            client_key:
                file.client_key ||
                String(file.id),
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
            file.document_revision || 0
        )


    documentSavedRevision.value =
        documentSaveRevision.value


    documentSaveError.value =
        ''


    documentEditorOpen.value =
        true

    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    })
}


function readDocumentEnvelope(
    content
) {
    try {
        const parsed = JSON.parse(
            String(content || '')
        )

        if (
            parsed &&
            typeof parsed === 'object' &&
            !Array.isArray(parsed)
        ) {
            return {
                title:
                    String(
                        parsed.title || ''
                    ),
                subtitle:
                    String(
                        parsed.subtitle || ''
                    ),
                doc:
                    parsed.doc ||
                    parsed
            }
        }
    } catch {
        // legacy content is handled by the editor's own loader
    }

    return {
        title: '',
        subtitle: '',
        doc: content || ''
    }
}


function updateDocumentBlocks(
    value
) {
    documentBlocks.value = value
}


async function saveDocumentTemplate(
    template
) {
    const payload =
        template ||
        documentTemplate.value

    if (
        !payload?.id ||
        !data.value?.version
    ) {
        return
    }

    const resolved =
        await resolveDocumentFolderId(
            {
                id: payload.id,
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
            }
        )

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
            documentSaveRevision.value + 1,
            Number(
                resolved.revision ||
                0
            )
        )

    // Reserve revision immediately so concurrent autosave events don't enqueue duplicates.
    documentSaveRevision.value =
        nextRevision

    queuedDocumentPayload.value = {
        id: resolved.id,
        client_key:
            resolved.client_key,
        revision:
            nextRevision,
        title: String(
            resolved.title ||
            'Untitled document'
        ).trim() || 'Untitled document',
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
    if (documentSaveInFlight.value || !queuedDocumentPayload.value) {
        return
    }

    documentSaveInFlight.value = true
    documentSaveError.value = ''

    while (queuedDocumentPayload.value) {
        const payload = queuedDocumentPayload.value
        queuedDocumentPayload.value = null

        try {
            await persistDocumentTemplate(payload)
        } catch (exception) {
            if (
                exception?.response?.status === 404 &&
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
                    String(remapped.id) !== String(payload.id)
                ) {
                    queuedDocumentPayload.value = {
                        ...payload,
                        id: remapped.id
                    }

                    continue
                }
            }

            const stale = exception?.response?.status === 409
                ? exception?.response?.data || {}
                : null
            const expectedRevision = Number(stale?.expected_revision)

            if (stale && Number.isFinite(expectedRevision) && expectedRevision >= 1) {
                const savedRevision = Number(stale.saved_revision || 0)
                documentSavedRevision.value = Math.max(documentSavedRevision.value, savedRevision)

                // Reset to server-authoritative revision and retry with expected next revision.
                documentSaveRevision.value = savedRevision

                queuedDocumentPayload.value = {
                    ...payload,
                    revision: expectedRevision,
                }

                continue
            }

            queuedDocumentPayload.value = payload
            documentSaveError.value = errorMessage(exception)
            showError(documentSaveError.value)
            break
        }
    }

    documentSaveInFlight.value = false
}


async function persistDocumentTemplate(payload) {
    const response = await api.put(
        `/blueprint-folders/${payload.id}/document`,
        {
            title: payload.title,
            subtitle: payload.subtitle,
            document_schema: payload.document_schema,
            revision: payload.revision,
        }
    )

    const saved = response.data || {}
    const savedRevision = Number(saved.saved_revision || payload.revision)
    const savedContent = String(saved.content || '')

    documentSaveRevision.value = Math.max(documentSaveRevision.value, payload.revision)
    documentSavedRevision.value = Math.max(documentSavedRevision.value, savedRevision)
    documentSaveError.value = ''

    documentTemplate.value = {
        ...documentTemplate.value,
        id: payload.id,
        client_key:
            payload.client_key ||
            documentTemplate.value?.client_key ||
            String(payload.id),
        name: String(saved.title || payload.title || 'Untitled document'),
        title: String(saved.title || payload.title || 'Untitled document'),
        subtitle: String(saved.subtitle || payload.subtitle || ''),
        content: savedContent,
        document_revision: savedRevision,
    }

    documentBlocks.value = saved.document_schema || payload.document_schema

    if (data.value?.version?.folders) {
        data.value.version.folders = data.value.version.folders.map(item =>
            String(item.id) === String(payload.id)
                ? {
                    ...item,
                    name: String(saved.title || payload.title || item.name),
                    template_name: String(saved.title || payload.title || item.template_name || item.name),
                    content: savedContent,
                    document_revision: savedRevision,
                }
                : item
        )
    }

    // Keep generic autosave baseline in sync with dedicated document writes.
    lastSavedSnapshot.value =
        getAutosaveSnapshot()
}


async function handleDocumentBack(meta = null) {
    const requiredRevision = Number(meta?.waitForRevision || 0)

    if (requiredRevision > 0) {
        await flushDocumentSaveQueue()

        if (documentSavedRevision.value < requiredRevision || documentSaveError.value) {
            return
        }
    }

    documentEditorOpen.value = false
}


function updateDocumentTitle(
    value
) {
    if (!documentTemplate.value) {
        return
    }

    const title =
        String(value || '').trim()

    documentTemplate.value = {
        ...documentTemplate.value,
        name: title,
        title
    }
}


function updateDocumentSubtitle(
    value
) {
    if (!documentTemplate.value) {
        return
    }

    documentTemplate.value = {
        ...documentTemplate.value,
        subtitle: String(
            value || ''
        )
    }
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

    if (
        data.value?.version?.id
    ) {
        scheduleBlueprintSave()
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


function handleBlueprintFileOpen(file) {
    const openUrl = normalizeOpenUrl(
        file?.open_url ||
        file?.url ||
        ''
    )

    if (openUrl) {
        window.open(openUrl, '_blank', 'noopener,noreferrer')
        return
    }

    showError('This blueprint file entry has no storage-backed file. Upload/open/download must be executed in a real Project Files workspace with persisted file records.')
}


function handleBlueprintFileDownload(file) {
    const downloadUrl = String(file?.download_url || '')

    if (downloadUrl) {
        const link = document.createElement('a')
        link.href = downloadUrl
        link.download = String(file?.name || 'download')
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        return
    }

    showError('This blueprint file entry has no storage-backed binary to download. Use a Project Files workspace to upload and retrieve real files.')
}


async function handleBlueprintFileUpload(payload = {}) {
    if (!data.value?.version?.id) {
        showError('Create and save the blueprint version before uploading files.')
        return
    }

    const files = Array.from(payload?.files || [])
    if (!files.length) {
        return
    }

    const parent = payload?.parent || null

    let folderId = null
    if (isPersistedFolderId(payload?.folderId)) {
        folderId = Number(payload.folderId)
    } else if (parent?.client_key) {
        const match = (data.value?.version?.folders || []).find(item => String(item.client_key) === String(parent.client_key))
        if (isPersistedFolderId(match?.id)) {
            folderId = Number(match.id)
        }
    }

    if (payload?.folderId && !folderId) {
        await saveBlueprint()
        const refreshed = (data.value?.version?.folders || []).find(item => String(item.client_key) === String(parent?.client_key || payload.folderId))
        if (isPersistedFolderId(refreshed?.id)) {
            folderId = Number(refreshed.id)
        }
    }

    const maxFilesPerRequest = 20

    try {
        const chunks = []
        for (let offset = 0; offset < files.length; offset += maxFilesPerRequest) {
            chunks.push(files.slice(offset, offset + maxFilesPerRequest))
        }

        for (
            let chunkIndex = 0;
            chunkIndex < chunks.length;
            chunkIndex += 1
        ) {
            const chunk =
                chunks[chunkIndex]

            const chunkOffset =
                chunkIndex *
                maxFilesPerRequest

            const body = new FormData()

            chunk.forEach((file, index) => {
                const sourceIndex =
                    chunkOffset + index

                const relativePath =
                    String(
                        payload
                            ?.relativePaths?.[
                            sourceIndex
                        ] ||
                        file.webkitRelativePath ||
                        file.name
                    )

                body.append('files[]', file)
                body.append(`relative_paths[${index}]`, relativePath)
            })

            if (folderId) {
                body.append('folder_id', String(folderId))
            }
            body.append('client_visible', '1')

            await api.post(`/blueprint-versions/${data.value.version.id}/files`, body)
        }

        await load()
    } catch (exception) {
        showError(errorMessage(exception))
    }
}


function handleBlueprintFolderOpen(folder) {
    blueprintCurrentFolderId.value =
        folder?.id ||
        null
}


function scheduleBlueprintSave(
    delay = 250
) {
    if (
        !data.value?.version?.id
    ) {
        return
    }

    if (
        blueprintSaveTimer.value
    ) {
        clearTimeout(
            blueprintSaveTimer.value
        )
    }

    blueprintSaveTimer.value =
        setTimeout(() => {
            blueprintSaveTimer.value =
                null

            void saveBlueprint()
        }, delay)
}


function foldersPayloadForSave() {
    const items =
        (data.value?.version?.folders || [])
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
                        item.resource_type === 'link'
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
                    String(item.id),
                    String(item.client_key)
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


async function saveBlueprint() {
    if (!data.value?.version) {
        return
    }

    if (
        saving.value
    ) {
        queuedBlueprintSave.value =
            true

        return
    }


    saving.value =
        true

    setStatus('saving')

    try {
        const currentFolders =
            (data.value.version.folders || [])
                .map(
                    item => ({
                        ...item
                    })
                )

        const response =
            await api.put(
            `/blueprint-versions/${data.value.version.id}`,
            {
                fields:
                    data.value.version.fields ||
                    [],

                folders:
                    foldersPayloadForSave()
            }
        )

        if (
            response?.data
        ) {
            data.value.version = {
                ...data.value.version,
                ...response.data,
                folders:
                    normalizeVersionFolders(
                        response.data.folders || [],
                        currentFolders
                    )
            }

            refreshOpenDocumentIdFromClientKey()
        }
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
        setStatus('idle')
        setLastSavedAt(new Date())

        if (
            queuedBlueprintSave.value
        ) {
            queuedBlueprintSave.value =
                false

            scheduleBlueprintSave(
                100
            )
        }
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


function deleteProduct() {
    if (
        isCreateMode.value ||
        !props.id ||
        deleting.value
    ) {
        return
    }

    showDeleteConfirm.value = true
}


async function confirmDeleteProduct() {
    if (
        isCreateMode.value ||
        !props.id ||
        deleting.value
    ) {
        return
    }

    deleting.value = true
    requestError.value = ''

    try {
        await api.delete(
            `/service-products/${props.id}`
        )

        showDeleteConfirm.value = false

        router.push({
            name: 'service-products.index'
        })
    } catch (exception) {
        showDeleteConfirm.value = false
        showError(errorMessage(exception))
    } finally {
        deleting.value = false
    }
}


function closeDeleteConfirm() {
    if (deleting.value) {
        return
    }

    showDeleteConfirm.value = false
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
        productForm.name_sk,
        productForm.description,
        productForm.description_sk,
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
        if (documentEditorOpen.value) {
            return
        }

        scheduleAutosave()
    },
    {
        deep: true
    }
)


onMounted(() => {
    load()
})
useAdminPageHeader({
    title: pageTitle,
    description: computed(() =>
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
                class="fixed top-11 right-1 z-50"
                @update:model-value="changeLanguage"
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


            <Teleport to="#admin-page-header-actions">
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
                            v-model="productName"
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
                            :disabled="saving"
                            :error="
                                errors.name?.[0] ||
                                errors.slug?.[0] ||
                                ''
                            "
                            @focus="handleFieldFocus"
                            @blur="handleFieldBlur"
                        />


                        <FormField
                            id="service-description"
                            v-model="productDescription"
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
                            :disabled="saving"
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
                        v-model="serviceSearch"
                        name="service"
                        type="autocomplete"
                        label="Add service"
                        placeholder="Start typing a service"
                        :options="serviceOptions"
                        :loading="servicesLoading || serviceCreating"
                        :disabled="!editable || serviceCreating"
                        @search="searchServices"
                        @select="handleServiceSelect"
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

                    <div
                        v-else-if="services.length"
                        class="
                            border-t
                            border-accent
                        "
                    >
                        <div
                            v-for="(
                                service,
                                index
                            ) in services"
                            :key="service.id"
                            class="
                                border-b
                                border-accent
                                py-5
                            "
                        >
                            <div
                                class="
                                    flex
                                    items-start
                                    gap-4
                                "
                            >
                                <span
                                    class="
                                        p
                                        w-8
                                        shrink-0
                                        pt-2
                                        text-dark/35
                                    "
                                >
                                    {{
                                        String(index + 1).padStart(2, '0')
                                    }}
                                </span>

                                <div
                                    class="
                                        min-w-0
                                        flex-1
                                        space-y-4
                                    "
                                >
                                    <FormField
                                        :id="`service-name-${service.id}`"
                                        :model-value="serviceField(service, 'name')"
                                        type="text"
                                        :label="language === 'sk' ? 'Názov' : 'Name'"
                                        :disabled="serviceSavingId === service.id || !editable"
                                        @update:model-value="value => setServiceField(service, 'name', value)"
                                        @blur="saveService(service)"
                                    />

                                    <FormField
                                        :id="`service-description-${service.id}`"
                                        :model-value="serviceField(service, 'description')"
                                        type="textarea"
                                        :label="language === 'sk' ? 'Popis' : 'Description'"
                                        :disabled="serviceSavingId === service.id || !editable"
                                        @update:model-value="value => setServiceField(service, 'description', value)"
                                        @blur="saveService(service)"
                                    />
                                </div>

                                <button
                                    v-if="editable"
                                    type="button"
                                    class="
                                        shrink-0
                                        cursor-pointer
                                        pt-2
                                        text-dark
                                        transition-colors
                                        duration-200
                                        hover:text-accent
                                        disabled:cursor-not-allowed
                                        disabled:opacity-40
                                    "
                                    :disabled="serviceDeletingId === service.id || serviceSavingId === service.id"
                                    :aria-label="`Remove ${serviceDisplayName(service)}`"
                                    @click="removeService(service)"
                                >
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <p
                        v-else
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


                <FileStructure
                    :model-value="
                        folders
                    "
                    :initial-folder-id="
                        blueprintCurrentFolderId
                    "
                    :allow-upload-control="
                        editable
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
                    @open-folder="
                        handleBlueprintFolderOpen
                    "
                    @open-file="
                        handleBlueprintFileOpen
                    "
                    @download-file="
                        handleBlueprintFileDownload
                    "
                    @upload-files="
                        handleBlueprintFileUpload
                    "
                />
            </section>

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
                    :loading="deleting"
                    :disabled="deleting"
                    :lowercase="true"
                    @click.prevent="deleteProduct"
                    align="left"
                />
            </section>

            <AdminConfirmDialog
                :open="showDeleteConfirm"
                title="Delete service?"
                :text="`This will permanently delete ${pageTitle}. This action cannot be undone.`"
                confirm-label="Delete service"
                :busy="deleting"
                @close="closeDeleteConfirm"
                @confirm="confirmDeleteProduct"
            />
        </div>
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