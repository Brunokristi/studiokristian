<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref
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


const fileInput =
    ref(null)


const textareaRef =
    ref(null)


const fieldWrapper =
    ref(null)


const tokenInput =
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


const filteredOptions =
    computed(() => props.options)


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


function handleInput(
    event
) {
    const value =
        event.target.value


    emit(
        'update:modelValue',
        value
    )


    if (
        props.type ===
        'autocomplete'
    ) {
        isAutocompleteOpen.value =
            true


        emit(
            'search',
            value
        )
    }
}


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


function handleBlur(
    event
) {
    emit(
        'blur',
        event
    )
}


function handleTextareaInput(
    event
) {
    emit(
        'update:modelValue',
        event.target.value
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


function toggleSelect() {
    if (
        props.disabled
    ) {
        return
    }


    isSelectOpen.value =
        !isSelectOpen.value
}


function handleSelectOption(
    value
) {
    emit(
        'update:modelValue',
        value
    )


    emit(
        'select',
        value
    )


    isSelectOpen.value =
        false
}


function handleAutocompleteOption(
    option
) {
    emit(
        'update:modelValue',
        option.value
    )


    emit(
        'select',
        option
    )


    isAutocompleteOpen.value =
        false
}


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


onMounted(() => {
    document.addEventListener(
        'mousedown',
        handleDocumentClick
    )
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
        <!-- Label -->
        <label
            v-if="label"
            :for="id"
            class="
                h3
                mb-2
                block
            "
        >
            {{ label }}


            <span
                v-if="required"
                class="text-accent p"
                aria-hidden="true"
            >
                *
            </span>
        </label>


        <!-- Text / Search / Email / Password -->
        <input
            v-if="
                type === 'text' ||
                type === 'search' ||
                type === 'email' ||
                type === 'password'
            "
            :id="id"
            :name="name"
            :type="type"
            :value="
                typeof modelValue === 'string' ||
                typeof modelValue === 'number'
                    ? modelValue
                    : ''
            "
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            :autofocus="autofocus"
            :disabled="disabled"
            :readonly="readonly"
            :aria-invalid="
                error
                    ? 'true'
                    : undefined
            "
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


        <!-- Autocomplete -->
        <div
            v-else-if="
                type === 'autocomplete'
            "
            class="relative"
        >
            <input
                :id="id"
                :name="name"
                type="text"
                :value="
                    typeof modelValue === 'string' ||
                    typeof modelValue === 'number'
                        ? modelValue
                        : ''
                "
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :required="required"
                :autofocus="autofocus"
                :disabled="disabled"
                :readonly="readonly"
                :aria-invalid="
                    error
                        ? 'true'
                        : undefined
                "
                :aria-expanded="
                    isAutocompleteOpen
                        ? 'true'
                        : 'false'
                "
                role="combobox"
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


            <div
                v-if="
                    isAutocompleteOpen &&
                    (loading || filteredOptions.length)
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
                    border-dark
                    bg-light
                "
                role="listbox"
            >
                <p
                    v-if="loading"
                    class="p px-4 py-3 text-dark/50"
                >
                    Searching addresses...
                </p>


                <button
                    v-else
                    v-for="
                        option
                        in filteredOptions
                    "
                    :key="
                        option.value
                    "
                    type="button"
                    class="
                        p
                        block
                        w-full
                        border-0
                        bg-light
                        px-4
                        py-3
                        text-left
                        text-dark
                        transition-colors
                        duration-200
                        hover:bg-dark
                        hover:text-light
                    "
                    role="option"
                    @mousedown.prevent="
                        handleAutocompleteOption(
                            option
                        )
                    "
                >
                    {{ option.label }}
                </button>
            </div>
        </div>


        <!-- Textarea -->
        <textarea
            v-else-if="
                type === 'textarea'
            "
            :id="id"
            ref="textareaRef"
            :name="name"
            :value="
                typeof modelValue === 'string' ||
                typeof modelValue === 'number'
                    ? modelValue
                    : ''
            "
            :placeholder="placeholder"
            :required="required"
            :autofocus="autofocus"
            :disabled="disabled"
            :readonly="readonly"
            :aria-invalid="
                error
                    ? 'true'
                    : undefined
            "
            rows="1"
            class="
                p
                box-border
                w-full
                resize-none
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
        />


        <!-- Select -->
        <div
            v-else-if="
                type === 'select'
            "
            class="relative"
        >
            <button
                :id="id"
                type="button"
                :disabled="disabled"
                class="
                    p
                    box-border
                    flex
                    h-6
                    w-full
                    appearance-none
                    items-center
                    justify-between
                    border-0
                    border-b
                    border-dark
                    bg-transparent
                    px-0
                    py-0
                    leading-6
                    text-left
                    text-dark
                    outline-none
                    transition-colors
                    duration-200
                    hover:border-accent
                    focus:border-accent
                    focus:ring-0
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "
                :class="{
                    'border-red-600':
                        error
                }"
                :aria-expanded="
                    isSelectOpen
                        ? 'true'
                        : 'false'
                "
                @click="
                    toggleSelect
                "
            >
                <span
                    class="
                        min-w-0
                        flex-1
                        truncate
                    "
                    :class="{
                        'text-dark/30':
                            !selectedLabel
                    }"
                >
                    {{
                        selectedLabel ||
                        placeholder ||
                        'Select an option'
                    }}
                </span>


                <span
                    class="
                        ml-3
                        shrink-0
                        font-mono
                        text-xs
                        leading-none
                        transition-transform
                        duration-200
                    "
                    :class="{
                        'rotate-180':
                            isSelectOpen
                    }"
                    aria-hidden="true"
                >
                    ↓
                </span>
            </button>


            <!-- Select options -->
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
                    border-dark
                    bg-light
                "
                role="listbox"
            >
                <button
                    v-for="
                        option
                        in options
                    "
                    :key="
                        option.value
                    "
                    type="button"
                    class="
                        p
                        block
                        w-full
                        border-0
                        bg-light
                        px-4
                        py-3
                        text-left
                        text-dark
                        transition-colors
                        duration-200
                        hover:bg-dark
                        hover:text-light
                    "
                    role="option"
                    @mousedown.prevent="
                        handleSelectOption(
                            option.value
                        )
                    "
                >
                    {{ option.label }}
                </button>
            </div>
        </div>


        <!-- File -->
        <template
            v-else-if="
                type === 'file'
            "
        >
            <input
                :id="id"
                ref="fileInput"
                :name="name"
                type="file"
                :multiple="multiple"
                :accept="fileAccept"
                :required="required"
                :disabled="disabled"
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
                    box-border
                    flex
                    h-6
                    w-full
                    appearance-none
                    items-center
                    justify-between
                    border-0
                    border-b
                    border-dark
                    bg-transparent
                    px-0
                    py-0
                    leading-6
                    text-left
                    text-dark
                    outline-none
                    transition-colors
                    duration-200
                    hover:border-accent
                    focus:border-accent
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


        <!-- Tokens -->
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


        <!-- Error -->
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