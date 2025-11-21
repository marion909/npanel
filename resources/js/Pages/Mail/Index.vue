<template>
    <AppLayout :user="$page.props.auth.user">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">Mail Management</h2>
                        <p class="mt-1 text-sm text-gray-600">Manage mailboxes and email aliases</p>
                    </div>
                    <div class="flex space-x-3">
                        <a 
                            href="/mail/settings"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Settings
                        </a>
                        <button 
                            @click="showCreateMailboxModal = true"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Mailbox
                        </button>
                        <button 
                            @click="showCreateAliasModal = true"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Alias
                        </button>
                    </div>
                </div>
            </div>

            <!-- Domain Filter -->
            <div class="mb-6 bg-white shadow sm:rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex-1 mr-4">
                        <label for="domain-filter" class="block text-sm font-medium text-gray-700 mb-2">Filter by Domain</label>
                        <select 
                            id="domain-filter"
                            v-model="selectedDomainId"
                            @change="filterByDomain"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                        >
                            <option :value="null">All Domains</option>
                            <option v-for="domain in domains" :key="domain.id" :value="domain.id">
                                {{ domain.domain_name }}
                            </option>
                        </select>
                    </div>
                    <div v-if="selectedDomainId" class="mt-6">
                        <a 
                            :href="`/mail/domains/${selectedDomainId}/dns`"
                            class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-md shadow-sm text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            View DNS Records
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button
                            @click="activeTab = 'mailboxes'"
                            :class="[
                                activeTab === 'mailboxes' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Mailboxes ({{ mailboxes.length }})
                        </button>
                        <button
                            @click="activeTab = 'aliases'"
                            :class="[
                                activeTab === 'aliases' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            Aliases & Forwarders ({{ aliases.length }})
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Mailboxes Tab -->
            <div v-show="activeTab === 'mailboxes'" class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul v-if="mailboxes.length > 0" class="divide-y divide-gray-200">
                    <li v-for="mailbox in mailboxes" :key="mailbox.id" class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center">
                                    <h3 class="text-lg font-medium text-gray-900">{{ mailbox.email }}</h3>
                                    <span 
                                        :class="statusBadgeClass(mailbox.status)"
                                        class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                    >
                                        {{ mailbox.status }}
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center text-sm text-gray-500">
                                    <span>Domain: {{ mailbox.domain.domain_name }}</span>
                                    <span class="mx-2">•</span>
                                    <span>Quota: {{ mailbox.used_mb }}MB / {{ mailbox.quota_mb }}MB</span>
                                </div>
                                <div class="mt-2">
                                    <div class="flex items-center">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                                            <div 
                                                :class="quotaBarClass(mailbox.quota_badge_color)"
                                                :style="{ width: `${mailbox.quota_percentage}%` }"
                                                class="h-2 rounded-full transition-all duration-300"
                                            ></div>
                                        </div>
                                        <span 
                                            :class="quotaTextClass(mailbox.quota_badge_color)"
                                            class="text-sm font-medium"
                                        >
                                            {{ mailbox.quota_percentage }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex space-x-2 ml-4">
                                <button 
                                    @click="calculateSize(mailbox)"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    title="Recalculate Size"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                                <button 
                                    @click="editMailbox(mailbox)"
                                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                    title="Edit Mailbox"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button 
                                    @click="deleteMailbox(mailbox)"
                                    class="inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50"
                                    title="Delete Mailbox"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
                <div v-else class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No mailboxes</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new mailbox.</p>
                </div>
            </div>

            <!-- Aliases Tab -->
            <div v-show="activeTab === 'aliases'" class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul v-if="aliases.length > 0" class="divide-y divide-gray-200">
                    <li v-for="alias in aliases" :key="alias.id" class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center">
                                    <h3 class="text-lg font-medium text-gray-900">{{ alias.source }}</h3>
                                    <span 
                                        :class="alias.type === 'catchall' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'"
                                        class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                    >
                                        {{ alias.type === 'catchall' ? 'Catch-All' : 'Alias' }}
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center text-sm text-gray-500">
                                    <span>Domain: {{ alias.domain.domain_name }}</span>
                                    <span class="mx-2">→</span>
                                    <span class="font-medium text-gray-900">{{ alias.destination }}</span>
                                </div>
                            </div>
                            <button 
                                @click="deleteAlias(alias)"
                                class="ml-4 inline-flex items-center px-3 py-2 border border-red-300 shadow-sm text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50"
                                title="Delete Alias"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </li>
                </ul>
                <div v-else class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No aliases</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new alias or catch-all forwarder.</p>
                </div>
            </div>
        </div>

        <!-- Create Mailbox Modal -->
        <CreateMailboxModal 
            v-if="showCreateMailboxModal"
            :domains="domains"
            @close="showCreateMailboxModal = false"
            @created="handleMailboxCreated"
        />

        <!-- Edit Mailbox Modal -->
        <EditMailboxModal 
            v-if="showEditMailboxModal"
            :mailbox="selectedMailbox"
            @close="showEditMailboxModal = false"
            @updated="handleMailboxUpdated"
        />

        <!-- Create Alias Modal -->
        <CreateAliasModal 
            v-if="showCreateAliasModal"
            :domains="domains"
            @close="showCreateAliasModal = false"
            @created="handleAliasCreated"
        />
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CreateMailboxModal from './CreateMailboxModal.vue';
import EditMailboxModal from './EditMailboxModal.vue';
import CreateAliasModal from './CreateAliasModal.vue';

const props = defineProps({
    domains: Array,
    mailboxes: Array,
    aliases: Array,
    selectedDomainId: Number,
});

const activeTab = ref('mailboxes');
const selectedDomainId = ref(props.selectedDomainId);
const showCreateMailboxModal = ref(false);
const showEditMailboxModal = ref(false);
const showCreateAliasModal = ref(false);
const selectedMailbox = ref(null);

const filterByDomain = () => {
    router.visit('/mail', {
        data: { domain_id: selectedDomainId.value },
        preserveState: true,
    });
};

const calculateSize = (mailbox) => {
    router.post(`/mail/mailboxes/${mailbox.id}/size`, {}, {
        preserveState: true,
        onSuccess: () => {
            router.reload();
        },
    });
};

const editMailbox = (mailbox) => {
    selectedMailbox.value = mailbox;
    showEditMailboxModal.value = true;
};

const deleteMailbox = (mailbox) => {
    if (confirm(`Are you sure you want to delete ${mailbox.email}? This action cannot be undone.`)) {
        router.delete(`/mail/mailboxes/${mailbox.id}`, {
            preserveState: true,
            onSuccess: () => {
                router.reload();
            },
        });
    }
};

const deleteAlias = (alias) => {
    if (confirm(`Are you sure you want to delete the alias ${alias.source}?`)) {
        router.delete(`/mail/aliases/${alias.id}`, {
            preserveState: true,
            onSuccess: () => {
                router.reload();
            },
        });
    }
};

const handleMailboxCreated = () => {
    showCreateMailboxModal.value = false;
    router.reload();
};

const handleMailboxUpdated = () => {
    showEditMailboxModal.value = false;
    router.reload();
};

const handleAliasCreated = () => {
    showCreateAliasModal.value = false;
    router.reload();
};

const statusBadgeClass = (status) => {
    return status === 'active' 
        ? 'bg-green-100 text-green-800' 
        : 'bg-red-100 text-red-800';
};

const quotaBarClass = (color) => {
    const classes = {
        green: 'bg-green-500',
        yellow: 'bg-yellow-500',
        red: 'bg-red-500',
    };
    return classes[color] || 'bg-gray-500';
};

const quotaTextClass = (color) => {
    const classes = {
        green: 'text-green-600',
        yellow: 'text-yellow-600',
        red: 'text-red-600',
    };
    return classes[color] || 'text-gray-600';
};
</script>
