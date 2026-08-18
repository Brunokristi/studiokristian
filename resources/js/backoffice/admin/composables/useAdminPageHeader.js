import {
    onBeforeUnmount,
    reactive,
    toValue,
    watchEffect
} from 'vue'


const header =
    reactive({
        title: '',
        eyebrow: '',
        description: '',
        breadcrumbs: []
    })


function resetHeader() {
    header.title = ''
    header.eyebrow = ''
    header.description = ''
    header.breadcrumbs = []
}


export function useAdminPageHeader(
    values = {}
) {
    watchEffect(() => {
        header.title =
            toValue(values.title) || ''

        header.eyebrow =
            toValue(values.eyebrow) || ''

        header.description =
            toValue(values.description) || ''

        header.breadcrumbs =
            toValue(values.breadcrumbs) || []
    })

    onBeforeUnmount(resetHeader)

    return {
        header
    }
}