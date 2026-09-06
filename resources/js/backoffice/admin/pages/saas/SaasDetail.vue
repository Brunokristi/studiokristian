<script setup>

import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch
} from 'vue'

import {
    useRouter
} from 'vue-router'

import api, {
    errorMessage,
    validationErrors
} from '../../composables/useAdminApi'

import {
    useServerTable
} from '../../composables/useServerTable'

import AdminDataTable from '@shared/components/DataTable.vue'
import AdminConfirmDialog from '../../../../shared/components/ConfirmDialog.vue'
import Button from '@shared/components/Button.vue'
import FormField from '@shared/components/FormField.vue'
import Modal from '@shared/components/Modal.vue'
import Tag from '@shared/components/Tag.vue'
import Toast from '@shared/components/Toast.vue'

import { useAdminPageHeader } from '../../composables/useAdminPageHeader'
import useAutosavePolicy from '../../composables/useAutosavePolicy'


const props = defineProps({
    id: {
        type: String,
        default: ''
    }
})


const router = useRouter()

const {
    enabled: autosaveEnabled,
    setLastSavedAt,
    setStatus
} = useAutosavePolicy()


const loading = ref(true)
const saving = ref(false)
const error = ref('')
const errors = ref({})
const showErrorToast = ref(false)
const showSuccessToast = ref(false)
const deletingProject = ref(false)
const showDeleteConfirm = ref(false)
const deletingPlan = ref(null)
const showPlanModal = ref(false)
const showPriceModal = ref(false)
const showCredentialModal = ref(false)
const showTokenModal = ref(false)
const credentialName = ref('')
const generatedProjectToken = ref('')
const tokenCopied = ref(false)
const projectCredentials = ref([])
const credentialLoading = ref(false)
const credentialSaving = ref(false)
const revokingCredentialId = ref(null)
const credentialToRevoke = ref(null)
const trialSaving = ref(false)
const featureSearch = ref('')
const trialAutosaveTimer = ref(null)
const suppressPlanAutosave = ref(false)
const lastSavedPlanSnapshot = ref('')
const lastSavedTrialSnapshot = ref('')
const editingPriceIndex = ref(null)
const project = ref(null)
const metrics = ref({})
const plans = ref([])
const editingPlan = ref(null)
const projectFeatures = ref([])
const featureLoading = ref(false)
const featureSaving = ref(false)
const showFeatureModal = ref(false)
const editingFeature = ref(null)
const featureToDelete = ref(null)
const deletingFeatureId = ref(null)
const planEntitlements = reactive({})


const featureForm = reactive({
    key: '',
    name: '',
    description: '',
    type: 'limit',
    unit: '',
    active: true,
    sort_order: 0
})


const planForm = reactive({
    name: '',
    description: '',
    features: [],
    active: true,
    sort_order: 0,
    prices: []
})


const priceForm = reactive({
    id: null,
    amount: '',
    currency: 'EUR',
    interval: 'monthly',
    active: true,
    stripe_price_id: ''
})


const trialForm = reactive({
    enabled: false,
    duration_days: 30,
    credits: 100
})


const planColumns = [
    {
        key: 'name',
        label: 'Plan'
    },
    {
        key: 'prices',
        label: 'Pricing'
    },
    {
        key: 'active',
        label: 'Status'
    }
]


const featureColumns = [
    {
        key: 'key',
        label: 'Key'
    },
    {
        key: 'name',
        label: 'Name'
    },
    {
        key: 'type',
        label: 'Type'
    },
    {
        key: 'unit',
        label: 'Unit'
    },
    {
        key: 'active',
        label: 'Status'
    },
    {
        key: 'actions',
        label: ''
    }
]


const featureTypeOptions = [
    {
        label: 'Boolean',
        value: 'boolean'
    },
    {
        label: 'Limit',
        value: 'limit'
    }
]


const priceColumns = [
    {
        key: 'amount',
        label: 'Amount'
    },
    {
        key: 'interval',
        label: 'Interval'
    },
    {
        key: 'active',
        label: 'Status'
    }
]


const customerColumns = [
    {
        key: 'company',
        label: 'Company'
    },
    {
        key: 'plan',
        label: 'Plan'
    },
    {
        key: 'status',
        label: 'Status'
    }
]


const subscriptionColumns = [
    {
        key: 'company',
        label: 'Company'
    },
    {
        key: 'plan',
        label: 'Plan'
    },
    {
        key: 'status',
        label: 'Status'
    },
    {
        key: 'current_period_end',
        label: 'Current period'
    },
    {
        key: 'mrr',
        label: 'MRR'
    }
]


const paymentColumns = [
    { key: 'company', label: 'Company' },
    { key: 'paid_at', label: 'Date' },
    { key: 'amount', label: 'Amount' },
    { key: 'status', label: 'Status' },
    { key: 'payment_method', label: 'Method' },
    { key: 'saas_invoice_id', label: 'Invoice' }
]


const invoiceColumns = [
    { key: 'company', label: 'Company' },
    { key: 'invoice_number', label: 'Invoice' },
    { key: 'invoice_date', label: 'Date' },
    { key: 'amount_paid', label: 'Amount' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' }
]


const {
    rows: customers,
    meta: customersMeta,
    loading: customersLoading,
    state: customersState
} = useServerTable(
    `/saas/projects/${props.id}/customers`
)


const {
    rows: subscriptions,
    meta: subscriptionsMeta,
    loading: subscriptionsLoading,
    state: subscriptionsState
} = useServerTable(
    `/saas/projects/${props.id}/subscriptions`
)


const {
    rows: payments,
    meta: paymentsMeta,
    loading: paymentsLoading,
    state: paymentsState
} = useServerTable(
    `/saas/projects/${props.id}/payments`
)


const {
    rows: invoices,
    meta: invoicesMeta,
    loading: invoicesLoading,
    state: invoicesState
} = useServerTable(
    `/saas/projects/${props.id}/invoices`
)


const pageTitle = computed(() =>
    project.value?.name ||
    'SaaS project'
)


function openCustomerBilling(customer) {
    router.push({
        name: 'saas.projects.customer',
        params: {
            id: props.id,
            companyId: customer.id
        }
    })
}


const intervalOptions = [
    {
        label: 'Monthly',
        value: 'monthly'
    },
    {
        label: 'Yearly',
        value: 'yearly'
    }
]


const priceRows = computed(() =>
    planForm.prices.map(
        (price, index) => ({
            ...price,
            id:
                price.id ||
                `new-price-${index}`,
            priceIndex:
                index
        })
    )
)


const priceIntervalOptions = computed(() =>
    intervalOptions.filter(
        option => {
            const currentIndex =
                editingPriceIndex.value

            return !planForm.prices.some(
                (price, index) =>
                    index !== currentIndex &&
                    price.interval ===
                    option.value
            )
        }
    )
)


const featureOptions = computed(() => {
    const query =
        String(
            featureSearch.value ||
            ''
        ).trim()

    const selected =
        planForm.features
            .map(
                feature =>
                    String(
                        feature ||
                        ''
                    ).trim()
            )
            .filter(Boolean)

    const options =
        selected.map(
            feature => ({
                label:
                    feature,
                value:
                    feature,
                existing:
                    true
            })
        )

    if (!query) {
        return options
    }

    const exactMatch =
        selected.some(
            feature =>
                feature.toLowerCase() ===
                query.toLowerCase()
        )

    if (exactMatch) {
        return options
    }

    return [
        ...options,
        {
            label:
                `Create "${query}"`,
            value:
                query,
            create:
                true
        }
    ]
})


function blankPrice() {
    return {
        id: null,
        amount: '',
        currency: 'EUR',
        interval: 'monthly',
        active: true,
        stripe_price_id: ''
    }
}


function resetPlanForm() {
    editingPlan.value = null
    errors.value = {}
    featureSearch.value = ''

    Object.assign(
        planForm,
        {
            name: '',
            description: '',
            features: [],
            active: true,
            sort_order: plans.value.length,
            prices: [
                blankPrice()
            ]
        }
    )

    rebuildPlanEntitlements()

    lastSavedPlanSnapshot.value =
        getPlanSnapshot()
}


function openCreatePlanModal() {
    resetPlanForm()
    showPlanModal.value = true
}


function closePlanModal() {
    if (saving.value) {
        return
    }

    showPlanModal.value = false
    editingPlan.value = null
    errors.value = {}
}


function applyPlanToForm(plan) {
    editingPlan.value = plan
    errors.value = {}

    Object.assign(
        planForm,
        {
            name:
                plan.name ||
                '',

            description:
                plan.description ||
                '',

            features:
                plan.features?.length
                    ? [
                        ...plan.features
                    ]
                    : [],

            active:
                Boolean(
                    plan.active
                ),

            sort_order:
                plan.sort_order ??
                0,

            prices:
                plan.prices?.length
                    ? plan.prices.map(
                        price => ({
                            id:
                                price.id,

                            amount:
                                String(
                                    Number(
                                        price.amount ||
                                        0
                                    ) / 100
                                ),

                            currency:
                                price.currency ||
                                'EUR',

                            interval:
                                price.interval ||
                                'monthly',

                            active:
                                Boolean(
                                    price.active
                                ),

                            stripe_price_id:
                                price.stripe_price_id ||
                                ''
                        })
                    )
                    : [
                        blankPrice()
                    ]
        }
    )

    rebuildPlanEntitlements(plan.entitlement_values || [])

    lastSavedPlanSnapshot.value =
        getPlanSnapshot()
}


function editPlan(plan) {
    suppressPlanAutosave.value = true

    applyPlanToForm(plan)
    showPlanModal.value = true

    requestAnimationFrame(() => {
        suppressPlanAutosave.value = false
    })
}


function formatCredentialDate(value) {
    if (!value) {
        return 'Never'
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }
    ).format(
        new Date(value)
    )
}


async function loadProjectCredentials() {
    if (!project.value?.id) {
        projectCredentials.value = []
        return
    }

    credentialLoading.value = true

    try {
        const response = await api.get(
            `/saas/projects/${project.value.id}/billing-api/project-credentials`
        )

        projectCredentials.value =
            response.data?.data ||
            []
    } catch (exception) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        credentialLoading.value = false
    }
}


/*
|--------------------------------------------------------------------------
| Entitlements
|--------------------------------------------------------------------------
*/

async function loadProjectFeatures() {
    if (!project.value?.id) {
        projectFeatures.value = []
        return
    }

    featureLoading.value = true

    try {
        const response = await api.get(
            `/saas/projects/${project.value.id}/features`
        )

        projectFeatures.value =
            response.data?.data ||
            []
    } catch (exception) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        featureLoading.value = false
    }
}


function rebuildPlanEntitlements(existingValues = []) {
    const values = {}

    for (const feature of projectFeatures.value) {
        const existing = existingValues.find(
            value => value.feature_id === feature.id
        )

        values[feature.id] = {
            boolean_value: existing?.boolean_value ?? false,
            limit_value: existing?.limit_value ?? '',
            is_unlimited: existing?.is_unlimited ?? false
        }
    }

    Object.keys(planEntitlements).forEach(
        key => delete planEntitlements[key]
    )

    Object.assign(planEntitlements, values)
}


function openCreateFeatureModal() {
    editingFeature.value = null

    Object.assign(featureForm, {
        key: '',
        name: '',
        description: '',
        type: 'limit',
        unit: '',
        active: true,
        sort_order: projectFeatures.value.length
    })

    showFeatureModal.value = true
}


function editFeature(feature) {
    editingFeature.value = feature

    Object.assign(featureForm, {
        key: feature.key || '',
        name: feature.name || '',
        description: feature.description || '',
        type: feature.type || 'limit',
        unit: feature.unit || '',
        active: Boolean(feature.active),
        sort_order: feature.sort_order ?? 0
    })

    showFeatureModal.value = true
}


function closeFeatureModal() {
    if (featureSaving.value) {
        return
    }

    showFeatureModal.value = false
    editingFeature.value = null
}


async function saveFeature() {
    if (featureSaving.value || !project.value?.id) {
        return
    }

    featureSaving.value = true
    errors.value = {}

    const payload = {
        key: featureForm.key.trim(),
        name: featureForm.name.trim(),
        description: featureForm.description,
        type: featureForm.type,
        unit: featureForm.unit || null,
        active: Boolean(featureForm.active),
        sort_order: Number(featureForm.sort_order || 0)
    }

    try {
        if (editingFeature.value?.id) {
            await api.put(
                `/saas/features/${editingFeature.value.id}`,
                payload
            )
        } else {
            await api.post(
                `/saas/projects/${project.value.id}/features`,
                payload
            )
        }

        showFeatureModal.value = false
        editingFeature.value = null

        await loadProjectFeatures()
    } catch (exception) {
        errors.value = validationErrors(exception)

        showError(
            errorMessage(exception)
        )
    } finally {
        featureSaving.value = false
    }
}


function requestDeleteFeature(feature) {
    featureToDelete.value = feature
}


async function deleteFeature() {
    const feature = featureToDelete.value

    if (!feature?.id || deletingFeatureId.value) {
        return
    }

    deletingFeatureId.value = feature.id

    try {
        await api.delete(`/saas/features/${feature.id}`)

        featureToDelete.value = null

        await loadProjectFeatures()
    } catch (exception) {
        showError(
            errorMessage(exception)
        )
    } finally {
        deletingFeatureId.value = null
    }
}


const credentialColumns = [
    {
        key: 'name',
        label: 'Name'
    },
    {
        key: 'created_at',
        label: 'Created'
    },
    {
        key: 'last_used_at',
        label: 'Last used'
    },
    {
        key: 'actions',
        label: ''
    }
]


function openCredentialModal() {
    credentialName.value =
        `${project.value?.name || 'SaaS'} production billing API`

    showCredentialModal.value = true
}


function closeCredentialModal() {
    if (!credentialSaving.value) {
        showCredentialModal.value = false
    }
}


async function generateProjectCredential() {
    if (
        credentialSaving.value ||
        !project.value?.id
    ) {
        return
    }

    credentialSaving.value = true

    try {
        const response = await api.post(
            `/saas/projects/${project.value.id}/billing-api/project-credentials`,
            {
                name: credentialName.value.trim()
            }
        )

        generatedProjectToken.value =
            response.data?.data?.token ||
            ''

        tokenCopied.value = false
        showCredentialModal.value = false
        showTokenModal.value = true

        await loadProjectCredentials()
    } catch (exception) {
        errors.value =
            validationErrors(
                exception
            )

        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        credentialSaving.value = false
    }
}


async function copyProjectToken() {
    if (!generatedProjectToken.value) {
        return
    }

    try {
        await navigator.clipboard.writeText(
            generatedProjectToken.value
        )

        tokenCopied.value = true
        showSuccessToast.value = true
    } catch {
        showError(
            'The token could not be copied. Copy it manually before closing this dialog.'
        )
    }
}


function closeTokenModal() {
    showTokenModal.value = false
    generatedProjectToken.value = ''
    tokenCopied.value = false
}


function requestRevokeCredential(credential) {
    credentialToRevoke.value = credential
}


async function revokeProjectCredential() {
    const credential = credentialToRevoke.value

    if (
        !credential?.id ||
        !project.value?.id ||
        revokingCredentialId.value
    ) {
        return
    }

    revokingCredentialId.value = credential.id

    try {
        await api.delete(
            `/saas/projects/${project.value.id}/billing-api/project-credentials/${credential.id}`
        )

        credentialToRevoke.value = null
        await loadProjectCredentials()
    } catch (exception) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        revokingCredentialId.value = null
    }
}


function applyTrialSettings(source) {
    Object.assign(
        trialForm,
        {
            enabled:
                Boolean(
                    source?.trial_enabled
                ),

            duration_days:
                source?.trial_duration_days ??
                30,

            credits:
                source?.trial_credits ??
                100
        }
    )

    lastSavedTrialSnapshot.value =
        getTrialSnapshot()
}


function getTrialSnapshot() {
    return JSON.stringify({
        trial_enabled:
            trialForm.enabled,

        trial_duration_days:
            Number(
                trialForm.duration_days
            ),

        trial_credits:
            Number(
                trialForm.credits
            )
    })
}


function clearTrialAutosaveTimer() {
    if (
        trialAutosaveTimer.value
    ) {
        clearTimeout(
            trialAutosaveTimer.value
        )

        trialAutosaveTimer.value =
            null
    }
}


function scheduleTrialAutosave() {
    if (
        trialSaving.value ||
        !project.value?.id ||
        !autosaveEnabled.value
    ) {
        return
    }

    const snapshot =
        getTrialSnapshot()

    if (
        snapshot ===
        lastSavedTrialSnapshot.value
    ) {
        return
    }

    clearTrialAutosaveTimer()

        trialAutosaveTimer.value =
            setTimeout(() => {
                void saveTrialSettings({
                    toast: false
                })
            }, 1200)
}


function isTextField(target) {
    return [
        'INPUT',
        'TEXTAREA',
        'SELECT'
    ].includes(
        target?.tagName
    )
}


function handleTrialFocusOut(event) {
    if (
        !isTextField(
            event.target
        )
    ) {
        return
    }

    clearTrialAutosaveTimer()

    void saveTrialSettings({
        toast: false
    })
}


async function saveTrialSettings(options = {}) {
    if (
        trialSaving.value ||
        !project.value?.id
    ) {
        return
    }

    clearTrialAutosaveTimer()
    trialSaving.value = true

    const requestSnapshot =
        getTrialSnapshot()

    try {
        const response = await api.put(
            `/saas/projects/${project.value.id}/trial-settings`,
            {
                trial_enabled:
                    trialForm.enabled,

                trial_duration_days:
                    Number(
                        trialForm.duration_days
                    ),

                trial_credits:
                    Number(
                        trialForm.credits
                    )
            }
        )

        const savedProject =
            response.data?.project?.data ||
            response.data?.project ||
            project.value

        if (
            getTrialSnapshot() ===
            requestSnapshot
        ) {
            project.value =
                savedProject

            applyTrialSettings(
                project.value
            )
        }

        if (
            options.toast ??
            true
        ) {
            showSuccessToast.value =
                true
        }
    } catch (exception) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        trialSaving.value = false

        if (
            getTrialSnapshot() !==
            lastSavedTrialSnapshot.value
        ) {
            scheduleTrialAutosave()
        }
    }
}


function resetPriceForm(price = null) {
    Object.assign(
        priceForm,
        price || blankPrice()
    )
}


function openCreatePriceModal() {
    editingPriceIndex.value = null
    resetPriceForm()
    showPriceModal.value = true
}


function editPrice(price) {
    editingPriceIndex.value =
        price.priceIndex

    resetPriceForm(
        planForm.prices[
            price.priceIndex
        ]
    )

    showPriceModal.value = true
}


function closePriceModal() {
    showPriceModal.value = false
    editingPriceIndex.value = null
}


function savePrice() {
    const value = {
        ...priceForm,
        active: true
    }

    const duplicate =
        planForm.prices.some(
            (price, index) =>
                index !== editingPriceIndex.value &&
                price.interval === value.interval
        )

    if (
        duplicate ||
        !intervalOptions.some(
            option =>
                option.value ===
                value.interval
        )
    ) {
        return
    }

    if (
        editingPriceIndex.value === null
    ) {
        planForm.prices.push(
            value
        )
    } else {
        planForm.prices.splice(
            editingPriceIndex.value,
            1,
            value
        )
    }

    closePriceModal()
}


function searchFeatures(query) {
    featureSearch.value =
        String(
            query ||
            ''
        )
}


function handleFeatureValuesUpdate(value) {
    if (!Array.isArray(value)) {
        return
    }

    planForm.features = [
        ...new Set(
            value
                .map(
                    feature =>
                        String(
                            feature ||
                            ''
                        ).trim()
                )
                .filter(Boolean)
        )
    ]
}


function handleFeatureSelect(option) {
    if (!option) {
        return
    }

    handleFeatureValuesUpdate(
        planForm.features
    )

    featureSearch.value = ''
}


function removePrice(index) {
    planForm.prices.splice(
        index,
        1
    )
}


function removePriceAndClose(index) {
    removePrice(index)
    closePriceModal()
}


function cents(value) {
    const number =
        Number(
            String(
                value ??
                ''
            ).replace(
                ',',
                '.'
            )
        )

    if (
        !Number.isFinite(
            number
        )
    ) {
        return 0
    }

    return Math.round(
        number * 100
    )
}


function money(
    amount,
    currency = 'EUR'
) {
    const value =
        Number(
            amount ||
            0
        )

    return new Intl.NumberFormat(
        'en-US',
        {
            style: 'currency',
            currency:
                String(
                    currency ||
                    'EUR'
                ).toUpperCase()
        }
    ).format(
        value / 100
    )
}


function formatDate(value) {
    if (!value) {
        return '—'
    }

    const date =
        new Date(
            value
        )

    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return '—'
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }
    ).format(
        date
    )
}


function subscriptionMrr(row) {
    const amount =
        Number(
            row.price?.amount ||
            0
        )

    const interval =
        row.price?.interval ||
        'monthly'

    if (
        interval === 'yearly'
    ) {
        return amount / 12
    }

    return amount
}


function showError(message) {
    error.value =
        message ||
        'Something went wrong.'

    showErrorToast.value =
        false

    requestAnimationFrame(() => {
        showErrorToast.value =
            true
    })
}


function planPayload() {
    return {
        name:
            planForm.name,

        description:
            planForm.description,

        features:
            planForm.features
                .map(
                    feature =>
                        String(
                            feature ||
                            ''
                        ).trim()
                )
                .filter(
                    Boolean
                ),

        active:
            Boolean(
                planForm.active
            ),

        sort_order:
            Number(
                planForm.sort_order ||
                0
            ),

        prices:
            planForm.prices.map(
                price => ({
                    id:
                        price.id,

                    amount:
                        cents(
                            price.amount
                        ),

                    currency:
                        String(
                            price.currency ||
                            'EUR'
                        ).toUpperCase(),

                    interval:
                        price.interval,

                    active:
                        Boolean(
                            price.active
                        )
                })
            ),

        entitlements:
            projectFeatures.value
                .filter(feature => feature.active)
                .map(feature => {
                    const value = planEntitlements[feature.id] || {}

                    if (feature.type === 'boolean') {
                        return {
                            feature_id: feature.id,
                            boolean_value: Boolean(value.boolean_value)
                        }
                    }

                    if (value.is_unlimited) {
                        return {
                            feature_id: feature.id,
                            is_unlimited: true
                        }
                    }

                    return {
                        feature_id: feature.id,
                        limit_value: Number(value.limit_value || 0)
                    }
                })
    }
}


function getPlanSnapshot() {
    return JSON.stringify(
        planPayload()
    )
}


async function load() {
    loading.value = true
    error.value = ''

    try {
        const [
            projectResponse,
            plansResponse,
            revenueResponse
        ] = await Promise.all([
            api.get(
                `/saas/projects/${props.id}`
            ),

            api.get(
                `/saas/projects/${props.id}/plans`
            ),

            api.get(
                `/saas/projects/${props.id}/revenue`
            )
        ])

        const loadedProject =
            projectResponse.data?.project?.data ||
            projectResponse.data?.project ||
            null

        project.value =
            loadedProject

        applyTrialSettings(
            project.value
        )

        await loadProjectCredentials()
        await loadProjectFeatures()

        metrics.value =
            projectResponse.data?.metrics ||
            {}

        if (
            revenueResponse.data?.metrics
        ) {
            metrics.value = {
                ...metrics.value,
                ...revenueResponse.data.metrics
            }
        }

        plans.value =
            plansResponse.data?.data ||
            []

        resetPlanForm()
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        loading.value = false
    }
}


async function savePlan(options = {}) {
    const closeAfterSave =
        options.close ??
        true

    const showToast =
        options.toast ??
        true

    if (
        saving.value
    ) {
        return
    }

    saving.value = true
    errors.value = {}

    const requestSnapshot =
        getPlanSnapshot()

    setStatus('saving')

    const payload =
        planPayload()

    try {
        let savedPlan = null

        if (
            editingPlan.value?.id
        ) {
            const response = await api.put(
                `/saas/plans/${editingPlan.value.id}`,
                payload
            )

            savedPlan =
                response.data?.data ||
                response.data
        } else {
            const response = await api.post(
                `/saas/projects/${props.id}/plans`,
                payload
            )

            savedPlan =
                response.data?.data ||
                response.data
        }

        if (
            closeAfterSave
        ) {
            showPlanModal.value = false
            editingPlan.value = null
        } else if (
            savedPlan?.id
        ) {
            editingPlan.value =
                savedPlan

            if (
                getPlanSnapshot() ===
                requestSnapshot
            ) {
            suppressPlanAutosave.value = true

            applyPlanToForm(
                savedPlan
            )

            suppressPlanAutosave.value = false
            }
        }

        if (
            savedPlan?.id
        ) {
            const index =
                plans.value.findIndex(
                    plan =>
                        String(plan.id) ===
                        String(savedPlan.id)
                )

            if (
                index >= 0
            ) {
                plans.value.splice(
                    index,
                    1,
                    savedPlan
                )
            } else {
                plans.value.push(
                    savedPlan
                )
            }
        }

        if (
            showToast
        ) {
            showSuccessToast.value =
                false

            requestAnimationFrame(() => {
                showSuccessToast.value =
                    true
            })
        }

        lastSavedPlanSnapshot.value =
            requestSnapshot

        setLastSavedAt(
            new Date()
        )

        if (
            closeAfterSave
        ) {
            await load()
        }
    } catch (
        exception
    ) {
        errors.value =
            validationErrors(
                exception
            )

        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        saving.value = false
        setStatus('idle')

    }
}


watch(
    trialForm,
    () => {
        scheduleTrialAutosave()
    },
    {
        deep: true
    }
)


onBeforeUnmount(() => {
    clearTrialAutosaveTimer()
})


function requestDeletePlan(plan) {
    deletingPlan.value =
        plan
}


async function confirmDeletePlan() {
    if (
        !deletingPlan.value?.id
    ) {
        return
    }

    try {
        await api.delete(
            `/saas/plans/${deletingPlan.value.id}`
        )

        deletingPlan.value =
            null

        showPlanModal.value =
            false

        editingPlan.value =
            null

        await load()
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    }
}


function deleteProject() {
    if (
        deletingProject.value
    ) {
        return
    }

    showDeleteConfirm.value =
        true
}


async function confirmDeleteProject() {
    if (
        deletingProject.value
    ) {
        return
    }

    deletingProject.value =
        true

    try {
        await api.delete(
            `/saas/projects/${props.id}`
        )

        showDeleteConfirm.value =
            false

        router.push({
            name:
                'saas.projects.index'
        })
    } catch (
        exception
    ) {
        showError(
            errorMessage(
                exception
            )
        )
    } finally {
        deletingProject.value =
            false
    }
}


onMounted(load)


useAdminPageHeader({
    title: pageTitle,
    eyebrow: 'SaaS',
    breadcrumbs: [
        {
            label: 'SaaS',
            to: {
                name:
                    'saas.projects.index'
            }
        },
        {
            label:
                pageTitle
        }
    ]
})

</script>


<template>

    <div
        class="
            w-full
            space-y-14
            lg:space-y-16
        "
    >

        <!-- TOASTS -->

        <Toast
            v-model="showErrorToast"
            heading="Something went wrong"
            :text="error"
            :duration="5000"
        />

        <Toast
            v-model="showSuccessToast"
            heading="Saved"
            text="The SaaS product changes have been saved."
            :duration="4000"
        />


        <!-- LOADING -->

        <div
            v-if="loading"
            class="
                border-t
                border-accent
                pt-6
            "
        >

            <p
                class="
                    p
                    uppercase
                    text-dark/40
                "
            >
                Loading SaaS project...
            </p>

        </div>


        <!-- ERROR -->

        <div
            v-else-if="error && !project"
            class="
                border-t
                border-accent
                pt-6
            "
        >

            <p class="p text-red-600">
                {{ error }}
            </p>

        </div>


        <template
            v-else-if="project"
        >

            <!-- ===================================================== -->
            <!-- REVENUE -->
            <!-- ===================================================== -->

            <section
                class="
                    space-y-6
                "
            >

                <h2
                    class="
                        h2
                        text-left
                        text-accent
                    "
                >
                    Revenue
                </h2>


                <div
                    class="
                        grid
                        gap-4
                        sm:grid-cols-2
                        lg:grid-cols-3
                    "
                >

                    <div
                        class="
                            border
                            border-accent
                            p-5
                        "
                    >

                        <p class="p uppercase text-dark/40">
                            MRR
                        </p>

                        <p class="h2 text-accent">
                            {{
                                money(
                                    metrics.mrr,
                                    metrics.currency
                                )
                            }}
                        </p>

                    </div>


                    <div
                        class="
                            border
                            border-accent
                            p-5
                        "
                    >

                        <p class="p uppercase text-dark/40">
                            ARR
                        </p>

                        <p class="h2 text-accent">
                            {{
                                money(
                                    metrics.arr,
                                    metrics.currency
                                )
                            }}
                        </p>

                    </div>


                    <div
                        class="
                            border
                            border-accent
                            p-5
                        "
                    >

                        <p class="p uppercase text-dark/40">
                            Active subscriptions
                        </p>

                        <p class="h2 text-accent">
                            {{
                                metrics.active_subscriptions ??
                                0
                            }}
                        </p>

                    </div>


                    <div
                        class="
                            border
                            border-accent
                            p-5
                        "
                    >

                        <p class="p uppercase text-dark/40">
                            Customers
                        </p>

                        <p class="h2 text-accent">
                            {{
                                metrics.active_customers ??
                                0
                            }}
                        </p>

                    </div>


                    <div
                        class="
                            border
                            border-accent
                            p-5
                        "
                    >

                        <p class="p uppercase text-dark/40">
                            Trials
                        </p>

                        <p class="h2 text-accent">
                            {{
                                metrics.trial_customers ??
                                0
                            }}
                        </p>

                    </div>


                    <div
                        class="
                            border
                            border-accent
                            p-5
                        "
                    >

                        <p class="p uppercase text-dark/40">
                            Churn
                        </p>

                        <p class="h2 text-accent">
                            {{
                                metrics.churn_rate ??
                                0
                            }}%
                        </p>

                    </div>

                </div>

            </section>


            <!-- ===================================================== -->
            <!-- APPLICATION TRIAL -->
            <!-- ===================================================== -->

            <section
                class="
                    space-y-6
                "
            >

                <h2
                    class="
                        h2
                        text-left
                        text-accent
                    "
                >
                    Application trial
                </h2>


                <div
                    class="
                        space-y-5
                    "
                    @focusout="handleTrialFocusOut"
                >

                    <FormField
                        id="saas-trial-enabled"
                        v-model="trialForm.enabled"
                        type="toggle"
                        label="Trial enabled"
                    />


                    <div
                        class="
                            grid
                            gap-5
                            sm:grid-cols-2
                        "
                    >

                        <FormField
                            id="saas-trial-duration"
                            v-model="trialForm.duration_days"
                            type="number"
                            label="Duration"
                            suffix="days"
                            required
                        />

                        <FormField
                            id="saas-trial-credits"
                            v-model="trialForm.credits"
                            type="number"
                            label="Credits"
                            suffix="credits"
                            required
                        />

                    </div>

                </div>

            </section>


            <!-- ===================================================== -->
            <!-- BILLING API -->
            <!-- ===================================================== -->

            <section
                class="
                    space-y-6
                "
            >

                <AdminDataTable
                    title="Billing API"
                    :columns="credentialColumns"
                    :rows="projectCredentials"
                    :loading="credentialLoading"
                    empty-title="No project credentials yet."
                    empty-text="Generate a project credential to access the billing API."
                    add-label=" "
                    @add="openCredentialModal"
                >

                    <!-- NAME -->

                    <template
                        #cell-name="{
                            value
                        }"
                    >

                        <span class="p font-medium">
                            {{ value || '—' }}
                        </span>

                    </template>


                    <!-- CREATED -->

                    <template
                        #cell-created_at="{
                            value
                        }"
                    >

                        <span class="p">
                            {{
                                formatCredentialDate(
                                    value
                                )
                            }}
                        </span>

                    </template>


                    <!-- LAST USED -->

                    <template
                        #cell-last_used_at="{
                            value
                        }"
                    >

                        <span class="p">
                            {{
                                formatCredentialDate(
                                    value
                                )
                            }}
                        </span>

                    </template>


                    <!-- ACTIONS -->

                    <template
                        #cell-actions="{
                            row
                        }"
                    >

                        <Button
                            v-if="row.active"
                            type="button"
                            text="Revoke"
                            align="left"
                            class="
                                text-red-600
                                hover:text-red-700
                            "
                            @click.stop="
                                requestRevokeCredential(
                                    row
                                )
                            "
                        />

                    </template>

                </AdminDataTable>

            </section>

            <!-- ===================================================== -->
            <!-- FEATURE DEFINITIONS -->
            <!-- ===================================================== -->

            <section class="space-y-6">

                <AdminDataTable
                    title="Feature definitions"
                    :columns="featureColumns"
                    :rows="projectFeatures"
                    :loading="featureLoading"
                    empty-title="No feature definitions yet."
                    empty-text="Define the features plans can configure, such as user limits or AI credits. Each plan sets its own value."
                    add-label="Add feature"
                    @add="openCreateFeatureModal"
                    @row-click="editFeature"
                >

                    <template #cell-type="{ value }">
                        <span class="p capitalize">{{ value }}</span>
                    </template>

                    <template #cell-unit="{ value }">
                        <span class="p">{{ value || '—' }}</span>
                    </template>

                    <template #cell-active="{ value }">
                        <Tag
                            size="sm"
                            tone="neutral"
                            :label="value ? 'active' : 'inactive'"
                        />
                    </template>

                    <template #cell-actions="{ row }">
                        <Button
                            type="button"
                            text="Delete"
                            align="left"
                            class="text-red-600 hover:text-red-700"
                            @click.stop="requestDeleteFeature(row)"
                        />
                    </template>

                </AdminDataTable>

            </section>

            <!-- ===================================================== -->
            <!-- PLANS -->
            <!-- ===================================================== -->

            <section
                class="
                    space-y-6
                "
            >

                <AdminDataTable
                    title="Plans"
                    :columns="planColumns"
                    :rows="plans"
                    :loading="loading"
                    empty-title="No plans yet."
                    empty-text="Create your first subscription plan."
                    add-label=" "
                    @row-click="editPlan"
                    @add="openCreatePlanModal"
                >

                    <!-- PLAN -->

                    <template
                        #cell-name="{
                            row,
                            value
                        }"
                    >

                        <div>

                            <span
                                class="
                                    p
                                    font-medium
                                "
                            >
                                {{
                                    value ||
                                    '—'
                                }}
                            </span>

                            <span
                                v-if="row.description"
                                class="
                                    mt-2
                                    block
                                    max-w-md
                                    text-xs
                                    text-dark/50
                                "
                            >
                                {{
                                    row.description
                                }}
                            </span>

                        </div>

                    </template>


                    <!-- PRICES -->

                    <template
                        #cell-prices="{
                            row
                        }"
                    >

                        <div
                            v-if="row.prices?.length"
                            class="
                                flex
                                flex-wrap
                                gap-2
                            "
                        >

                            <Tag
                                v-for="price in row.prices"
                                :key="
                                    price.id ||
                                    `${price.amount}-${price.interval}`
                                "
                                size="sm"
                                tone="neutral"
                                :label="
                                    `${money(
                                        price.amount,
                                        price.currency
                                    )} / ${price.interval}`
                                "
                            />

                        </div>

                        <span
                            v-else
                            class="p text-dark/40"
                        >
                            No pricing
                        </span>

                    </template>


                    <!-- STATUS -->

                    <template
                        #cell-active="{
                            value
                        }"
                    >

                        <Tag
                            size="sm"
                            tone="neutral"
                            :label="
                                value
                                    ? 'active'
                                    : 'inactive'
                            "
                        />

                    </template>

                </AdminDataTable>

            </section>


            <!-- ===================================================== -->
            <!-- CUSTOMERS -->
            <!-- ===================================================== -->

            <section
                class="
                    space-y-6
                "
            >

                <AdminDataTable
                    v-model:search="customersState.search"
                    title="Customers"
                    search-placeholder="Search customers"
                    :columns="customerColumns"
                    :rows="customers"
                    :loading="customersLoading"
                    :meta="customersMeta"
                    empty-title="No customers yet."
                    empty-text="Companies with billing activity will appear here."
                    @row-click="openCustomerBilling"
                    @page-change="
                        page =>
                            customersState.page =
                            page
                    "
                >

                    <template
                        #cell-company="{
                            row
                        }"
                    >

                        <span
                            class="
                                p
                                font-medium
                            "
                        >
                            {{
                                row.name ||
                                '—'
                            }}
                        </span>

                    </template>


                    <template
                        #cell-plan="{
                            row
                        }"
                    >

                        <span class="p">
                            {{
                                row.plan ||
                                '—'
                            }}
                        </span>

                    </template>


                    <template
                        #cell-status="{
                            value
                        }"
                    >

                        <Tag
                            size="sm"
                            tone="neutral"
                            :label="
                                String(
                                    value ||
                                    'incomplete'
                                ).replaceAll(
                                    '_',
                                    ' '
                                )
                            "
                        />

                    </template>

                </AdminDataTable>

            </section>


            <!-- ===================================================== -->
            <!-- SUBSCRIPTIONS -->
            <!-- ===================================================== -->

            <section
                class="
                    space-y-6
                "
            >

                <AdminDataTable
                    v-model:search="subscriptionsState.search"
                    title="Subscriptions"
                    search-placeholder="Search subscriptions"
                    :columns="subscriptionColumns"
                    :rows="subscriptions"
                    :loading="subscriptionsLoading"
                    :meta="subscriptionsMeta"
                    empty-title="No subscriptions yet."
                    empty-text="Company subscriptions will appear here after billing is connected."
                    @page-change="
                        page =>
                            subscriptionsState.page =
                            page
                    "
                >

                    <template
                        #cell-company="{
                            row
                        }"
                    >

                        <span
                            class="
                                p
                                font-medium
                            "
                        >
                            {{
                                row.company?.name ||
                                '—'
                            }}
                        </span>

                    </template>


                    <template
                        #cell-plan="{
                            row
                        }"
                    >

                        <span class="p">
                            {{
                                row.plan?.name ||
                                '—'
                            }}
                        </span>

                    </template>


                    <template
                        #cell-status="{
                            value
                        }"
                    >

                        <Tag
                            size="sm"
                            tone="neutral"
                            :label="
                                String(
                                    value ||
                                    'incomplete'
                                ).replaceAll(
                                    '_',
                                    ' '
                                )
                            "
                        />

                    </template>


                    <template
                        #cell-current_period_end="{
                            value
                        }"
                    >

                        <span class="p">
                            {{
                                formatDate(
                                    value
                                )
                            }}
                        </span>

                    </template>


                    <template
                        #cell-mrr="{
                            row
                        }"
                    >

                        <span class="p">
                            {{
                                money(
                                    subscriptionMrr(
                                        row
                                    ),
                                    row.price?.currency ||
                                    'EUR'
                                )
                            }}
                        </span>

                    </template>

                </AdminDataTable>

            </section>


            <!-- ===================================================== -->
            <!-- PAYMENTS -->
            <!-- ===================================================== -->

            <section class="space-y-6">
                <AdminDataTable
                    v-model:search="paymentsState.search"
                    title="Payments"
                    search-placeholder="Search payments"
                    :columns="paymentColumns"
                    :rows="payments"
                    :loading="paymentsLoading"
                    :meta="paymentsMeta"
                    empty-title="No payments yet."
                    empty-text="Paid invoices will appear here after Stripe webhooks are processed."
                    @page-change="page => paymentsState.page = page"
                >
                    <template #cell-company="{ row }">
                        <span class="p font-medium">
                            {{ row.company?.name || '—' }}
                        </span>
                    </template>

                    <template #cell-paid_at="{ value }">
                        <span class="p">{{ formatDate(value) }}</span>
                    </template>

                    <template #cell-amount="{ row, value }">
                        <span class="p">
                            {{ money(value, row.currency) }}
                        </span>
                    </template>

                    <template #cell-status="{ value }">
                        <Tag
                            size="sm"
                            tone="neutral"
                            :label="value || 'unknown'"
                        />
                    </template>

                    <template #cell-payment_method="{ row }">
                        <span class="p uppercase">
                            {{ row.payment_method_brand ? `${row.payment_method_brand} •••• ${row.payment_method_last4 || ''}` : '—' }}
                        </span>
                    </template>
                </AdminDataTable>
            </section>


            <!-- ===================================================== -->
            <!-- INVOICES -->
            <!-- ===================================================== -->

            <section class="space-y-6">
                <AdminDataTable
                    v-model:search="invoicesState.search"
                    title="Invoices"
                    search-placeholder="Search invoices"
                    :columns="invoiceColumns"
                    :rows="invoices"
                    :loading="invoicesLoading"
                    :meta="invoicesMeta"
                    empty-title="No invoices yet."
                    empty-text="Stripe invoices will appear here after webhook synchronization."
                    @page-change="page => invoicesState.page = page"
                >
                    <template #cell-company="{ row }">
                        <span class="p font-medium">
                            {{ row.company?.name || '—' }}
                        </span>
                    </template>

                    <template #cell-invoice_number="{ value }">
                        <span class="p">{{ value || '—' }}</span>
                    </template>

                    <template #cell-invoice_date="{ value }">
                        <span class="p">{{ formatDate(value) }}</span>
                    </template>

                    <template #cell-amount_paid="{ row, value }">
                        <span class="p">
                            {{ money(value, row.currency) }}
                        </span>
                    </template>

                    <template #cell-status="{ value }">
                        <Tag
                            size="sm"
                            tone="neutral"
                            :label="value || 'unknown'"
                        />
                    </template>

                    <template #cell-actions="{ row }">
                        <div class="flex gap-3">
                            <a
                                v-if="row.hosted_invoice_url"
                                :href="row.hosted_invoice_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="p text-accent"
                            >
                                view
                            </a>
                            <a
                                v-if="row.invoice_pdf_url"
                                :href="row.invoice_pdf_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="p text-accent"
                            >
                                PDF
                            </a>
                        </div>
                    </template>
                </AdminDataTable>
            </section>


            <!-- ===================================================== -->
            <!-- DANGER ZONE -->
            <!-- ===================================================== -->

            <section
                class="
                    space-y-4
                "
            >

                <h2
                    class="
                        h2
                        text-left
                        text-accent
                    "
                >
                    Danger zone
                </h2>


                <Button
                    type="button"
                    text="Discontinue SaaS management"
                    :loading="deletingProject"
                    loading-text="Discontinuing..."
                    :disabled="deletingProject"
                    align="left"
                    class="
                        text-red-600
                        hover:text-red-700
                    "
                    @click="deleteProject"
                />

            </section>


            <!-- ===================================================== -->
            <!-- PLAN MODAL -->
            <!-- ===================================================== -->

            <Modal
                :open="showPlanModal"
                :title="
                    editingPlan
                        ? 'Edit plan'
                        : 'Create plan'
                "
                :subtitle="
                    editingPlan
                        ? `Edit ${editingPlan.name}`
                        : 'Create a subscription plan for this SaaS product.'
                "
                :aria-label="
                    editingPlan
                        ? 'Edit subscription plan'
                        : 'Create subscription plan'
                "
                close-label="Close plan"
                panel-class="
                    overflow-y-auto
                    border
                    border-accent
                    bg-light
                    shadow-xl
                "
                max-width-class="max-w-2xl"
                body-class="p-0"
                @close="closePlanModal"
            >

                <form
                    class="
                        space-y-12
                        bg-light
                        p-5
                        sm:p-6
                    "
                    @submit.prevent="savePlan"
                >

                    <!-- BASIC INFORMATION -->

                    <section
                        class="
                            space-y-6
                        "
                    >

                        <FormField
                            id="saas-plan-name"
                            v-model="planForm.name"
                            type="text"
                            label="Name"
                            required
                            placeholder="Pro"
                            :error="
                                errors.name?.[0] ||
                                ''
                            "
                        />

                        <FormField
                            id="saas-plan-description"
                            v-model="planForm.description"
                            type="textarea"
                       e     label="Description"
                            placeholder="Describe what this plan includes."
                            :error="
                                errors.description?.[0] ||
                                ''
                            "
                        />


                        <!-- FEATURES -->

                        <FormField
                            id="saas-plan-features"
                            :model-value="planForm.features"
                            name="features"
                            type="autocomplete"
                            multiple
                            label="Features"
                            placeholder="Start typing a feature"
                            :options="featureOptions"
                            :error="
                                errors.features?.[0] ||
                                ''
                            "
                            @search="searchFeatures"
                            @update:model-value="
                                handleFeatureValuesUpdate
                            "
                            @select="handleFeatureSelect"
                        />

                    </section>


                    <!-- PRICING -->

                    <section
                        class="
                            space-y-6
                        "
                    >

                        <AdminDataTable
                            title="Pricing"
                            :columns="priceColumns"
                            :rows="priceRows"
                            empty-title="No prices yet."
                            empty-text="Add a price for this plan."
                            :add-label="
                                priceRows.length < 2
                                    ? 'Add price'
                                    : ''
                            "
                            @add="openCreatePriceModal"
                            @row-click="editPrice"
                        >

                            <template
                                #cell-amount="{
                                    row,
                                    value
                                }"
                            >

                                <span class="p">
                                    {{
                                        money(
                                            cents(
                                                value
                                            ),
                                            row.currency
                                        )
                                    }}
                                </span>

                            </template>


                            <template
                                #cell-currency="{
                                    value
                                }"
                            >

                                <span class="p uppercase">
                                    {{ value || 'EUR' }}
                                </span>

                            </template>


                            <template
                                #cell-interval="{
                                    value
                                }"
                            >

                                <span class="p capitalize">
                                    {{ value || 'monthly' }}
                                </span>

                            </template>


                            <template
                                #cell-active="{
                                    value
                                }"
                            >

                                <Tag
                                    size="sm"
                                    tone="neutral"
                                    :label="
                                        value
                                            ? 'active'
                                            : 'inactive'
                                    "
                                />

                            </template>

                        </AdminDataTable>

                    </section>


                    <!-- ENTITLEMENTS -->

                    <section
                        v-if="projectFeatures.filter(feature => feature.active).length"
                        class="space-y-6"
                    >
                        <h3 class="h2 text-accent text-left">
                            Entitlements
                        </h3>

                        <p class="p uppercase text-dark/50">
                            This plan's own value for each feature defined on the SaaS Project.
                        </p>

                        <div
                            v-for="feature in projectFeatures.filter(feature => feature.active)"
                            :key="feature.id"
                            class="space-y-3 border border-accent p-4"
                        >
                            <FormField
                                v-if="feature.type === 'boolean'"
                                :id="`saas-plan-entitlement-${feature.id}`"
                                v-model="planEntitlements[feature.id].boolean_value"
                                type="toggle"
                                :label="feature.name"
                            />

                            <template v-else>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="p font-medium">
                                        {{ feature.name }}<template v-if="feature.unit"> ({{ feature.unit }})</template>
                                    </span>

                                    <FormField
                                        :id="`saas-plan-entitlement-${feature.id}-unlimited`"
                                        v-model="planEntitlements[feature.id].is_unlimited"
                                        type="toggle"
                                        label="Unlimited"
                                    />
                                </div>

                                <FormField
                                    v-if="!planEntitlements[feature.id].is_unlimited"
                                    :id="`saas-plan-entitlement-${feature.id}`"
                                    v-model="planEntitlements[feature.id].limit_value"
                                    type="number"
                                    label="Value"
                                />
                            </template>
                        </div>
                    </section>


                    <!-- PRICE MODAL -->

                    <Modal
                        :open="showPriceModal"
                        :title="
                            editingPriceIndex === null
                                ? 'Add price'
                                : 'Update price'
                        "
                        :subtitle="
                            editingPriceIndex === null
                                ? 'Add a billing price for this plan.'
                                : 'Update the billing price for this plan.'
                        "
                        aria-label="Price editor"
                        close-label="Close price editor"
                        panel-class="
                            border
                            border-accent
                            bg-light
                            shadow-xl
                        "
                        body-class="p-0"
                        max-width-class="max-w-xl"
                        @close="closePriceModal"
                    >

                        <form
                            class="
                                space-y-12
                                bg-light
                                p-5
                                sm:p-6
                            "
                            @submit.prevent="savePrice"
                        >

                            <div
                                class="
                                    grid
                                    gap-5
                                    sm:grid-cols-2
                                "
                            >

                                <FormField
                                    id="saas-price-amount"
                                    v-model="priceForm.amount"
                                    type="number"
                                    label="Price amount"
                                    placeholder="19.00"
                                    required
                                    suffix="€"
                                />

                                <FormField
                                    id="saas-price-interval"
                                    v-model="priceForm.interval"
                                    type="select"
                                    label="Interval"
                                    :options="priceIntervalOptions"
                                    required
                                />

                            </div>


                            <div
                                class="
                                    flex
                                    flex-col
                                    gap-3
                                "
                            >

                                <h3
                                    class="
                                        h2
                                        text-accent
                                        text-left
                                    "
                                >
                                    Product status
                                </h3>

                                <FormField
                                    id="saas-price-active"
                                    v-model="priceForm.active"
                                    type="toggle"
                                    label="Price active"
                                />

                            </div>

                            <div
                                class="
                                    flex
                                    flex-col
                                    gap-3
                                "
                            >
                                <Button
                                    type="button"
                                    text="cancel"
                                    @click="closePriceModal"
                                    align="right"
                                />

                                <Button
                                    type="submit"
                                    :text="
                                        editingPriceIndex === null
                                            ? 'add price'
                                            : 'update price'
                                    "
                                    variant="dark"
                                    align="right"
                                />
                            </div>

                        </form>

                    </Modal>


                    <!-- DANGER ZONE -->

                    <section
                        class="
                            space-y-5
                        "
                    >

                        <h3
                            class="
                                h2
                                text-accent
                                text-left
                            "
                        >
                            Danger zone
                        </h3>

                        <FormField
                            id="saas-plan-active"
                            v-model="planForm.active"
                            type="toggle"
                            label="Product active"
                        />

                    </section>


                    <!-- ACTIONS -->

                    <div
                        class="
                            flex
                            flex-col
                            gap-3
                        "
                    >

                        <div
                            class="
                                flex
                                flex-wrap
                                gap-3
                            "
                        >

                            <Button
                                type="button"
                                text="Cancel"
                                :disabled="saving"
                                @click="closePlanModal"
                                align="right"
                            />

                            <Button
                                type="submit"
                                :text="
                                    editingPlan
                                        ? 'Save changes'
                                        : 'Create plan'
                                "
                                :loading="saving"
                                :loading-text="
                                    editingPlan
                                        ? 'Saving...'
                                        : 'Creating...'
                                "
                                variant="dark"
                                align="right"
                            />

                        </div>

                    </div>

                </form>

            </Modal>


            <Modal
                :open="showCredentialModal"
                title="Generate Project Credential"
                subtitle="Create a server-to-server credential for this SaaS Project."
                aria-label="Generate Project Credential"
                close-label="Close credential form"
                panel-class="
                    border
                    border-accent
                    bg-light
                    shadow-xl
                "
                @close="closeCredentialModal"
            >
                <form
                    class="space-y-6"
                    @submit.prevent="generateProjectCredential"
                >
                    <FormField
                        id="saas-project-credential-name"
                        v-model="credentialName"
                        type="text"
                        label="Credential name"
                        placeholder="Production billing API"
                        required
                        autofocus
                    />

                    <div class="flex flex-col gap-3">
                        <Button
                            type="button"
                            text="Cancel"
                            @click="closeCredentialModal"
                            align="right"
                        />
                        <Button
                            type="submit"
                            text="Generate Credential"
                            variant="dark"
                            :loading="credentialSaving"
                            loading-text="Generating..."
                            align="right"
                        />
                    </div>
                </form>
            </Modal>


            <Modal
                :open="showTokenModal"
                title="Project Credential Created"
                subtitle="Copy this token now. It will not be shown again."
                aria-label="Project Credential Created"
                close-label="Close token dialog"
                panel-class="
                    border
                    border-accent
                    bg-light
                    shadow-xl
                "
                @close="closeTokenModal"
            >
                <div class="space-y-6">

                    <div class="break-all border border-accent bg-accent p-4 font-mono text-xs text-light">
                        {{ generatedProjectToken }}
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Button
                            type="button"
                            text="Copy Token"
                            variant="dark"
                            @click="copyProjectToken"
                            align="right"
                        />
                    </div>
                </div>
            </Modal>


            <AdminConfirmDialog
                :open="Boolean(credentialToRevoke)"
                title="Revoke Project Credential?"
                :text="`
                    Revoke ${credentialToRevoke?.name || 'this credential'}? It will immediately stop working for Billing API requests.
                `"
                confirm-label="Revoke Credential"
                :busy="Boolean(revokingCredentialId)"
                @close="credentialToRevoke = null"
                @confirm="revokeProjectCredential"
            />


            <Modal
                :open="showFeatureModal"
                :title="editingFeature ? 'Edit feature definition' : 'Add feature definition'"
                subtitle="Feature definitions belong to this SaaS Project. Each plan configures its own value for every feature."
                aria-label="Feature definition editor"
                close-label="Close feature definition editor"
                panel-class="
                    border
                    border-accent
                    bg-light
                    shadow-xl
                "
                @close="closeFeatureModal"
            >
                <form
                    class="space-y-6"
                    @submit.prevent="saveFeature"
                >
                    <FormField
                        id="saas-feature-key"
                        v-model="featureForm.key"
                        type="text"
                        label="Key"
                        placeholder="ai_credits_monthly"
                        required
                        :error="errors.key?.[0] || ''"
                    />

                    <FormField
                        id="saas-feature-name"
                        v-model="featureForm.name"
                        type="text"
                        label="Name"
                        placeholder="AI credits / month"
                        required
                        :error="errors.name?.[0] || ''"
                    />

                    <FormField
                        id="saas-feature-description"
                        v-model="featureForm.description"
                        type="textarea"
                        label="Description"
                        :error="errors.description?.[0] || ''"
                    />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField
                            id="saas-feature-type"
                            v-model="featureForm.type"
                            type="select"
                            label="Type"
                            :options="featureTypeOptions"
                            required
                            :error="errors.type?.[0] || ''"
                        />

                        <FormField
                            id="saas-feature-unit"
                            v-model="featureForm.unit"
                            type="text"
                            label="Unit"
                            placeholder="credits"
                            :error="errors.unit?.[0] || ''"
                        />
                    </div>

                    <FormField
                        id="saas-feature-active"
                        v-model="featureForm.active"
                        type="toggle"
                        label="Active"
                    />

                    <div class="flex justify-end gap-3 border-t border-accent pt-5">
                        <Button
                            type="button"
                            text="Cancel"
                            @click="closeFeatureModal"
                        />

                        <Button
                            type="submit"
                            :text="editingFeature ? 'Save feature' : 'Add feature'"
                            variant="dark"
                            :loading="featureSaving"
                            loading-text="Saving..."
                        />
                    </div>
                </form>
            </Modal>


            <AdminConfirmDialog
                :open="Boolean(featureToDelete)"
                title="Delete feature definition?"
                :text="`
                    Delete ${featureToDelete?.name || 'this feature'}? Plans will no longer be able to configure it.
                `"
                confirm-label="Delete feature"
                :busy="Boolean(deletingFeatureId)"
                @close="featureToDelete = null"
                @confirm="deleteFeature"
            />


            <!-- ===================================================== -->
            <!-- DELETE PROJECT CONFIRMATION -->
            <!-- ===================================================== -->

            <AdminConfirmDialog
                :open="showDeleteConfirm"
                title="Discontinue SaaS management?"
                :text="
                    `This will remove ${pageTitle} from SaaS management. The underlying Project and billing history will be kept.`
                "
                confirm-label="Discontinue SaaS"
                :busy="deletingProject"
                @close="
                    showDeleteConfirm = false
                "
                @confirm="confirmDeleteProject"
            />


            <!-- ===================================================== -->
            <!-- DELETE PLAN CONFIRMATION -->
            <!-- ===================================================== -->

            <AdminConfirmDialog
                :open="Boolean(deletingPlan)"
                title="Deactivate plan?"
                :text="
                    `Deactivate ${deletingPlan?.name || 'this plan'} in StudioKristian and Stripe? Existing billing history will be kept.`
                "
                confirm-label="Deactivate plan"
                @close="
                    deletingPlan = null
                "
                @confirm="confirmDeletePlan"
            />

        </template>

    </div>

</template>