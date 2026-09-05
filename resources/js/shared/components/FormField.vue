<script setup>

import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch
} from 'vue'


const props =
    defineProps({

        id: {
            type: String,
            default: ''
        },

        name: {
            type: String,
            default: ''
        },

        label: {
            type: String,
            default: ''
        },

        type: {
            type: String,
            default: 'text'
        },

        modelValue: {
            type: [
                String,
                Number,
                Boolean,
                Array,
                Object
            ],
            default: ''
        },

        error: {
            type: String,
            default: ''
        },

        placeholder: {
            type: String,
            default: ''
        },

        suffix: {
            type: String,
            default: ''
        },

        autocomplete: {
            type: String,
            default: ''
        },

        options: {
            type: Array,
            default: () => []
        },

        loading: {
            type: Boolean,
            default: false
        },

        multiple: {
            type: Boolean,
            default: false
        },

        fileAccept: {
            type: String,
            default: ''
        },

        required: {
            type: Boolean,
            default: false
        },

        autofocus: {
            type: Boolean,
            default: false
        },

        disabled: {
            type: Boolean,
            default: false
        },

        readonly: {
            type: Boolean,
            default: false
        }
    })


const emit =
    defineEmits([
        'update:modelValue',
        'change',
        'keydown',
        'focus',
        'blur',
        'select',
        'search'
    ])


const isSelectOpen =
    ref(false)


const isAutocompleteOpen =
    ref(false)


const fieldWrapper =
    ref(null)


const fileInput =
    ref(null)


const textareaRef =
    ref(null)


const tokenInput =
    ref('')


/*
|--------------------------------------------------------------------------
| Autocomplete input
|--------------------------------------------------------------------------
*/

const autocompleteInput =
    ref('')


const selectedLabel =
    computed(() => {

        const selected =
            props.options.find(
                option =>
                    String(
                        option.value
                    ) ===
                    String(
                        props.modelValue
                    )
            )

        return (
            selected?.label ||
            ''
        )

    })


const selectedOptions =
    computed(() => {

        if (
            !props.multiple ||
            !Array.isArray(
                props.modelValue
            )
        ) {
            return []
        }


        return props.modelValue
            .map(
                selectedValue =>
                    props.options.find(
                        option =>
                            String(
                                option.value
                            ) ===
                            String(
                                selectedValue
                            )
                    )
            )
            .filter(
                Boolean
            )

    })


const fileCount =
    computed(() => {

        if (
            Array.isArray(
                props.modelValue
            )
        ) {
            return props.modelValue.length
        }


        return props.modelValue
            ? 1
            : 0

    })


const tokenList =
    computed(() => {

        if (
            !Array.isArray(
                props.modelValue
            )
        ) {
            return []
        }


        return props.modelValue.filter(
            item =>
                typeof item ===
                'string'
        )

    })


/*
|--------------------------------------------------------------------------
| Standard input
|--------------------------------------------------------------------------
*/

function handleInput(
    event
) {

    const value =
        event.target.value


    /*
     * Normal inputs work exactly as before.
     */

    if (
        props.type !==
        'autocomplete'
    ) {

        emit(
            'update:modelValue',
            value
        )

        emit(
            'change',
            value
        )

        return

    }


    /*
     * Multiple autocomplete.
     *
     * The typed text is kept separately
     * from modelValue.
     */

    if (
        props.multiple
    ) {

        autocompleteInput.value =
            value


        isAutocompleteOpen.value =
            true


        emit(
            'search',
            value
        )


        return

    }


    /*
     * Single autocomplete.
     */

    autocompleteInput.value =
        value


    emit(
        'update:modelValue',
        value
    )


    emit(
        'change',
        value
    )


    isAutocompleteOpen.value =
        true


    emit(
        'search',
        value
    )

}


/*
|--------------------------------------------------------------------------
| Keyboard
|--------------------------------------------------------------------------
*/

function handleKeydown(
    event
) {

    if (
        event.key ===
        'Escape'
    ) {

        isSelectOpen.value =
            false

        isAutocompleteOpen.value =
            false

    }


    emit(
        'keydown',
        event
    )

}


/*
|--------------------------------------------------------------------------
| Focus
|--------------------------------------------------------------------------
*/

function handleFocus(
    event
) {

    if (
        props.type ===
        'autocomplete'
    ) {

        isAutocompleteOpen.value =
            true

    }


    emit(
        'focus',
        event
    )

}


/*
|--------------------------------------------------------------------------
| Blur
|--------------------------------------------------------------------------
*/

function handleBlur(
    event
) {

    emit(
        'blur',
        event
    )

}


/*
|--------------------------------------------------------------------------
| Textarea
|--------------------------------------------------------------------------
*/

function handleTextareaInput(
    event
) {

    const value =
        event.target.value


    emit(
        'update:modelValue',
        value
    )


    emit(
        'change',
        value
    )


    resizeTextarea()

}


function resizeTextarea() {

    nextTick(() => {

        if (
            !textareaRef.value
        ) {
            return
        }


        textareaRef.value.style.height =
            'auto'


        textareaRef.value.style.height =
            `${textareaRef.value.scrollHeight}px`

    })

}


watch(
    () => props.modelValue,
    () => {

        if (
            props.type ===
            'textarea'
        ) {

            resizeTextarea()

        }

    },
    {
        immediate: true
    }
)


/*
|--------------------------------------------------------------------------
| Select
|--------------------------------------------------------------------------
*/

function toggleSelect() {

    if (
        props.disabled
    ) {
        return
    }


    isSelectOpen.value =
        !isSelectOpen.value

}


function isOptionSelected(
    option
) {

    if (
        props.multiple
    ) {

        if (
            !Array.isArray(
                props.modelValue
            )
        ) {
            return false
        }


        return props.modelValue.some(
            value =>
                String(
                    value
                ) ===
                String(
                    option.value
                )
        )

    }


    return (
        String(
            props.modelValue
        ) ===
        String(
            option.value
        )
    )

}


function handleSelectOption(
    option
) {

    if (
        props.multiple
    ) {

        const currentValues =
            Array.isArray(
                props.modelValue
            )
                ? [
                    ...props.modelValue
                ]
                : []


        const index =
            currentValues.findIndex(
                value =>
                    String(
                        value
                    ) ===
                    String(
                        option.value
                    )
            )


        if (
            index === -1
        ) {

            currentValues.push(
                option.value
            )

        } else {

            currentValues.splice(
                index,
                1
            )

        }


        emit(
            'update:modelValue',
            currentValues
        )


        emit(
            'change',
            currentValues
        )


        return

    }


    emit(
        'update:modelValue',
        option.value
    )


    emit(
        'change',
        option.value
    )


    emit(
        'select',
        option
    )


    isSelectOpen.value =
        false

}


function removeSelectedOption(
    value
) {

    if (
        !Array.isArray(
            props.modelValue
        )
    ) {
        return
    }


    const values =
        props.modelValue.filter(
            item =>
                String(
                    item
                ) !==
                String(
                    value
                )
        )


    emit(
        'update:modelValue',
        values
    )


    emit(
        'change',
        values
    )

}


/*
|--------------------------------------------------------------------------
| Autocomplete
|--------------------------------------------------------------------------
*/

function isAutocompleteOptionSelected(
    option
) {

    if (
        !props.multiple ||
        !Array.isArray(
            props.modelValue
        )
    ) {
        return false
    }


    return props.modelValue.some(
        value =>
            String(
                value
            ) ===
            String(
                option.value
            )
    )

}


function handleAutocompleteOption(
    option
) {

    /*
     * Multiple autocomplete.
     */

    if (
        props.multiple
    ) {

        const currentValues =
            Array.isArray(
                props.modelValue
            )
                ? [
                    ...props.modelValue
                ]
                : []


        const alreadySelected =
            currentValues.some(
                value =>
                    String(
                        value
                    ) ===
                    String(
                        option.value
                    )
            )


        if (
            !alreadySelected
        ) {

            currentValues.push(
                option.value
            )

        }


        emit(
            'update:modelValue',
            currentValues
        )


        emit(
            'change',
            currentValues
        )


        emit(
            'select',
            option
        )


        /*
         * Clear only the search input.
         */

        autocompleteInput.value =
            ''


        isAutocompleteOpen.value =
            false


        /*
         * Re-open the dropdown after Vue
         * has updated the selection.
         */

        nextTick(() => {

            if (
                !props.disabled
            ) {

                isAutocompleteOpen.value =
                    true

            }

        })


        return

    }


    /*
     * Single autocomplete.
     */

    emit(
        'update:modelValue',
        option.value
    )


    emit(
        'change',
        option.value
    )


    emit(
        'select',
        option
    )


    autocompleteInput.value =
        ''


    isAutocompleteOpen.value =
        false

}


/*
|--------------------------------------------------------------------------
| Outside click
|--------------------------------------------------------------------------
*/

function handleDocumentClick(
    event
) {

    if (
        !fieldWrapper.value
    ) {
        return
    }


    if (
        fieldWrapper.value.contains(
            event.target
        )
    ) {
        return
    }


    isSelectOpen.value =
        false

    isAutocompleteOpen.value =
        false

}


/*
|--------------------------------------------------------------------------
| File
|--------------------------------------------------------------------------
*/

function handleFileChange(
    event
) {

    const input =
        event.target


    if (
        props.multiple
    ) {

        const files =
            Array.from(
                input.files ||
                []
            )


        emit(
            'update:modelValue',
            files.length
                ? files
                : null
        )


        return

    }


    emit(
        'update:modelValue',
        input.files?.[0] ||
        null
    )

}


function openFilePicker() {

    if (
        props.disabled
    ) {
        return
    }


    fileInput.value?.click()

}


/*
|--------------------------------------------------------------------------
| Tokens
|--------------------------------------------------------------------------
*/

function addToken() {

    const value =
        tokenInput.value.trim()


    if (
        !value
    ) {
        return
    }


    if (
        !tokenList.value.includes(
            value
        )
    ) {

        emit(
            'update:modelValue',
            [
                ...tokenList.value,
                value
            ]
        )

    }


    tokenInput.value =
        ''

}


function removeToken(
    index
) {

    emit(
        'update:modelValue',
        tokenList.value.filter(
            (
                _,
                itemIndex
            ) =>
                itemIndex !==
                index
        )
    )

}


function handleTokenEnter(
    event
) {

    event.preventDefault()

    addToken()

}


/*
|--------------------------------------------------------------------------
| Checkbox / Toggle
|--------------------------------------------------------------------------
*/

function handleBooleanChange(
    event
) {

    const value =
        event.target.checked


    emit(
        'update:modelValue',
        value
    )


    emit(
        'change',
        value
    )

}


onMounted(() => {

    document.addEventListener(
        'mousedown',
        handleDocumentClick
    )


    if (
        props.type ===
        'textarea'
    ) {

        resizeTextarea()

    }

})


onBeforeUnmount(() => {

    document.removeEventListener(
        'mousedown',
        handleDocumentClick
    )

})

</script>


<template>

    <div
        ref="fieldWrapper"
        class="w-full"
    >

        <!-- ===================================================== -->
        <!-- CHECKBOX -->
        <!-- ===================================================== -->

        <div
            v-if="
                type === 'checkbox'
            "
            class="
                w-full
            "
        >

            <label
                :for="id"
                class="
                    flex
                    cursor-pointer
                    items-center
                    gap-3
                "
                :class="{
                    'cursor-not-allowed opacity-50':
                        disabled
                }"
            >

                <input
                    :id="id"
                    :name="name"
                    type="checkbox"
                    :checked="
                        Boolean(
                            modelValue
                        )
                    "
                    :required="required"
                    :autofocus="autofocus"
                    :disabled="disabled"
                    :readonly="readonly"
                    class="
                        h-4
                        w-4
                        border-accent
                        text-accent
                        focus:ring-accent
                    "
                    @change="
                        handleBooleanChange
                    "
                >

                <span
                    v-if="label"
                    class="p"
                >
                    {{ label }}

                    <span
                        v-if="required"
                        class="text-accent"
                        aria-hidden="true"
                    >
                        *
                    </span>
                </span>

            </label>

        </div>


        <!-- ===================================================== -->
        <!-- TOGGLE -->
        <!-- ===================================================== -->

        <div
            v-else-if="
                type === 'toggle'
            "
            class="
                w-full
            "
        >

            <label
                :for="id"
                class="
                    flex
                    cursor-pointer
                    items-center
                    justify-between
                    gap-4
                "
                :class="{
                    'cursor-not-allowed opacity-50':
                        disabled
                }"
            >

                <span
                    v-if="label"
                    class="
                        h3
                    "
                >
                    {{ label }}

                    <span
                        v-if="required"
                        class="text-accent"
                        aria-hidden="true"
                    >
                        *
                    </span>
                </span>


                <span
                    class="
                        relative
                        inline-flex
                        h-[22px]
                        w-[42px]
                        shrink-0
                        items-center
                    "
                >

                    <input
                        :id="id"
                        :name="name"
                        type="checkbox"
                        role="switch"
                        :checked="
                            Boolean(
                                modelValue
                            )
                        "
                        :required="required"
                        :autofocus="autofocus"
                        :disabled="disabled"
                        :readonly="readonly"
                        class="
                            peer
                            absolute
                            inset-0
                            h-full
                            w-full
                            cursor-pointer
                            opacity-0
                        "
                        @change="
                            handleBooleanChange
                        "
                    >

                    <span
                        class="
                            pointer-events-none
                            absolute
                            inset-0
                            border
                            border-dark
                            bg-transparent
                            transition-colors
                            duration-200
                            peer-checked:border-accent
                            peer-checked:bg-accent
                        "
                    ></span>

                    <span
                        class="
                            pointer-events-none
                            absolute
                            left-[2px]
                            h-[18px]
                            w-[18px]
                            rounded-full
                            bg-dark
                            transition-transform
                            duration-200
                            peer-checked:translate-x-[20px]
                            peer-checked:bg-light
                        "
                    ></span>

                </span>

            </label>

        </div>


        <!-- ===================================================== -->
        <!-- LABEL -->
        <!-- ===================================================== -->

        <div
            v-else-if="
                label
            "
            class="
                mb-2
            "
        >

            <label
                :for="id"
                class="h3 block"
            >
                {{ label }}

                <span
                    v-if="required"
                    class="text-accent"
                    aria-hidden="true"
                >
                    *
                </span>
            </label>

        </div>


        <!-- ===================================================== -->
        <!-- SELECT -->
        <!-- ===================================================== -->

        <div
            v-if="
                type === 'select'
            "
            class="
                relative
            "
        >

            <button
                :id="id"
                type="button"
                :disabled="disabled"
                class="
                    p
                    flex
                    h-6
                    w-full
                    items-center
                    justify-between
                    border-0
                    border-b
                    border-dark
                    bg-transparent
                    px-0
                    py-0
                    text-left
                    outline-none
                    transition-colors
                    duration-200
                    hover:border-accent
                    focus:border-accent
                    focus:outline-none
                    focus:ring-0
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "
                :class="{
                    'border-red-600':
                        error
                }"
                @click="
                    toggleSelect
                "
                @keydown="
                    handleKeydown
                "
            >

                <span
                    class="
                        min-w-0
                        flex-1
                        truncate
                    "
                >

                    <template
                        v-if="
                            multiple
                        "
                    >

                        {{
                            selectedOptions.length
                                ? selectedOptions
                                    .map(
                                        option =>
                                            option.label
                                    )
                                    .join(', ')
                                : placeholder
                        }}

                    </template>


                    <template
                        v-else
                    >

                        {{
                            selectedLabel ||
                            placeholder
                        }}

                    </template>

                </span>


                <span
                    class="
                        ml-3
                        shrink-0
                        font-mono
                        text-xs
                    "
                >
                    +
                </span>

            </button>


            <div
                v-if="
                    isSelectOpen
                "
                class="
                    absolute
                    left-0
                    right-0
                    top-full
                    z-50
                    mt-2
                    max-h-60
                    overflow-y-auto
                    border
                    border-accent
                    bg-light
                    shadow-lg
                "
            >

                <button
                    v-for="
                        option in options
                    "
                    :key="
                        option.value
                    "
                    type="button"
                    class="
                        p
                        flex
                        w-full
                        items-center
                        justify-between
                        px-3
                        py-2
                        text-left
                        transition-colors
                        hover:bg-accent
                        hover:text-light
                    "
                    :class="{
                        'bg-accent text-light':
                            isOptionSelected(
                                option
                            )
                    }"
                    @click="
                        handleSelectOption(
                            option
                        )
                    "
                >

                    <span>
                        {{ option.label }}
                    </span>

                    <span
                        v-if="
                            isOptionSelected(
                                option
                            )
                        "
                        class="font-mono"
                    >
                        ✓
                    </span>

                </button>

            </div>


            <div
                v-if="
                    multiple &&
                    selectedOptions.length
                "
                class="
                    mt-3
                    flex
                    flex-wrap
                    gap-2
                "
            >

                <span
                    v-for="
                        option in selectedOptions
                    "
                    :key="
                        option.value
                    "
                    class="
                        inline-flex
                        items-center
                        gap-2
                        bg-accent
                        px-2
                        py-1
                        font-mono
                        text-[10px]
                        font-bold
                        uppercase
                        text-light
                    "
                >

                    {{ option.label }}

                    <button
                        type="button"
                        class="
                            leading-none
                            transition-opacity
                            hover:opacity-60
                        "
                        @click="
                            removeSelectedOption(
                                option.value
                            )
                        "
                    >
                        ×
                    </button>

                </span>

            </div>

        </div>


        <!-- ===================================================== -->
        <!-- AUTOCOMPLETE -->
        <!-- ===================================================== -->

        <div
            v-else-if="
                type === 'autocomplete'
            "
            class="
                relative
            "
        >

            <!-- Multiple selected values -->

            <div
                v-if="
                    multiple &&
                    selectedOptions.length
                "
                class="
                    mb-3
                    flex
                    flex-wrap
                    gap-2
                "
            >

                <span
                    v-for="
                        option in selectedOptions
                    "
                    :key="
                        option.value
                    "
                    class="
                        inline-flex
                        items-center
                        gap-2
                        bg-accent
                        px-2
                        py-1
                        font-mono
                        text-[10px]
                        font-bold
                        uppercase
                        text-light
                    "
                >

                    {{ option.label }}

                    <button
                        type="button"
                        class="
                            leading-none
                            transition-opacity
                            hover:opacity-60
                        "
                        @click="
                            removeSelectedOption(
                                option.value
                            )
                        "
                    >
                        ×
                    </button>

                </span>

            </div>


            <input
                :id="id"
                :name="name"
                :value="
                    multiple
                        ? autocompleteInput
                        : (
                            autocompleteInput ||
                            modelValue
                        )
                "
                type="text"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                :autofocus="autofocus"
                class="
                    p
                    box-border
                    h-6
                    w-full
                    appearance-none
                    border-0
                    border-b
                    border-dark
                    bg-transparent
                    px-0
                    py-0
                    leading-6
                    text-dark
                    outline-none
                    transition-colors
                    duration-200
                    placeholder:text-dark/30
                    focus:border-accent
                    focus:outline-none
                    focus:ring-0
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "
                :class="{
                    'border-red-600':
                        error
                }"
                @input="
                    handleInput
                "
                @keydown="
                    handleKeydown
                "
                @focus="
                    handleFocus
                "
                @blur="
                    handleBlur
                "
            >


            <!-- Loading -->

            <div
                v-if="
                    loading
                "
                class="
                    absolute
                    right-0
                    top-1/2
                    -translate-y-1/2
                "
            >
                <span
                    class="
                        font-mono
                        text-xs
                    "
                >
                    ...
                </span>
            </div>


            <!-- Suggestions -->

            <div
                v-if="
                    isAutocompleteOpen &&
                    options.length &&
                    !loading
                "
                class="
                    absolute
                    left-0
                    right-0
                    top-full
                    z-50
                    mt-2
                    max-h-60
                    overflow-y-auto
                    border
                    border-accent
                    bg-light
                    shadow-lg
                "
            >

                <button
                    v-for="
                        option in options
                    "
                    :key="
                        option.value
                    "
                    type="button"
                    class="
                        p
                        flex
                        w-full
                        items-center
                        justify-between
                        px-3
                        py-2
                        text-left
                        transition-colors
                        hover:bg-accent
                        hover:text-light
                    "
                    :class="{
                        'bg-accent text-light':
                            isAutocompleteOptionSelected(
                                option
                            )
                    }"
                    @click="
                        handleAutocompleteOption(
                            option
                        )
                    "
                >

                    <span>
                        {{ option.label }}
                    </span>

                    <span
                        v-if="
                            isAutocompleteOptionSelected(
                                option
                            )
                        "
                        class="font-mono"
                    >
                        ✓
                    </span>

                </button>

            </div>

        </div>


        <!-- ===================================================== -->
        <!-- TEXTAREA -->
        <!-- ===================================================== -->

        <textarea
            v-else-if="
                type === 'textarea'
            "
            ref="textareaRef"
            :id="id"
            :name="name"
            :value="modelValue"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :autofocus="autofocus"
            rows="1"
            class="
                p
                block
                min-h-6
                w-full
                resize-none
                appearance-none
                overflow-hidden
                border-0
                border-b
                border-dark
                bg-transparent
                px-0
                py-0
                leading-6
                text-dark
                outline-none
                transition-colors
                duration-200
                placeholder:text-dark/30
                focus:border-accent
                focus:outline-none
                focus:ring-0
                disabled:cursor-not-allowed
                disabled:opacity-50
            "
            :class="{
                'border-red-600':
                    error
            }"
            @input="
                handleTextareaInput
            "
            @keydown="
                handleKeydown
            "
            @focus="
                handleFocus
            "
            @blur="
                handleBlur
            "
        ></textarea>


        <!-- ===================================================== -->
        <!-- FILE -->
        <!-- ===================================================== -->

        <template
            v-else-if="
                type === 'file'
            "
        >

            <input
                ref="fileInput"
                :id="id"
                :name="name"
                type="file"
                :accept="fileAccept"
                :multiple="multiple"
                :disabled="disabled"
                :required="required"
                class="hidden"
                @change="
                    handleFileChange
                "
            >


            <button
                type="button"
                :disabled="disabled"
                class="
                    p
                    flex
                    h-6
                    w-full
                    items-center
                    justify-between
                    border-0
                    border-b
                    border-dark
                    bg-transparent
                    px-0
                    py-0
                    text-left
                    text-dark
                    outline-none
                    transition-colors
                    duration-200
                    hover:border-accent
                    focus:border-accent
                    focus:outline-none
                    focus:ring-0
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "
                :class="{
                    'border-red-600':
                        error
                }"
                @click="
                    openFilePicker
                "
            >

                <span
                    class="
                        min-w-0
                        flex-1
                        truncate
                    "
                >

                    {{
                        fileCount
                            ? `${fileCount} file${fileCount === 1 ? '' : 's'} selected`
                            : placeholder ||
                              'Choose file'
                    }}

                </span>


                <span
                    class="
                        ml-3
                        shrink-0
                        font-mono
                        text-xs
                    "
                >
                    +
                </span>

            </button>

        </template>


        <!-- ===================================================== -->
        <!-- TOKENS -->
        <!-- ===================================================== -->

        <div
            v-else-if="
                type === 'tokens'
            "
        >

            <div
                v-if="
                    tokenList.length
                "
                class="
                    mb-3
                    flex
                    flex-wrap
                    gap-2
                "
            >

                <span
                    v-for="
                        (
                            token,
                            index
                        )
                        in tokenList
                    "
                    :key="
                        `${token}-${index}`
                    "
                    class="
                        inline-flex
                        items-center
                        gap-2
                        bg-accent
                        px-2
                        py-1
                        font-mono
                        text-[10px]
                        font-bold
                        uppercase
                        leading-none
                        text-light
                    "
                >

                    {{ token }}


                    <button
                        type="button"
                        aria-label="Remove"
                        class="
                            leading-none
                            transition-opacity
                            hover:opacity-60
                        "
                        @click="
                            removeToken(
                                index
                            )
                        "
                    >
                        ×
                    </button>

                </span>

            </div>


            <input
                :id="id"
                v-model="
                    tokenInput
                "
                :name="name"
                type="text"
                :placeholder="
                    placeholder ||
                    'Type and press Enter'
                "
                :disabled="disabled"
                class="
                    p
                    box-border
                    h-6
                    w-full
                    appearance-none
                    border-0
                    border-b
                    border-dark
                    bg-transparent
                    px-0
                    py-0
                    leading-6
                    text-dark
                    outline-none
                    transition-colors
                    duration-200
                    placeholder:text-dark/30
                    focus:border-accent
                    focus:outline-none
                    focus:ring-0
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "
                :class="{
                    'border-red-600':
                        error
                }"
                @keydown.enter="
                    handleTokenEnter
                "
                @blur="
                    addToken
                "
            >

        </div>


        <!-- ===================================================== -->
        <!-- STANDARD INPUT -->
        <!-- ===================================================== -->

        <div
            v-else-if="
                ![
                    'checkbox',
                    'toggle',
                    'select',
                    'autocomplete',
                    'textarea',
                    'file',
                    'tokens'
                ].includes(
                    type
                )
            "
            class="
                relative
                w-full
            "
        >

            <input
                :id="id"
                :name="name"
                :type="type"
                :value="
                    typeof modelValue === 'boolean'
                        ? ''
                        : modelValue
                "
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                :autofocus="autofocus"
                :class="[
                    'p',
                    'box-border',
                    'h-6',
                    'w-full',
                    'appearance-none',
                    'border-0',
                    'border-b',
                    'border-dark',
                    'bg-transparent',
                    'px-0',
                    'py-0',
                    'leading-6',
                    'text-dark',
                    'outline-none',
                    'transition-colors',
                    'duration-200',
                    'placeholder:text-dark/30',
                    'focus:border-accent',
                    'focus:outline-none',
                    'focus:ring-0',
                    'disabled:cursor-not-allowed',
                    'disabled:opacity-50',
                    {
                        'pr-16':
                            suffix
                    },
                    {
                        'border-red-600':
                            error
                    }
                ]"
                @input="
                    handleInput
                "
                @keydown="
                    handleKeydown
                "
                @focus="
                    handleFocus
                "
                @blur="
                    handleBlur
                "
            >


            <!-- Suffix -->

            <span
                v-if="
                    suffix
                "
                class="
                    pointer-events-none
                    absolute
                    inset-y-0
                    right-0
                    flex
                    items-center
                    p
                    text-dark/50
                "
            >
                {{ suffix }}
            </span>

        </div>


        <!-- ===================================================== -->
        <!-- COMMON ERROR -->
        <!-- ===================================================== -->

        <p
            v-if="
                error
            "
            class="
                p
                mt-2
                text-red-600
            "
        >
            {{ error }}
        </p>

    </div>

</template>
