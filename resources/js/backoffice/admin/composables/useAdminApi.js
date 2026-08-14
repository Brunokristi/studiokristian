import axios from 'axios'


function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || ''
}


function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)

    if (!match?.[1]) {
        return ''
    }

    try {
        return decodeURIComponent(match[1])
    } catch {
        return match[1]
    }
}

const api = axios.create({
    baseURL: '/admin/client-portal/api',
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
        'X-XSRF-TOKEN': xsrfToken(),
    },
})


api.interceptors.request.use(config => {
    config.headers = config.headers || {}
    config.headers['X-CSRF-TOKEN'] = csrfToken()

    const xsrf = xsrfToken()

    if (xsrf) {
        config.headers['X-XSRF-TOKEN'] = xsrf
    }

    return config
})

export function validationErrors(error) {
    return error.response?.status === 422 ? error.response.data.errors ?? {} : {}
}

export function errorMessage(error) {
    if (error.response?.status === 419) {
        return 'Your session expired. Refresh the page and sign in again.'
    }

    return error.response?.data?.message || 'The request could not be completed.'
}

export default api