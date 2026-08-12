<script setup>
defineProps({
    data: { type: Object, required: true },
    csrfToken: { type: String, required: true },
})

function money(value, currency) {
    return `${new Intl.NumberFormat('sk-SK', { minimumFractionDigits: 2 }).format(value)} ${currency}`
}

function fileSize(bytes) {
    return `${new Intl.NumberFormat('sk-SK', { maximumFractionDigits: 1 }).format(bytes / 1024)} KB`
}
</script>

<template>
    <section>
        <a :href="data.urls.dashboard" class="font-mono text-xs uppercase text-dark/45 hover:text-dark">← All projects</a>
        <div class="mt-8 flex flex-wrap items-end justify-between gap-5 border-b border-dark/20 pb-8">
            <div>
                <p class="font-mono text-xs font-bold uppercase text-dark/45">{{ data.project.service_name }}</p>
                <h1 class="mt-3 font-mono text-4xl font-bold uppercase sm:text-5xl">{{ data.project.name }}</h1>
            </div>
            <span class="border border-dark/20 px-3 py-2 font-mono text-xs uppercase">{{ data.project.status }}</span>
        </div>

        <div class="mt-10 grid gap-10 lg:grid-cols-2">
            <section>
                <h2 class="font-mono text-sm font-bold uppercase">Contracts</h2>
                <div class="mt-4 grid gap-2">
                    <a v-for="contract in data.project.contracts" :key="contract.id" :href="contract.url" class="flex justify-between gap-4 border border-dark/20 bg-white p-4 hover:border-dark">
                        <span><strong>{{ contract.title }}</strong><small class="mt-1 block text-dark/45">Version {{ contract.version }}</small></span>
                        <span class="font-mono text-xs uppercase">{{ contract.status }}</span>
                    </a>
                    <p v-if="!data.project.contracts.length" class="text-sm text-dark/45">No contracts are available.</p>
                </div>
            </section>

            <section>
                <h2 class="font-mono text-sm font-bold uppercase">Offers</h2>
                <div class="mt-4 grid gap-2">
                    <a v-for="offer in data.project.offers" :key="offer.id" :href="offer.url" class="flex justify-between gap-4 border border-dark/20 bg-white p-4 hover:border-dark">
                        <span><strong>{{ offer.number }}</strong><small class="mt-1 block text-dark/45">Version {{ offer.version }} · {{ money(offer.total, offer.currency) }}</small></span>
                        <span class="font-mono text-xs uppercase">{{ offer.status }}</span>
                    </a>
                    <p v-if="!data.project.offers.length" class="text-sm text-dark/45">No offers are available.</p>
                </div>
            </section>

            <section>
                <h2 class="font-mono text-sm font-bold uppercase">Files</h2>
                <div class="mt-4 grid gap-2">
                    <a v-for="file in data.project.files" :key="file.id" :href="file.url" class="flex justify-between gap-4 border border-dark/20 bg-white p-4 hover:border-dark">
                        <span>{{ file.display_name }}</span><span class="text-sm text-dark/45">{{ fileSize(file.size) }} ↓</span>
                    </a>
                    <p v-if="!data.project.files.length" class="text-sm text-dark/45">No client files are available.</p>
                </div>
            </section>

            <section>
                <h2 class="font-mono text-sm font-bold uppercase">Services and accounts</h2>
                <div class="mt-4 grid gap-2">
                    <article v-for="service in data.project.services" :key="service.id" class="border border-dark/20 bg-white p-4">
                        <strong>{{ service.name }}</strong>
                        <p class="mt-2 text-xs text-dark/45">Owner: {{ service.account_owner }} · Billing: {{ service.billing_owner || 'Not specified' }} · Managed by: {{ service.renewal_responsibility || 'Not specified' }}</p>
                        <a v-if="service.login_url" :href="service.login_url" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-sm underline">Sign in ↗</a>
                        <p class="mt-3 text-sm">{{ service.access_instructions || 'Access details are shared securely.' }}</p>
                    </article>
                    <p v-if="!data.project.services.length" class="text-sm text-dark/45">No services are available.</p>
                </div>
            </section>
        </div>

        <section class="mt-12 border-t border-dark/20 pt-10">
            <h2 class="font-mono text-sm font-bold uppercase">Support</h2>
            <div class="mt-4 grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="grid content-start gap-2">
                    <article v-for="ticket in data.project.tickets" :key="ticket.id" class="border border-dark/20 bg-white p-4">
                        <div class="flex justify-between gap-4"><strong>{{ ticket.title }}</strong><span class="font-mono text-xs uppercase">{{ ticket.status.replace('_', ' ') }}</span></div>
                        <p class="mt-2 text-sm text-dark/60">{{ ticket.description }}</p>
                    </article>
                    <p v-if="!data.project.tickets.length" class="text-sm text-dark/45">You have not created any requests yet.</p>
                </div>
                <form class="border border-dark/20 bg-white p-5" method="POST" :action="data.project.ticket_url">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <strong class="font-mono text-sm uppercase">New request</strong>
                    <input class="mt-4 w-full border border-dark/30 p-3" name="title" placeholder="Subject" required>
                    <textarea class="mt-3 min-h-28 w-full border border-dark/30 p-3" name="description" placeholder="Describe the issue" required></textarea>
                    <select class="mt-3 w-full border border-dark/30 p-3" name="priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select>
                    <button class="mt-4 border border-dark bg-dark px-4 py-3 font-mono text-xs font-bold uppercase text-white" type="submit">Send request</button>
                </form>
            </div>
        </section>
    </section>
</template>
