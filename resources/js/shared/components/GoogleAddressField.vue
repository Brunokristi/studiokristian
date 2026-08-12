<script setup>
import {
    importLibrary,
    setOptions
} from '@googlemaps/js-api-loader'
import {
    onBeforeUnmount,
    onMounted,
    ref,
    watch
} from 'vue'

import FormField from './FormField.vue'


const props = defineProps({
    id: {
        type: String,
        default: 'company-address'
    },
    modelValue: {
        type: String,
        default: ''
    },
    label: {
        type: String,
        default: 'Company address'
    },
    placeholder: {
        type: String,
        default: 'Start typing an address'
    },
    error: {
        type: String,
        default: ''
    },
    required: {
        type: Boolean,
        default: false
    }
})


const emit = defineEmits([
    'update:modelValue'
])


const apiKey =
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY ||
    ''


const autocompleteHost =
    ref(null)


const autocomplete =
    ref(null)


const loading =
    ref(Boolean(apiKey))


const loadFailed =
    ref(false)


async function handlePlaceSelect(
    event
) {
    const place =
        event.placePrediction.toPlace()


    await place.fetchFields({
        fields: [
            'formattedAddress'
        ]
    })


    emit(
        'update:modelValue',
        place.formattedAddress ||
        ''
    )
}


function handleInput(
    event
) {
    emit(
        'update:modelValue',
        event.target.value ||
        ''
    )
}


onMounted(async () => {
    if (
        !apiKey
    ) {
        loading.value =
            false


        return
    }


    try {
        setOptions({
            key: apiKey,
            v: 'weekly',
            language: 'sk',
            region: 'SK'
        })


        const {
            PlaceAutocompleteElement
        } = await importLibrary(
            'places'
        )


        const element =
            new PlaceAutocompleteElement()


        element.id =
            props.id

        element.placeholder =
            props.placeholder

        element.value =
            props.modelValue

        element.addEventListener(
            'gmp-select',
            handlePlaceSelect
        )

        element.addEventListener(
            'input',
            handleInput
        )


        autocompleteHost.value?.appendChild(
            element
        )

        autocomplete.value =
            element
    } catch (
        error
    ) {
        console.error(
            'Google address autocomplete could not be loaded.',
            error
        )

        loadFailed.value =
            true
    } finally {
        loading.value =
            false
    }
})


onBeforeUnmount(() => {
    autocomplete.value?.removeEventListener(
        'gmp-select',
        handlePlaceSelect
    )

    autocomplete.value?.removeEventListener(
        'input',
        handleInput
    )
})


watch(
    () => props.modelValue,
    value => {
        if (
            autocomplete.value &&
            autocomplete.value.value !== value
        ) {
            autocomplete.value.value =
                value
        }
    }
)
</script>


<template>
    <div class="w-full">
        <FormField
            v-if="!apiKey || loadFailed"
            :id="id"
            :model-value="modelValue"
            name="address"
            type="text"
            :label="label"
            :placeholder="placeholder"
            :error="error"
            :required="required"
            @update:model-value="emit('update:modelValue', $event)"
        />


        <template v-else>
            <label
                :for="id"
                class="h3 mb-2 block"
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


            <div
                v-if="loading"
                class="p h-6 border-b border-dark text-dark/40"
            >
                Loading address search...
            </div>


            <div
                ref="autocompleteHost"
                class="google-address-field"
                :class="{
                    'google-address-field--error': error
                }"
            />


            <p
                v-if="error"
                class="p mt-2 text-red-600"
            >
                {{ error }}
            </p>
        </template>
    </div>
</template>


<style scoped>
.google-address-field :deep(gmp-place-autocomplete) {
    width: 100%;
    color-scheme: light;
    --gmpx-color-primary: #ee4e2c;
    --gmpx-color-surface: transparent;
    --gmpx-font-family-base: inherit;
    --gmpx-font-size-base: 1rem;
}

.google-address-field--error {
    border-bottom-color: rgb(220 38 38);
}
</style>