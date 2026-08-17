<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
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


import useAutosavePolicy from '../../composables/useAutosavePolicy'


import AdminPageHeader from '../../components/AdminPageHeader.vue'
import AdminConfirmDialog from '../../components/AdminConfirmDialog.vue'
import DocumentEditor from '../../components/DocumentEditor.vue'
import ServiceFileStructure from '../../components/ServiceFileStructure.vue'
import ProjectTicket from '../../components/ProjectTicket.vue'



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


const {
    enabled: autosaveEnabled,
    setLastSavedAt,
    setStatus
} =
    useAutosavePolicy()


const projectId =
    ref(
        props.id || ''
    )


const project =
    ref(null)


const loading =
    ref(true)


const error =
    ref('')


const showErrorToast =
    ref(false)


const busy =
    ref(false)


const saving =
    ref(false)


const errors =
    ref({})


const showDeleteConfirm =
    ref(false)


const tickets =
    ref([])



const contactOptions =
    ref([])


const lookups =
    ref({
        companies: [],
        service_products: []
    })


const coworkers =
    ref([])


const currentUser =
    ref(null)


function currentAdminShellUser() {
    try {
        const raw =
            document.querySelector(
                '#client-portal-admin-user'
            )?.textContent ||
            '{}'

        return JSON.parse(raw)
    } catch {
        return {}
    }
}


const shellUser =
    currentAdminShellUser()


const coworker =
    reactive({
        name: '',
        email: ''
    })


const ticketForm =
    reactive({
        title: '',
        description: '',
        priority: 'normal',
        assigned_to: null
    })


const resendingCoworkerId =
    ref(null)


const resendingContactId =
    ref(null)


const projectForm =
    reactive({
        company_id: '',
        service_product_id: '',
        name: '',
        summary: '',
        internal_notes: '',
        portal_status: 'draft',
        started_at: '',
        completed_at: '',
        contact_ids: [],
        coworker_ids: []
    })


const projectAutosaveTimer =
    ref(null)


const suppressProjectAutosave =
    ref(false)


const lastSavedProjectSnapshot =
    ref('')


const initialCompany =
    ref('')


const projectFolders =
    ref([])


const projectFilesFolderKey =
    ref(null)


const projectFilesInitialFolderId =
    computed(() => {
        if (
            projectFilesFolderKey.value ===
            null ||
            projectFilesFolderKey.value ===
            undefined ||
            String(
                projectFilesFolderKey.value
            ).trim() ===
            ''
        ) {
            return null
        }


        const key =
            String(
                projectFilesFolderKey.value
            )


        const folder =
            (
                projectFolders.value ||
                []
            ).find(
                item =>
                    item?.type ===
                        'folder' &&
                    (
                        String(
                            item.client_key ||
                            ''
                        ) ===
                            key ||
                        String(
                            item.id
                        ) ===
                            key
                    )
            )


        return folder?.id ??
            null
    })


const structureSaveTimer =
    ref(null)


const structureSaving =
    ref(false)


const structureSaveQueued =
    ref(false)


const documentEditorOpen =
    ref(false)


const documentTemplate =
    ref(null)


const documentBlocks =
    ref({})


const documentSaveInFlight =
    ref(false)


const documentSaveError =
    ref('')


const documentSaveRevision =
    ref(0)


const documentSavedRevision =
    ref(0)


const syncingDocumentRoute =
    ref(false)


/*
|--------------------------------------------------------------------------
| Ticket drag and drop
|--------------------------------------------------------------------------
*/

const draggedTicket =
    ref(null)


const dragOverStatus =
    ref('')


function startTicketDrag(
    ticket
) {
    dragOverStatus.value =
        ''


    draggedTicket.value =
        ticket
}


function endTicketDrag() {
    dragOverStatus.value =
        ''


    draggedTicket.value =
        null
}


function handleTicketDragEnter(
    status
) {
    if (
        !draggedTicket.value
    ) {
        return
    }


    dragOverStatus.value =
        status
}


function handleTicketDrop(
    status
) {
    if (
        !draggedTicket.value
    ) {
        return
    }


    const ticket =
        draggedTicket.value


    dragOverStatus.value =
        ''


    draggedTicket.value =
        null


    if (
        ticket.status ===
        status
    ) {
        return
    }


    moveTicket(
        ticket,
        status
    )
}


/*
|--------------------------------------------------------------------------
| Ticket assignees
|--------------------------------------------------------------------------
*/

const ticketAssignees =
    computed(() => {
        const options =
            []

        const seen =
            new Set()


        function pushUser(
            user
        ) {
            const id =
                Number(
                    user?.id ||
                    0
                )


            if (
                !id ||
                seen.has(id)
            ) {
                return
            }


            seen.add(
                id
            )


            options.push({
                id,

                name:
                    String(
                        user?.name ||
                        'Unknown user'
                    ),

                is_admin:
                    Boolean(
                        user?.is_admin
                    )
            })
        }


        const currentUser =
            project.value?.current_user ||
            null


        if (
            currentUser?.is_admin
        ) {
            pushUser(
                currentUser
            )
        }


        for (
            const user
            of project.value?.coworkers ||
            []
        ) {
            pushUser(
                user
            )
        }


        return options
    })


const ticketAssigneeOptions =
    computed(() => [
        {
            label:
                'Unassigned',

            value:
                null
        },

        ...ticketAssignees.value.map(
            user => ({
                label:
                    `${user.name}${user.is_admin ? ' (admin)' : ''}`,

                value:
                    user.id
            })
        )
    ])


/*
|--------------------------------------------------------------------------
| Project
|--------------------------------------------------------------------------
*/

const pageTitle =
    computed(() =>
        projectForm.name ||
        project.value?.name ||
        'New project'
    )


const selectedCompanyName =
    computed(() => {
        const company =
            lookups.value.companies.find(
                item =>
                    String(
                        item.id
                    ) ===
                    String(
                        projectForm.company_id
                    )
            )


        return (
            company?.name ||
            ''
        )
    })


const projectReady =
    computed(() =>
        Boolean(
            projectId.value
        )
    )


const canManageProjectSettings =
    computed(() =>
        Boolean(
            currentUser.value
                ?.is_admin ??
            shellUser?.is_admin
        )
    )


const statusOptions = [
    {
        label:
            'Draft',

        value:
            'draft'
    },

    {
        label:
            'Active',

        value:
            'active'
    },

    {
        label:
            'On hold',

        value:
            'on_hold'
    },

    {
        label:
            'Completed',

        value:
            'completed'
    },

    {
        label:
            'Archived',

        value:
            'archived'
    }
]


const serviceOptions =
    computed(() =>
        lookups.value.service_products.map(
            product => ({
                label:
                    product.active
                        ? product.name
                        : `${product.name} (inactive)`,

                value:
                    String(
                        product.id
                    ),

                disabled:
                    !product.active &&
                    String(
                        product.id
                    ) !==
                    String(
                        projectForm.service_product_id
                    )
            })
        )
    )


const contactAssignmentOptions =
    computed(() =>
        contactOptions.value.map(
            contact => ({
                label:
                    `${contact.first_name || ''} ${contact.last_name || ''}`
                        .trim() ||
                    contact.email ||
                    'Contact',

                value:
                    contact.id
            })
        )
    )


const coworkerAssignmentOptions =
    computed(() => {
        const options =
            []

        const seen =
            new Set()


        function pushOption(
            user
        ) {
            const id =
                Number(
                    user?.id ||
                    0
                )


            if (
                !id ||
                seen.has(id)
            ) {
                return
            }


            seen.add(
                id
            )


            options.push({
                label:
                    `${user.name} ${user.is_admin ? '(admin)' : ''}`,
                value:
                    id
            })
        }


        if (
            currentUser.value?.is_admin
        ) {
            pushOption(
                currentUser.value
            )
        }


        for (
            const coworker
            of coworkers.value
        ) {
            pushOption(
                coworker
            )
        }


        return options
    })


/*
|--------------------------------------------------------------------------
| Ticket options
|--------------------------------------------------------------------------
*/

const ticketPriorityOptions = [
    {
        label:
            'Low',

        value:
            'low'
    },

    {
        label:
            'Normal',

        value:
            'normal'
    },

    {
        label:
            'High',

        value:
            'high'
    },

    {
        label:
            'Urgent',

        value:
            'urgent'
    }
]


const ticketStatusOptions = [
    {
        label:
            'New',

        value:
            'new'
    },

    {
        label:
            'In progress',

        value:
            'in_progress'
    },

    {
        label:
            'Finished',

        value:
            'finished'
    }
]


/*
|--------------------------------------------------------------------------
| Project helpers
|--------------------------------------------------------------------------
*/

function normalizeCompanyPrefill() {
    const raw =
        route.query.company_id ??
        route.query.client_id ??
        ''


    const value =
        Array.isArray(
            raw
        )
            ? raw[0]
            : raw


    return String(
        value ||
        ''
    ).trim()
}


function getProjectAutosaveSnapshot() {
    return JSON.stringify({
        company_id:
            String(
                projectForm.company_id ||
                ''
            ).trim(),

        service_product_id:
            String(
                projectForm.service_product_id ||
                ''
            ).trim(),

        name:
            String(
                projectForm.name ||
                ''
            ).trim(),

        summary:
            String(
                projectForm.summary ||
                ''
            ),

        internal_notes:
            String(
                projectForm.internal_notes ||
                ''
            ),

        portal_status:
            String(
                projectForm.portal_status ||
                'draft'
            ),

        started_at:
            String(
                projectForm.started_at ||
                ''
            ),

        completed_at:
            String(
                projectForm.completed_at ||
                ''
            ),

        contact_ids:
            [
                ...projectForm.contact_ids
            ]
                .map(
                    value =>
                        Number(
                            value
                        )
                )
                .filter(
                    Number.isFinite
                )
                .sort(
                    (
                        a,
                        b
                    ) =>
                        a - b
                ),

        coworker_ids:
            [
                ...projectForm.coworker_ids
            ]
                .map(
                    value =>
                        Number(
                            value
                        )
                )
                .filter(
                    Number.isFinite
                )
                .sort(
                    (
                        a,
                        b
                    ) =>
                        a - b
                )
    })
}


function canAutosaveProject() {
    if (
        !canManageProjectSettings.value
    ) {
        return false
    }

    return Boolean(
        String(
            projectForm.company_id ||
            ''
        ).trim() &&

        String(
            projectForm.service_product_id ||
            ''
        ).trim() &&

        String(
            projectForm.name ||
            ''
        ).trim()
    )
}


/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
*/

function showError(
    message
) {
    error.value =
        message


    showErrorToast.value =
        false


    requestAnimationFrame(() => {
        showErrorToast.value =
            true
    })
}


/*
|--------------------------------------------------------------------------
| Project structure
|--------------------------------------------------------------------------
*/

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
        Number(
            value
        )


    return (
        Number.isInteger(
            numeric
        ) &&
        numeric > 0
    )
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
                ...previousFolders.filter(
                    item =>
                        !item
                            ?.__uploaded_file
                )
            ]
            : []


    const previousById =
        new Map(
            previous
                .filter(
                    item =>
                        isPersistedFolderId(
                            item?.id
                        )
                )
                .map(
                    item => [
                        String(
                            Number(
                                item.id
                            )
                        ),
                        item
                    ]
                )
        )


    source.sort(
        (
            a,
            b
        ) =>
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
            item => {
                const previousItem =
                    previousById.get(
                        String(
                            Number(
                                item.id
                            )
                        )
                    ) ||
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


function structureItemsOnly(
    items
) {
    return (
        Array.isArray(items)
            ? items
            : []
    ).filter(
        item =>
            !item
                ?.__uploaded_file
    )
}


function uploadedItemsOnly(
    items
) {
    return (
        Array.isArray(items)
            ? items
            : []
    ).filter(
        item =>
            Boolean(
                item
                    ?.__uploaded_file
            )
    )
}


function foldersPayloadForSave() {
    const items =
        structureItemsOnly(
            projectFolders.value ||
            []
        ).map(
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


/*
|--------------------------------------------------------------------------
| Project loading
|--------------------------------------------------------------------------
*/

function applyProjectToForm(
    projectData
) {
    if (
        !projectData
    ) {
        return
    }


    project.value =
        projectData


    currentUser.value =
        projectData.current_user ||
        currentUser.value ||
        shellUser ||
        null


    projectId.value =
        String(
            projectData.id ||
            projectId.value ||
            ''
        )


    initialCompany.value =
        String(
            projectData.company_id ||
            initialCompany.value ||
            ''
        )


    Object.assign(
        projectForm,
        {
            company_id:
                String(
                    projectData.company_id ||
                    ''
                ),

            service_product_id:
                String(
                    projectData.service_product_id ||
                    ''
                ),

            name:
                projectData.name ||
                '',

            summary:
                projectData.summary ||
                '',

            internal_notes:
                projectData.internal_notes ||
                '',

            portal_status:
                projectData.status ||
                'draft',

            started_at:
                projectData.started_at ||
                '',

            completed_at:
                projectData.completed_at ||
                '',

            contact_ids:
                (
                    projectData.contacts ||
                    []
                ).map(
                    contact =>
                        contact.id
                ),

            coworker_ids:
                (
                    projectData.coworkers ||
                    []
                ).map(
                    coworker =>
                        coworker.id
                )
        }
    )


    lastSavedProjectSnapshot.value =
        getProjectAutosaveSnapshot()
}


async function loadLookupsAndCoworkers() {
    if (
        !canManageProjectSettings.value
    ) {
        currentUser.value =
            currentUser.value ||
            shellUser ||
            null

        lookups.value = {
            companies: [],
            service_products: []
        }

        coworkers.value =
            []

        return
    }

    const [
        lookupsResponse,
        coworkersResponse
    ] =
        await Promise.all([
            api.get(
                '/lookups'
            ),

            api.get(
                '/coworkers',
                {
                    params: {
                        per_page:
                            1000
                    }
                }
            )
        ])


    lookups.value =
        lookupsResponse.data ||
        {
            companies: [],
            service_products: []
        }


    coworkers.value =
        coworkersResponse.data?.data ||
        []


    currentUser.value =
        coworkersResponse.data?.current_user ||
        null
}


async function loadContacts(
    companyId,
    preserve = false
) {
    if (
        !companyId
    ) {
        contactOptions.value =
            []


        if (
            !preserve
        ) {
            projectForm.contact_ids =
                []
        }


        return
    }


    const response =
        await api.get(
            `/companies/${companyId}/contacts/options`
        )


    contactOptions.value =
        response.data ||
        []


    if (
        !preserve
    ) {
        projectForm.contact_ids =
            projectForm.contact_ids.filter(
                id =>
                    contactOptions.value.some(
                        contact =>
                            String(
                                contact.id
                            ) ===
                            String(
                                id
                            )
                    )
            )
    }
}


function mapUploadedFileToStructureItem(
    file,
    structureItems
) {
    const folderId =
        file?.folder_id ??
        null

    const parentFolder =
        folderId === null
            ? null
            : (
                structureItems ||
                []
            ).find(
                item =>
                    String(
                        item.id
                    ) ===
                    String(
                        folderId
                    )
            )

    return {
        id: `project-file-${file.id}`,
        client_key: `project-file-${file.id}`,
        type: 'file',
        resource_type: 'file',
        name:
            file?.display_name ||
            file?.original_filename ||
            'file',
        parent_id: folderId,
        parent_client_key:
            parentFolder
                ? String(
                    parentFolder.client_key ||
                    parentFolder.id
                )
                : null,
        mime_type:
            file?.mime_type ||
            '',
        extension:
            file?.extension ||
            '',
        size: Number(
            file?.size ||
            0
        ),
        open_url:
            file?.open_url ||
            '',
        download_url:
            file?.download_url ||
            '',
        __uploaded_file: true
    }
}


async function fetchProjectUploadedFileItems(
    id,
    structureItems
) {
    const collected = []
    const queue = [null]
    const visited =
        new Set()

    while (queue.length) {
        const folderId =
            queue.shift()

        const response =
            await api.get(
                `/projects/${id}/files`,
                {
                    params:
                        folderId === null
                            ? {}
                            : {
                                folder_id:
                                    folderId
                            }
                }
            )

        const folders =
            Array.isArray(
                response.data?.folders
            )
                ? response.data.folders
                : []

        const files =
            Array.isArray(
                response.data?.files
            )
                ? response.data.files
                : []

        folders.forEach(
            folder => {
                const key =
                    String(
                        folder.id
                    )

                if (
                    visited.has(key)
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

        files.forEach(
            file => {
                collected.push(
                    mapUploadedFileToStructureItem(
                        file,
                        structureItems
                    )
                )
            }
        )
    }

    return collected
}


async function loadProjectDetails(
    id
) {
    const response =
        await api.get(
            `/projects/${id}`
        )


    const projectData =
        response.data.data


    applyProjectToForm(
        projectData
    )


    const normalizedStructure =
        normalizeProjectFolders(
            projectData?.folders ||
            [],

            projectFolders.value ||
            []
        )

    const uploadedItems =
        await fetchProjectUploadedFileItems(
            id,
            normalizedStructure
        )

    projectFolders.value = [
        ...normalizedStructure,
        ...uploadedItems
    ]


    tickets.value =
        (
            await api.get(
                `/projects/${id}/tickets`
            )
        ).data


    await loadContacts(
        projectData.company_id,
        true
    )
}


async function load() {
    loading.value =
        true


    suppressProjectAutosave.value =
        true


    try {
        await loadLookupsAndCoworkers()


        const projectRouteId =
            String(
                props.id ||
                projectId.value ||
                ''
            ).trim()


        if (
            projectRouteId
        ) {
            projectId.value =
                projectRouteId


            await loadProjectDetails(
                projectRouteId
            )
        } else {
            const companyId =
                normalizeCompanyPrefill()


            if (
                !companyId
            ) {
                showError(
                    'Create projects from a client detail page so the client is assigned automatically.'
                )


                await router.replace({
                    name:
                        'clients.index'
                })


                return
            }


            project.value =
                null


            projectFolders.value =
                []


            tickets.value =
                []


            Object.assign(
                projectForm,
                {
                    company_id:
                        companyId,

                    service_product_id:
                        '',

                    name:
                        '',

                    summary:
                        '',

                    internal_notes:
                        '',

                    portal_status:
                        'draft',

                    started_at:
                        '',

                    completed_at:
                        '',

                    contact_ids:
                        [],

                    coworker_ids:
                        []
                }
            )


            initialCompany.value =
                companyId


            await loadContacts(
                companyId,
                true
            )


            lastSavedProjectSnapshot.value =
                getProjectAutosaveSnapshot()
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
        loading.value =
            false


        suppressProjectAutosave.value =
            false
    }
}


/*
|--------------------------------------------------------------------------
| Project autosave
|--------------------------------------------------------------------------
*/

async function saveProjectForm() {
    if (
        !canManageProjectSettings.value ||
        saving.value ||
        !canAutosaveProject()
    ) {
        return
    }


    suppressProjectAutosave.value =
        true


    saving.value =
        true


    setStatus(
        'saving'
    )


    error.value =
        ''


    errors.value =
        {}


    try {
        const payload = {
            company_id:
                projectForm.company_id,

            service_product_id:
                projectForm.service_product_id,

            name:
                projectForm.name,

            summary:
                projectForm.summary,

            internal_notes:
                projectForm.internal_notes,

            portal_status:
                projectForm.portal_status,

            started_at:
                projectForm.started_at,

            completed_at:
                projectForm.completed_at,

            contact_ids:
                projectForm.contact_ids,

            coworker_ids:
                projectForm.coworker_ids
        }


        if (
            projectId.value
        ) {
            const response =
                await api.put(
                    `/projects/${projectId.value}`,
                    payload
                )


            project.value =
                response.data.data ||
                project.value


            await loadProjectDetails(
                projectId.value
            )
        } else {
            const response =
                await api.post(
                    '/projects',
                    payload
                )


            const createdId =
                String(
                    response.data.data.id
                )


            projectId.value =
                createdId


            project.value =
                response.data.data ||
                project.value


            await router.replace({
                name:
                    'projects.show',

                params: {
                    id:
                        createdId
                }
            })


            await loadProjectDetails(
                createdId
            )
        }


        lastSavedProjectSnapshot.value =
            getProjectAutosaveSnapshot()


        setLastSavedAt()
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


        setStatus(
            'idle'
        )


        suppressProjectAutosave.value =
            false
    }
}


function scheduleProjectAutosave() {
    if (
        suppressProjectAutosave.value ||
        loading.value ||
        saving.value ||
        !autosaveEnabled.value ||
        !canAutosaveProject()
    ) {
        return
    }


    const snapshot =
        getProjectAutosaveSnapshot()


    if (
        lastSavedProjectSnapshot.value &&
        snapshot ===
            lastSavedProjectSnapshot.value
    ) {
        return
    }


    if (
        projectAutosaveTimer.value
    ) {
        clearTimeout(
            projectAutosaveTimer.value
        )
    }


    projectAutosaveTimer.value =
        setTimeout(
            () => {
                if (
                    !saving.value &&
                    autosaveEnabled.value &&
                    canAutosaveProject()
                ) {
                    void saveProjectForm()
                }
            },

            600
        )
}


/*
|--------------------------------------------------------------------------
| Project watchers
|--------------------------------------------------------------------------
*/

watch(
    () =>
        projectForm.company_id,

    async (
        value,
        oldValue
    ) => {
        if (
            oldValue !==
                undefined &&
            value !==
                oldValue
        ) {
            await loadContacts(
                value,

                String(
                    value
                ) ===
                    String(
                        initialCompany.value
                    )
            )
        }
    }
)


watch(
    () => ({
        ...projectForm
    }),

    () => {
        scheduleProjectAutosave()
    },

    {
        deep:
            true
    }
)


watch(
    () =>
        props.id,

    value => {
        const nextId =
            String(
                value ||
                ''
            ).trim()


        if (
            nextId !==
            projectId.value
        ) {
            projectId.value =
                nextId


            void load()
        }
    }
)


watch(
    () => [
        route.query.document,
        projectFolders.value.length
    ],

    () => {
        syncProjectDocumentFromRoute()
    },

    {
        immediate:
            true
    }
)


onMounted(
    load
)


/*
|--------------------------------------------------------------------------
| Project actions
|--------------------------------------------------------------------------
*/

async function togglePublishing() {
    if (
        !canManageProjectSettings.value ||
        !project.value ||
        busy.value
    ) {
        return
    }


    busy.value =
        true


    try {
        const response =
            await api.put(
                `/projects/${projectId.value}/publishing`,
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
    } finally {
        busy.value =
            false
    }
}


function requestDelete() {
    if (
        !canManageProjectSettings.value
    ) {
        return
    }

    showDeleteConfirm.value =
        true
}


function closeDeleteConfirm() {
    if (
        busy.value
    ) {
        return
    }


    showDeleteConfirm.value =
        false
}


async function destroyProject() {
    if (
        !canManageProjectSettings.value ||
        busy.value
    ) {
        return
    }


    busy.value =
        true


    try {
        await api.delete(
            `/projects/${projectId.value}`
        )


        showDeleteConfirm.value =
            false


        if (
            window.history.length >
            1
        ) {
            router.back()
        } else {
            router.push({
                name:
                    'clients.index'
            })
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
        busy.value =
            false
    }
}


/*
|--------------------------------------------------------------------------
| Team
|--------------------------------------------------------------------------
*/

async function inviteCoworker() {
    if (
        !canManageProjectSettings.value
    ) {
        return
    }

    try {
        await api.post(
            `/projects/${projectId.value}/coworkers`,
            coworker
        )


        coworker.name =
            ''


        coworker.email =
            ''


        await load()
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


async function inviteContact(
    contactId
) {
    if (
        !canManageProjectSettings.value ||
        !contactId
    ) {
        return
    }


    try {
        await api.post(
            `/projects/${projectId.value}/contacts/invite`,
            {
                contact_id:
                    contactId
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
    }
}


async function resendCoworkerInvitation(
    userId
) {
    if (
        !canManageProjectSettings.value ||
        !userId ||
        resendingCoworkerId.value
    ) {
        return
    }


    resendingCoworkerId.value =
        userId


    try {
        await api.post(
            `/projects/${projectId.value}/coworkers/${userId}/resend-invitation`
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
        resendingCoworkerId.value =
            null
    }
}


async function resendContactInvitation(
    contactId
) {
    if (
        !canManageProjectSettings.value ||
        !contactId ||
        resendingContactId.value
    ) {
        return
    }


    resendingContactId.value =
        contactId


    try {
        await api.post(
            `/projects/${projectId.value}/contacts/${contactId}/resend-invitation`
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
        resendingContactId.value =
            null
    }
}


/*
|--------------------------------------------------------------------------
| Tickets
|--------------------------------------------------------------------------
*/

async function createTicket() {
    if (
        !ticketForm.title ||
        !ticketForm.description
    ) {
        return
    }

    try {
        const response =
            await api.post(
            `/projects/${projectId.value}/tickets`,
            ticketForm
        )

        const createdTicket =
            response.data?.data ||
            response.data ||
            null

        if (
            createdTicket
        ) {
            tickets.value = [
                createdTicket,
                ...tickets.value.filter(
                    item =>
                        String(
                            item.id
                        ) !==
                        String(
                            createdTicket.id
                        )
                )
            ]
        }

        Object.assign(
            ticketForm,
            {
                title: '',
                description: '',
                priority: 'normal',
                assigned_to: null
            }
        )

        await nextTick()

        const description =
            document.getElementById(
                'ticket-description'
            )

        if (
            description &&
            description.tagName === 'TEXTAREA'
        ) {
            description.style.height = 'auto'
        }

        try {
            const refreshed =
                await api.get(
                    `/projects/${projectId.value}/tickets`
                )

            tickets.value =
                refreshed.data
        } catch {
            // Keep optimistic ticket list if refresh fails.
        }
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


async function saveTicket({
    ticket,
    data,
    done
}) {
    try {
        const response =
            await api.put(
                `/projects/${projectId.value}/tickets/${ticket.id}`,
                {
                    title:
                        data.title,

                    description:
                        data.description,

                    priority:
                        data.priority,

                    assigned_to:
                        data.assigned_to,

                    status:
                        data.status
                }
            )


        const updatedTicket =
            response.data?.data ||
            response.data ||
            null


        const index =
            tickets.value.findIndex(
                item =>
                    String(
                        item.id
                    ) ===
                    String(
                        ticket.id
                    )
            )


        if (
            index !==
            -1 &&
            updatedTicket
        ) {
            tickets.value.splice(
                index,
                1,
                updatedTicket
            )
        }


        done()
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )


        done()
    }
}


async function deleteTicket({
    ticket,
    done
}) {
    try {
        await api.delete(
            `/projects/${projectId.value}/tickets/${ticket.id}`
        )


        tickets.value =
            tickets.value.filter(
                item =>
                    String(
                        item.id
                    ) !==
                    String(
                        ticket.id
                    )
            )


        done()
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )


        done()
    }
}


async function moveTicket(
    ticket,
    status
) {
    if (
        !ticket ||
        !status ||
        ticket.status ===
            status
    ) {
        return
    }


    try {
        const response =
            await api.put(
                `/projects/${projectId.value}/tickets/${ticket.id}`,
                {
                    status,

                    priority:
                        ticket.priority,

                    assigned_to:
                        ticket.assigned_to
                }
            )


        const updatedTicket =
            response.data?.data ||
            response.data ||
            null


        const index =
            tickets.value.findIndex(
                item =>
                    String(
                        item.id
                    ) ===
                    String(
                        ticket.id
                    )
            )


        if (
            index !==
            -1 &&
            updatedTicket
        ) {
            tickets.value.splice(
                index,
                1,
                updatedTicket
            )
        }
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


function ticketsFor(
    status
) {
    return tickets.value.filter(
        ticket =>
            ticket &&
            ticket.status ===
            status
    )
}


function createProjectTicket() {
    const element =
        document.getElementById(
            'new-ticket'
        )


    element?.scrollIntoView({
        behavior:
            'smooth',

        block:
            'center'
    })


    requestAnimationFrame(
        () => {
            document
                .getElementById(
                    'ticket-title'
                )
                ?.focus()
        }
    )
}


/*
|--------------------------------------------------------------------------
| Files
|--------------------------------------------------------------------------
*/

function queueStructureSave(
    value
) {
    projectFolders.value =
        value


    if (
        structureSaveTimer.value
    ) {
        clearTimeout(
            structureSaveTimer.value
        )
    }


    structureSaveTimer.value =
        setTimeout(
            () => {
                structureSaveTimer.value =
                    null


                void saveProjectStructure()
            },

            250
        )
}


async function saveProjectStructure() {
    if (
        structureSaving.value
    ) {
        structureSaveQueued.value =
            true

        return
    }


    structureSaving.value =
        true

    structureSaveQueued.value =
        false


    try {
        const response =
            await api.put(
                `/projects/${projectId.value}/structure`,
                {
                    folders:
                        foldersPayloadForSave()
                }
            )


        const uploadedItems =
            uploadedItemsOnly(
                projectFolders.value
            )

        projectFolders.value =
            [
                ...normalizeProjectFolders(
                response.data?.folders ||
                [],

                projectFolders.value ||
                []
                ),
                ...uploadedItems
            ]
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        structureSaving.value =
            false

        if (
            structureSaveQueued.value
        ) {
            structureSaveQueued.value =
                false

            void saveProjectStructure()
        }
    }
}


async function handleProjectFileUpload(
    payload = {}
) {
    if (!projectId.value) {
        showError(
            'Create and save the project before uploading files.'
        )

        return
    }

    const files = Array.from(
        payload?.files ||
        []
    )

    if (!files.length) {
        return
    }

    const parent =
        payload?.parent ||
        null

    let folderId = null

    if (
        isPersistedFolderId(
            payload?.folderId
        )
    ) {
        folderId = Number(
            payload.folderId
        )
    } else if (parent?.client_key) {
        const match =
            (projectFolders.value || []).find(
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
            folderId = Number(
                match.id
            )
        }
    }

    if (
        payload?.folderId &&
        !folderId
    ) {
        await saveProjectStructure()

        const refreshed =
            (projectFolders.value || []).find(
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
            folderId = Number(
                refreshed.id
            )
        }
    }

    const maxFilesPerRequest = 20

    try {
        const chunks = []

        for (
            let offset = 0;
            offset < files.length;
            offset += maxFilesPerRequest
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
                chunks[chunkIndex]

            const chunkOffset =
                chunkIndex *
                maxFilesPerRequest

            const body =
                new FormData()

            chunk.forEach(
                (file, index) => {
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

            if (folderId) {
                body.append(
                    'folder_id',
                    String(folderId)
                )
            }

            body.append(
                'client_visible',
                '1'
            )

            await api.post(
                `/projects/${projectId.value}/files`,
                body
            )
        }

        await loadProjectDetails(
            projectId.value
        )
    } catch (exception) {
        showError(
            errorMessage(
                exception
            )
        )
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


    if (
        !raw
    ) {
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


function handleProjectStructureOpenFile(
    item
) {
    const openUrl =
        String(
            item?.open_url ||
            ''
        ).trim()

    if (openUrl) {
        window.open(
            openUrl,
            '_blank',
            'noopener,noreferrer'
        )

        return
    }

    if (
        item?.resource_type ===
        'link'
    ) {
        const linkUrl =
            normalizeOpenUrl(
                item?.url ||
                ''
            )


        if (
            linkUrl
        ) {
            window.open(
                linkUrl,
                '_blank',
                'noopener,noreferrer'
            )


            return
        }
    }


    showError(
        'This project file entry has no storage-backed file to open. Use Project Files below for uploaded binaries.'
    )
}


function handleProjectStructureDownloadFile(
    item
) {
    const downloadUrl =
        String(
            item?.download_url ||
            ''
        )


    if (
        downloadUrl
    ) {
        const link =
            document.createElement(
                'a'
            )


        link.href =
            downloadUrl


        link.download =
            String(
                item?.name ||
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
        'This project file entry has no storage-backed binary to download. Use Project Files below for uploaded binaries.'
    )
}


/*
|--------------------------------------------------------------------------
| Document editor
|--------------------------------------------------------------------------
*/

function readDocumentEnvelope(
    content
) {
    try {
        const parsed =
            JSON.parse(
                String(
                    content ||
                    ''
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
        // Legacy content is handled by the editor.
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


function projectDocumentRouteKey(
    item
) {
    return String(
        item?.client_key ||
        item?.id ||
        ''
    ).trim()
}


function findProjectDocumentByRouteKey(
    key
) {
    const value =
        String(
            key ||
            ''
        ).trim()


    if (!value) {
        return null
    }


    return (
        projectFolders.value ||
        []
    ).find(
        item =>
            item?.type ===
                'file' &&
            item?.resource_type ===
                'document' &&
            (
                String(
                    item.client_key ||
                    ''
                ) ===
                    value ||
                String(
                    item.id ||
                    ''
                ) ===
                    value
            )
    )
}


async function setProjectDocumentRoute(
    key
) {
    const value =
        String(
            key ||
            ''
        ).trim()


    const nextQuery = {
        ...route.query
    }


    if (value) {
        nextQuery.document =
            value
    } else {
        delete nextQuery.document
    }


    await router.replace({
        query: nextQuery
    })
}


async function openProjectDocument(
    item,
    options = {}
) {
    const {
        updateRoute =
            true
    } = options


    if (
        !item?.id
    ) {
        return
    }


    if (updateRoute) {
        const key =
            projectDocumentRouteKey(
                item
            )


        if (
            key &&
            String(
                route.query.document ||
                ''
            ) !==
                key
        ) {
            syncingDocumentRoute.value =
                true


            try {
                await setProjectDocumentRoute(
                    key
                )
            } finally {
                syncingDocumentRoute.value =
                    false
            }
        }
    }


    const envelope =
        readDocumentEnvelope(
            item.content ||
            ''
        )


    projectFilesFolderKey.value =
        item.parent_client_key ??
        item.parent_id ??
        null


    documentTemplate.value = {
        id:
            item.id,

        client_key:
            item.client_key ||
            String(
                item.id
            ),

        name:
            item.name ||
            envelope.title ||
            'Untitled document',

        title:
            item.name ||
            envelope.title ||
            'Untitled document',

        subtitle:
            item.subtitle ||
            envelope.subtitle ||
            '',

        requires_client_signature:
            Boolean(
                item.requires_client_signature
            ),

        is_signed:
            Boolean(
                item.is_signed ||
                item.signed ||
                item.signed_at
            ),

        content:
            item.content ||
            ''
    }


    documentBlocks.value =
        envelope.doc


    documentSaveRevision.value =
        Number(
            item.document_revision ||
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


function handleProjectFilesOpenFolder(
    folder
) {
    projectFilesFolderKey.value =
        folder?.client_key ??
        folder?.id ??
        null
}


function syncProjectDocumentFromRoute() {
    if (
        syncingDocumentRoute.value
    ) {
        return
    }


    const routeKey =
        String(
            route.query.document ||
            ''
        ).trim()


    if (!routeKey) {
        if (
            documentEditorOpen.value
        ) {
            documentEditorOpen.value =
                false
        }


        return
    }


    const currentKey =
        projectDocumentRouteKey(
            documentTemplate.value
        )


    if (
        documentEditorOpen.value &&
        currentKey ===
            routeKey
    ) {
        return
    }


    const match =
        findProjectDocumentByRouteKey(
            routeKey
        )


    if (match) {
        void openProjectDocument(
            match,
            {
                updateRoute:
                    false
            }
        )
    }
}


function updateDocumentBlocks(
    value
) {
    documentBlocks.value =
        value
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
            value ||
            ''
        ).trim()


    documentTemplate.value = {
        ...documentTemplate.value,

        name:
            title,

        title:
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
                value ||
                ''
            )
    }
}


async function saveProjectDocument(
    template
) {
    const payload =
        template ||
        documentTemplate.value


    if (
        !payload?.id
    ) {
        return
    }


    const title =
        String(
            payload.title ||
            payload.name ||
            'Untitled document'
        ).trim() ||
        'Untitled document'


    const subtitle =
        String(
            payload.subtitle ||
            ''
        )


    const content =
        JSON.stringify({
            title,

            subtitle,

            doc:
                payload.document_schema ||
                documentBlocks.value ||
                {}
        })


    const index =
        (
            projectFolders.value ||
            []
        ).findIndex(
            item =>
                String(
                    item.id
                ) ===
                String(
                    payload.id
                ) ||
                String(
                    item.client_key
                ) ===
                String(
                    payload.client_key ||
                    ''
                )
        )


    if (
        index <
        0
    ) {
        documentSaveError.value =
            'Document file could not be found in the project structure.'


        showError(
            documentSaveError.value
        )


        return
    }


    documentSaveInFlight.value =
        true


    documentSaveError.value =
        ''


    try {
        const next =
            [
                ...projectFolders.value
            ]


        next[index] = {
            ...next[index],

            name:
                title,

            template_name:
                title,

            content
        }


        projectFolders.value =
            next


        documentBlocks.value =
            payload.document_schema ||
            documentBlocks.value ||
            {}


        documentTemplate.value = {
            ...documentTemplate.value,

            id:
                next[index].id,

            client_key:
                next[index].client_key ||
                payload.client_key,

            name:
                title,

            title,

            subtitle,

            content
        }


        documentSaveRevision.value +=
            1


        documentSavedRevision.value =
            documentSaveRevision.value


        await saveProjectStructure()
    } catch (
        exception
    ) {
        documentSaveError.value =
            errorMessage(
                exception
            )


        showError(
            documentSaveError.value
        )
    } finally {
        documentSaveInFlight.value =
            false
    }
}


async function handleDocumentBack() {
    documentEditorOpen.value =
        false


    syncingDocumentRoute.value =
        true


    try {
        await setProjectDocumentRoute(
            ''
        )
    } finally {
        syncingDocumentRoute.value =
            false
    }
}


function openPortfolioEditor() {
    router.push({
        name:
            'portfolio.edit',

        params: {
            id:
                projectId.value
        }
    })
}


function openClient() {
    if (
        !project.value?.company?.id
    ) {
        return
    }


    router.push({
        name:
            'clients.show',

        params: {
            id:
                project.value.company.id
        }
    })
}


onBeforeUnmount(
    () => {
        if (
            projectAutosaveTimer.value
        ) {
            clearTimeout(
                projectAutosaveTimer.value
            )
        }


        if (
            structureSaveTimer.value
        ) {
            clearTimeout(
                structureSaveTimer.value
            )
        }
    }
)
</script>


<template>
    <div
        class="
            w-full
            space-y-14
            lg:space-y-16
        "
    >
        <!-- Document editor -->
        <DocumentEditor
            v-if="
                documentEditorOpen
            "
            :model-value="
                documentBlocks
            "
            :template="
                documentTemplate
            "
            :title="
                documentTemplate?.name ||
                ''
            "
            :subtitle="
                documentTemplate?.subtitle ||
                ''
            "
            :show-signature-status="
                true
            "
            :requires-signature="
                Boolean(
                    documentTemplate?.requires_client_signature
                )
            "
            :signature-signed="
                Boolean(
                    documentTemplate?.is_signed
                )
            "
            :editable="
                true
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
            :project-files-enabled="
                true
            "
            :project-id="
                projectId
            "
            language="en"
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
                saveProjectDocument
            "
        />


        <template
            v-else
        >
            <Toast
                v-model="
                    showErrorToast
                "
                heading="Something went wrong"
                :text="
                    error
                "
                :duration="
                    5000
                "
            />


            <!-- Header -->
            <AdminPageHeader
                :title="
                    pageTitle
                "
                :description="
                    projectReady
                        ? (
                            project?.summary ||
                            'Project workspace and delivery.'
                        )
                        : 'Create the project here. Changes save automatically.'
                "
                :eyebrow="
                    project?.project_code ||
                    'Project'
                "
                :breadcrumbs="[
                    {
                        label: 'Projects',

                        to: {
                            name:
                                'projects.index'
                        }
                    },

                    {
                        label:
                            pageTitle
                    }
                ]"
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
                    Loading project...
                </p>
            </div>


            <template
                v-else
            >
                <!-- Project information -->
                <section
                    v-if="
                        canManageProjectSettings
                    "
                    class="
                        space-y-14
                    "
                >
                    <div
                        class="
                            flex
                            flex-col
                            gap-3
                            sm:flex-row
                            sm:items-end
                            sm:justify-between
                        "
                    >
                        <div>
                            <h2
                                class="
                                    h2
                                    text-accent
                                "
                            >
                                Project information
                            </h2>
                        </div>
                    </div>


                    <div
                        class="
                            grid
                            grid-cols-1
                            gap-8
                            md:grid-cols-2
                            md:gap-20
                        "
                    >
                        <section
                            class="
                                space-y-8
                            "
                        >
                            <FormField
                                id="project-name"
                                v-model="
                                    projectForm.name
                                "
                                name="name"
                                type="text"
                                label="Project name"
                                placeholder="Project name"
                                required
                                :error="
                                    errors.name?.[0] ||
                                    ''
                                "
                            />


                            <FormField
                                id="project-service"
                                v-model="
                                    projectForm.service_product_id
                                "
                                name="service_product_id"
                                type="select"
                                label="Service product"
                                :options="
                                    serviceOptions
                                "
                                required
                                :error="
                                    errors.service_product_id?.[0] ||
                                    ''
                                "
                            />


                            <FormField
                                id="project-status"
                                v-model="
                                    projectForm.portal_status
                                "
                                name="portal_status"
                                type="select"
                                label="Status"
                                :options="
                                    statusOptions
                                "
                                :error="
                                    errors.portal_status?.[0] ||
                                    ''
                                "
                            />


                            <div
                                class="
                                    grid
                                    gap-7
                                    sm:grid-cols-2
                                "
                            >
                                <FormField
                                    id="project-started"
                                    v-model="
                                        projectForm.started_at
                                    "
                                    name="started_at"
                                    type="date"
                                    label="Started"
                                    :error="
                                        errors.started_at?.[0] ||
                                        ''
                                    "
                                />


                                <FormField
                                    id="project-completed"
                                    v-model="
                                        projectForm.completed_at
                                    "
                                    name="completed_at"
                                    type="date"
                                    label="Completed"
                                    :error="
                                        errors.completed_at?.[0] ||
                                        ''
                                    "
                                />
                            </div>
                        </section>


                        <section
                            class="
                                space-y-8
                            "
                        >
                            <FormField
                                id="project-summary"
                                v-model="
                                    projectForm.summary
                                "
                                name="summary"
                                type="textarea"
                                label="Summary"
                                placeholder="Brief description of the project"
                                :error="
                                    errors.summary?.[0] ||
                                    ''
                                "
                            />


                            <FormField
                                id="project-notes"
                                v-model="
                                    projectForm.internal_notes
                                "
                                name="internal_notes"
                                type="textarea"
                                label="Internal notes"
                                placeholder="Visible only to your team"
                                :error="
                                    errors.internal_notes?.[0] ||
                                    ''
                                "
                            />
                        </section>
                    </div>
                </section>


                <template
                    v-if="
                        projectReady
                    "
                >
                    <!-- Assigned people -->
                    <section
                        v-if="
                            canManageProjectSettings
                        "
                        class="
                            space-y-8
                        "
                    >
                        <div>
                            <h3
                                class="
                                    h2
                                    text-accent
                                    text-left
                                "
                            >
                                Assigned people
                            </h3>
                        </div>


                        <div
                            class="
                                grid
                                grid-cols-1
                                gap-8
                                md:grid-cols-2
                            "
                        >
                            <FormField
                                id="project-contacts"
                                v-model="
                                    projectForm.contact_ids
                                "
                                name="contact_ids"
                                type="select"
                                label="Client contacts"
                                placeholder="Select contacts"
                                multiple
                                :options="
                                    contactAssignmentOptions
                                "
                                :disabled="
                                    !projectForm.company_id ||
                                    saving
                                "
                                :error="
                                    errors.contact_ids?.[0] ||
                                    ''
                                "
                            />


                            <FormField
                                id="project-coworkers"
                                v-model="
                                    projectForm.coworker_ids
                                "
                                name="coworker_ids"
                                type="select"
                                label="Coworkers"
                                placeholder="Select coworkers"
                                multiple
                                :options="
                                    coworkerAssignmentOptions
                                "
                                :disabled="
                                    saving
                                "
                                :error="
                                    errors.coworker_ids?.[0] ||
                                    ''
                                "
                            />
                        </div>
                    </section>


                    <!-- Tickets -->
                    <section
                        class="
                            space-y-8
                        "
                    >
                        <div
                            class="
                                flex
                                items-end
                                justify-between
                                gap-6
                            "
                        >
                            <h2
                                class="
                                    h2
                                    text-accent
                                "
                            >
                                Tickets
                            </h2>

                        </div>


                        <!-- New ticket -->
                        <div
                            id="new-ticket"
                            class="
                                grid
                                grid-cols-1
                                gap-8
                                md:grid-cols-2
                                lg:grid-cols-4
                            "
                        >
                            <FormField
                                id="ticket-title"
                                v-model="
                                    ticketForm.title
                                "
                                name="title"
                                type="text"
                                label="Title"
                                placeholder="Ticket title"
                                required
                            />


                            <FormField
                                id="ticket-description"
                                v-model="
                                    ticketForm.description
                                "
                                name="description"
                                type="textarea"
                                label="Description"
                                placeholder="What needs to be done?"
                                required
                            />


                            <FormField
                                id="ticket-priority"
                                v-model="
                                    ticketForm.priority
                                "
                                name="priority"
                                type="select"
                                label="Priority"
                                :options="
                                    ticketPriorityOptions
                                "
                            />


                            <FormField
                                id="ticket-assignee"
                                v-model="
                                    ticketForm.assigned_to
                                "
                                name="assigned_to"
                                type="select"
                                label="Assignee"
                                :options="
                                    ticketAssigneeOptions
                                "
                            />


                            <div
                                class="
                                    md:col-span-2
                                    lg:col-span-4
                                "
                            >
                                <Button
                                    type="button"
                                    text="add ticket"
                                    variant="accent"
                                    align="right"
                                    @click="
                                        createTicket
                                    "
                                />
                            </div>
                        </div>


                        <!-- Ticket board -->
                        <div
                            class="
                                grid
                                gap-8
                                lg:grid-cols-3
                            "
                        >
                            <section
                                v-for="
                                    column in [
                                        {
                                            key:
                                                'new',

                                            label:
                                                'New'
                                        },

                                        {
                                            key:
                                                'in_progress',

                                            label:
                                                'In progress'
                                        },

                                        {
                                            key:
                                                'finished',

                                            label:
                                                'Finished'
                                        }
                                    ]
                                "
                                :key="
                                    column.key
                                "
                                :class="[
                                    'min-w-0 p-2 transition-colors',
                                    dragOverStatus === column.key
                                        ? 'bg-accent/10'
                                        : ''
                                ]"
                                @dragover.prevent="
                                    handleTicketDragEnter(
                                        column.key
                                    )
                                "
                                @dragenter="
                                    handleTicketDragEnter(
                                        column.key
                                    )
                                "
                                @drop.prevent="
                                    handleTicketDrop(
                                        column.key
                                    )
                                "
                            >
                                <div
                                    class="
                                        flex
                                        items-center
                                        justify-between
                                        py-4
                                    "
                                >
                                    <h3
                                        class="
                                            h3
                                            text-accent
                                        "
                                    >
                                        {{
                                            column.label
                                        }}
                                    </h3>


                                    <span
                                        class="
                                            p
                                            text-dark/40
                                        "
                                    >
                                        {{
                                            ticketsFor(
                                                column.key
                                            ).length
                                        }}
                                    </span>
                                </div>


                                <div
                                    class="
                                        min-h-32
                                        space-y-2
                                    "
                                >
                                    <ProjectTicket
                                        v-for="
                                            ticket in ticketsFor(
                                                column.key
                                            )
                                        "
                                        :key="
                                            ticket.id
                                        "
                                        :ticket="
                                            ticket
                                        "
                                        :priority-options="
                                            ticketPriorityOptions
                                        "
                                        :assignee-options="
                                            ticketAssigneeOptions
                                        "
                                        :status-options="
                                            ticketStatusOptions
                                        "
                                        @drag-start="
                                            startTicketDrag
                                        "
                                        @drag-end="
                                            endTicketDrag
                                        "
                                        @save="
                                            saveTicket
                                        "
                                        @delete="
                                            deleteTicket
                                        "
                                    />
                                </div>
                            </section>
                        </div>
                    </section>


                    <!-- Files -->
                    <section
                        class="
                            space-y-8
                        "
                    >
                        <div
                            class="
                                flex
                                flex-col
                                gap-3
                                sm:flex-row
                                sm:items-end
                                sm:justify-between
                            "
                        >
                            <div>
                                <h2
                                    class="
                                        h2
                                        text-accent
                                    "
                                >
                                    Project files
                                </h2>
                            </div>
                        </div>


                        <ServiceFileStructure
                            :model-value="
                                projectFolders
                            "
                            :initial-folder-id="
                                projectFilesInitialFolderId
                            "
                            :allow-upload-control="
                                true
                            "
                            :allow-metadata-editing="
                                true
                            "
                            :prevent-deleting-required="
                                true
                            "
                            @update:model-value="
                                queueStructureSave
                            "
                            @open-folder="
                                handleProjectFilesOpenFolder
                            "
                            @open-document="
                                openProjectDocument
                            "
                            @open-file="
                                handleProjectStructureOpenFile
                            "
                            @download-file="
                                handleProjectStructureDownloadFile
                            "
                            @upload-files="
                                handleProjectFileUpload
                            "
                        />
                    </section>


                    <!-- Danger zone -->
                    <section
                        v-if="
                            canManageProjectSettings
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
                                Danger zone
                            </h2>
                        </div>


                        <Button
                            type="button"
                            text="delete project"
                            align="left"
                            :disabled="
                                busy
                            "
                            @click="
                                requestDelete
                            "
                        />
                    </section>
                </template>
            </template>


            <AdminConfirmDialog
                :open="
                    showDeleteConfirm
                "
                title="Delete project?"
                text="This will permanently delete the project and its related records."
                confirm-label="Delete project"
                :busy="
                    busy
                "
                @close="
                    closeDeleteConfirm
                "
                @confirm="
                    destroyProject
                "
            />
        </template>
    </div>
</template>