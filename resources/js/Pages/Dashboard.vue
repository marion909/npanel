<template>
    <AppLayout :user="$page.props.auth.user">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">Domains</h2>
                        <p class="mt-1 text-sm text-gray-600">Manage your domains and hosting</p>
                    </div>
                    <button 
                        @click="showCreateModal = true"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Domain
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">Total Domains</dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ domains.length }}</dd>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">Active</dt>
                        <dd class="mt-1 text-3xl font-semibold text-green-600">{{ activeDomains }}</dd>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">SSL Enabled</dt>
                        <dd class="mt-1 text-3xl font-semibold text-blue-600">{{ sslEnabledDomains }}</dd>
                    </div>
                </div>
            </div>

            <!-- Domains List -->
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul v-if="domains.length > 0" class="divide-y divide-gray-200">
                    <li v-for="domain in domains" :key="domain.id">
                        <div class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center">
                                        <h3 class="text-lg font-medium text-gray-900 truncate">
                                            {{ domain.domain_name }}
                                        </h3>
                                        <span 
                                            :class="statusClass(domain.status)"
                                            class="ml-3 px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        >
                                            {{ domain.status }}
                                        </span>
                                        <span 
                                            v-if="domain.ssl_enabled"
                                            class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"
                                        >
                                            SSL
                                        </span>
                                    </div>
                                    <div class="mt-2 flex items-center text-sm text-gray-500">
                                        <span>PHP {{ domain.php_version }}</span>
                                        <span class="mx-2">•</span>
                                        <span>{{ domain.subdomains?.length || 0 }} subdomains</span>
                                        <span v-if="domain.ssl_expiry_date" class="mx-2">•</span>
                                        <span v-if="domain.ssl_expiry_date" :class="expiryClass(domain.ssl_expiry_date)">
                                            SSL expires {{ formatDate(domain.ssl_expiry_date) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button 
                                        @click="viewDomain(domain)"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        View
                                    </button>
                                    <button 
                                        v-if="domain.status === 'suspended'"
                                        @click="resumeDomain(domain)"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-green-700 bg-white hover:bg-gray-50"
                                    >
                                        Resume
                                    </button>
                                    <button 
                                        v-else-if="domain.status === 'active'"
                                        @click="suspendDomain(domain)"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-yellow-700 bg-white hover:bg-gray-50"
                                    >
                                        Suspend
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
                <div v-else class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No domains</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating a new domain.</p>
                    <div class="mt-6">
                        <button 
                            @click="showCreateModal = true"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                        >
                            Add Domain
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Domain Modal (simplified for now) -->
        <div v-if="showCreateModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Add New Domain</h3>
                        <form @submit.prevent="createDomain">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">Domain Name</label>
                                <input 
                                    v-model="form.domain_name" 
                                    type="text" 
                                    placeholder="example.com"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                    required
                                >
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700">PHP Version</label>
                                <select 
                                    v-model="form.php_version"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="8.3">PHP 8.3</option>
                                    <option value="8.2">PHP 8.2</option>
                                    <option value="8.1">PHP 8.1</option>
                                    <option value="8.0">PHP 8.0</option>
                                    <option value="7.4">PHP 7.4</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="flex items-center">
                                    <input 
                                        v-model="form.ssl_enabled" 
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">Enable SSL (Let's Encrypt)</span>
                                </label>
                            </div>
                            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3">
                                <button 
                                    type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:text-sm"
                                >
                                    Create Domain
                                </button>
                                <button 
                                    type="button"
                                    @click="showCreateModal = false"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:text-sm"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    domains: Array
});

const showCreateModal = ref(false);
const form = ref({
    domain_name: '',
    php_version: '8.3',
    ssl_enabled: false
});

const activeDomains = computed(() => {
    return props.domains.filter(d => d.status === 'active').length;
});

const sslEnabledDomains = computed(() => {
    return props.domains.filter(d => d.ssl_enabled).length;
});

const statusClass = (status) => {
    const classes = {
        'active': 'bg-green-100 text-green-800',
        'pending': 'bg-yellow-100 text-yellow-800',
        'suspended': 'bg-red-100 text-red-800',
        'failed': 'bg-red-100 text-red-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const expiryClass = (date) => {
    const days = Math.floor((new Date(date) - new Date()) / (1000 * 60 * 60 * 24));
    if (days <= 7) return 'text-red-600 font-semibold';
    if (days <= 30) return 'text-yellow-600';
    return 'text-gray-500';
};

const formatDate = (date) => {
    const d = new Date(date);
    const days = Math.floor((d - new Date()) / (1000 * 60 * 60 * 24));
    if (days < 0) return 'expired';
    if (days === 0) return 'today';
    if (days === 1) return 'tomorrow';
    return `in ${days} days`;
};

const createDomain = async () => {
    router.post('/domains', form.value, {
        onSuccess: () => {
            showCreateModal.value = false;
            form.value = {
                domain_name: '',
                php_version: '8.3',
                ssl_enabled: false
            };
        },
        onError: (errors) => {
            console.error('Error creating domain:', errors);
            alert(errors.message || 'Failed to create domain');
        }
    });
};

const viewDomain = (domain) => {
    router.visit(`/domains/${domain.id}`);
};

const suspendDomain = (domain) => {
    if (confirm(`Suspend ${domain.domain_name}?`)) {
        router.post(`/domains/${domain.id}/suspend`, {}, {
            preserveScroll: true,
        });
    }
};

const resumeDomain = (domain) => {
    if (confirm(`Resume ${domain.domain_name}?`)) {
        router.post(`/domains/${domain.id}/resume`, {}, {
            preserveScroll: true,
        });
    }
};
</script>
