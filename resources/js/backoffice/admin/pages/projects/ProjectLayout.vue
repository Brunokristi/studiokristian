<script setup>
import {
    computed,
    onBeforeUnmount,
    provide,
    ref,
    watch
} from 'vue'

import {
    RouterView,
    useRoute
} from 'vue-router'

import api, {
    errorMessage
} from '../../composables/useAdminApi'

import {
    activeProjectContext,
    projectContextKey
} from '../../composables/useProjectContext'


const route =
    useRoute()


const projectId =
    computed(() =>
        String(
            route.params.id ||
            ''
        )
    )


const project =
    ref(null)


const loading =
    ref(false)


const error =
    ref('')


async function loadProject() {
    if (!projectId.value) {
        project.value =
            null

        return
    }


    loading.value =
        true

    error.value =
        ''


    try {
        const response =
            await api.get(
                `/projects/${projectId.value}`
            )


        project.value =
            response.data?.data ||
            null

        activeProjectContext.id =
            projectId.value

        activeProjectContext.project =
            project.value
    } catch (exception) {
        error.value =
            errorMessage(
                exception
            )
    } finally {
        loading.value =
            false
    }
}


provide(
    projectContextKey,
    {
        projectId,
        project,
        loading,
        error,
        reloadProject: loadProject,
        setProject(value) {
            project.value =
                value

            activeProjectContext.project =
                value
        }
    }
)


watch(
    projectId,
    loadProject,
    {
        immediate: true
    }
)


onBeforeUnmount(() => {
    if (
        activeProjectContext.id ===
        projectId.value
    ) {
        activeProjectContext.id =
            ''

        activeProjectContext.project =
            null
    }
})
</script>


<template>
    <div class="w-full">
        <p
            v-if="loading"
            class="p uppercase text-dark"
        >
            Loading project...
        </p>

        <p
            v-else-if="error"
            class="p text-red-600"
        >
            {{ error }}
        </p>

        <RouterView v-else />
    </div>
</template>