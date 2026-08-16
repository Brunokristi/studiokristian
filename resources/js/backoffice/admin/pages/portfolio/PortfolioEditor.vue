<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch
} from 'vue'

import {
    RouterLink
} from 'vue-router'

import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'

import AdminPageHeader from '../../components/AdminPageHeader.vue'

import Button from '@shared/components/Button.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'
import Slideshow from '@shared/components/Slideshow.vue'
import Info from '@shared/components/Info.vue'
import ProjectFilePickerModal from '../../components/ProjectFilePickerModal.vue'
import FormField from '../../../../shared/components/FormField.vue'
import useAutosavePolicy from '../../composables/useAutosavePolicy'


const props = defineProps({
    id: {
        type: String,
        required: true
    }
})


const {
    enabled: autosaveEnabled,
    setStatus,
    setLastSavedAt
} = useAutosavePolicy()


const project = ref(null)
const loading = ref(true)
const saving = ref(false)

const error = ref('')
const errors = ref({})

const showErrorToast = ref(false)
const showSuccessToast = ref(false)

const language = ref('en')

const nameElement = ref(null)
const summaryElement = ref(null)

const showImagePickerModal = ref(false)
const imagePickerLoading = ref(false)
const imagePickerError = ref('')
const imagePickerItems = ref([])
const imagePickerMode = ref('add')
const imagePickerReplaceIndex = ref(null)
const imagePickerCurrentFolderId = ref(null)
const imagePickerUploading = ref(false)

const autosaveTimer = ref(null)
const suppressAutosave = ref(false)
const lastSavedSnapshot = ref('')
const autosaveError = ref('')


const imagePickerTitle = computed(() => {
    if (
        imagePickerMode.value ===
        'logo'
    ) {
        return 'Project files · logo'
    }

    return 'Project files'
})


const imagePickerSubtitle = computed(() => {
    if (
        imagePickerMode.value ===
        'logo'
    ) {
        return 'Select an image file from project files for the logo.'
    }

    return 'Same project structure as workspace. Double-click a PNG file to select it.'
})


const pageTitle = computed(() => {
    if (!project.value) {
        return 'Portfolio'
    }

    return project.value.name || 'Portfolio'
})


const languageLabel = computed(() => {
    return language.value === 'sk'
        ? 'Slovak'
        : 'English'
})


const projectName = computed({
    get() {
        if (!project.value) {
            return ''
        }

        return language.value === 'sk'
            ? project.value.name_sk || ''
            : project.value.name || ''
    },

    set(value) {
        if (!project.value) {
            return
        }

        if (language.value === 'sk') {
            project.value.name_sk = value
        } else {
            project.value.name = value
        }
    }
})


const projectSummary = computed({
    get() {
        if (!project.value) {
            return ''
        }

        return language.value === 'sk'
            ? project.value.summary_sk || ''
            : project.value.summary || ''
    },

    set(value) {
        if (!project.value) {
            return
        }

        if (language.value === 'sk') {
            project.value.summary_sk = value
        } else {
            project.value.summary = value
        }
    }
})


function normalizeImage(
    image,
    index
) {
    return {
        ...image,

        path:
            image?.path ||
            '',

        existing_path:
            image?.existing_path ||
            image?.path ||
            '',

        preview:
            image?.preview ||
            image?.src ||
            image?.existing_path ||
            image?.path ||
            '',

        src:
            image?.src ||
            image?.existing_path ||
            image?.path ||
            '',

        description:
            image?.description ||
            '',

        description_sk:
            image?.description_sk ||
            '',

        alt:
            image?.alt ||
            '',

        alt_sk:
            image?.alt_sk ||
            '',

        file:
            image?.file ||
            null,

        sort_order:
            index
    }
}


function normalizeFeature(
    feature,
    index
) {
    return {
        ...feature,

        title:
            feature?.title ||
            '',

        title_sk:
            feature?.title_sk ||
            '',

        description:
            feature?.description ||
            '',

        description_sk:
            feature?.description_sk ||
            '',

        sort_order:
            index
    }
}


function featureHasContent(
    feature
) {
    const englishTitle =
        String(
            feature?.title ||
            ''
        ).trim()

    const slovakTitle =
        String(
            feature?.title_sk ||
            ''
        ).trim()

    const englishDescription =
        String(
            feature?.description ||
            ''
        ).trim()

    const slovakDescription =
        String(
            feature?.description_sk ||
            ''
        ).trim()

    return (
        (
            englishTitle ||
            slovakTitle
        ) &&
        (
            englishDescription ||
            slovakDescription
        )
    )
}


function incompleteFeatureDrafts() {
    return (
        project.value?.features ||
        []
    )
        .filter(
            feature =>
                !featureHasContent(
                    feature
                )
        )
        .map(
            feature => ({
                ...feature
            })
        )
}


function getImageSnapshot(
    image,
    index
) {
    const file =
        image?.file ||
        null

    return {
        path:
            image?.path ||
            '',
        existing_path:
            image?.existing_path ||
            '',
        description:
            image?.description ||
            '',
        description_sk:
            image?.description_sk ||
            '',
        alt:
            image?.alt ||
            '',
        alt_sk:
            image?.alt_sk ||
            '',
        sort_order:
            Number(
                image?.sort_order ??
                index
            ),
        file_name:
            file?.name ||
            '',
        file_size:
            Number(
                file?.size ||
                0
            ),
        file_mtime:
            Number(
                file?.lastModified ||
                0
            )
    }
}


function getFeatureSnapshot(
    feature,
    index
) {
    return {
        title:
            feature?.title ||
            '',
        title_sk:
            feature?.title_sk ||
            '',
        description:
            feature?.description ||
            '',
        description_sk:
            feature?.description_sk ||
            '',
        sort_order:
            Number(
                feature?.sort_order ??
                index
            )
    }
}


function getProjectAutosaveSnapshot() {
    if (!project.value) {
        return ''
    }

    return JSON.stringify({
        company_id:
            project.value.company_id ||
            '',
        service_product_id:
            project.value.service_product_id ||
            '',
        portal_status:
            project.value.portal_status ||
            '',
        name:
            project.value.name ||
            '',
        name_sk:
            project.value.name_sk ||
            '',
        url:
            project.value.url ||
            '',
        live_url:
            project.value.live_url ||
            '',
        summary:
            project.value.summary ||
            '',
        summary_sk:
            project.value.summary_sk ||
            '',
        hex_color:
            project.value.hex_color ||
            '',
        logo_path:
            project.value.logo_path ||
            '',
        logo_file_name:
            project.value.logo_file?.name ||
            '',
        logo_file_size:
            Number(
                project.value.logo_file
                    ?.size ||
                    0
            ),
        logo_file_mtime:
            Number(
                project.value.logo_file
                    ?.lastModified ||
                    0
            ),
        images:
            (
                project.value.images ||
                []
            ).map(
                getImageSnapshot
            ),
        features:
            (
                project.value
                    .features ||
                []
            ).map(
                getFeatureSnapshot
            )
    })
}


function clearAutosaveTimer() {
    if (
        autosaveTimer.value
    ) {
        clearTimeout(
            autosaveTimer.value
        )

        autosaveTimer.value =
            null
    }
}


function scheduleAutosave(
    delay = 900
) {
    if (
        suppressAutosave.value ||
        loading.value ||
        !autosaveEnabled.value ||
        !project.value
    ) {
        return
    }

    clearAutosaveTimer()

    autosaveTimer.value =
        setTimeout(() => {
            void runAutosave()
        }, delay)
}


async function runAutosave() {
    if (
        suppressAutosave.value ||
        loading.value ||
        !autosaveEnabled.value ||
        !project.value
    ) {
        return
    }

    if (saving.value) {
        scheduleAutosave(700)

        return
    }

    const currentSnapshot =
        getProjectAutosaveSnapshot()

    if (
        !currentSnapshot ||
        currentSnapshot ===
            lastSavedSnapshot.value
    ) {
        return
    }

    const saved =
        await save({
            showSuccessToast:
                false
        })

    if (!saved) {
        autosaveError.value =
            'Autosave failed. Please check the form values.'

        return
    }

    autosaveError.value =
        ''

    if (
        getProjectAutosaveSnapshot() !==
        lastSavedSnapshot.value
    ) {
        scheduleAutosave(700)
    }
}


function applyLanguageSummaryValue(
    value
) {
    if (!project.value) {
        return
    }

    if (
        language.value ===
        'sk'
    ) {
        project.value.summary_sk =
            String(value || '')

        return
    }

    project.value.summary =
        String(value || '')
}


function handleLogoFileChange(
    value
) {
    if (!project.value) {
        return
    }

    project.value.logo_file =
        value ||
        null
}


function clearLogoSelection() {
    if (!project.value) {
        return
    }

    project.value.logo_file = null
    project.value.logo_path = ''
}


function normalizeProjectFolders(
    serverFolders = [],
    previousFolders = []
) {
    const source =
        Array.isArray(
            serverFolders
        )
            ? [
                ...serverFolders
            ]
            : []

    const previous =
        Array.isArray(
            previousFolders
        )
            ? [
                ...previousFolders
            ]
            : []

    source.sort(
        (a, b) =>
            Number(
                a?.sort_order ||
                0
            ) -
            Number(
                b?.sort_order ||
                0
            )
    )

    previous.sort(
        (a, b) =>
            Number(
                a?.sort_order ||
                0
            ) -
            Number(
                b?.sort_order ||
                0
            )
    )

    const normalized =
        source.map(
            (
                item,
                index
            ) => {
                const previousItem =
                    previous[
                        index
                    ] ||
                    null

                return {
                    ...item,

                    client_key:
                        previousItem?.client_key ||
                        String(
                            item.id
                        ),

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
                    String(
                        item.id
                    ),
                    item.client_key
                ]
            )
        )

    return normalized.map(
        item => ({
            ...item,

            parent_client_key:
                item.parent_id !==
                    null &&
                item.parent_id !==
                    undefined
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

    return (
        Number.isInteger(
            numeric
        ) &&
        numeric > 0
    )
}


function imagePickerStructureItems() {
    return (
        imagePickerItems.value ||
        []
    ).filter(
        item =>
            !item?.__uploaded_file
    )
}


function imagePickerFoldersPayloadForSave() {
    const items =
        imagePickerStructureItems().map(
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
                item.parent_id !==
                    null &&
                item.parent_id !==
                    undefined
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


async function persistImagePickerStructure() {
    const response =
        await api.put(
            `/projects/${props.id}/structure`,
            {
                folders:
                    imagePickerFoldersPayloadForSave()
            }
        )

    const uploadedFiles =
        (
            imagePickerItems.value ||
            []
        ).filter(
            item =>
                item?.__uploaded_file
        )

    const structureItems =
        normalizeProjectFolders(
            response.data?.folders ||
            [],
            imagePickerStructureItems()
        )

    imagePickerItems.value = [
        ...structureItems,
        ...uploadedFiles
    ]
}


function imagePickerFileName(
    file
) {
    return (
        file?.display_name ||
        file?.original_filename ||
        file?.name ||
        'image'
    )
}


function isPngProjectFile(
    file
) {
    const mime = String(
        file?.mime_type ||
        ''
    ).toLowerCase()

    if (
        mime ===
        'image/png'
    ) {
        return true
    }

    const name = String(
        imagePickerFileName(
            file
        ) ||
        ''
    ).toLowerCase()

    return name.endsWith(
        '.png'
    )
}


function isImageProjectFile(
    file
) {
    const mime = String(
        file?.mime_type ||
        ''
    ).toLowerCase()

    if (
        mime.startsWith(
            'image/'
        )
    ) {
        return true
    }

    const name = String(
        imagePickerFileName(
            file
        ) ||
        ''
    ).toLowerCase()

    return /\.(png|jpe?g|gif|webp|svg|avif)$/i.test(
        name
    )
}


function pickerAcceptsProjectFile(
    file
) {
    if (
        imagePickerMode.value ===
        'logo'
    ) {
        return isImageProjectFile(
            file
        )
    }

    return isPngProjectFile(
        file
    )
}


function normalizePickerFile(
    file
) {
    return {
        id:
            `project-file-${file.id}`,

        client_key:
            `project-file-${file.id}`,

        type:
            'file',

        resource_type:
            'file',

        name:
            imagePickerFileName(
                file
            ),

        parent_id:
            file.folder_id ??
            null,

        parent_client_key:
            file.folder_id
                ? String(
                    file.folder_id
                )
                : null,

        mime_type:
            file.mime_type ||
            '',

        open_url:
            file.open_url ||
            '',

        download_url:
            file.download_url ||
            '',

        extension:
            file.extension ||
            '',

        size:
            Number(
                file.size ||
                0
            ),

        __uploaded_file:
            true
    }
}


async function loadImagePickerItems() {
    imagePickerLoading.value =
        true

    imagePickerError.value =
        ''

    try {
        const projectResponse =
            await api.get(
                `/projects/${props.id}`
            )

        const projectData =
            projectResponse.data?.data ||
            {}

        const structureItems =
            normalizeProjectFolders(
                projectData?.folders ||
                [],
                imagePickerItems.value.filter(
                    item =>
                        !item
                            ?.__uploaded_file
                )
            )

        const collectedFiles = []

        const queue = [null]
        const visited =
            new Set()

        while (
            queue.length
        ) {
            const folderId =
                queue.shift()

            const response =
                await api.get(
                    `/projects/${props.id}/files`,
                    {
                        params:
                            folderId ===
                            null
                                ? {}
                                : {
                                    folder_id:
                                        folderId
                                }
                    }
                )

            const files =
                Array.isArray(
                    response.data?.files
                )
                    ? response.data.files
                    : []

            const folders =
                Array.isArray(
                    response.data?.folders
                )
                    ? response.data.folders
                    : []

            folders.forEach(
                folder => {
                    const key =
                        String(
                            folder.id
                        )

                    if (
                        visited.has(
                            key
                        )
                    ) {
                        return
                    }

                    visited.add(
                        key
                    )

                    queue.push(
                        folder.id
                    )
                }
            )

            files
                .filter(
                    pickerAcceptsProjectFile
                )
                .forEach(
                    file => {
                        collectedFiles.push(
                            normalizePickerFile(
                                file
                            )
                        )
                    }
                )
        }

        imagePickerItems.value =
            [
                ...structureItems,
                ...collectedFiles
            ]
    } catch (
        exception
    ) {
        imagePickerError.value =
            errorMessage(
                exception
            )
    } finally {
        imagePickerLoading.value =
            false
    }
}


function openImagePickerForAdd() {
    imagePickerMode.value =
        'add'

    imagePickerReplaceIndex.value =
        null

    imagePickerCurrentFolderId.value =
        null

    showImagePickerModal.value =
        true

    void loadImagePickerItems()
}


function openImagePickerForLogo() {
    imagePickerMode.value =
        'logo'

    imagePickerReplaceIndex.value =
        null

    imagePickerCurrentFolderId.value =
        null

    showImagePickerModal.value =
        true

    void loadImagePickerItems()
}


function openImagePickerForReplace(
    payload
) {
    imagePickerMode.value =
        'replace'

    imagePickerReplaceIndex.value =
        Number(
            payload?.index
        )

    imagePickerCurrentFolderId.value =
        null

    showImagePickerModal.value =
        true

    void loadImagePickerItems()
}


function closeImagePickerModal() {
    showImagePickerModal.value =
        false

    imagePickerError.value =
        ''
}


async function handleImagePickerUpload(
    payload
) {
    const files =
        Array.from(
            payload?.files ||
            []
        )

    if (
        !files.length
    ) {
        return
    }

    const parent =
        payload?.parent ||
        null

    let folderId =
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
    } else if (
        parent?.client_key
    ) {
        const match =
            imagePickerStructureItems().find(
                item =>
                    String(
                        item.client_key
                    ) ===
                    String(
                        parent.client_key
                    )
            )

        if (
            isPersistedFolderId(
                match?.id
            )
        ) {
            folderId =
                Number(
                    match.id
                )
        }
    }

    if (
        payload?.folderId &&
        !folderId
    ) {
        await persistImagePickerStructure()

        const refreshed =
            imagePickerStructureItems().find(
                item =>
                    String(
                        item.client_key
                    ) ===
                    String(
                        parent?.client_key ||
                        payload.folderId
                    )
            )

        if (
            isPersistedFolderId(
                refreshed?.id
            )
        ) {
            folderId =
                Number(
                    refreshed.id
                )
        }
    }

    imagePickerUploading.value =
        true

    try {
        const maxFilesPerRequest =
            20

        const chunks = []

        for (
            let offset = 0;
            offset < files.length;
            offset +=
                maxFilesPerRequest
        ) {
            chunks.push(
                files.slice(
                    offset,
                    offset +
                        maxFilesPerRequest
                )
            )
        }

        for (
            let chunkIndex = 0;
            chunkIndex < chunks.length;
            chunkIndex += 1
        ) {
            const chunk =
                chunks[
                    chunkIndex
                ]

            const chunkOffset =
                chunkIndex *
                maxFilesPerRequest

            const body =
                new FormData()

            chunk.forEach(
                (
                    file,
                    index
                ) => {
                    const sourceIndex =
                        chunkOffset +
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

            if (
                folderId
            ) {
                body.append(
                    'folder_id',
                    String(
                        folderId
                    )
                )
            }

            body.append(
                'client_visible',
                '1'
            )

            const response =
                await api.post(
                    `/projects/${props.id}/files`,
                    body
                )

            const failedCount =
                Number(
                    response?.data
                        ?.failed_count ||
                    0
                )

            if (
                failedCount >
                0
            ) {
                const message =
                    String(
                        response?.data
                            ?.errors?.[0]
                            ?.message ||
                        ''
                    ).trim()

                showError(
                    message ||
                    'Some files could not be uploaded.'
                )
            }
        }

        await loadImagePickerItems()
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        imagePickerUploading.value =
            false
    }
}


function handleImagePickerFolderOpen(
    folder
) {
    imagePickerCurrentFolderId.value =
        folder?.id ??
        null
}


function handleImagePickerStructureUpdate(
    value
) {
    imagePickerItems.value =
        Array.isArray(
            value
        )
            ? value
            : []
}


async function createPortfolioImageFromProjectFile(
    file
) {
    const openUrl =
        String(
            file?.open_url ||
            ''
        ).trim()

    if (
        !openUrl
    ) {
        throw new Error(
            'Selected file is missing an open URL.'
        )
    }

    const response =
        await fetch(
            openUrl
        )

    if (
        !response.ok
    ) {
        throw new Error(
            'Failed to read the selected image file.'
        )
    }

    const blob =
        await response.blob()

    const fileName =
        imagePickerFileName(
            file
        )

    const imageFile =
        new File(
            [blob],
            fileName,
            {
                type:
                    blob.type ||
                    file.mime_type ||
                    'image/*'
            }
        )

    const preview =
        URL.createObjectURL(
            imageFile
        )

    return {
        path: '',

        existing_path: '',

        src:
            preview,

        preview:
            preview,

        description: '',

        description_sk: '',

        alt: '',

        alt_sk: '',

        caption: '',

        file:
            imageFile
    }
}


async function createLogoFileFromProjectFile(
    file
) {
    const openUrl =
        String(
            file?.open_url ||
            ''
        ).trim()

    if (
        !openUrl
    ) {
        throw new Error(
            'Selected file is missing an open URL.'
        )
    }

    const response =
        await fetch(
            openUrl
        )

    if (
        !response.ok
    ) {
        throw new Error(
            'Failed to read the selected logo file.'
        )
    }

    const blob =
        await response.blob()

    const fileName =
        imagePickerFileName(
            file
        )

    return new File(
        [blob],
        fileName,
        {
            type:
                blob.type ||
                file.mime_type ||
                'image/*'
        }
    )
}


async function handleImagePickerFileOpen(
    file
) {
    if (
        !project.value
    ) {
        return
    }

    if (
        !file?.open_url
    ) {
        return
    }

    const isLogoMode =
        imagePickerMode.value ===
        'logo'

    if (
        isLogoMode &&
        !isImageProjectFile(
            file
        )
    ) {
        showError(
            'Please select an image file from Project Files.'
        )

        return
    }

    if (
        !isLogoMode &&
        !isPngProjectFile(
            file
        )
    ) {
        showError(
            'Please select a PNG file from Project Files.'
        )

        return
    }

    try {
        if (isLogoMode) {
            const logoFile =
                await createLogoFileFromProjectFile(
                    file
                )

            project.value.logo_file =
                logoFile

            closeImagePickerModal()

            return
        }

        const portfolioImage =
            await createPortfolioImageFromProjectFile(
                file
            )

        if (
            imagePickerMode.value ===
                'replace' &&
            imagePickerReplaceIndex.value !==
                null &&
            imagePickerReplaceIndex.value >=
                0
        ) {
            const nextImages =
                [
                    ...(
                        project.value
                            .images ||
                        []
                    )
                ]

            if (
                imagePickerReplaceIndex.value <
                nextImages.length
            ) {
                nextImages[
                    imagePickerReplaceIndex.value
                ] = {
                    ...nextImages[
                        imagePickerReplaceIndex.value
                    ],
                    ...portfolioImage
                }

                project.value.images =
                    nextImages
            }
        } else {
            project.value.images =
                [
                    ...(
                        project.value
                            .images ||
                        []
                    ),
                    portfolioImage
                ]
        }

        closeImagePickerModal()
    } catch (
        exception
    ) {
        showError(
            exception instanceof Error
                ? exception.message
                : 'Could not attach image from project files.'
        )
    }
}


async function load() {
    loading.value =
        true

    error.value =
        ''

    try {
        suppressAutosave.value =
            true

        clearAutosaveTimer()

        const response =
            await api.get(
                `/projects/${props.id}/portfolio`
            )

        const loadedProject =
            response.data.project

        loadedProject.images =
            (
                loadedProject.images ||
                []
            ).map(
                normalizeImage
            )

        loadedProject.features =
            (
                loadedProject.features ||
                []
            ).map(
                normalizeFeature
            )

        project.value =
            loadedProject

        await nextTick()

        syncEditableFields()

        lastSavedSnapshot.value =
            getProjectAutosaveSnapshot()
        autosaveError.value =
            ''
    } catch (
        exception
    ) {
        error.value =
            errorMessage(
                exception
            )
    } finally {
        suppressAutosave.value =
            false

        loading.value =
            false
    }
}


function syncEditableFields() {
    if (
        nameElement.value
    ) {
        nameElement.value.textContent =
            projectName.value
    }

    if (
        summaryElement.value
    ) {
        summaryElement.value.textContent =
            projectSummary.value
    }
}


function changeLanguage(
    value
) {
    if (
        language.value ===
        value
    ) {
        return
    }

    language.value =
        value

    nextTick(() => {
        syncEditableFields()
    })
}


function handleNameInput(
    event
) {
    projectName.value =
        event.target.textContent ||
        ''
}


function handleSummaryInput(
    event
) {
    projectSummary.value =
        event.target.textContent ||
        ''
}


function handleNameKeydown(
    event
) {
    if (
        event.key !==
        'Enter'
    ) {
        return
    }

    event.preventDefault()

    if (
        summaryElement.value
    ) {
        summaryElement.value.focus()
    }
}


function featureHeading(
    feature
) {
    if (
        language.value ===
        'sk'
    ) {
        return (
            feature.title_sk ||
            ''
        )
    }

    return (
        feature.title ||
        ''
    )
}


function featureText(
    feature
) {
    if (
        language.value ===
        'sk'
    ) {
        return (
            feature.description_sk ||
            ''
        )
    }

    return (
        feature.description ||
        ''
    )
}


function updateFeatureHeading(
    index,
    value
) {
    if (
        !project.value?.features?.[index]
    ) {
        return
    }

    if (
        language.value ===
        'sk'
    ) {
        project.value.features[
            index
        ].title_sk =
            value
    } else {
        project.value.features[
            index
        ].title =
            value
    }
}


function updateFeatureText(
    index,
    value
) {
    if (
        !project.value?.features?.[index]
    ) {
        return
    }

    if (
        language.value ===
        'sk'
    ) {
        project.value.features[
            index
        ].description_sk =
            value
    } else {
        project.value.features[
            index
        ].description =
            value
    }
}


function addFeature() {
    if (
        !project.value
    ) {
        return
    }

    const isSlovak =
        language.value ===
        'sk'

    project.value.features.push({
        id:
            `new-${Date.now()}-${Math.random()}`,

        title:
            isSlovak
                ? ''
                : 'New feature',

        title_sk:
            isSlovak
                ? 'Nova funkcionalita'
                : '',

        description:
            isSlovak
                ? ''
                : 'Add your feature description here.',

        description_sk:
            isSlovak
                ? 'Pridajte popis funkcionality.'
                : '',

        sort_order:
            project.value.features.length
    })
}


function handleFeatureDrop(
    payload
) {
    if (
        !project.value?.features?.length
    ) {
        return
    }

    const from = Number(
        payload?.from
    )

    const to = Number(
        payload?.to
    )

    if (
        Number.isNaN(from) ||
        Number.isNaN(to) ||
        from < 0 ||
        to < 0 ||
        from === to ||
        from >=
            project.value.features.length ||
        to >=
            project.value.features.length
    ) {
        return
    }

    const features = [
        ...project.value.features
    ]

    const moved =
        features.splice(
            from,
            1
        )[0]

    features.splice(
        to,
        0,
        moved
    )

    features.forEach(
        (
            feature,
            featureIndex
        ) => {
            feature.sort_order =
                featureIndex
        }
    )

    project.value.features =
        features
}


function removeFeature(
    index
) {
    if (
        !project.value
    ) {
        return
    }

    project.value.features.splice(
        index,
        1
    )

    project.value.features.forEach(
        (
            feature,
            featureIndex
        ) => {
            feature.sort_order =
                featureIndex
        }
    )
}


function moveFeatureUp(
    index
) {
    if (
        !project.value ||
        index <= 0
    ) {
        return
    }

    const features =
        project.value.features

    const current =
        features[index]

    features[index] =
        features[index - 1]

    features[index - 1] =
        current

    features.forEach(
        (
            feature,
            featureIndex
        ) => {
            feature.sort_order =
                featureIndex
        }
    )
}


function moveFeatureDown(
    index
) {
    if (
        !project.value ||
        index >=
            project.value.features.length -
            1
    ) {
        return
    }

    const features =
        project.value.features

    const current =
        features[index]

    features[index] =
        features[index + 1]

    features[index + 1] =
        current

    features.forEach(
        (
            feature,
            featureIndex
        ) => {
            feature.sort_order =
                featureIndex
        }
    )
}


function append(
    body,
    key,
    value
) {
    if (
        value !== null &&
        value !== undefined
    ) {
        body.append(
            key,
            value
        )
    }
}


async function save(
    options = {}
) {
    const showSuccessToastNow =
        options.showSuccessToast ??
        true

    let didSave = false
    const localIncompleteFeatures =
        incompleteFeatureDrafts()

    if (
        !project.value ||
        saving.value
    ) {
        return false
    }

    saving.value =
        true

    setStatus('saving')

    error.value =
        ''

    errors.value =
        {}

    try {
        suppressAutosave.value =
            true

        clearAutosaveTimer()

        const body =
            new FormData()

        body.append(
            '_method',
            'PUT'
        )

        const projectFields = [
            'company_id',
            'service_product_id',
            'portal_status',
            'name',
            'name_sk',
            'url',
            'live_url',
            'summary',
            'summary_sk',
            'hex_color'
        ]

        projectFields.forEach(
            key => {
                append(
                    body,
                    key,
                    project.value[
                        key
                    ] ??
                    ''
                )
            }
        )

        append(
            body,
            'existing_logo_path',
            project.value.logo_path ||
            ''
        )

        if (
            project.value.logo_file
        ) {
            body.append(
                'logo_file',
                project.value.logo_file
            )
        }

        project.value.images.forEach(
            (
                image,
                index
            ) => {
                append(
                    body,
                    `images[${index}][path]`,
                    image.path ||
                    ''
                )

                append(
                    body,
                    `images[${index}][existing_path]`,
                    image.existing_path ||
                    ''
                )

                append(
                    body,
                    `images[${index}][description]`,
                    image.description ||
                    ''
                )

                append(
                    body,
                    `images[${index}][description_sk]`,
                    image.description_sk ||
                    ''
                )

                append(
                    body,
                    `images[${index}][alt]`,
                    image.alt ||
                    ''
                )

                append(
                    body,
                    `images[${index}][alt_sk]`,
                    image.alt_sk ||
                    ''
                )

                append(
                    body,
                    `images[${index}][sort_order]`,
                    index
                )

                if (
                    image.file
                ) {
                    body.append(
                        `images[${index}][file]`,
                        image.file
                    )
                }
            }
        )

        project.value.features.forEach(
            (
                feature,
                index
            ) => {
                append(
                    body,
                    `features[${index}][title]`,
                    feature.title ||
                    ''
                )

                append(
                    body,
                    `features[${index}][title_sk]`,
                    feature.title_sk ||
                    ''
                )

                append(
                    body,
                    `features[${index}][description]`,
                    feature.description ||
                    ''
                )

                append(
                    body,
                    `features[${index}][description_sk]`,
                    feature.description_sk ||
                    ''
                )

                append(
                    body,
                    `features[${index}][sort_order]`,
                    index
                )
            }
        )

        const response =
            await api.post(
                `/projects/${props.id}/portfolio`,
                body
            )

        const savedProject =
            response.data.project

        savedProject.images =
            (
                savedProject.images ||
                []
            ).map(
                normalizeImage
            )

        savedProject.features =
            (
                savedProject.features ||
                []
            ).map(
                normalizeFeature
            )

        if (
            localIncompleteFeatures.length
        ) {
            const startIndex =
                savedProject.features.length

            savedProject.features = [
                ...savedProject.features,
                ...localIncompleteFeatures.map(
                    (
                        feature,
                        index
                    ) =>
                        normalizeFeature(
                            feature,
                            startIndex +
                                index
                        )
                )
            ]
        }

        project.value =
            savedProject

        await nextTick()

        syncEditableFields()

        lastSavedSnapshot.value =
            getProjectAutosaveSnapshot()

        autosaveError.value =
            ''

        didSave = true

        if (
            showSuccessToastNow
        ) {
            showSuccessToast.value =
                false

            nextTick(() => {
                showSuccessToast.value =
                    true
            })
        }

        return true
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

        return false
    } finally {
        setStatus('idle')

        if (
            didSave
        ) {
            setLastSavedAt(
                new Date()
            )
        }

        suppressAutosave.value =
            false

        saving.value =
            false
    }
}


async function togglePublishing() {
    if (
        !project.value
    ) {
        return
    }

    try {
        const response =
            await api.put(
                `/projects/${props.id}/publishing`,
                {
                    is_published:
                        !project.value
                            .is_published
                }
            )

        project.value.is_published =
            response.data.data.is_published
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


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


function openLiveWebsite() {
    if (
        !project.value?.live_url
    ) {
        return
    }

    window.open(
        project.value.live_url,
        '_blank',
        'noopener,noreferrer'
    )
}


onMounted(
    load
)


watch(
    () => autosaveEnabled.value,
    enabled => {
        if (!enabled) {
            clearAutosaveTimer()
            setStatus('idle')

            return
        }

        if (
            getProjectAutosaveSnapshot() &&
            getProjectAutosaveSnapshot() !==
                lastSavedSnapshot.value
        ) {
            scheduleAutosave(300)
        }
    }
)


watch(
    () =>
        getProjectAutosaveSnapshot(),
    snapshot => {
        if (
            !snapshot ||
            suppressAutosave.value ||
            loading.value
        ) {
            return
        }

        if (
            snapshot ===
            lastSavedSnapshot.value
        ) {
            return
        }

        scheduleAutosave()
    }
)


onBeforeUnmount(() => {
    clearAutosaveTimer()
})
</script>


<template>
    <div
        v-if="project"
        class="
            min-h-full
            w-full
        "
    >
        <Toast
            v-model="
                showErrorToast
            "
            heading="Something went wrong"
            :text="error"
            :duration="5000"
        />


        <Toast
            v-model="
                showSuccessToast
            "
            heading="Portfolio saved"
            text="Your portfolio changes have been saved."
            :duration="4000"
        />


        <AdminPageHeader
            :title="pageTitle"
            eyebrow="Portfolio"
            :breadcrumbs="[
                { label: 'Portfolio' },
                { label: pageTitle }
            ]"
        >
        </AdminPageHeader>


        <main
            class="
                mx-auto
                w-full
                py-10
                lg:py-16
            "
        >
            <div
                class="
                    space-y-16
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
                            col-span-1
                            text-left
                            text-accent
                            md:col-span-2
                        "
                    >
                        Preview
                    </h2>

                    <div
                        class="
                            fixed
                            top-11
                            right-1
                            flex
                            items-center
                            gap-1
                            border
                            border-dark
                            bg-light
                            p-1
                            z-50
                        "
                    >
                        <button
                            type="button"
                            class="
                                px-3
                                py-2
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                transition-colors
                            "
                            :class="
                                language === 'en'
                                    ? 'bg-accent text-light'
                                    : 'text-dark hover:text-accent'
                            "
                            @click="
                                changeLanguage(
                                    'en'
                                )
                            "
                        >
                            EN
                        </button>


                        <button
                            type="button"
                            class="
                                px-3
                                py-2
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                transition-colors
                            "
                            :class="
                                language === 'sk'
                                    ? 'bg-accent text-light'
                                    : 'text-dark hover:text-accent'
                            "
                            @click="
                                changeLanguage(
                                    'sk'
                                )
                            "
                        >
                            SK
                        </button>
                    </div>

                    <div class="border border-accent py-10 space-y-20">
                        <Slideshow
                            v-model:images="
                                project.images
                            "
                            :editable="true"
                            :language="language"
                            :show-arrows="true"
                            :use-project-files-picker="true"
                            @request-project-image-add="
                                openImagePickerForAdd
                            "
                            @request-project-image-replace="
                                openImagePickerForReplace
                            "
                        />

                        <section>
                            <h2
                                ref="nameElement"
                                contenteditable="true"
                                spellcheck="true"
                                class="
                                    h2
                                    min-h-[1.2em]
                                    cursor-text
                                    text-accent
                                    outline-none
                                "
                                @input="
                                    handleNameInput
                                "
                                @keydown="
                                    handleNameKeydown
                                "
                            >
                                {{ projectName }}
                            </h2>
                        </section>

                        <section>
                            <div
                                class="
                                    w-full
                                "
                            >
                                <Info
                                    v-for="
                                        (
                                            feature,
                                            index
                                        ) in project.features
                                    "
                                    :key="
                                        `${feature.id || index}-${language}`
                                    "
                                    :heading="
                                        featureHeading(
                                            feature
                                        )
                                    "
                                    :text="
                                        featureText(
                                            feature
                                        )
                                    "
                                    :editable="
                                        true
                                    "
                                    :index="index"
                                    :draggable="true"
                                    @update:heading="
                                        updateFeatureHeading(
                                            index,
                                            $event
                                        )
                                    "
                                    @update:text="
                                        updateFeatureText(
                                            index,
                                            $event
                                        )
                                    "
                                    @move-up="
                                        moveFeatureUp(
                                            index
                                        )
                                    "
                                    @move-down="
                                        moveFeatureDown(
                                            index
                                        )
                                    "
                                    @remove="
                                        removeFeature(
                                            index
                                        )
                                    "
                                    @drop="
                                        handleFeatureDrop(
                                            $event
                                        )
                                    "
                                />
                            </div>


                            <!-- Add information -->
                            <button
                                type="button"
                                class="
                                    flex
                                    w-full
                                    items-center
                                    gap-3
                                    bg-accent
                                    px-4
                                    py-4
                                    text-left
                                    uppercase
                                    text-light
                                    transition-colors
                                    hover:text-light
                                    h3
                                    border-light
                                    border-y
                                "
                                @click="
                                    addFeature
                                "
                            >
                                <i class="bi bi-plus-lg h3"></i>

                                <span>
                                    add feature
                                </span>
                            </button>
                        </section>

                        <Button
                        v-if="
                            project.live_url
                        "
                            type="button"
                            text="view live"
                            variant="dark"
                            @click="
                                openLiveWebsite
                            "
                        />
                    </div>
                </div>

                <div
                    class="
                        space-y-4
                    "
                >
                    <h2
                        class="
                            h2
                            col-span-1
                            text-left
                            text-accent
                            md:col-span-2
                        "
                    >
                        Meta settings
                    </h2>

                    <div
                        class="
                            space-y-4
                        "
                    >
                        <FormField
                            id="meta-summary"
                            type="textarea"
                            :label="
                                language === 'sk'
                                    ? 'Popis projektu'
                                    : 'Summary'
                            "
                            :model-value="
                                projectSummary
                            "
                            placeholder="Short project summary"
                            :error="
                                errors[
                                    language === 'sk'
                                        ? 'summary_sk'
                                        : 'summary'
                                ]
                            "
                            @update:model-value="
                                applyLanguageSummaryValue
                            "
                        />

                        <FormField
                            id="meta-color"
                            type="text"
                            label="Brand color"
                            :model-value="
                                project.hex_color ||
                                ''
                            "
                            placeholder="#133EB4"
                            :error="
                                errors.hex_color
                            "
                            @update:model-value="
                                value => {
                                    project.hex_color = String(
                                        value ||
                                        ''
                                    )
                                }
                            "
                        />

                        <div
                            class="
                                flex
                                flex-col
                                items-center
                                gap-4
                            "
                        >
                            <div
                                class="
                                    relative
                                    h-[350px]
                                    w-[250px]
                                    shrink-0
                                    overflow-hidden
                                    bg-accent/[0.04]
                                "
                            >
                                <!-- ================================================= -->
                                <!-- CURRENT LOGO -->
                                <!-- ================================================= -->

                                <button
                                    v-if="
                                        project.logo_file ||
                                        project.logo_path
                                    "
                                    type="button"
                                    class="
                                        flex
                                        h-full
                                        w-full
                                        cursor-pointer
                                        items-center
                                        justify-center
                                        bg-light
                                    "
                                    @click="
                                        openImagePickerForLogo
                                    "
                                >
                                    <img
                                        :src="
                                            project.logo_file?.preview ||
                                            project.logo_file?.src ||
                                            project.logo_path
                                        "
                                        :alt="
                                            project.logo_file?.name ||
                                            'Project logo'
                                        "
                                        class="
                                            max-h-full
                                            max-w-full
                                            object-contain
                                            p-8
                                        "
                                    />
                                </button>


                                <!-- ================================================= -->
                                <!-- EMPTY LOGO -->
                                <!-- ================================================= -->

                                <button
                                    v-else
                                    type="button"
                                    class="
                                        group
                                        flex
                                        h-full
                                        w-full
                                        cursor-pointer
                                        flex-col
                                        items-center
                                        justify-center
                                        gap-3
                                        bg-transparent
                                        text-accent
                                        transition-colors
                                        duration-200
                                        hover:bg-accent
                                        hover:text-light
                                    "
                                    @click="
                                        openImagePickerForLogo
                                    "
                                >
                                    <span
                                        class="
                                            leading-none
                                        "
                                    >
                                        <i
                                            class="
                                                bi
                                                bi-plus-lg
                                            "
                                        />
                                    </span>

                                    <span
                                        class="
                                            h3
                                            uppercase
                                        "
                                    >
                                        logo
                                    </span>
                                </button>


                                <!-- ================================================= -->
                                <!-- LOGO ACTIONS -->
                                <!-- ================================================= -->

                                <div
                                    v-if="
                                        project.logo_file ||
                                        project.logo_path
                                    "
                                    class="
                                        absolute
                                        inset-x-0
                                        bottom-0
                                        z-10
                                        flex
                                        gap-2
                                        items-center
                                        justify-end
                                        bg-[#f7f8fd]
                                        px-3
                                        py-2
                                    "
                                >
                                    <button
                                        type="button"
                                        class="
                                            p
                                            text-dark
                                            transition-colors
                                            hover:text-accent
                                        "
                                        @click="
                                            openImagePickerForLogo
                                        "
                                    >
                                        <i class="bi bi-arrow-repeat" />
                                    </button>


                                    <button
                                        type="button"
                                        class="
                                            p
                                            text-dark
                                            transition-colors
                                            hover:text-accent
                                        "
                                        @click="
                                            clearLogoSelection
                                        "
                                    >
                                        <i class="bi bi-eraser" />
                                    </button>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>

                <div
                    class="
                        space-y-4
                    "
                >
                    <h2
                        class="
                            h2
                            col-span-1
                            text-left
                            text-accent
                            md:col-span-2
                        "
                    >
                        SEO settings
                    </h2>

                    <div
                        class="
                            space-y-4
                        "
                    >
                        <FormField
                            id="seo-title"
                            type="text"
                            :label="
                                language === 'sk'
                                    ? 'SEO názov'
                                    : 'SEO title'
                            "
                            :model-value="
                                projectName
                            "
                            placeholder="Project title"
                            :error="
                                errors[
                                    language === 'sk'
                                        ? 'name_sk'
                                        : 'name'
                                ]
                            "
                            @update:model-value="
                                value => {
                                    projectName = String(
                                        value ||
                                        ''
                                    )

                                    syncEditableFields()
                                }
                            "
                        />


                        <FormField
                            id="seo-slug"
                            type="text"
                            label="URL slug"
                            :model-value="
                                project.url ||
                                ''
                            "
                            placeholder="project-slug"
                            :error="
                                errors.url
                            "
                            @update:model-value="
                                value => {
                                    project.url = String(
                                        value ||
                                        ''
                                    )
                                }
                            "
                        />


                        <FormField
                            id="seo-live-url"
                            type="text"
                            label="Live URL"
                            :model-value="
                                project.live_url ||
                                ''
                            "
                            placeholder="https://example.com"
                            :error="
                                errors.live_url
                            "
                            @update:model-value="
                                value => {
                                    project.live_url = String(
                                        value ||
                                        ''
                                    )
                                }
                            "
                        />


                        <p
                            v-if="
                                autosaveError
                            "
                            class="
                                p
                                text-red-600
                            "
                        >
                            {{ autosaveError }}
                        </p>

                    </div>
                </div>

                <div
                    class="
                        space-y-4
                    "
                >
                    <h2
                        class="
                            h2
                            col-span-1
                            text-left
                            text-accent
                            md:col-span-2
                        "
                    >
                        Danger zone
                    </h2>

                    <Button
                        type="button"
                        :text="
                            project.is_published
                                ? 'hide from website'
                                : 'show on website'
                        "
                        variant="dark"
                        align="left"
                        @click="
                            togglePublishing
                        "
                    />
                </div>
            </div>

            <ProjectFilePickerModal
                v-model:open="
                    showImagePickerModal
                "
                :model-value="
                    imagePickerItems
                "
                :initial-folder-id="
                    imagePickerCurrentFolderId
                "
                :loading="
                    imagePickerLoading
                "
                :error="
                    imagePickerError
                "
                :title="
                    imagePickerTitle
                "
                :subtitle="
                    imagePickerSubtitle
                "
                :allow-upload-control="
                    imagePickerMode !==
                    'logo'
                "
                :allow-metadata-editing="
                    true
                "
                :prevent-deleting-required="
                    true
                "
                :disabled="
                    false
                "
                @close="
                    closeImagePickerModal
                "
                @update:model-value="
                    handleImagePickerStructureUpdate
                "
                @open-folder="
                    handleImagePickerFolderOpen
                "
                @open-file="
                    handleImagePickerFileOpen
                "
                @upload-files="
                    handleImagePickerUpload
                "
            />
        </main>

    </div>


    <!-- ============================================================= -->
    <!-- LOADING -->
    <!-- ============================================================= -->

    <div
        v-else-if="loading"
        class="
            flex
            min-h-[60vh]
            items-center
            justify-center
        "
    >
        <p
            class="
                p
                uppercase
                text-dark/40
            "
        >
            Loading portfolio...
        </p>
    </div>


    <!-- ============================================================= -->
    <!-- ERROR -->
    <!-- ============================================================= -->

    <div
        v-else
        class="
            flex
            min-h-[60vh]
            items-center
            justify-center
        "
    >
        <div
            class="
                max-w-md
                text-center
            "
        >
            <p
                class="
                    h3
                    text-red-600
                "
            >
                Unable to load portfolio
            </p>


            <p
                class="
                    p
                    mt-3
                    text-dark/50
                "
            >
                {{ error }}
            </p>
        </div>
    </div>
</template>