<script setup>
import {
    ref
} from 'vue'


import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Tag from '@shared/components/Tag.vue'


const props =
    defineProps({
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
        }
    })


const emit =
    defineEmits([
        'move',
        'save',
        'drag-start',
        'drag-end'
    ])


const open =
    ref(false)


const saving =
    ref(false)


const dragged =
    ref(false)


const form =
    ref({
        title:
            props.ticket.title ||
            '',

        description:
            props.ticket.description ||
            '',

        priority:
            props.ticket.priority ||
            '',

        assigned_to:
            props.ticket.assigned_to ??
            '',

        status:
            props.ticket.status ||
            'new'
    })


function openTicket() {
    form.value = {
        title:
            props.ticket.title ||
            '',

        description:
            props.ticket.description ||
            '',

        priority:
            props.ticket.priority ||
            '',

        assigned_to:
            props.ticket.assigned_to ??
            '',

        status:
            props.ticket.status ||
            'new'
    }


    open.value =
        true
}


function closeTicket() {
    if (
        saving.value
    ) {
        return
    }


    open.value =
        false
}


function startDrag(
    event
) {
    dragged.value =
        true


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
    dragged.value =
        false


    emit(
        'drag-end'
    )
}


function saveTicket() {
    if (
        saving.value
    ) {
        return
    }


    saving.value =
        true


    emit(
        'save',
        {
            ticket:
                props.ticket,

            data: {
                ...form.value
            },

            done: () => {
                saving.value =
                    false

                open.value =
                    false
            }
        }
    )
}


function changeStatus(
    status
) {
    form.value.status =
        status
}


function getAssigneeName() {
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


    const option =
        props.assigneeOptions.find(
            item =>
                String(
                    item.value
                ) ===
                String(
                    props.ticket.assigned_to
                )
        )


    return (
        option?.label ||
        'Unassigned'
    )
}


function getCreatorName() {
    if (
        props.ticket.creator?.name
    ) {
        return props.ticket.creator.name
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
</script>


<template>
    <!-- Ticket -->
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
        :class="
            dragged
                ? 'opacity-40'
                : ''
        "
        @click="
            openTicket
        "
        @dragstart="
            startDrag
        "
        @dragend="
            endDrag
        "
    >
        <p
            class="
                p
                min-w-0
                flex-1
                font-medium
            "
        >
            {{
                ticket.title
            }}
        </p>

        <div class="mt-2 flex flex-wrap gap-2">

            <Tag
                v-if="
                    ticket.assigned_to
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
        </div>
    </article>


    <!-- Ticket dialog -->
    <Teleport to="body">
        <div
            v-if="open"
            class="
                fixed
                inset-0
                z-[100]
                flex
                items-center
                justify-center
                bg-dark/50
                p-4
                backdrop-blur-sm
            "
            @click.self="
                closeTicket
            "
        >
            <section
                class="
                    max-h-[90vh]
                    w-full
                    max-w-2xl
                    overflow-y-auto
                    border
                    border-accent
                    bg-light
                "
                role="dialog"
                aria-modal="true"
                :aria-label="
                    `Edit ticket ${ticket.title}`
                "
            >
                <!-- Header -->
                <header
                    class="
                        flex
                        items-start
                        justify-between
                        gap-6
                        border-b
                        border-accent
                        p-5
                        sm:p-6
                    "
                >
                    <div>
                        <p
                            class="
                                h3
                                text-accent
                            "
                        >
                            Ticket
                        </p>

                        <p
                            class="
                                p
                                mt-2
                                text-dark/40
                            "
                        >
                            Created by
                            {{
                                getCreatorName()
                            }}
                        </p>
                    </div>


                    <button
                        type="button"
                        class="
                            shrink-0
                            font-mono
                            text-xl
                            leading-none
                            text-dark
                            transition-colors
                            hover:text-accent
                        "
                        aria-label="Close ticket"
                        @click="
                            closeTicket
                        "
                    >
                        ×
                    </button>
                </header>


                <!-- Form -->
                <form
                    class="
                        space-y-8
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
                                `ticket-assignee-${ticket.id}`
                            "
                            v-model="
                                form.assigned_to
                            "
                            name="assigned_to"
                            type="select"
                            label="Assignee"
                            :options="
                                assigneeOptions
                            "
                        />
                    </div>


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


                    <!-- Actions -->
                    <div
                        class="
                            flex
                            flex-col-reverse
                            gap-3
                            border-t
                            border-accent
                            pt-6
                            sm:flex-row
                            sm:justify-end
                        "
                    >
                        <Button
                            type="button"
                            text="cancel"
                            variant="default"
                            align="left"
                            :disabled="
                                saving
                            "
                            @click="
                                closeTicket
                            "
                        />


                        <Button
                            type="submit"
                            text="save ticket"
                            variant="accent"
                            align="left"
                            :loading="
                                saving
                            "
                            :disabled="
                                saving
                            "
                        />
                    </div>
                </form>
            </section>
        </div>
    </Teleport>
</template>