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
        breadcrumbs: [],
        homeUrl: '/client'
    })


function resetHeader() {
    header.title = ''
    header.eyebrow = ''
    header.breadcrumbs = []
    header.homeUrl = '/client'
}


export function useClientPageHeader(
    values = {}
) {
    watchEffect(() => {
        header.title =
            toValue(values.title) || ''

        header.eyebrow =
            toValue(values.eyebrow) || ''

        header.breadcrumbs =
            toValue(values.breadcrumbs) || []

        header.homeUrl =
            toValue(values.homeUrl) || '/client'
    })

    onBeforeUnmount(resetHeader)

    return {
        header
    }
}