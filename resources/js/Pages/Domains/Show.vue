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
                                <Link :href="`/domains/${domain.id}/files`" 
                                      class="px-4 py-2 bg-purple-500 text-white rounded-md hover:bg-purple-600 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                    </svg>
                                    Files
                                </Link>
                                <button v-if="showSSLButton" 
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
                            <button @click="showAddSubdomainModal = true" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 text-sm">
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
                                        <button @click="editSubdomain(subdomain)" class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                                        <button v-if="!['www', '@'].includes(subdomain.subdomain_name)" 
                                                @click="deleteSubdomain(subdomain)" 
                                                class="text-red-600 hover:text-red-800 text-sm">Delete</button>
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

        <!-- Add Subdomain Modal -->
        <div v-if="showAddSubdomainModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showAddSubdomainModal = false"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Add Subdomain</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subdomain Name</label>
                                <div class="flex items-center">
                                    <input v-model="newSubdomain.subdomain_name" type="text" 
                                           placeholder="blog, shop, api, etc."
                                           class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-l-md" />
                                    <span class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm rounded-r-md">
                                        .{{ domain.domain_name }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Document Root</label>
                                <div class="relative">
                                    <select v-model="documentRootType" @change="updateDocumentRoot"
                                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md mb-2">
                                        <option value="subdomain">Separater Subdomain-Ordner (Standard)</option>
                                        <option value="main">Hauptdomain Root</option>
                                        <option value="subfolder">Unterordner der Hauptdomain</option>
                                    </select>
                                    <div v-if="documentRootType === 'subdomain'" class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                                        {{ getSubdomainPath() }}
                                    </div>
                                    <div v-else-if="documentRootType === 'main'" class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                                        {{ domain.document_root }}
                                    </div>
                                    <div v-else-if="documentRootType === 'subfolder'" class="space-y-2">
                                        <input v-model="subfolderName" type="text" 
                                               placeholder="Unterordner (z.B. blog, shop)"
                                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                        <div class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                                            {{ domain.document_root }}/{{ subfolderName || 'unterordner' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">PHP Version</label>
                                <select v-model="newSubdomain.php_version" 
                                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    <option value="7.4">PHP 7.4</option>
                                    <option value="8.0">PHP 8.0</option>
                                    <option value="8.1">PHP 8.1</option>
                                    <option value="8.2">PHP 8.2</option>
                                    <option value="8.3">PHP 8.3</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="addSubdomain" :disabled="!newSubdomain.subdomain_name"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                            Create
                        </button>
                        <button @click="showAddSubdomainModal = false" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Subdomain Modal -->
        <div v-if="showEditSubdomainModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showEditSubdomainModal = false"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Subdomain</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subdomain</label>
                                <p class="text-sm text-gray-900 font-medium">{{ editingSubdomain?.subdomain_name }}.{{ domain.domain_name }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">PHP Version</label>
                                <select v-model="editingSubdomain.php_version" 
                                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    <option value="7.4">PHP 7.4</option>
                                    <option value="8.0">PHP 8.0</option>
                                    <option value="8.1">PHP 8.1</option>
                                    <option value="8.2">PHP 8.2</option>
                                    <option value="8.3">PHP 8.3</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="updateSubdomain"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Update
                        </button>
                        <button @click="showEditSubdomainModal = false" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    domain: Object
});

const showAddSubdomainModal = ref(false);
const showEditSubdomainModal = ref(false);
const documentRootType = ref('subdomain');
const subfolderName = ref('');
const newSubdomain = ref({
    subdomain_name: '',
    php_version: props.domain.php_version || '8.3',
    document_root: '',
});
const editingSubdomain = ref(null);

const statusClass = computed(() => {
    const status = props.domain.status;
    if (status === 'active') return 'bg-green-100 text-green-800';
    if (status === 'pending') return 'bg-yellow-100 text-yellow-800';
    if (status === 'suspended') return 'bg-orange-100 text-orange-800';
    if (status === 'failed') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
});

const showSSLButton = computed(() => {
    // Show button if domain is active and SSL is not enabled (handles 0, false, null)
    return props.domain.status === 'active' && !props.domain.ssl_enabled;
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

const getSubdomainPath = () => {
    const baseDir = props.domain.document_root.split('/public_html')[0];
    return `${baseDir}/subdomains/${newSubdomain.value.subdomain_name || 'subdomain'}`;
};

const updateDocumentRoot = () => {
    if (documentRootType.value === 'subdomain') {
        newSubdomain.value.document_root = '';
    } else if (documentRootType.value === 'main') {
        newSubdomain.value.document_root = props.domain.document_root;
    } else if (documentRootType.value === 'subfolder') {
        subfolderName.value = '';
        newSubdomain.value.document_root = '';
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

const addSubdomain = () => {
    if (!newSubdomain.value.subdomain_name) return;
    
    // Set document root based on type
    if (documentRootType.value === 'subfolder' && subfolderName.value) {
        newSubdomain.value.document_root = `${props.domain.document_root}/${subfolderName.value}`;
    }
    
    router.post(`/domains/${props.domain.id}/subdomains`, newSubdomain.value, {
        onSuccess: () => {
            showAddSubdomainModal.value = false;
            documentRootType.value = 'subdomain';
            subfolderName.value = '';
            newSubdomain.value = {
                subdomain_name: '',
                php_version: props.domain.php_version || '8.3',
                document_root: '',
            };
        },
        onError: (errors) => {
            alert('Failed to create subdomain: ' + Object.values(errors).join(', '));
        }
    });
};

const editSubdomain = (subdomain) => {
    editingSubdomain.value = { ...subdomain };
    showEditSubdomainModal.value = true;
};

const updateSubdomain = () => {
    if (!editingSubdomain.value) return;
    
    router.put(`/domains/${props.domain.id}/subdomains/${editingSubdomain.value.id}`, {
        php_version: editingSubdomain.value.php_version,
    }, {
        onSuccess: () => {
            showEditSubdomainModal.value = false;
            editingSubdomain.value = null;
        },
        onError: (errors) => {
            alert('Failed to update subdomain: ' + Object.values(errors).join(', '));
        }
    });
};

const deleteSubdomain = (subdomain) => {
    if (confirm(`Are you sure you want to delete ${subdomain.subdomain_name}.${props.domain.domain_name}? This action cannot be undone.`)) {
        router.delete(`/domains/${props.domain.id}/subdomains/${subdomain.id}`, {
            onSuccess: () => {
                // Success message handled by controller
            },
            onError: (errors) => {
                alert('Failed to delete subdomain: ' + Object.values(errors).join(', '));
            }
        });
    }
};
</script>
