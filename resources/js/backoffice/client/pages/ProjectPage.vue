<script setup>
import { computed, ref, watch } from 'vue'

import ServiceFileStructure from '../../admin/components/ServiceFileStructure.vue'
import Info from '@shared/components/Info.vue'
import ClientPageHeader from '../components/ClientPageHeader.vue'

const props = defineProps({
    data: { type: Object, required: true },
    csrfToken: { type: String, required: true },
    locale: { type: String, required: true },
})

const selectedDocumentId = ref(null)

const copy = computed(() => {
    if (props.locale === 'sk') {
        return {
            allProjects: 'Vsetky projekty',
            waitingSignature: 'Caka na vas podpis',
            toDoNow: 'Urobit teraz',
            reviewAndSign: 'Skontrolujte a podpiste tieto dokumenty.',
            projectDetails: 'Detaily projektu',
            signatureRequired: 'Vyziadany podpis',
            company: 'Spolocnost',
            service: 'Sluzba',
            status: 'Stav',
            pendingSignatures: 'Cakajuce podpisy',
            contracts: 'Zmluvy',
            offers: 'Ponuky',
            files: 'Subory',
            documents: 'Dokumenty',
            services: 'Sluzby a pristupy',
            noContracts: 'Ziadne zmluvy nie su dostupne.',
            noOffers: 'Ziadne ponuky nie su dostupne.',
            noFiles: 'Ziadne klientske subory nie su dostupne.',
            noDocuments: 'Ziadne dokumenty nie su dostupne.',
            noServices: 'Ziadne sluzby nie su dostupne.',
            readOnly: 'Len na citanie',
            signed: 'Podpisane',
            noContent: '<p>Obsah nie je k dispozicii.</p>',
            signDocument: 'Podpisat dokument',
            owner: 'Vlastnik',
            billing: 'Fakturacia',
            managedBy: 'Spravuje',
            notSpecified: 'Nezadane',
            signIn: 'Prihlasit sa',
            accessDetails: 'Pristupove udaje sa zdielaju bezpecne.',
            support: 'Podpora',
            noTickets: 'Zatial ste nevytvorili ziadne poziadavky.',
            describeProblem: 'Popiste svoj problem',
            supportHint: 'Napiste nam, co sa stalo a co potrebujete. Ostatne nastavime interne.',
            descriptionPlaceholder: 'Popiste problem',
            sendRequest: 'Poslat poziadavku',
            version: 'Verzia',
            view: 'zobrazit',
            openDocumentHint: 'Dvojklikom otvorite dokument na zobrazenie a podpis.',
        }
    }

    return {
        allProjects: 'All projects',
        waitingSignature: 'Waiting for your signature',
        toDoNow: 'To do now',
        reviewAndSign: 'Please review and sign these documents.',
        projectDetails: 'Project details',
        signatureRequired: 'Signature required',
        company: 'Company',
        service: 'Service',
        status: 'Status',
        pendingSignatures: 'Pending signatures',
        contracts: 'Contracts',
        offers: 'Offers',
        files: 'Files',
        documents: 'Documents',
        services: 'Services and accounts',
        noContracts: 'No contracts are available.',
        noOffers: 'No offers are available.',
        noFiles: 'No client files are available.',
        noDocuments: 'No documents are available.',
        noServices: 'No services are available.',
        readOnly: 'Read only',
        signed: 'Signed',
        noContent: '<p>No content provided.</p>',
        signDocument: 'Sign document',
        owner: 'Owner',
        billing: 'Billing',
        managedBy: 'Managed by',
        notSpecified: 'Not specified',
        signIn: 'Sign in',
        accessDetails: 'Access details are shared securely.',
        support: 'Support',
        noTickets: 'You have not created any requests yet.',
        describeProblem: 'Describe your problem',
        supportHint: 'Tell us what happened and what you need. We will set the details internally.',
        descriptionPlaceholder: 'Describe the problem',
        sendRequest: 'Send request',
        version: 'Version',
        view: 'view',
        openDocumentHint: 'Double-click a document to review and sign.',
    }
})

const documentItems = computed(() => {
    const structured = Array.isArray(props.data.project.document_structure)
        ? props.data.project.document_structure
        : []

    if (structured.length) {
        return structured.map((item) => ({
            id: item.id,
            parent_id: item.parent_id ?? null,
            type: item.type,
            name: item.name,
            resource_type: item.resource_type || (item.type === 'folder' ? 'folder' : 'document'),
            content: item.content || '',
            requires_client_signature: Boolean(item.requires_client_signature),
            requires_signature: Boolean(item.requires_signature),
            signed: Boolean(item.signed),
            can_sign: Boolean(item.can_sign),
            sign_url: item.sign_url || null,
            requirement_level: item.requirement_level || null,
        }))
    }

    return (props.data.project.documents || []).map((document) => ({
        id: document.id,
        parent_id: null,
        type: 'file',
        resource_type: 'document',
        name: document.name,
        content: document.content || '',
        requires_client_signature: Boolean(document.requires_signature),
        requires_signature: Boolean(document.requires_signature),
        signed: Boolean(document.signed),
        can_sign: Boolean(document.can_sign),
        sign_url: document.sign_url || null,
        requirement_level: document.requires_signature ? 'required' : 'recommended',
    }))
})

const selectedDocument = computed(() => {
    const id = selectedDocumentId.value

    if (id === null || id === undefined) {
        return null
    }

    return documentItems.value.find((item) => String(item.id) === String(id) && item.type === 'file') || null
})

watch(
    documentItems,
    (items) => {
        if (!items.length) {
            selectedDocumentId.value = null
            return
        }

        const exists = items.some((item) => String(item.id) === String(selectedDocumentId.value))

        if (exists) {
            return
        }

        const firstDocument = items.find((item) => item.type === 'file')
        selectedDocumentId.value = firstDocument ? firstDocument.id : null
    },
    { immediate: true }
)

function openDocument(item) {
    if (!item || item.type !== 'file') {
        return
    }

    selectedDocumentId.value = item.id
}

const projectDetails = computed(() => [
    {
        heading: copy.value.company,
        text: String(props.data.contact?.company_name || copy.value.notSpecified),
    },
    {
        heading: copy.value.service,
        text: String(props.data.project?.service_name || copy.value.notSpecified),
    },
    {
        heading: copy.value.status,
        text: String(props.data.project?.status || copy.value.notSpecified),
    },
    {
        heading: copy.value.pendingSignatures,
        text: String(props.data.project?.pending_signatures_count || 0),
    },
])

function money(value, currency) {
    return `${new Intl.NumberFormat('sk-SK', { minimumFractionDigits: 2 }).format(value)} ${currency}`
}

function fileSize(bytes) {
    return `${new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 1 }).format(bytes / 1024)} KB`
}

function statusLabel(value) {
    return String(value || '').replaceAll('_', ' ')
}
</script>

<template>
    <section>
        <ClientPageHeader
            :title="data.project.name"
            :eyebrow="data.project.service_name"
            :home-url="data.urls.dashboard"
            :breadcrumbs="[
                { label: copy.allProjects, href: data.urls.dashboard },
                { label: data.project.name }
            ]"
        >
            <p
                v-if="data.project.pending_signatures_count"
                class="font-mono text-xs uppercase text-accent"
            >
                {{ copy.waitingSignature }}: {{ data.project.pending_signatures_count }}
            </p>
            <span class="border border-dark/20 px-3 py-2 font-mono text-xs uppercase">{{ data.project.status }}</span>
        </ClientPageHeader>

        <section
            v-if="data.project.todo_signatures?.length"
            class="mt-6 border border-dark/20 bg-white p-6"
        >
            <h2 class="font-mono text-sm font-bold uppercase">{{ copy.toDoNow }}</h2>
            <p class="mt-2 text-sm text-dark/60">{{ copy.reviewAndSign }}</p>
            <ul class="mt-4 grid gap-2">
                <li
                    v-for="document in data.project.todo_signatures"
                    :key="`todo-${document.id}`"
                    class="flex items-center justify-between gap-3 border border-dark/20 p-3"
                >
                    <span>{{ document.name }}</span>
                    <span class="font-mono text-xs uppercase text-accent">{{ copy.signatureRequired }}</span>
                </li>
            </ul>
        </section>

        <section class="mt-10">
            <h2 class="font-mono text-sm font-bold uppercase">{{ copy.projectDetails }}</h2>
            <div class="mt-4 grid gap-3">
                <Info
                    v-for="(detail, index) in projectDetails"
                    :key="`project-detail-${index}`"
                    :heading="detail.heading"
                    :text="detail.text"
                />
            </div>
        </section>

        <div class="mt-10 grid gap-10 lg:grid-cols-2">
            <section>
                <h2 class="font-mono text-sm font-bold uppercase">{{ copy.contracts }}</h2>
                <div class="mt-4 grid gap-2">
                    <a v-for="contract in data.project.contracts" :key="contract.id" :href="contract.url" class="flex justify-between gap-4 border border-dark/20 bg-white p-4 hover:border-dark">
                        <span><strong>{{ contract.title }}</strong><small class="mt-1 block text-dark/45">{{ copy.version }} {{ contract.version }}</small></span>
                        <span class="font-mono text-xs uppercase">{{ contract.status }}</span>
                    </a>
                    <p v-if="!data.project.contracts.length" class="text-sm text-dark/45">{{ copy.noContracts }}</p>
                </div>
            </section>

            <section>
                <h2 class="font-mono text-sm font-bold uppercase">{{ copy.offers }}</h2>
                <div class="mt-4 grid gap-2">
                    <a v-for="offer in data.project.offers" :key="offer.id" :href="offer.url" class="flex justify-between gap-4 border border-dark/20 bg-white p-4 hover:border-dark">
                        <span><strong>{{ offer.number }}</strong><small class="mt-1 block text-dark/45">{{ copy.version }} {{ offer.version }} · {{ money(offer.total, offer.currency) }}</small></span>
                        <span class="font-mono text-xs uppercase">{{ offer.status }}</span>
                    </a>
                    <p v-if="!data.project.offers.length" class="text-sm text-dark/45">{{ copy.noOffers }}</p>
                </div>
            </section>

            <section>
                <h2 class="font-mono text-sm font-bold uppercase">{{ copy.files }}</h2>
                <div class="mt-4 grid gap-2">
                    <a v-for="file in data.project.files" :key="file.id" :href="file.url" class="flex justify-between gap-4 border border-dark/20 bg-white p-4 hover:border-dark">
                        <span>{{ file.display_name }}</span><span class="text-sm text-dark/45">{{ fileSize(file.size) }} {{ copy.view }}</span>
                    </a>
                    <p v-if="!data.project.files.length" class="text-sm text-dark/45">{{ copy.noFiles }}</p>
                </div>
            </section>

            <section>
                <h2 class="font-mono text-sm font-bold uppercase">{{ copy.documents }}</h2>
                <div class="mt-4 grid gap-4">
                    <ServiceFileStructure
                        :model-value="documentItems"
                        :allow-upload-control="false"
                        :allow-metadata-editing="false"
                        :disabled="true"
                        @open-document="openDocument"
                        @open-file="openDocument"
                    />
                    <p class="text-sm text-dark/55">{{ copy.openDocumentHint }}</p>

                    <article
                        v-if="selectedDocument"
                        class="border border-dark/20 bg-white p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <strong>{{ selectedDocument.name }}</strong>
                            <span class="font-mono text-xs uppercase">
                                {{ selectedDocument.requires_signature ? (selectedDocument.signed ? copy.signed : copy.signatureRequired) : copy.readOnly }}
                            </span>
                        </div>
                        <div
                            class="prose mt-3 max-w-none text-sm"
                            v-html="selectedDocument.content || copy.noContent"
                        />
                        <form
                            v-if="selectedDocument.can_sign"
                            class="mt-4"
                            method="POST"
                            :action="selectedDocument.sign_url"
                        >
                            <input type="hidden" name="_token" :value="csrfToken">
                            <button class="border border-dark bg-dark px-4 py-2 font-mono text-xs font-bold uppercase text-white" type="submit">
                                {{ copy.signDocument }}
                            </button>
                        </form>
                    </article>

                    <p v-if="!documentItems.length" class="text-sm text-dark/45">{{ copy.noDocuments }}</p>
                </div>
            </section>

            <section>
                <h2 class="font-mono text-sm font-bold uppercase">{{ copy.services }}</h2>
                <div class="mt-4 grid gap-2">
                    <article v-for="service in data.project.services" :key="service.id" class="border border-dark/20 bg-white p-4">
                        <strong>{{ service.name }}</strong>
                        <p class="mt-2 text-xs text-dark/45">{{ copy.owner }}: {{ service.account_owner }} · {{ copy.billing }}: {{ service.billing_owner || copy.notSpecified }} · {{ copy.managedBy }}: {{ service.renewal_responsibility || copy.notSpecified }}</p>
                        <a v-if="service.login_url" :href="service.login_url" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-sm underline">{{ copy.signIn }} ↗</a>
                        <p class="mt-3 text-sm">{{ service.access_instructions || copy.accessDetails }}</p>
                    </article>
                    <p v-if="!data.project.services.length" class="text-sm text-dark/45">{{ copy.noServices }}</p>
                </div>
            </section>
        </div>

        <section class="mt-12 border-t border-dark/20 pt-10">
            <h2 class="font-mono text-sm font-bold uppercase">{{ copy.support }}</h2>
            <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="grid content-start gap-2">
                    <article v-for="ticket in data.project.tickets" :key="ticket.id" class="border border-dark/20 bg-white p-4">
                        <div class="flex justify-between gap-4"><strong>{{ ticket.title }}</strong><span class="font-mono text-xs uppercase">{{ statusLabel(ticket.status) }}</span></div>
                        <p class="mt-2 text-sm text-dark/60">{{ ticket.description }}</p>
                    </article>
                    <p v-if="!data.project.tickets.length" class="text-sm text-dark/45">{{ copy.noTickets }}</p>
                </div>
                <form class="border border-dark/20 bg-white p-5" method="POST" :action="data.project.ticket_url">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <strong class="font-mono text-sm uppercase">{{ copy.describeProblem }}</strong>
                    <p class="mt-2 text-sm text-dark/60">{{ copy.supportHint }}</p>
                    <textarea class="mt-3 min-h-32 w-full border border-dark/30 p-3" name="description" :placeholder="copy.descriptionPlaceholder" required></textarea>
                    <button class="mt-4 border border-dark bg-dark px-4 py-3 font-mono text-xs font-bold uppercase text-white" type="submit">{{ copy.sendRequest }}</button>
                </form>
            </div>
        </section>
    </section>
</template>
