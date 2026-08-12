<script setup>
const props = defineProps({
    data: { type: Object, required: true },
    csrfToken: { type: String, required: true },
})

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
const canAccept = props.data.contact.can_accept_documents && ['sent', 'viewed'].includes(props.data.contract.status)
</script>

<template>
    <article>
        <a :href="data.contract.project.url" class="font-mono text-xs uppercase text-dark/45 hover:text-dark">← {{ data.contract.project.name }}</a>
        <header class="mt-8 flex flex-wrap items-end justify-between gap-5 border-b border-dark/20 pb-8">
            <div>
                <p class="font-mono text-xs font-bold uppercase text-dark/45">Contract · Version {{ data.contract.version }}</p>
                <h1 class="mt-3 font-mono text-4xl font-bold uppercase sm:text-5xl">{{ data.contract.title }}</h1>
            </div>
            <a :href="data.contract.download_url" class="border border-dark px-4 py-3 font-mono text-xs font-bold uppercase hover:bg-dark hover:text-white">Download PDF ↓</a>
        </header>

        <div class="mt-8 whitespace-pre-wrap border border-dark/20 bg-white p-6 text-sm leading-7 sm:p-10">{{ data.contract.rendered_content }}</div>

        <section v-if="data.contract.status === 'accepted'" class="mt-5 border border-dark/20 border-t-[6px] border-t-dark bg-white p-6">
            <p class="font-mono text-xs uppercase text-dark/45">Completed</p>
            <h2 class="mt-2 font-mono text-2xl font-bold uppercase">Contract accepted</h2>
            <dl class="mt-6 grid grid-cols-[130px_1fr] gap-3 text-sm">
                <dt class="text-dark/45">Accepted by</dt><dd>{{ data.contract.acceptance.signer_name }} for {{ data.contract.project.company_name }}</dd>
                <dt class="text-dark/45">Date and time</dt><dd>{{ new Date(data.contract.accepted_at).toLocaleString() }}</dd>
                <dt class="text-dark/45">Version</dt><dd>{{ data.contract.version }}</dd>
            </dl>
        </section>

        <section v-else-if="['sent', 'viewed'].includes(data.contract.status)" class="mt-5 border border-dark/20 bg-white p-6">
            <p class="text-sm">You are accepting this document for <strong>{{ data.contract.project.company_name }}</strong>.</p>
            <form v-if="canAccept" class="mt-6" method="POST" :action="data.contract.accept_url">
                <input type="hidden" name="_token" :value="csrfToken">
                <input type="hidden" name="request_identifier" :value="data.contract.request_identifier">
                <input type="hidden" name="timezone" :value="timezone">
                <label class="my-4 flex items-start gap-3 text-sm"><input class="mt-1" type="checkbox" name="read_and_agreed" value="1" required><span>I have read the entire document and agree to its terms.</span></label>
                <label class="my-4 flex items-start gap-3 text-sm"><input class="mt-1" type="checkbox" name="authorized_to_act" value="1" required><span>I confirm that I am authorized to act for {{ data.contract.project.company_name }}.</span></label>
                <button class="mt-3 border border-dark bg-dark px-4 py-3 font-mono text-xs font-bold uppercase text-white" type="submit">Accept and conclude contract</button>
            </form>
            <p v-else class="mt-4 text-sm text-dark/45">You do not have permission to accept this document.</p>
        </section>
    </article>
</template>
