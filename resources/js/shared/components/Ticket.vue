<script setup>
import {
    computed,
    ref
} from 'vue'

import AdminConfirmDialog
    from '@shared/components/ConfirmDialog.vue'

import Modal
    from '@shared/components/Modal.vue'

import Button
    from '@shared/components/Button.vue'

import FormField
    from '@shared/components/FormField.vue'

import Tag
    from '@shared/components/Tag.vue'


const props = defineProps({
    ticket: {
        type: Object,
        required: true
    },

    priorityOptions: {
        type: Array,
        default: () => []
    },

    assigneeOptions: {
        type: Array,
        default: () => []
    },

    statusOptions: {
        type: Array,
        default: () => []
    },

    tagOptions: {
        type: Array,
        default: () => []
    }
})


const emit = defineEmits([
    'move',
    'save',
    'delete',
    'drag-start',
    'drag-end',
    'create-tag'
])


const open = ref(false)
const saving = ref(false)
const deleting = ref(false)
const showDeleteConfirm = ref(false)
const dragged = ref(false)
const tagSearch = ref('')
const creatingTag = ref(false)


function buildForm() {
    return {
        title:
            props.ticket.title ||
            '',

        description:
            props.ticket.description ||
            '',

        priority:
            props.ticket.priority ||
            'normal',

        deadline:
            props.ticket.deadline
                ? String(
                    props.ticket.deadline
                ).slice(
                    0,
                    10
                )
                : '',

        assigned_to:
            props.ticket.assigned_to ??
            '',

        assignees:
            Array.isArray(
                props.ticket.assignees
            )
                ? props.ticket.assignees.map(
                    item =>
                        `${item.type}:${item.id}`
                )
                : props.ticket.assigned_to
                    ? [
                        `user:${props.ticket.assigned_to}`
                    ]
                    : [],

        tag_ids:
            Array.isArray(
                props.ticket.tags
            )
                ? props.ticket.tags.map(
                    tag =>
                        Number(
                            tag.id
                        )
                )
                : [],

        status:
            props.ticket.status ||
            'new'
    }
}


const form = ref(buildForm())


const deleteConfirmText = computed(
    () =>
        `Are you sure you want to delete "${props.ticket.title}"?`
)


function openTicket() {
    form.value = buildForm()
    tagSearch.value = ''
    open.value = true
}


function closeTicket() {
    if (
        saving.value ||
        creatingTag.value
    ) {
        return
    }

    open.value = false
    tagSearch.value = ''
}


/*
|--------------------------------------------------------------------------
| Ticket tags
|--------------------------------------------------------------------------
*/

const selectedTagIds = computed(() => {
    return Array.isArray(
        form.value.tag_ids
    )
        ? form.value.tag_ids
            .map(
                id =>
                    Number(id)
            )
            .filter(
                id =>
                    Number.isInteger(id) &&
                    id > 0
            )
        : []
})


const selectedTags = computed(() => {
    return props.tagOptions.filter(
        option =>
            selectedTagIds.value.includes(
                Number(option.value)
            )
    )
})


const availableTagOptions = computed(() => {
    const query =
        String(
            tagSearch.value ||
            ''
        ).trim()

    const selected =
        new Set(
            selectedTagIds.value.map(
                id =>
                    String(id)
            )
        )

    const existing =
        props.tagOptions
            .filter(
                option => {
                    const optionId =
                        Number(
                            option.value
                        )

                    /*
                     * Do not show already selected
                     * tags in the autocomplete.
                     */

                    if (
                        selected.has(
                            String(optionId)
                        )
                    ) {
                        return false
                    }

                    if (!query) {
                        return true
                    }

                    return String(
                        option.label ||
                        option.name ||
                        ''
                    )
                        .toLowerCase()
                        .includes(
                            query.toLowerCase()
                        )
                }
            )
            .map(
                option => ({
                    ...option,
                    existing: true
                })
            )

    if (!query) {
        return existing
    }

    const exactMatch =
        props.tagOptions.some(
            option =>
                String(
                    option.label ||
                    option.name ||
                    ''
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


function searchTags(query) {
    tagSearch.value =
        String(
            query ||
            ''
        )
}


function removeTag(tagId) {
    const id =
        Number(tagId)

    form.value.tag_ids =
        selectedTagIds.value.filter(
            selectedId =>
                selectedId !== id
        )
}


async function selectTag(option) {
    if (
        !option ||
        creatingTag.value
    ) {
        return
    }

    /*
     * Existing tag
     */

    if (option.existing) {
        const id =
            Number(option.value)

        if (
            !selectedTagIds.value.includes(id)
        ) {
            form.value.tag_ids = [
                ...selectedTagIds.value,
                id
            ]
        }

        tagSearch.value = ''
        return
    }

    /*
     * Create new tag
     */

    if (
        option.value !== '__create__' ||
        !option.name
    ) {
        return
    }

    const name =
        String(
            option.name
        ).trim()

    if (!name) {
        return
    }

    /*
     * Preserve the currently selected tags.
     */

    const previousIds = [
        ...selectedTagIds.value
    ]

    /*
     * Check if the tag already exists.
     */

    const existing =
        props.tagOptions.find(
            item =>
                String(
                    item.label ||
                    item.name ||
                    ''
                )
                    .trim()
                    .toLowerCase() ===
                name.toLowerCase()
        )

    if (existing?.value) {
        const id =
            Number(existing.value)

        if (
            !previousIds.includes(id)
        ) {
            form.value.tag_ids = [
                ...previousIds,
                id
            ]
        }

        tagSearch.value = ''
        return
    }

    creatingTag.value = true

    try {
        /*
         * Parent handles the actual API request.
         *
         * Expected callback:
         *
         * done(createdTag)
         */

        await new Promise(
            (
                resolve,
                reject
            ) => {
                let settled = false

                const finish = (
                    createdTag,
                    error = null
                ) => {
                    if (settled) {
                        return
                    }

                    settled = true

                    if (error) {
                        reject(error)
                    } else {
                        resolve(createdTag)
                    }
                }

                emit(
                    'create-tag',
                    {
                        name,
                        done: finish
                    }
                )
            }
        ).then(
            createdTag => {
                const id =
                    Number(
                        createdTag?.id
                    )

                if (!id) {
                    throw new Error(
                        'The created tag did not return a valid ID.'
                    )
                }

                form.value.tag_ids = [
                    ...previousIds,
                    id
                ]

                tagSearch.value = ''
            }
        )
    } catch (exception) {
        /*
         * Restore the exact previous
         * selection if creation fails.
         */

        form.value.tag_ids = [
            ...previousIds
        ]

        /*
         * Parent may handle the actual error.
         * We simply leave the current selection intact.
         */
    } finally {
        creatingTag.value = false
    }
}


/*
|--------------------------------------------------------------------------
| Drag and drop
|--------------------------------------------------------------------------
*/

function startDrag(event) {
    dragged.value = true

    event.dataTransfer.effectAllowed =
        'move'

    event.dataTransfer.setData(
        'text/plain',
        String(
            props.ticket.id
        )
    )

    emit(
        'drag-start',
        props.ticket
    )
}


function endDrag() {
    dragged.value = false

    emit(
        'drag-end'
    )
}


/*
|--------------------------------------------------------------------------
| Assignees
|--------------------------------------------------------------------------
*/

function normalizeAssignees(values) {
    return (
        Array.isArray(values)
            ? values
            : []
    )
        .map(
            value => {
                if (
                    value &&
                    typeof value ===
                        'object'
                ) {
                    return {
                        type:
                            value.type,

                        id:
                            Number(
                                value.id
                            )
                    }
                }

                const [
                    type,
                    id
                ] =
                    String(
                        value
                    ).split(':')

                return {
                    type,
                    id:
                        Number(id)
                }
            }
        )
        .filter(
            item =>
                (
                    item.type ===
                        'user' ||
                    item.type ===
                        'contact'
                ) &&
                Number.isInteger(
                    item.id
                ) &&
                item.id > 0
        )
}


/*
|--------------------------------------------------------------------------
| Save / delete
|--------------------------------------------------------------------------
*/

function saveTicket() {
    if (
        saving.value ||
        deleting.value ||
        creatingTag.value
    ) {
        return
    }

    saving.value = true

    emit(
        'save',
        {
            ticket:
                props.ticket,

            data: {
                title:
                    form.value.title,

                description:
                    form.value.description,

                priority:
                    form.value.priority,

                deadline:
                    form.value.deadline ||
                    null,

                assignees:
                    normalizeAssignees(
                        form.value.assignees
                    ),

                tag_ids:
                    selectedTagIds.value,

                status:
                    form.value.status
            },

            done:
                (
                    success = true
                ) => {
                    saving.value = false

                    if (success) {
                        open.value = false
                        tagSearch.value = ''
                    }
                }
        }
    )
}


function promptDeleteTicket() {
    if (
        saving.value ||
        deleting.value ||
        creatingTag.value
    ) {
        return
    }

    showDeleteConfirm.value = true
}


function confirmDeleteTicket() {
    if (
        saving.value ||
        deleting.value
    ) {
        return
    }

    showDeleteConfirm.value = false
    deleting.value = true

    emit(
        'delete',
        {
            ticket:
                props.ticket,

            done:
                (
                    success = true
                ) => {
                    deleting.value = false
                    showDeleteConfirm.value = false

                    if (success) {
                        open.value = false
                    }
                }
        }
    )
}


/*
|--------------------------------------------------------------------------
| Display helpers
|--------------------------------------------------------------------------
*/

function getAssigneeName() {
    if (
        Array.isArray(
            props.ticket.assignees
        ) &&
        props.ticket.assignees.length
    ) {
        return props.ticket.assignees
            .map(
                assignee => {
                    const option =
                        props.assigneeOptions.find(
                            item =>
                                item.value ===
                                `${assignee.type}:${assignee.id}`
                        )

                    return (
                        option?.label ||
                        'Assignee'
                    )
                }
            )
            .join(', ')
    }

    if (
        props.ticket.assignee?.name
    ) {
        return props.ticket.assignee.name
    }

    if (
        props.ticket.assignee?.first_name ||
        props.ticket.assignee?.last_name
    ) {
        return [
            props.ticket.assignee.first_name,
            props.ticket.assignee.last_name
        ]
            .filter(Boolean)
            .join(' ')
    }

    return 'Unassigned'
}


function getCreatorName() {
    if (
        props.ticket.creator?.name
    ) {
        return props.ticket.creator.name
    }

    if (
        props.ticket.clientCreator
    ) {
        return [
            props.ticket.clientCreator.first_name,
            props.ticket.clientCreator.last_name
        ]
            .filter(Boolean)
            .join(' ')
    }

    if (
        props.ticket.client_creator
    ) {
        return [
            props.ticket.client_creator.first_name,
            props.ticket.client_creator.last_name
        ]
            .filter(Boolean)
            .join(' ')
    }

    return 'Unknown'
}


function getDeadlineState() {
    if (!props.ticket.deadline) {
        return null
    }

    const deadline =
        new Date(
            `${String(
                props.ticket.deadline
            ).slice(
                0,
                10
            )}T23:59:59`
        )

    const today =
        new Date()

    today.setHours(
        0,
        0,
        0,
        0
    )

    const tomorrow =
        new Date(today)

    tomorrow.setDate(
        tomorrow.getDate() + 1
    )

    const deadlineDay =
        new Date(deadline)

    deadlineDay.setHours(
        0,
        0,
        0,
        0
    )

    if (
        deadlineDay < today &&
        props.ticket.status !== 'finished'
    ) {
        return {
            label:
                'Overdue',

            class:
                'text-red-600'
        }
    }

    if (
        deadlineDay.getTime() ===
        today.getTime()
    ) {
        return {
            label:
                'Today',

            class:
                'text-accent'
        }
    }

    if (
        deadlineDay.getTime() ===
        tomorrow.getTime()
    ) {
        return {
            label:
                'Tomorrow',

            class:
                'text-accent'
        }
    }

    return {
        label:
            deadlineDay.toLocaleDateString(
                'en-GB',
                {
                    day:
                        '2-digit',

                    month:
                        'short',

                    year:
                        'numeric'
                }
            ),

        class:
            'text-dark/60'
    }
}
</script>


<template>
    <article
        draggable="true"
        class="
            cursor-pointer
            border
            border-accent
            bg-light
            p-4
            transition-all
            duration-200
            hover:bg-accent/[0.04]
            active:cursor-grabbing
        "
        :class="{
            'opacity-40':
                dragged
        }"
        @click="openTicket"
        @dragstart="startDrag"
        @dragend="endDrag"
    >
        <div
            class="
                flex
                items-start
                justify-between
                gap-3
            "
        >
            <p
                class="
                    min-w-0
                    flex-1
                    font-medium
                "
            >
                {{
                    ticket.title
                }}
            </p>

            <span
                v-if="
                    ticket.priority ===
                    'urgent'
                "
                class="
                    shrink-0
                    font-mono
                    text-[10px]
                    font-bold
                    uppercase
                    text-red-600
                "
            >
                Urgent
            </span>
        </div>


        <p
            v-if="
                ticket.description
            "
            class="
                mt-2
                line-clamp-2
                text-dark
                p
            "
        >
            {{
                ticket.description
            }}
        </p>


        <div
            class="
                mt-3
                flex
                flex-wrap
                gap-2
            "
        >
            <Tag
                v-if="
                    ticket.assigned_to ||
                    ticket.assignees?.length
                "
                :text="
                    getAssigneeName()
                "
            />

            <Tag
                v-if="
                    ticket.priority
                "
                :text="
                    ticket.priority
                "
            />

            <Tag
                v-for="
                    tag in ticket.tags || []
                "
                :key="
                    `ticket-tag-${tag.id}`
                "
                :text="
                    tag.name
                "
            />
        </div>


        <div
            v-if="
                getDeadlineState()
            "
            class="
                mt-3
                flex
                items-center
                gap-2
                font-mono
                text-[10px]
                font-bold
                uppercase
            "
            :class="
                getDeadlineState().class
            "
        >
            <span>
                Deadline
            </span>

            <span>
                ·
            </span>

            <span>
                {{
                    getDeadlineState().label
                }}
            </span>
        </div>
    </article>


    <AdminConfirmDialog
        :open="
            showDeleteConfirm
        "
        title="Delete ticket"
        :text="
            deleteConfirmText
        "
        confirm-label="Delete ticket"
        :busy="
            deleting
        "
        @close="
            showDeleteConfirm = false
        "
        @confirm="
            confirmDeleteTicket
        "
    />


    <Modal
        :open="
            open
        "
        title="Ticket"
        :subtitle="
            `Created by ${getCreatorName()}`
        "
        :aria-label="
            `Edit ticket ${ticket.title}`
        "
        close-label="Close ticket"
        panel-class="
            overflow-y-auto
            border
            border-accent
            bg-light
            shadow-xl
        "
        max-width-class="max-w-2xl"
        body-class="p-0"
        @close="
            closeTicket
        "
    >
        <form
            class="
                space-y-8
                bg-light
                p-5
                sm:p-6
            "
            @submit.prevent="
                saveTicket
            "
        >
            <FormField
                :id="
                    `ticket-title-${ticket.id}`
                "
                v-model="
                    form.title
                "
                name="title"
                type="text"
                label="Title"
                required
            />


            <FormField
                :id="
                    `ticket-description-${ticket.id}`
                "
                v-model="
                    form.description
                "
                name="description"
                type="textarea"
                label="Description"
                placeholder="What needs to be done?"
                required
            />


            <div
                class="
                    grid
                    gap-8
                    sm:grid-cols-2
                "
            >
                <FormField
                    :id="
                        `ticket-priority-${ticket.id}`
                    "
                    v-model="
                        form.priority
                    "
                    name="priority"
                    type="select"
                    label="Priority"
                    :options="
                        priorityOptions
                    "
                />


                <FormField
                    :id="
                        `ticket-deadline-${ticket.id}`
                    "
                    v-model="
                        form.deadline
                    "
                    name="deadline"
                    type="date"
                    label="Deadline"
                />
            </div>


            <FormField
                :id="
                    `ticket-assignee-${ticket.id}`
                "
                v-model="
                    form.assignees
                "
                name="assignees"
                type="select"
                label="Assignee"
                multiple
                :options="
                    assigneeOptions
                "
            />


            <!--
                Selected ticket tags.

                These intentionally appear ABOVE
                the autocomplete, like Services.
            -->

            <div
                v-if="
                    selectedTags.length
                "
                class="
                    flex
                    flex-wrap
                    gap-2
                "
            >
                <div
                    v-for="
                        tag in selectedTags
                    "
                    :key="
                        `selected-tag-${tag.value}`
                    "
                    class="
                        inline-flex
                        items-center
                        gap-2
                        border
                        border-accent
                        bg-accent
                        px-3
                        py-1.5
                        font-mono
                        text-[10px]
                        font-bold
                        uppercase
                        text-light
                    "
                >
                    <span>
                        {{
                            tag.label
                        }}
                    </span>


                    <button
                        type="button"
                        class="
                            flex
                            h-4
                            w-4
                            items-center
                            justify-center
                            rounded-full
                            text-light
                            transition
                            hover:bg-light/20
                        "
                        :disabled="
                            saving ||
                            deleting ||
                            creatingTag
                        "
                        @click.stop="
                            removeTag(
                                tag.value
                            )
                        "
                    >
                        <i
                            class="
                                bi
                                bi-x
                                text-[11px]
                            "
                        ></i>
                    </button>
                </div>
            </div>


            <FormField
                :id="
                    `ticket-tags-${ticket.id}`
                "
                :model-value="
                    tagSearch
                "
                name="tag_search"
                type="autocomplete"
                label="Tags"
                placeholder="Start typing a tag"
                :options="
                    availableTagOptions
                "
                :loading="
                    creatingTag
                "
                :disabled="
                    creatingTag ||
                    saving ||
                    deleting
                "
                @search="
                    searchTags
                "
                @select="
                    selectTag
                "
                @update:model-value="
                    value => {
                        /*
                         * The autocomplete is only the
                         * search control. Selected values
                         * live in form.tag_ids.
                         */

                        if (
                            typeof value ===
                            'string'
                        ) {
                            tagSearch =
                                value
                        }
                    }
                "
            />


            <FormField
                :id="
                    `ticket-status-${ticket.id}`
                "
                v-model="
                    form.status
                "
                name="status"
                type="select"
                label="Status"
                :options="
                    statusOptions
                "
            />


            <div
                class="
                    flex
                    flex-col
                    gap-4
                "
            >
                <Button
                    type="button"
                    text="delete ticket"
                    variant="dark"
                    align="right"
                    :loading="
                        deleting
                    "
                    :disabled="
                        saving ||
                        deleting ||
                        creatingTag
                    "
                    @click="
                        promptDeleteTicket
                    "
                />


                <Button
                    type="submit"
                    text="save ticket"
                    variant="accent"
                    align="right"
                    :loading="
                        saving
                    "
                    :disabled="
                        saving ||
                        deleting ||
                        creatingTag
                    "
                    hover-variant="dark"
                />
            </div>
        </form>
    </Modal>
</template>
