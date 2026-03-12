<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  heading: {
    type: String,
    default: '',
  },
  text: {
    type: String,
    default: '',
  },
  bgColor: {
    type: String,
    default: '',
  },
  image: {
    type: String,
    default: '',
  },
  link: {
    type: String,
    default: '',
  },
  textColor: {
    type: String,
    default: 'text-dark',
  },
})

const tag = computed(() => (props.link ? RouterLink : 'article'))
const hoverBgColor = computed(() => props.bgColor || 'bg-accent')
</script>

<template>
  <component
    :is="tag"
    :to="link || undefined"
    class="group relative min-h-[250px] p-6 overflow-hidden flex flex-col justify-between bg-light"
    :class="[link ? 'cursor-pointer' : '']"
  >
    <div
      class="absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
      :class="hoverBgColor"
    ></div>

    <div
      class="relative z-10 transition-colors duration-300"
      :class="[textColor, 'group-hover:text-light']"
    >
      <h3 class="h3 mb-6">
        {{ heading }}
      </h3>

      <p class="p">
        {{ text }}
      </p>
    </div>

    <div
    v-if="image"
    class="relative z-10 mt-8 flex justify-center opacity-0 transition-opacity duration-300 group-hover:opacity-100"
    >
    <img :src="image" alt="" class="max-h-6 object-contain" />
    </div>
  </component>
</template>