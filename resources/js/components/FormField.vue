<script setup lang="ts">
import {
    computed,
    nextTick,
    ref
} from 'vue'


interface Option {
    label: string
    value: string | number
}


interface Props {
    id?: string
    name?: string
    label?: string
    type?:
        | 'email'
        | 'text'
        | 'password'
        | 'textarea'
        | 'select'
        | 'file'
        | 'tokens'
    modelValue?:
        | string
        | number
        | string[]
        | File
        | File[]
        | null
    error?: string
    placeholder?: string
    autocomplete?: string
    options?: Option[]
    multiple?: boolean
    fileAccept?: string
    required?: boolean
    autofocus?: boolean
    disabled?: boolean
    readonly?: boolean
}


const props = withDefaults(
    defineProps<Props>(),
    {
        type: 'text',
        modelValue: '',
        options: () => [],
        multiple: false,
        required: false,
        autofocus: false,
        disabled: false,
        readonly: false
    }
)


const emit = defineEmits<{
    'update:modelValue': [
        value:
            | string
            | number
            | string[]
            | File
            | File[]
            | null
    ]
    keydown: [event: KeyboardEvent]
    focus: [event: FocusEvent]
    blur: [event: FocusEvent]
}>()


const isSelectOpen =
    ref(false)


const fileInput =
    ref<HTMLInputElement | null>(null)


const textareaRef =
    ref<HTMLTextAreaElement | null>(null)


const tokenInput =
    ref('')


const selectedLabel =
    computed(() => {
        const selected =
            props.options.find(
                (option) =>
                    option.value ===
                    props.modelValue
            )


        return (
            selected?.label ||
            ''
        )
    })


const fileCount =
    computed(() => {
        if (
            Array.isArray(
                props.modelValue
            )
        ) {
            return (
                props.modelValue.length
            )
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
            (
                item
            ): item is string =>
                typeof item ===
                'string'
        )
    })


function handleInput(
    event: Event
) {
    const input =
        event.target as HTMLInputElement


    emit(
        'update:modelValue',
        input.value
    )
}


function handleKeydown(
    event: KeyboardEvent
) {
    emit(
        'keydown',
        event
    )
}


function handleTextareaInput(
    event: Event
) {
    const textarea =
        event.target as HTMLTextAreaElement


    emit(
        'update:modelValue',
        textarea.value
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


function handleSelectOption(
    value: string | number
) {
    emit(
        'update:modelValue',
        value
    )


    isSelectOpen.value =
        false
}


function handleFileChange(
    event: Event
) {
    const input =
        event.target as HTMLInputElement


    if (props.multiple) {
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


function addToken() {
    const value =
        tokenInput.value.trim()


    if (!value) {
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
    index: number
) {
    emit(
        'update:modelValue',
        tokenList.value.filter(
            (_, itemIndex) =>
                itemIndex !== index
        )
    )
}


function handleTokenEnter(
    event: KeyboardEvent
) {
    event.preventDefault()


    addToken()
}
</script>


<template>
    <div class="w-full">
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
                class="text-accent"
                aria-hidden="true"
            >
                *
            </span>
        </label>


        <!-- Text / Email / Password -->
        <input
            v-if="
                type === 'text' ||
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
                w-full
                border-0
                border-b
                border-dark/30
                bg-transparent
                px-0
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
            @input="handleInput"
            @keydown="handleKeydown"
            @focus="handleFocus"
            @blur="handleBlur"
        >


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
            rows="1"
            class="
                p
                min-h-[3rem]
                w-full
                resize-none
                overflow-hidden
                border-0
                border-b
                border-dark/30
                bg-transparent
                px-0
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
            @input="handleTextareaInput"
            @keydown="handleKeydown"
            @focus="handleFocus"
            @blur="handleBlur"
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
                    flex
                    w-full
                    items-center
                    justify-between
                    border-b
                    border-dark/30
                    bg-transparent
                    px-0
                    text-left
                    text-dark
                    transition-colors
                    duration-200
                    hover:border-accent
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "
                @click="
                    isSelectOpen =
                        !isSelectOpen
                "
            >
                <span
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


                <i
                    class="
                        bi
                        bi-chevron-down
                        text-xs
                        transition-transform
                        duration-200
                    "
                    :class="{
                        'rotate-180':
                            isSelectOpen
                    }"
                />
            </button>


            <div
                v-if="isSelectOpen"
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
                    border-dark/15
                    bg-light
                "
            >
                <button
                    v-for="
                        option
                        in options
                    "
                    :key="option.value"
                    type="button"
                    class="
                        p
                        block
                        w-full
                        px-4
                        text-left
                        text-dark
                        transition-colors
                        hover:bg-dark
                        hover:text-light
                    "
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
                @change="handleFileChange"
            >


            <button
                type="button"
                :disabled="disabled"
                class="
                    p
                    flex
                    w-full
                    items-center
                    justify-between
                    border-b
                    border-dark/30
                    text-left
                    text-dark
                    transition-colors
                    hover:border-accent
                    disabled:cursor-not-allowed
                    disabled:opacity-50
                "
                @click="
                    fileInput?.click()
                "
            >
                <span>
                    {{
                        fileCount
                            ? `${fileCount} file${fileCount === 1 ? '' : 's'} selected`
                            : placeholder ||
                                'Choose file'
                    }}
                </span>


                <i class="bi bi-paperclip" />
            </button>
        </template>


        <!-- Tokens -->
        <div
            v-else-if="
                type === 'tokens'
            "
        >
            <div
                v-if="tokenList.length"
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
                        px-3
                        py-1.5
                        font-mono
                        text-xs
                        font-bold
                        text-dark
                    "
                >
                    {{ token }}


                    <button
                        type="button"
                        aria-label="Remove"
                        @click="
                            removeToken(
                                index
                            )
                        "
                    >
                        <i class="bi bi-x-lg" />
                    </button>
                </span>
            </div>


            <input
                :id="id"
                v-model="tokenInput"
                :name="name"
                type="text"
                :placeholder="
                    placeholder ||
                    'Type and press Enter'
                "
                :disabled="disabled"
                class="
                    p
                    w-full
                    border-0
                    border-b
                    border-dark/30
                    bg-transparent
                    px-0
                    text-dark
                    outline-none
                    placeholder:text-dark/30
                    focus:border-accent
                    focus:ring-0
                "
                @keydown.enter="
                    handleTokenEnter
                "
                @blur="addToken"
            >
        </div>


        <!-- Error -->
        <p
            v-if="error"
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