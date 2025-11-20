<template>
    <Head :title="`Domain: ${domain.domain_name}`" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">{{ domain.domain_name }}</h2>
                                <div class="mt-2 flex items-center space-x-4">
                                    <span :class="statusClass" class="px-3 py-1 rounded-full text-sm font-medium">
                                        {{ domain.status.toUpperCase() }}
                                    </span>
                                    <span v-if="domain.ssl_enabled" class="text-green-600 text-sm flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                        SSL Active
                                    </span>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <button v-if="!domain.ssl_enabled && domain.status === 'active'" 
                                        @click="issueSSL" 
                                        class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                    Issue SSL
                                </button>
                                <Link :href="`/domains/${domain.id}/edit`" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                                    Edit
                                </Link>
                                <button @click="confirmDelete" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Domain Details -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Basic Information -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Document Root</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ domain.document_root }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Nginx Config</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ domain.nginx_config_path }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(domain.created_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(domain.updated_at) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- PHP Configuration -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">PHP Configuration</h3>
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">PHP Version</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded">PHP {{ domain.php_version }}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">FPM Pool</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ domain.php_fpm_pool || 'Not configured' }}</dd>
                                </div>
                                <div v-if="domain.php_fpm_pool_details">
                                    <dt class="text-sm font-medium text-gray-500">Socket Path</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono">{{ domain.php_fpm_pool_details.socket_path }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- SSL Certificate -->
                <div v-if="domain.ssl_certificate" class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">SSL Certificate</h3>
                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Provider</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ domain.ssl_certificate.provider }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Auto Renew</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    <span :class="domain.ssl_certificate.auto_renew ? 'text-green-600' : 'text-red-600'">
                                        {{ domain.ssl_certificate.auto_renew ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Issue Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ formatDate(domain.ssl_certificate.issue_date) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Expiry Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ formatDate(domain.ssl_certificate.expiry_date) }}</dd>
                            </div>
                            <div class="md:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Certificate Path</dt>
                                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ domain.ssl_certificate.certificate_path }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Subdomains -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Subdomains</h3>
                            <button class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-sm">
                                Add Subdomain
                            </button>
                        </div>
                        
                        <div v-if="domain.subdomains && domain.subdomains.length > 0" class="space-y-3">
                            <div v-for="subdomain in domain.subdomains" :key="subdomain.id" 
                                 class="border rounded-lg p-4 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-900">
                                            {{ subdomain.subdomain_name === '@' ? domain.domain_name : `${subdomain.subdomain_name}.${domain.domain_name}` }}
                                        </h4>
                                        <p class="text-sm text-gray-500 mt-1 font-mono">{{ subdomain.document_root }}</p>
                                        <div class="mt-2 flex items-center space-x-3">
                                            <span class="text-xs px-2 py-1 bg-purple-100 text-purple-800 rounded">
                                                PHP {{ subdomain.php_version }}
                                            </span>
                                            <span v-if="subdomain.ssl_enabled" class="text-xs text-green-600">SSL Enabled</span>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                                        <button class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-8 text-gray-500">
                            No subdomains configured
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    domain: Object
});

const statusClass = computed(() => {
    const status = props.domain.status;
    if (status === 'active') return 'bg-green-100 text-green-800';
    if (status === 'pending') return 'bg-yellow-100 text-yellow-800';
    if (status === 'suspended') return 'bg-orange-100 text-orange-800';
    if (status === 'failed') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
});

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleString('de-DE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const confirmDelete = () => {
    if (confirm(`Are you sure you want to delete ${props.domain.domain_name}? This action cannot be undone.`)) {
        router.delete(`/domains/${props.domain.id}`, {
            onSuccess: () => {
                // Redirect is handled by controller
            },
            onError: (errors) => {
                alert('Failed to delete domain: ' + Object.values(errors).join(', '));
            }
        });
    }
};

const issueSSL = () => {
    if (confirm(`Issue SSL certificate for ${props.domain.domain_name}? This will request a free Let's Encrypt certificate.`)) {
        router.post(`/domains/${props.domain.id}/ssl`, {}, {
            onSuccess: () => {
                // Success message handled by controller
            },
            onError: (errors) => {
                alert('Failed to issue SSL: ' + Object.values(errors).join(', '));
            }
        });
    }
};
</script>
