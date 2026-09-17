<script setup>
import ProjectWorkspaceOperations from './ProjectWorkspaceOperations.vue'


defineProps({
    id: {
        type: String,
        default: ''
    }
})
</script>


<template>
    <ProjectWorkspaceOperations
        :id="id"
        section="files"
    />
</template>