import {
    inject,
    reactive
} from 'vue'


export const projectContextKey =
    Symbol('project-context')


export const activeProjectContext =
    reactive({
        id: '',
        project: null
    })


export function useProjectContext() {
    const context =
        inject(
            projectContextKey,
            null
        )


    if (!context) {
        throw new Error(
            'Project context is only available inside ProjectLayout.'
        )
    }


    return context
}


export function useOptionalProjectContext() {
    return inject(
        projectContextKey,
        null
    )
}