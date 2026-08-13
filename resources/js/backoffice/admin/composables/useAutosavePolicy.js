import {
    computed,
    ref
} from 'vue'


const STORAGE_KEY =
    'studio-kristian-admin-autosave'


const autosaveEnabled =
    ref(load())


const autosaveStatus =
    ref('idle')


const lastSavedAt =
    ref(null)


function load() {
    try {
        const value =
            localStorage.getItem(
                STORAGE_KEY
            )

        if (
            value === null
        ) {
            return true
        }

        return value === 'true'
    } catch {
        return true
    }
}


function setEnabled(
    value
) {
    autosaveEnabled.value =
        Boolean(value)

    try {
        localStorage.setItem(
            STORAGE_KEY,
            String(
                autosaveEnabled.value
            )
        )
    } catch {
        // ignore storage issues
    }
}


export default function useAutosavePolicy() {
    return {
        enabled:
            computed(() =>
                autosaveEnabled.value
            ),

        status:
            computed(() =>
                autosaveStatus.value
            ),

        lastSavedAt:
            computed(() =>
                lastSavedAt.value
            ),

        setEnabled,

        setStatus(
            value
        ) {
            autosaveStatus.value =
                String(
                    value || 'idle'
                )
        },

        setLastSavedAt(
            value =
                new Date()
        ) {
            lastSavedAt.value =
                value
        },

        toggle() {
            setEnabled(
                !autosaveEnabled.value
            )
        }
    }
}
