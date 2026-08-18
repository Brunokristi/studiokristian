<script setup>
import {
    computed
} from 'vue'


import FormField from '@shared/components/FormField.vue'


const props =
    defineProps({
        title: {
            type: String,
            required: true
        },


        columns: {
            type: Array,
            required: true
        },


        rows: {
            type: Array,
            default: () => []
        },


        loading: {
            type: Boolean,
            default: false
        },


        emptyTitle: {
            type: String,
            default: 'No records yet.'
        },


        emptyText: {
            type: String,
            default: ''
        },


        search: {
            type: String,
            default: ''
        },


        searchPlaceholder: {
            type: String,
            default: 'Search'
        },


        filters: {
            type: Array,
            default: () => []
        },


        filterValues: {
            type: Object,
            default: () => ({})
        },


        sort: {
            type: String,
            default: ''
        },


        direction: {
            type: String,
            default: 'asc'
        },


        addLabel: {
            type: String,
            default: ''
        },


        meta: {
            type: Object,
            default: null
        }
    })


const emit =
    defineEmits([
        'update:search',
        'update:filterValues',
        'sort',
        'row-click',
        'add',
        'page-change'
    ])


const searchValue =
    computed({
        get() {
            return props.search
        },


        set(value) {
            emit(
                'update:search',
                value
            )
        }
    })


const hasActiveFilters =
    computed(() => {
        return props.filters.some(
            filter => {
                const value =
                    props.filterValues[
                        filter.key
                    ]


                return (
                    value !== '' &&
                    value !== null &&
                    value !== undefined
                )
            }
        )
    })


const hasActiveControls =
    computed(() => {
        return (
            searchValue.value
                .trim() !== '' ||
            hasActiveFilters.value
        )
    })


function filterValue(
    key
) {
    return (
        props.filterValues[
            key
        ] ??
        ''
    )
}


function updateFilter(
    key,
    value
) {
    emit(
        'update:filterValues',
        {
            ...props.filterValues,

            [key]:
                value
        }
    )
}


function clearFilters() {
    const values =
        {}


    props.filters.forEach(
        filter => {
            values[
                filter.key
            ] = ''
        }
    )


    emit(
        'update:search',
        ''
    )


    emit(
        'update:filterValues',
        values
    )
}
</script>


<template>
    <section
        class="
            w-full
            space-y-4
        "
    >
        <!-- Title -->
        <h2
            class="
                h2
                text-left
                text-accent
            "
        >
            {{ title }}
        </h2>


        <!-- Controls -->
        <div
            class="
                flex
                flex-col
                gap-5

                xl:flex-row
                xl:items-end
                xl:justify-between
            "
        >
            <!-- Search + filters -->
            <div
                class="
                    grid
                    w-full
                    gap-5

                    sm:grid-cols-2
                    lg:grid-cols-3

                    xl:flex
                    xl:flex-1
                    xl:items-end
                "
            >
                <!-- Search -->
                <div
                    class="
                        w-full

                        sm:col-span-2
                        lg:col-span-1

                        xl:max-w-sm
                        xl:flex-1
                    "
                >
                    <FormField
                        :id="
                            `table-search-${title}`
                        "
                        v-model="
                            searchValue
                        "
                        type="text"
                        label="Search"
                        :placeholder="
                            searchPlaceholder
                        "
                        autocomplete="off"
                    />
                </div>


                <!-- Filters -->
                <div
                    v-for="
                        filter
                        in filters
                    "
                    :key="
                        filter.key
                    "
                    class="
                        w-full

                        xl:w-52
                        xl:shrink-0
                    "
                >
                    <!-- Select -->
                    <FormField
                        v-if="
                            !filter.type ||
                            filter.type ===
                                'select'
                        "
                        :id="
                            `table-filter-${filter.key}`
                        "
                        type="select"
                        :label="
                            filter.label
                        "
                        :model-value="
                            filterValue(
                                filter.key
                            )
                        "
                        :placeholder="
                            filter.placeholder ||
                            'All'
                        "
                        :options="
                            filter.options ||
                            []
                        "
                        @update:model-value="
                            updateFilter(
                                filter.key,
                                $event
                            )
                        "
                    />


                    <!-- Text -->
                    <FormField
                        v-else-if="
                            filter.type ===
                            'text'
                        "
                        :id="
                            `table-filter-${filter.key}`
                        "
                        type="text"
                        :label="
                            filter.label
                        "
                        :model-value="
                            filterValue(
                                filter.key
                            )
                        "
                        :placeholder="
                            filter.placeholder ||
                            ''
                        "
                        @update:model-value="
                            updateFilter(
                                filter.key,
                                $event
                            )
                        "
                    />


                    <!-- Email -->
                    <FormField
                        v-else-if="
                            filter.type ===
                            'email'
                        "
                        :id="
                            `table-filter-${filter.key}`
                        "
                        type="email"
                        :label="
                            filter.label
                        "
                        :model-value="
                            filterValue(
                                filter.key
                            )
                        "
                        :placeholder="
                            filter.placeholder ||
                            ''
                        "
                        @update:model-value="
                            updateFilter(
                                filter.key,
                                $event
                            )
                        "
                    />
                </div>
            </div>


            <!-- Clear -->
            <div
                v-if="
                    hasActiveControls
                "
                class="
                    flex
                    w-full
                    items-end

                    xl:w-auto
                    xl:shrink-0
                    xl:justify-end
                "
            >
                <button
                    type="button"
                    class="
                        font-mono
                        text-xs
                        font-bold
                        lowercase
                        text-dark/50
                        transition-colors
                        duration-200
                        hover:text-accent
                    "
                    @click="
                        clearFilters
                    "
                >
                    clear
                </button>
            </div>
        </div>


        <!-- Dataset -->
        <div
            class="
                w-full
                border
                border-accent
                bg-light
            "
        >
            <!-- Scroll only the actual table -->
            <div
                class="
                    w-full
                    overflow-x-auto
                "
            >
                <table
                    class="
                        w-full
                        min-w-[720px]
                        border-collapse
                        text-left
                    "
                >
                    <!-- Head -->
                    <thead>
                        <tr
                            class="
                                border-b
                                border-accent
                            "
                        >
                            <th
                                v-for="
                                    column
                                    in columns
                                "
                                :key="
                                    column.key
                                "
                                scope="col"
                                class="
                                    px-4
                                    py-4
                                    align-middle
                                "
                            >
                                <button
                                    v-if="
                                        column.sortable
                                    "
                                    type="button"
                                    class="
                                        h3
                                        inline-flex
                                        items-center
                                        gap-2
                                        text-left
                                        transition-colors
                                        duration-200
                                        hover:text-accent
                                    "
                                    @click="
                                        $emit(
                                            'sort',
                                            column.sortKey ||
                                            column.key
                                        )
                                    "
                                >
                                    {{
                                        column.label
                                    }}


                                    <span
                                        class="
                                            text-accent
                                        "
                                        aria-hidden="true"
                                    >
                                        {{
                                            sort ===
                                            (
                                                column.sortKey ||
                                                column.key
                                            )
                                                ? direction ===
                                                    'asc'
                                                    ? '↑'
                                                    : '↓'
                                                : '↕'
                                        }}
                                    </span>
                                </button>


                                <span
                                    v-else
                                    class="h3"
                                >
                                    {{
                                        column.label
                                    }}
                                </span>
                            </th>
                        </tr>
                    </thead>


                    <!-- Loading -->
                    <tbody
                        v-if="
                            loading
                        "
                        aria-live="polite"
                    >
                        <tr
                            v-for="
                                index
                                in 3
                            "
                            :key="
                                index
                            "
                            class="
                                border-b
                                border-accent/20
                                last:border-b-0
                            "
                        >
                            <td
                                v-for="
                                    column
                                    in columns
                                "
                                :key="
                                    column.key
                                "
                                class="
                                    px-4
                                    py-5
                                "
                            >
                                <span
                                    class="
                                        block
                                        h-2
                                        w-3/4
                                        animate-pulse
                                        bg-accent/10
                                    "
                                />
                            </td>
                        </tr>
                    </tbody>


                    <!-- Content -->
                    <tbody
                        v-else
                    >
                        <!-- Rows -->
                        <tr
                            v-for="
                                row
                                in rows
                            "
                            :key="
                                row.id
                            "
                            class="
                                border-0
                                transition-colors
                                duration-200
                                hover:bg-accent/[0.04]
                                cursor-pointer
                            "
                            :class="
                                $attrs.onRowClick
                                    ? 'cursor-pointer'
                                    : ''
                            "
                            @click="
                                $emit(
                                    'row-click',
                                    row
                                )
                            "
                        >
                            <td
                                v-for="
                                    column
                                    in columns
                                "
                                :key="
                                    column.key
                                "
                                class="
                                    px-4
                                    py-4
                                    align-middle
                                "
                            >
                                <slot
                                    :name="
                                        `cell-${column.key}`
                                    "
                                    :row="
                                        row
                                    "
                                    :value="
                                        row[
                                            column.key
                                        ]
                                    "
                                >
                                    <span class="p">
                                        {{
                                            row[
                                                column.key
                                            ] ??
                                            '—'
                                        }}
                                    </span>
                                </slot>
                            </td>
                        </tr>


                        <!-- Empty -->
                        <tr
                            v-if="
                                !rows.length
                            "
                            :class="
                                addLabel
                                    ? 'border-b border-accent/20'
                                    : ''
                            "
                        >
                            <td
                                :colspan="
                                    columns.length
                                "
                                class="
                                    px-6
                                    py-10
                                    text-center
                                "
                            >
                                <p
                                    class="
                                        h3
                                        text-accent
                                    "
                                >
                                    {{
                                        emptyTitle
                                    }}
                                </p>


                                <p
                                    v-if="
                                        emptyText
                                    "
                                    class="
                                        p
                                        mx-auto
                                        mt-2
                                        max-w-md
                                        uppercase
                                        text-dark/40
                                    "
                                >
                                    {{
                                        emptyText
                                    }}
                                </p>
                            </td>
                        </tr>


                        <!-- Add row -->
                        <tr
                            v-if="
                                addLabel
                            "
                        >
                            <td
                                :colspan="
                                    columns.length
                                "
                                class="
                                    p-0
                                "
                            >
                                <button
                                    type="button"
                                    class="
                                        group
                                        flex
                                        w-full
                                        items-center
                                        justify-center
                                        gap-3
                                        px-4
                                        py-4
                                        p
                                        lowercase
                                        text-white
                                        bg-accent
                                        transition-colors
                                        duration-200
                                        hover:bg-accent
                                        hover:text-light
                                    "
                                    @click.stop="
                                        emit(
                                            'add'
                                        )
                                    "
                                >
                                    <span
                                        class="
                                            text-xl
                                            font-light
                                            leading-none
                                            text-center
                                        "
                                        aria-hidden="true"
                                    >
                                        +
                                    </span>


                                    <span>
                                        {{
                                            addLabel
                                        }}
                                    </span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


            <!-- Pagination -->
            <div
                v-if="
                    meta && meta.last_page > 1
                "
                class="
                    flex
                    items-center
                    justify-between
                    gap-4
                    border-t
                    border-accent
                    px-4
                    py-4
                "
            >
                <p class="text-xs text-dark/60">
                    {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        class="
                            border
                            border-accent
                            px-3
                            py-1.5
                            font-mono
                            text-[10px]
                            font-bold
                            uppercase
                            text-dark
                            transition-colors
                            duration-200
                            enabled:hover:border-accent
                            enabled:hover:text-accent
                            disabled:cursor-not-allowed
                            disabled:border-dark/20
                            disabled:text-dark/30
                        "
                        :disabled="meta.current_page <= 1"
                        @click="
                            $emit(
                                'page-change',
                                meta.current_page - 1
                            )
                        "
                    >
                        Previous
                    </button>

                    <button
                        type="button"
                        class="
                            border
                            border-accent
                            px-3
                            py-1.5
                            font-mono
                            text-[10px]
                            font-bold
                            uppercase
                            text-dark
                            transition-colors
                            duration-200
                            enabled:hover:border-accent
                            enabled:hover:text-accent
                            disabled:cursor-not-allowed
                            disabled:border-dark/20
                            disabled:text-dark/30
                        "
                        :disabled="meta.current_page >= meta.last_page"
                        @click="
                            $emit(
                                'page-change',
                                meta.current_page + 1
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>