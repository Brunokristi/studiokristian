<script setup>
import {
    computed,
    ref,
    watch
} from 'vue'


import ServiceFileStructure from '../../admin/components/ServiceFileStructure.vue'
import Info from '@shared/components/Info.vue'
import ClientPageHeader from '../components/ClientPageHeader.vue'


const props = defineProps({
    data: {
        type: Object,
        required: true
    },

    csrfToken: {
        type: String,
        required: true
    },

    locale: {
        type: String,
        required: true
    }
})


const selectedDocumentId =
    ref(null)


/*
|--------------------------------------------------------------------------
| Copy
|--------------------------------------------------------------------------
*/

const copy =
    computed(() => {
        if (
            props.locale === 'sk'
        ) {
            return {
                allProjects:
                    'Všetky projekty',

                waitingSignature:
                    'Čaká na váš podpis',

                toDoNow:
                    'Vyžaduje vašu pozornosť',

                reviewAndSign:
                    'Skontrolujte a podpíšte tieto dokumenty.',

                projectDetails:
                    'Detaily projektu',

                signatureRequired:
                    'Vyžaduje podpis',

                company:
                    'Spoločnosť',

                service:
                    'Služba',

                status:
                    'Stav',

                pendingSignatures:
                    'Čakajúce podpisy',

                contracts:
                    'Zmluvy',

                offers:
                    'Ponuky',

                files:
                    'Súbory',

                documents:
                    'Dokumenty',

                services:
                    'Služby a prístupy',

                support:
                    'Podpora',

                noContracts:
                    'Nie sú dostupné žiadne zmluvy.',

                noOffers:
                    'Nie sú dostupné žiadne ponuky.',

                noFiles:
                    'Nie sú dostupné žiadne klientske súbory.',

                noDocuments:
                    'Nie sú dostupné žiadne dokumenty.',

                noServices:
                    'Nie sú dostupné žiadne služby.',

                readOnly:
                    'Len na čítanie',

                signed:
                    'Podpísané',

                noContent:
                    '<p>Obsah nie je k dispozícii.</p>',

                signDocument:
                    'Podpísať dokument',

                owner:
                    'Vlastník',

                billing:
                    'Fakturácia',

                managedBy:
                    'Spravuje',

                notSpecified:
                    'Neuvedené',

                signIn:
                    'Prihlásiť sa',

                accessDetails:
                    'Prístupové údaje sú zdieľané bezpečne.',

                noTickets:
                    'Zatiaľ ste nevytvorili žiadne požiadavky.',

                describeProblem:
                    'Popíšte, čo potrebujete',

                supportHint:
                    'Napíšte nám, čo sa stalo a čo potrebujete. Ostatné nastavíme interne.',

                descriptionPlaceholder:
                    'Popíšte svoju požiadavku',

                sendRequest:
                    'Odoslať požiadavku',

                version:
                    'Verzia',

                view:
                    'zobraziť',

                openDocumentHint:
                    'Dvojklikom otvoríte dokument na zobrazenie a podpis.',

                noActions:
                    'Momentálne nie je potrebná žiadna akcia.'
            }
        }


        return {
            allProjects:
                'All projects',

            waitingSignature:
                'Waiting for your signature',

            toDoNow:
                'Action required',

            reviewAndSign:
                'Please review and sign these documents.',

            projectDetails:
                'Project details',

            signatureRequired:
                'Signature required',

            company:
                'Company',

            service:
                'Service',

            status:
                'Status',

            pendingSignatures:
                'Pending signatures',

            contracts:
                'Contracts',

            offers:
                'Offers',

            files:
                'Files',

            documents:
                'Documents',

            services:
                'Services and access',

            support:
                'Support',

            noContracts:
                'No contracts are available.',

            noOffers:
                'No offers are available.',

            noFiles:
                'No client files are available.',

            noDocuments:
                'No documents are available.',

            noServices:
                'No services are available.',

            readOnly:
                'Read only',

            signed:
                'Signed',

            noContent:
                '<p>No content provided.</p>',

            signDocument:
                'Sign document',

            owner:
                'Owner',

            billing:
                'Billing',

            managedBy:
                'Managed by',

            notSpecified:
                'Not specified',

            signIn:
                'Sign in',

            accessDetails:
                'Access details are shared securely.',

            noTickets:
                'You have not created any requests yet.',

            describeProblem:
                'Describe what you need',

            supportHint:
                'Tell us what happened and what you need. We will handle the details internally.',

            descriptionPlaceholder:
                'Describe your request',

            sendRequest:
                'Send request',

            version:
                'Version',

            view:
                'view',

            openDocumentHint:
                'Double-click a document to review and sign.',

            noActions:
                'No action is currently required.'
        }
    })


/*
|--------------------------------------------------------------------------
| Documents
|--------------------------------------------------------------------------
*/

const documentItems =
    computed(() => {
        const structured =
            Array.isArray(
                props.data.project.document_structure
            )
                ? props.data.project.document_structure
                : []


        if (
            structured.length
        ) {
            return structured.map(
                item => ({
                    id:
                        item.id,

                    parent_id:
                        item.parent_id ??
                        null,

                    type:
                        item.type,

                    name:
                        item.name,

                    resource_type:
                        item.resource_type ||
                        (
                            item.type ===
                            'folder'
                                ? 'folder'
                                : 'document'
                        ),

                    content:
                        item.content ||
                        '',

                    requires_client_signature:
                        Boolean(
                            item.requires_client_signature
                        ),

                    requires_signature:
                        Boolean(
                            item.requires_signature
                        ),

                    signed:
                        Boolean(
                            item.signed
                        ),

                    can_sign:
                        Boolean(
                            item.can_sign
                        ),

                    sign_url:
                        item.sign_url ||
                        null,

                    requirement_level:
                        item.requirement_level ||
                        null
                })
            )
        }


        return (
            props.data.project.documents ||
            []
        ).map(
            document => ({
                id:
                    document.id,

                parent_id:
                    null,

                type:
                    'file',

                resource_type:
                    'document',

                name:
                    document.name,

                content:
                    document.content ||
                    '',

                requires_client_signature:
                    Boolean(
                        document.requires_signature
                    ),

                requires_signature:
                    Boolean(
                        document.requires_signature
                    ),

                signed:
                    Boolean(
                        document.signed
                    ),

                can_sign:
                    Boolean(
                        document.can_sign
                    ),

                sign_url:
                    document.sign_url ||
                    null,

                requirement_level:
                    document.requires_signature
                        ? 'required'
                        : 'recommended'
            })
        )
    })


const selectedDocument =
    computed(() => {
        const id =
            selectedDocumentId.value


        if (
            id === null ||
            id === undefined
        ) {
            return null
        }


        return documentItems.value.find(
            item =>
                String(item.id) ===
                    String(id) &&
                item.type === 'file'
        ) || null
    })


watch(
    documentItems,
    items => {
        if (
            !items.length
        ) {
            selectedDocumentId.value =
                null

            return
        }


        const exists =
            items.some(
                item =>
                    String(item.id) ===
                    String(
                        selectedDocumentId.value
                    )
            )


        if (
            exists
        ) {
            return
        }


        const firstDocument =
            items.find(
                item =>
                    item.type ===
                    'file'
            )


        selectedDocumentId.value =
            firstDocument
                ? firstDocument.id
                : null
    },
    {
        immediate: true
    }
)


function openDocument(
    item
) {
    if (
        !item ||
        item.type !== 'file'
    ) {
        return
    }


    selectedDocumentId.value =
        item.id
}


/*
|--------------------------------------------------------------------------
| Project details
|--------------------------------------------------------------------------
*/

const projectDetails =
    computed(() => [
        {
            heading:
                copy.value.company,

            text:
                String(
                    props.data.contact?.company_name ||
                    copy.value.notSpecified
                )
        },

        {
            heading:
                copy.value.service,

            text:
                String(
                    props.data.project?.service_name ||
                    copy.value.notSpecified
                )
        },

        {
            heading:
                copy.value.status,

            text:
                String(
                    props.data.project?.status ||
                    copy.value.notSpecified
                )
        },

        {
            heading:
                copy.value.pendingSignatures,

            text:
                String(
                    props.data.project?.pending_signatures_count ||
                    0
                )
        }
    ])


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function money(
    value,
    currency
) {
    return `${new Intl.NumberFormat(
        'sk-SK',
        {
            minimumFractionDigits: 2
        }
    ).format(value)} ${currency}`
}


function fileSize(
    bytes
) {
    return `${new Intl.NumberFormat(
        'sk-SK',
        {
            maximumFractionDigits: 1
        }
    ).format(
        bytes / 1024
    )} KB`
}


function statusLabel(
    value
) {
    return String(
        value || ''
    ).replaceAll(
        '_',
        ' '
    )
}
</script>


<template>
    <div
        class="
            w-full
            space-y-12
            lg:space-y-14
        "
    >
        <!--
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        -->

        <ClientPageHeader
            :title="
                data.project.name
            "
            :eyebrow="
                data.project.service_name
            "
            :home-url="
                data.urls.dashboard
            "
            :breadcrumbs="[
                {
                    label:
                        copy.allProjects,

                    href:
                        data.urls.dashboard
                },

                {
                    label:
                        data.project.name
                }
            ]"
        >
            <div
                class="
                    flex
                    flex-wrap
                    items-center
                    gap-4
                "
            >
                <p
                    v-if="
                        data.project
                            .pending_signatures_count
                    "
                    class="
                        font-mono
                        text-xs
                        uppercase
                        text-accent
                    "
                >
                    {{
                        copy.waitingSignature
                    }}:

                    {{
                        data.project
                            .pending_signatures_count
                    }}
                </p>

                <span
                    class="
                        border
                        border-dark/20
                        px-3
                        py-2
                        font-mono
                        text-xs
                        uppercase
                    "
                >
                    {{
                        data.project.status
                    }}
                </span>
            </div>
        </ClientPageHeader>


        <!--
        |--------------------------------------------------------------------------
        | Required actions
        |--------------------------------------------------------------------------
        -->

        <section
            v-if="
                data.project
                    .todo_signatures?.length
            "
        >
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.toDoNow
                    }}
                </h2>

                <p
                    class="
                        mt-2
                        text-sm
                        text-dark/60
                    "
                >
                    {{
                        copy.reviewAndSign
                    }}
                </p>
            </div>

            <div
                class="
                    mt-5
                    grid
                    gap-2
                "
            >
                <button
                    v-for="
                        document in data.project.todo_signatures
                    "
                    :key="
                        `todo-${document.id}`
                    "
                    type="button"
                    class="
                        flex
                        w-full
                        items-center
                        justify-between
                        gap-4
                        border-b
                        border-dark/20
                        py-4
                        text-left
                        transition-colors
                        hover:bg-accent
                        hover:px-4
                        hover:text-light
                    "
                    @click="
                        openDocument(
                            {
                                id:
                                    document.id,

                                type:
                                    'file'
                            }
                        )
                    "
                >
                    <span
                        class="
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                        "
                    >
                        {{
                            document.name
                        }}
                    </span>

                    <span
                        class="
                            shrink-0
                            font-mono
                            text-[10px]
                            font-bold
                            uppercase
                            text-accent
                        "
                    >
                        {{
                            copy.signatureRequired
                        }}
                    </span>
                </button>
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Project details
        |--------------------------------------------------------------------------
        -->

        <section>
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.projectDetails
                    }}
                </h2>
            </div>

            <div
                class="
                    mt-5
                    grid
                    gap-2
                "
            >
                <Info
                    v-for="
                        (
                            detail,
                            index
                        ) in projectDetails
                    "
                    :key="
                        `project-detail-${index}`
                    "
                    :heading="
                        detail.heading
                    "
                    :text="
                        detail.text
                    "
                />
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Contracts
        |--------------------------------------------------------------------------
        -->

        <section>
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.contracts
                    }}
                </h2>
            </div>

            <div
                class="
                    mt-5
                    grid
                    gap-2
                "
            >
                <a
                    v-for="
                        contract in data.project.contracts
                    "
                    :key="
                        contract.id
                    "
                    :href="
                        contract.url
                    "
                    class="
                        flex
                        items-center
                        justify-between
                        gap-4
                        border-b
                        border-dark/20
                        py-4
                        transition-colors
                        hover:bg-accent
                        hover:px-4
                        hover:text-light
                    "
                >
                    <span>
                        <strong
                            class="
                                font-mono
                                text-xs
                                uppercase
                            "
                        >
                            {{
                                contract.title
                            }}
                        </strong>

                        <small
                            class="
                                mt-1
                                block
                                text-dark/45
                            "
                        >
                            {{
                                copy.version
                            }}

                            {{
                                contract.version
                            }}
                        </small>
                    </span>

                    <span
                        class="
                            shrink-0
                            font-mono
                            text-[10px]
                            uppercase
                        "
                    >
                        {{
                            contract.status
                        }}
                    </span>
                </a>

                <p
                    v-if="
                        !data.project.contracts.length
                    "
                    class="
                        py-4
                        text-sm
                        text-dark/45
                    "
                >
                    {{
                        copy.noContracts
                    }}
                </p>
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Offers
        |--------------------------------------------------------------------------
        -->

        <section>
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.offers
                    }}
                </h2>
            </div>

            <div
                class="
                    mt-5
                    grid
                    gap-2
                "
            >
                <a
                    v-for="
                        offer in data.project.offers
                    "
                    :key="
                        offer.id
                    "
                    :href="
                        offer.url
                    "
                    class="
                        flex
                        items-center
                        justify-between
                        gap-4
                        border-b
                        border-dark/20
                        py-4
                        transition-colors
                        hover:bg-accent
                        hover:px-4
                        hover:text-light
                    "
                >
                    <span>
                        <strong
                            class="
                                font-mono
                                text-xs
                                uppercase
                            "
                        >
                            {{
                                offer.number
                            }}
                        </strong>

                        <small
                            class="
                                mt-1
                                block
                                text-dark/45
                            "
                        >
                            {{
                                copy.version
                            }}

                            {{
                                offer.version
                            }}

                            ·

                            {{
                                money(
                                    offer.total,
                                    offer.currency
                                )
                            }}
                        </small>
                    </span>

                    <span
                        class="
                            shrink-0
                            font-mono
                            text-[10px]
                            uppercase
                        "
                    >
                        {{
                            offer.status
                        }}
                    </span>
                </a>

                <p
                    v-if="
                        !data.project.offers.length
                    "
                    class="
                        py-4
                        text-sm
                        text-dark/45
                    "
                >
                    {{
                        copy.noOffers
                    }}
                </p>
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Files
        |--------------------------------------------------------------------------
        -->

        <section>
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.files
                    }}
                </h2>
            </div>

            <div
                class="
                    mt-5
                    grid
                    gap-2
                "
            >
                <a
                    v-for="
                        file in data.project.files
                    "
                    :key="
                        file.id
                    "
                    :href="
                        file.url
                    "
                    class="
                        flex
                        items-center
                        justify-between
                        gap-4
                        border-b
                        border-dark/20
                        py-4
                        transition-colors
                        hover:bg-accent
                        hover:px-4
                        hover:text-light
                    "
                >
                    <span
                        class="
                            font-mono
                            text-xs
                            uppercase
                        "
                    >
                        {{
                            file.display_name
                        }}
                    </span>

                    <span
                        class="
                            shrink-0
                            text-xs
                            text-dark/45
                        "
                    >
                        {{
                            fileSize(
                                file.size
                            )
                        }}

                        ·

                        {{
                            copy.view
                        }}
                    </span>
                </a>

                <p
                    v-if="
                        !data.project.files.length
                    "
                    class="
                        py-4
                        text-sm
                        text-dark/45
                    "
                >
                    {{
                        copy.noFiles
                    }}
                </p>
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        -->

        <section>
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.documents
                    }}
                </h2>
            </div>

            <div
                class="
                    mt-5
                "
            >
                <ServiceFileStructure
                    :model-value="
                        documentItems
                    "
                    :allow-upload-control="
                        false
                    "
                    :allow-metadata-editing="
                        false
                    "
                    :disabled="
                        true
                    "
                    @open-document="
                        openDocument
                    "
                    @open-file="
                        openDocument
                    "
                />

                <p
                    class="
                        mt-3
                        text-xs
                        text-dark/45
                    "
                >
                    {{
                        copy.openDocumentHint
                    }}
                </p>

                <article
                    v-if="
                        selectedDocument
                    "
                    class="
                        mt-6
                        border-t
                        border-accent
                        pt-5
                    "
                >
                    <div
                        class="
                            flex
                            flex-wrap
                            items-start
                            justify-between
                            gap-3
                        "
                    >
                        <strong
                            class="
                                font-mono
                                text-xs
                                uppercase
                            "
                        >
                            {{
                                selectedDocument.name
                            }}
                        </strong>

                        <span
                            class="
                                font-mono
                                text-[10px]
                                uppercase
                                text-accent
                            "
                        >
                            {{
                                selectedDocument.requires_signature
                                    ? (
                                        selectedDocument.signed
                                            ? copy.signed
                                            : copy.signatureRequired
                                    )
                                    : copy.readOnly
                            }}
                        </span>
                    </div>

                    <div
                        class="
                            prose
                            mt-5
                            max-w-none
                            text-sm
                        "
                        v-html="
                            selectedDocument.content ||
                            copy.noContent
                        "
                    />

                    <form
                        v-if="
                            selectedDocument.can_sign
                        "
                        class="
                            mt-6
                        "
                        method="POST"
                        :action="
                            selectedDocument.sign_url
                        "
                    >
                        <input
                            type="hidden"
                            name="_token"
                            :value="
                                csrfToken
                            "
                        >

                        <button
                            class="
                                border
                                border-dark
                                bg-dark
                                px-4
                                py-3
                                font-mono
                                text-xs
                                font-bold
                                uppercase
                                text-white
                                transition-colors
                                hover:bg-accent
                                hover:border-accent
                            "
                            type="submit"
                        >
                            {{
                                copy.signDocument
                            }}
                        </button>
                    </form>
                </article>

                <p
                    v-if="
                        !documentItems.length
                    "
                    class="
                        py-4
                        text-sm
                        text-dark/45
                    "
                >
                    {{
                        copy.noDocuments
                    }}
                </p>
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Services
        |--------------------------------------------------------------------------
        -->

        <section>
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.services
                    }}
                </h2>
            </div>

            <div
                class="
                    mt-5
                    grid
                    gap-2
                "
            >
                <article
                    v-for="
                        service in data.project.services
                    "
                    :key="
                        service.id
                    "
                    class="
                        border-b
                        border-dark/20
                        py-5
                    "
                >
                    <div
                        class="
                            flex
                            flex-wrap
                            items-start
                            justify-between
                            gap-4
                        "
                    >
                        <strong
                            class="
                                font-mono
                                text-xs
                                uppercase
                            "
                        >
                            {{
                                service.name
                            }}
                        </strong>

                        <a
                            v-if="
                                service.login_url
                            "
                            :href="
                                service.login_url
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="
                                font-mono
                                text-[10px]
                                font-bold
                                uppercase
                                underline
                                transition-colors
                                hover:text-accent
                            "
                        >
                            {{
                                copy.signIn
                            }}

                            ↗
                        </a>
                    </div>

                    <p
                        class="
                            mt-2
                            text-xs
                            text-dark/45
                        "
                    >
                        {{
                            copy.owner
                        }}:

                        {{
                            service.account_owner
                        }}

                        ·

                        {{
                            copy.billing
                        }}:

                        {{
                            service.billing_owner ||
                            copy.notSpecified
                        }}

                        ·

                        {{
                            copy.managedBy
                        }}:

                        {{
                            service.renewal_responsibility ||
                            copy.notSpecified
                        }}
                    </p>

                    <p
                        class="
                            mt-3
                            max-w-3xl
                            text-sm
                            text-dark/70
                        "
                    >
                        {{
                            service.access_instructions ||
                            copy.accessDetails
                        }}
                    </p>
                </article>

                <p
                    v-if="
                        !data.project.services.length
                    "
                    class="
                        py-4
                        text-sm
                        text-dark/45
                    "
                >
                    {{
                        copy.noServices
                    }}
                </p>
            </div>
        </section>


        <!--
        |--------------------------------------------------------------------------
        | Support
        |--------------------------------------------------------------------------
        -->

        <section>
            <div
                class="
                    border-t
                    border-accent
                    pt-5
                "
            >
                <h2
                    class="
                        font-mono
                        text-sm
                        font-bold
                        uppercase
                    "
                >
                    {{
                        copy.support
                    }}
                </h2>
            </div>

            <div
                class="
                    mt-5
                    grid
                    gap-8
                    lg:grid-cols-[minmax(0,1fr)_360px]
                "
            >
                <div
                    class="
                        grid
                        content-start
                        gap-2
                    "
                >
                    <article
                        v-for="
                            ticket in data.project.tickets
                        "
                        :key="
                            ticket.id
                        "
                        class="
                            border-b
                            border-dark/20
                            py-5
                        "
                    >
                        <div
                            class="
                                flex
                                flex-wrap
                                justify-between
                                gap-4
                            "
                        >
                            <strong
                                class="
                                    font-mono
                                    text-xs
                                    uppercase
                                "
                            >
                                {{
                                    ticket.title
                                }}
                            </strong>

                            <span
                                class="
                                    font-mono
                                    text-[10px]
                                    uppercase
                                "
                            >
                                {{
                                    statusLabel(
                                        ticket.status
                                    )
                                }}
                            </span>
                        </div>

                        <p
                            class="
                                mt-2
                                text-sm
                                text-dark/60
                            "
                        >
                            {{
                                ticket.description
                            }}
                        </p>
                    </article>

                    <p
                        v-if="
                            !data.project.tickets.length
                        "
                        class="
                            py-4
                            text-sm
                            text-dark/45
                        "
                    >
                        {{
                            copy.noTickets
                        }}
                    </p>
                </div>


                <form
                    class="
                        h-fit
                        border-t
                        border-accent
                        pt-5
                    "
                    method="POST"
                    :action="
                        data.project.ticket_url
                    "
                >
                    <strong
                        class="
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                        "
                    >
                        {{
                            copy.describeProblem
                        }}
                    </strong>

                    <p
                        class="
                            mt-2
                            text-sm
                            text-dark/60
                        "
                    >
                        {{
                            copy.supportHint
                        }}
                    </p>

                    <textarea
                        class="
                            mt-4
                            min-h-32
                            w-full
                            resize-y
                            border
                            border-dark/30
                            bg-transparent
                            p-3
                            text-sm
                            outline-none
                            transition-colors
                            focus:border-accent
                            focus:ring-0
                        "
                        name="description"
                        :placeholder="
                            copy.descriptionPlaceholder
                        "
                        required
                    />

                    <input
                        type="hidden"
                        name="_token"
                        :value="
                            csrfToken
                        "
                    >

                    <button
                        class="
                            mt-4
                            border
                            border-dark
                            bg-dark
                            px-4
                            py-3
                            font-mono
                            text-xs
                            font-bold
                            uppercase
                            text-white
                            transition-colors
                            hover:border-accent
                            hover:bg-accent
                        "
                        type="submit"
                    >
                        {{
                            copy.sendRequest
                        }}
                    </button>
                </form>
            </div>
        </section>
    </div>
</template>