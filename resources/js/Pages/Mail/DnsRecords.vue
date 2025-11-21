<template>
    <AppLayout :user="$page.props.auth.user">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">DNS Records</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ domain.domain_name }}</p>
                    </div>
                    <a 
                        href="/mail"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Mail
                    </a>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Add these DNS records to your domain registrar
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>These DNS records must be configured at your domain registrar (e.g., Cloudflare, Namecheap, etc.) for email to work properly.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DNS Records -->
            <div class="space-y-6">
                <!-- MX Record -->
                <div v-if="records.MX" class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">MX Record</h3>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500">Mail server address</p>
                            </div>
                            <button 
                                @click="copyToClipboard(records.MX.value, 'MX')"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Copy
                            </button>
                        </div>
                    </div>
                    <div class="px-4 py-5 sm:p-6 bg-gray-50">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.MX.type }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.MX.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">TTL</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.MX.ttl }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4">
                            <dt class="text-sm font-medium text-gray-500 mb-1">Value</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono bg-white p-3 rounded border border-gray-200">
                                {{ records.MX.value }}
                            </dd>
                        </div>
                    </div>
                </div>

                <!-- SPF Record -->
                <div v-if="records.SPF" class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">SPF Record</h3>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500">Sender Policy Framework</p>
                            </div>
                            <button 
                                @click="copyToClipboard(records.SPF.value, 'SPF')"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Copy
                            </button>
                        </div>
                    </div>
                    <div class="px-4 py-5 sm:p-6 bg-gray-50">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.SPF.type }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.SPF.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">TTL</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.SPF.ttl }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4">
                            <dt class="text-sm font-medium text-gray-500 mb-1">Value</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono bg-white p-3 rounded border border-gray-200">
                                {{ records.SPF.value }}
                            </dd>
                        </div>
                    </div>
                </div>

                <!-- DKIM Record -->
                <div v-if="records.DKIM" class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">DKIM Record</h3>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500">DomainKeys Identified Mail</p>
                            </div>
                            <button 
                                @click="copyToClipboard(records.DKIM.value, 'DKIM')"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Copy
                            </button>
                        </div>
                    </div>
                    <div class="px-4 py-5 sm:p-6 bg-gray-50">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.DKIM.type }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.DKIM.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">TTL</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.DKIM.ttl }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4">
                            <dt class="text-sm font-medium text-gray-500 mb-1">Value</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono bg-white p-3 rounded border border-gray-200 break-all">
                                {{ records.DKIM.value }}
                            </dd>
                        </div>
                    </div>
                </div>
                <div v-else class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">DKIM Key Not Yet Generated</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>DKIM keys are generated when you create your first mailbox for this domain. Create a mailbox first, then return here to get the DKIM record.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DMARC Record -->
                <div v-if="records.DMARC" class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900">DMARC Record</h3>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500">Domain-based Message Authentication</p>
                            </div>
                            <button 
                                @click="copyToClipboard(records.DMARC.value, 'DMARC')"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Copy
                            </button>
                        </div>
                    </div>
                    <div class="px-4 py-5 sm:p-6 bg-gray-50">
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.DMARC.type }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.DMARC.name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">TTL</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ records.DMARC.ttl }}</dd>
                            </div>
                        </dl>
                        <div class="mt-4">
                            <dt class="text-sm font-medium text-gray-500 mb-1">Value</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono bg-white p-3 rounded border border-gray-200">
                                {{ records.DMARC.value }}
                            </dd>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    domain: Object,
    records: Object,
});

const copyToClipboard = async (text, recordType) => {
    try {
        await navigator.clipboard.writeText(text);
        alert(`${recordType} record copied to clipboard!`);
    } catch (err) {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard');
    }
};
</script>
