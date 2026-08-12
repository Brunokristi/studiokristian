<script setup>
const props = defineProps({
    data: { type: Object, required: true },
    csrfToken: { type: String, required: true },
})

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
const canAccept = props.data.contact.can_accept_documents && ['sent', 'viewed'].includes(props.data.offer.status)

function money(value) {
    return `${new Intl.NumberFormat('sk-SK', { minimumFractionDigits: 2 }).format(value)} ${props.data.offer.currency}`
}
</script>

<template>
    <article>
        <a :href="data.offer.project.url" class="font-mono text-xs uppercase text-dark/45 hover:text-dark">← {{ data.offer.project.name }}</a>
        <header class="mt-8 flex flex-wrap items-end justify-between gap-5 border-b border-dark/20 pb-8">
            <div>
                <p class="font-mono text-xs font-bold uppercase text-dark/45">Price offer · Version {{ data.offer.version }}</p>
                <h1 class="mt-3 font-mono text-4xl font-bold uppercase sm:text-5xl">{{ data.offer.number }}</h1>
            </div>
            <a v-if="data.offer.download_url" :href="data.offer.download_url" class="border border-dark px-4 py-3 font-mono text-xs font-bold uppercase hover:bg-dark hover:text-white">Download PDF ↓</a>
        </header>

        <div class="mt-8 overflow-x-auto border border-dark/20 bg-white">
            <table class="w-full min-w-[620px] border-collapse text-sm">
                <thead><tr><th class="p-4 text-left">Item</th><th class="p-4 text-right">Quantity</th><th class="p-4 text-right">Price</th></tr></thead>
                <tbody>
                    <tr v-for="item in data.offer.items" :key="item.id" class="border-t border-dark/15">
                        <td class="p-4"><strong>{{ item.name }}</strong><p v-if="item.description" class="mt-1 text-dark/45">{{ item.description }}</p></td>
                        <td class="p-4 text-right">{{ item.quantity }} {{ item.unit }}</td>
                        <td class="p-4 text-right">{{ money(item.total) }}</td>
                    </tr>
                </tbody>
                <tfoot><tr class="border-t-2 border-dark"><th colspan="2" class="p-4 text-right">Total</th><th class="p-4 text-right">{{ money(data.offer.total) }}</th></tr></tfoot>
            </table>
        </div>

        <section v-if="data.offer.status === 'accepted'" class="mt-5 border border-dark/20 border-t-[6px] border-t-dark bg-white p-6">
            <h2 class="font-mono text-2xl font-bold uppercase">Price offer accepted</h2>
            <p class="mt-4 text-sm">{{ data.offer.acceptance.signer_name }} for {{ data.offer.project.company_name }} · {{ new Date(data.offer.accepted_at).toLocaleString() }}</p>
        </section>

        <section v-else-if="['sent', 'viewed'].includes(data.offer.status)" class="mt-5 border border-dark/20 bg-white p-6">
            <form v-if="canAccept" method="POST" :action="data.offer.accept_url">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="request_identifier" :value="data.offer.request_identifier">
                <input type="hidden" name="timezone" :value="timezone">
                <label class="my-4 flex items-start gap-3 text-sm"><input class="mt-1" type="checkbox" name="read_and_agreed" value="1" required><span>I have read the entire offer and agree to its terms.</span></label>
                <label class="my-4 flex items-start gap-3 text-sm"><input class="mt-1" type="checkbox" name="authorized_to_act" value="1" required><span>I confirm that I am authorized to act for {{ data.offer.project.company_name }}.</span></label>
                <button class="mt-3 border border-dark bg-dark px-4 py-3 font-mono text-xs font-bold uppercase text-white" type="submit">Accept price offer</button>
            </form>
            <p v-else class="text-sm text-dark/45">You do not have permission to accept this document.</p>
        </section>
    </article>
</template>
