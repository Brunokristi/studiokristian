import { onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { errorMessage } from './useAdminApi'

export function useServerTable(endpoint, defaults = {}) {
    const route = useRoute()
    const router = useRouter()
    const rows = ref([])
    const meta = ref(null)
    const loading = ref(true)
    const error = ref('')
    const state = reactive({
        search: route.query.search ?? '', status: route.query.status ?? '', sort: route.query.sort ?? defaults.sort ?? 'updated_at',
        direction: route.query.direction ?? defaults.direction ?? 'desc', page: Number(route.query.page ?? 1), per_page: 25,
        ...Object.fromEntries(Object.keys(defaults).filter(key => !['sort', 'direction'].includes(key)).map(key => [key, route.query[key] ?? defaults[key]])),
    })
    let timer

    async function load() {
        loading.value = true
        error.value = ''
        try {
            const params = Object.fromEntries(Object.entries(state).filter(([, value]) => value !== '' && value !== null))
            const response = await api.get(endpoint, { params })
            rows.value = response.data.data
            meta.value = response.data.meta
            const query = Object.fromEntries(Object.entries(params).filter(([key, value]) => key !== 'per_page' && value !== defaults[key]))
            router.replace({ query })
        } catch (requestError) {
            error.value = errorMessage(requestError)
        } finally {
            loading.value = false
        }
    }

    function sortBy(column) {
        if (state.sort === column) state.direction = state.direction === 'asc' ? 'desc' : 'asc'
        else { state.sort = column; state.direction = 'asc' }
    }

    watch(state, () => { clearTimeout(timer); timer = setTimeout(() => { if (state.page < 1) state.page = 1; load() }, 280) }, { deep: true, immediate: true })
    onBeforeUnmount(() => clearTimeout(timer))

    return { rows, meta, loading, error, state, load, sortBy }
}