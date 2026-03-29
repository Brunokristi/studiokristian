<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    text: string;
    tag?: string;
    minWeight?: number;
    maxWeight?: number;
    minSpacing?: number;
    maxSpacing?: number;
    minDuration?: number;
    maxDuration?: number;
    minDelay?: number;
    maxDelay?: number;
}

const props = withDefaults(defineProps<Props>(), {
    tag: 'span',
    minWeight: 400,
    maxWeight: 800,
    minSpacing: 0,
    maxSpacing: 0.08,
    minDuration: 1800,
    maxDuration: 4200,
    minDelay: 0,
    maxDelay: 2600,
});

const randomBetween = (min: number, max: number) =>
    Math.random() * (max - min) + min;

const tokens = computed(() =>
    (props.text.match(/\S+|\s+/g) ?? []).map((token, index) => {
        const isWord = /[\p{L}\p{N}]/u.test(token);

        return {
            token,
            index,
            isWord,
            duration: isWord ? randomBetween(props.minDuration, props.maxDuration) : 0,
            delay: isWord ? randomBetween(props.minDelay, props.maxDelay) : 0,
            weight: isWord ? randomBetween(props.minWeight, props.maxWeight) : props.minWeight,
            spacing: isWord ? randomBetween(props.minSpacing, props.maxSpacing) : props.minSpacing,
        };
    })
);
</script>

<template>
    <component :is="tag" class="animated-mono-text font-mono">
        <span
            v-for="item in tokens"
            :key="`${item.token}-${item.index}`"
            class="animated-mono-text__token"
            :class="{ 'animated-mono-text__token--word': item.isWord }"
            :style="
                item.isWord
                    ? {
                        '--word-duration': `${item.duration}ms`,
                        '--word-delay': `${item.delay}ms`,
                        '--min-wght': props.minWeight,
                        '--max-wght': item.weight,
                        '--min-spacing': `${props.minSpacing}em`,
                        '--max-spacing': `${item.spacing}em`,
                    }
                    : undefined
            "
        >
            {{ item.token }}
        </span>
    </component>
</template>

<style scoped>
.animated-mono-text {
    display: inline;
}

.animated-mono-text__token {
    display: inline;
}

.animated-mono-text__token--word {
    display: inline-block;
    font-variation-settings: "wght" var(--min-wght);
    letter-spacing: var(--min-spacing);
    animation-name: mono-word-drift;
    animation-duration: var(--word-duration);
    animation-delay: var(--word-delay);
    animation-timing-function: ease-in-out;
    animation-iteration-count: infinite;
    animation-direction: alternate;
    animation-fill-mode: both;
    will-change: font-variation-settings, letter-spacing;
}

@keyframes mono-word-drift {
    0% {
        font-variation-settings: "wght" var(--min-wght);
        letter-spacing: var(--min-spacing);
    }

    100% {
        font-variation-settings: "wght" var(--max-wght);
        letter-spacing: var(--max-spacing);
    }
}

@media (prefers-reduced-motion: reduce) {
    .animated-mono-text__token--word {
        animation: none;
    }
}
</style>