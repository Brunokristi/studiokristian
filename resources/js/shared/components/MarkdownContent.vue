<script setup>
import { computed } from 'vue'
import { renderMarkdown } from '@shared/utils/markdown.js'


const props = defineProps({
    source: {
        type: String,
        default: ''
    }
})


const html = computed(() => renderMarkdown(props.source))
</script>


<template>
    <!-- Sanitized with DOMPurify in renderMarkdown(). -->
    <div
        class="markdown-content p text-dark"
        v-html="html"
    />
</template>


<style scoped>
.markdown-content :deep(> * + *) {
    margin-top: 0.5rem;
}

.markdown-content :deep(h1),
.markdown-content :deep(h2),
.markdown-content :deep(h3),
.markdown-content :deep(h4),
.markdown-content :deep(h5),
.markdown-content :deep(h6) {
    font-family: ui-monospace, 'Space Mono', monospace;
    font-weight: 700;
    text-transform: uppercase;
    line-height: 1.3;
}

.markdown-content :deep(h1) {
    font-size: 1rem;
}

.markdown-content :deep(h2) {
    font-size: 0.9375rem;
}

.markdown-content :deep(h3),
.markdown-content :deep(h4),
.markdown-content :deep(h5),
.markdown-content :deep(h6) {
    font-size: 0.875rem;
}

.markdown-content :deep(ul) {
    list-style: disc;
    padding-left: 1.25rem;
}

.markdown-content :deep(ol) {
    list-style: decimal;
    padding-left: 1.25rem;
}

.markdown-content :deep(li + li) {
    margin-top: 0.125rem;
}

.markdown-content :deep(strong) {
    font-weight: 700;
}

.markdown-content :deep(em) {
    font-style: italic;
}

.markdown-content :deep(a) {
    text-decoration: underline;
    text-underline-offset: 2px;
}

.markdown-content :deep(blockquote) {
    border-left: 2px solid currentColor;
    padding-left: 0.75rem;
    opacity: 0.75;
}

.markdown-content :deep(code) {
    font-family: ui-monospace, 'Space Mono', monospace;
    font-size: 0.8125rem;
}

.markdown-content :deep(pre) {
    overflow-x: auto;
    border: 1px solid currentColor;
    padding: 0.5rem 0.75rem;
}

.markdown-content :deep(hr) {
    border-top: 1px solid currentColor;
    opacity: 0.3;
}

.markdown-content :deep(table) {
    border-collapse: collapse;
}

.markdown-content :deep(th),
.markdown-content :deep(td) {
    border: 1px solid currentColor;
    padding: 0.25rem 0.5rem;
    text-align: left;
}
</style>
