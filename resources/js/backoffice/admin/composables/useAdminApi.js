import axios from 'axios'

const api = axios.create({
    baseURL: '/admin/client-portal/api',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

export function validationErrors(error) {
    return error.response?.status === 422 ? error.response.data.errors ?? {} : {}
}

export function errorMessage(error) {
    return error.response?.data?.message || 'The request could not be completed.'
}

export default api