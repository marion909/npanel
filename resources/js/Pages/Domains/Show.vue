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
                                            <span v-if="subdomain.wordpress_installed" class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded flex items-center">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                                                </svg>
                                                WordPress
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button v-if="subdomain.wordpress_installed" 
                                                @click="showWordPressInfo(subdomain)" 
                                                class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                            WP Info
                                        </button>
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
                            <div class="border-t pt-4">
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="newSubdomain.install_wordpress" 
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm">
                                        <span class="font-medium text-gray-700">🚀 WordPress One-Click Installation</span>
                                        <span class="block text-xs text-gray-500 mt-1">
                                            Installiert automatisch WordPress mit Datenbank und zeigt Zugangsdaten an
                                        </span>
                                    </span>
                                </label>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">Document Root</label>
                                <div class="relative">
                                    <select v-model="editDocumentRootType" @change="updateEditDocumentRoot"
                                            class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md mb-2">
                                        <option value="subdomain">Separater Subdomain-Ordner</option>
                                        <option value="main">Hauptdomain Root</option>
                                        <option value="subfolder">Unterordner der Hauptdomain</option>
                                        <option value="custom">Benutzerdefiniert</option>
                                    </select>
                                    <div v-if="editDocumentRootType === 'subdomain'" class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                                        {{ getEditSubdomainPath() }}
                                    </div>
                                    <div v-else-if="editDocumentRootType === 'main'" class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                                        {{ domain.document_root }}
                                    </div>
                                    <div v-else-if="editDocumentRootType === 'subfolder'" class="space-y-2">
                                        <input v-model="editSubfolderName" type="text" 
                                               placeholder="Unterordner (z.B. blog, shop)"
                                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                                        <div class="text-xs text-gray-500 font-mono bg-gray-50 p-2 rounded">
                                            {{ domain.document_root }}/{{ editSubfolderName || 'unterordner' }}
                                        </div>
                                    </div>
                                    <div v-else-if="editDocumentRootType === 'custom'" class="space-y-2">
                                        <input v-model="editingSubdomain.document_root" type="text" 
                                               placeholder="Vollständiger Pfad"
                                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md font-mono" />
                                        <p class="text-xs text-gray-500">Aktuell: {{ editingSubdomain.document_root }}</p>
                                    </div>
                                </div>
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

        <!-- WordPress Credentials Modal -->
        <div v-if="showWordPressCredentials" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showWordPressCredentials = false"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    🚀 WordPress erfolgreich installiert!
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <!-- Site URLs -->
                                    <div class="bg-blue-50 p-4 rounded-lg">
                                        <h4 class="font-semibold text-blue-900 mb-2">Website URLs</h4>
                                        <div class="space-y-2">
                                            <div>
                                                <label class="text-sm text-blue-700">Website:</label>
                                                <a :href="wordPressCredentials.site_url" target="_blank" class="block text-blue-600 hover:underline font-mono text-sm">
                                                    {{ wordPressCredentials.site_url }}
                                                </a>
                                            </div>
                                            <div>
                                                <label class="text-sm text-blue-700">Admin Panel:</label>
                                                <a :href="wordPressCredentials.admin_url" target="_blank" class="block text-blue-600 hover:underline font-mono text-sm">
                                                    {{ wordPressCredentials.admin_url }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WordPress Admin Credentials -->
                                    <div class="bg-purple-50 p-4 rounded-lg">
                                        <h4 class="font-semibold text-purple-900 mb-2">WordPress Admin Zugang</h4>
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <label class="text-sm text-purple-700">Benutzername:</label>
                                                    <p class="font-mono text-sm">{{ wordPressCredentials.admin_user }}</p>
                                                </div>
                                                <button @click="copyToClipboard(wordPressCredentials.admin_user)" 
                                                        class="ml-2 px-2 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700">
                                                    Kopieren
                                                </button>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <label class="text-sm text-purple-700">Passwort:</label>
                                                    <p class="font-mono text-sm break-all">{{ wordPressCredentials.admin_password }}</p>
                                                </div>
                                                <button @click="copyToClipboard(wordPressCredentials.admin_password)" 
                                                        class="ml-2 px-2 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700">
                                                    Kopieren
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Database Credentials -->
                                    <div class="bg-green-50 p-4 rounded-lg">
                                        <h4 class="font-semibold text-green-900 mb-2">Datenbank Zugangsdaten</h4>
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <label class="text-sm text-green-700">Datenbankname:</label>
                                                    <p class="font-mono text-sm">{{ wordPressCredentials.db_name }}</p>
                                                </div>
                                                <button @click="copyToClipboard(wordPressCredentials.db_name)" 
                                                        class="ml-2 px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                                    Kopieren
                                                </button>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <label class="text-sm text-green-700">Benutzer:</label>
                                                    <p class="font-mono text-sm">{{ wordPressCredentials.db_user }}</p>
                                                </div>
                                                <button @click="copyToClipboard(wordPressCredentials.db_user)" 
                                                        class="ml-2 px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                                    Kopieren
                                                </button>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <label class="text-sm text-green-700">Passwort:</label>
                                                    <p class="font-mono text-sm break-all">{{ wordPressCredentials.db_password }}</p>
                                                </div>
                                                <button @click="copyToClipboard(wordPressCredentials.db_password)" 
                                                        class="ml-2 px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                                    Kopieren
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Warning -->
                                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm text-yellow-700">
                                                    <strong>Wichtig:</strong> Speichern Sie diese Zugangsdaten sicher! Sie werden aus Sicherheitsgründen nur einmal angezeigt.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="copyAllCredentials"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            📋 Alle kopieren
                        </button>
                        <button @click="showWordPressCredentials = false" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Schließen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    domain: Object
});

const showAddSubdomainModal = ref(false);
const showEditSubdomainModal = ref(false);
const showWordPressCredentials = ref(false);
const wordPressCredentials = ref(null);
const wordPressPollingInterval = ref(null);
const documentRootType = ref('subdomain');
const subfolderName = ref('');
const editDocumentRootType = ref('custom');
const editSubfolderName = ref('');
const newSubdomain = ref({
    subdomain_name: '',
    php_version: props.domain.php_version || '8.3',
    document_root: '',
    install_wordpress: false,
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
    if (confirm(`Are you sure you want to delete ${props.domain.domain_name}? This will delete all databases, files, and configurations. SSL certificates will be kept for reuse. This action cannot be undone!`)) {
        router.delete(`/domains/${props.domain.id}`, {
            preserveState: false,
            onBefore: () => {
                console.log('Deleting domain:', props.domain.domain_name);
            },
            onSuccess: () => {
                console.log('Domain deleted successfully');
                // Redirect is handled by controller
            },
            onError: (errors) => {
                console.error('Delete failed:', errors);
                const errorMessage = typeof errors === 'string' 
                    ? errors 
                    : Object.values(errors).join(', ');
                alert('Failed to delete domain: ' + errorMessage);
            },
            onFinish: () => {
                console.log('Delete request finished');
            }
        });
    }
};

const getSubdomainPath = () => {
    const baseDir = props.domain.document_root.split('/public_html')[0];
    return `${baseDir}/subdomains/${newSubdomain.value.subdomain_name || 'subdomain'}`;
};

const getEditSubdomainPath = () => {
    const baseDir = props.domain.document_root.split('/public_html')[0];
    return `${baseDir}/subdomains/${editingSubdomain.value?.subdomain_name || 'subdomain'}`;
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

const updateEditDocumentRoot = () => {
    if (editDocumentRootType.value === 'subdomain') {
        const baseDir = props.domain.document_root.split('/public_html')[0];
        editingSubdomain.value.document_root = `${baseDir}/subdomains/${editingSubdomain.value.subdomain_name}`;
    } else if (editDocumentRootType.value === 'main') {
        editingSubdomain.value.document_root = props.domain.document_root;
    } else if (editDocumentRootType.value === 'subfolder') {
        editSubfolderName.value = '';
    }
    // 'custom' does nothing, user can type freely
};

const issueSSL = () => {
    if (confirm(`Issue SSL certificate for ${props.domain.domain_name}? This will request a free Let's Encrypt certificate.`)) {
        router.post(`/domains/${props.domain.id}/ssl`, {}, {
            onSuccess: () => {
                // Start polling to check SSL status
                startSslPolling();
            },
            onError: (errors) => {
                alert('Failed to issue SSL: ' + Object.values(errors).join(', '));
            }
        });
    }
};

// Poll for SSL status updates
let sslPollingInterval = null;
const startSslPolling = () => {
    // Clear any existing interval
    if (sslPollingInterval) {
        clearInterval(sslPollingInterval);
    }
    
    // Poll every 5 seconds for up to 5 minutes
    let pollCount = 0;
    const maxPolls = 60; // 5 minutes
    
    sslPollingInterval = setInterval(() => {
        pollCount++;
        
        // Reload page data
        router.reload({
            only: ['domain'],
            preserveScroll: true,
            onSuccess: () => {
                // Stop polling if SSL is enabled or max attempts reached
                if (props.domain.ssl_enabled || pollCount >= maxPolls) {
                    clearInterval(sslPollingInterval);
                    sslPollingInterval = null;
                }
            }
        });
    }, 5000);
};

// Cleanup on unmount
onUnmounted(() => {
    if (sslPollingInterval) {
        clearInterval(sslPollingInterval);
    }
    if (wordPressPollingInterval.value) {
        clearInterval(wordPressPollingInterval.value);
    }
});

const addSubdomain = () => {
    if (!newSubdomain.value.subdomain_name) return;
    
    // Set document root based on type
    if (documentRootType.value === 'subfolder' && subfolderName.value) {
        newSubdomain.value.document_root = `${props.domain.document_root}/${subfolderName.value}`;
    }
    
    const shouldInstallWordPress = newSubdomain.value.install_wordpress;
    
    router.post(`/domains/${props.domain.id}/subdomains`, newSubdomain.value, {
        onSuccess: (page) => {
            showAddSubdomainModal.value = false;
            documentRootType.value = 'subdomain';
            subfolderName.value = '';
            
            // Start polling for WordPress credentials if installation was requested
            if (shouldInstallWordPress && page.props.flash?.subdomain_id) {
                startWordPressPolling(page.props.flash.subdomain_id);
            }
            
            newSubdomain.value = {
                subdomain_name: '',
                php_version: props.domain.php_version || '8.3',
                document_root: '',
                install_wordpress: false,
            };
        },
        onError: (errors) => {
            alert('Failed to create subdomain: ' + Object.values(errors).join(', '));
        }
    });
};

// Poll for WordPress installation credentials
const startWordPressPolling = (subdomainId) => {
    let pollCount = 0;
    const maxPolls = 36; // 3 minutes (36 * 5 seconds)
    
    wordPressPollingInterval.value = setInterval(() => {
        pollCount++;
        
        // Fetch WordPress credentials
        fetch(`/domains/${props.domain.id}/subdomains/${subdomainId}/wordpress-credentials`)
            .then(response => {
                if (response.ok) {
                    return response.json();
                }
                throw new Error('Not ready yet');
            })
            .then(data => {
                // Credentials are ready
                if (data.success && data.credentials) {
                    wordPressCredentials.value = data.credentials;
                    showWordPressCredentials.value = true;
                    
                    // Stop polling
                    clearInterval(wordPressPollingInterval.value);
                    wordPressPollingInterval.value = null;
                }
            })
            .catch(() => {
                // Not ready yet, continue polling
                if (pollCount >= maxPolls) {
                    // Timeout reached
                    clearInterval(wordPressPollingInterval.value);
                    wordPressPollingInterval.value = null;
                    alert('WordPress installation is taking longer than expected. Please check back in a few minutes.');
                }
            });
    }, 5000);
};

// Copy to clipboard function
const copyToClipboard = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        // Simple visual feedback
        alert('In Zwischenablage kopiert!');
    } catch (err) {
        console.error('Failed to copy:', err);
        alert('Kopieren fehlgeschlagen. Bitte manuell kopieren.');
    }
};

// Show WordPress info for subdomain
const showWordPressInfo = async (subdomain) => {
    try {
        const response = await fetch(`/domains/${props.domain.id}/subdomains/${subdomain.id}/wordpress-credentials`);
        const data = await response.json();
        
        if (data.success && data.credentials) {
            wordPressCredentials.value = data.credentials;
            showWordPressCredentials.value = true;
        } else {
            alert('WordPress Zugangsdaten nicht gefunden oder abgelaufen.');
        }
    } catch (error) {
        console.error('Failed to fetch WordPress credentials:', error);
        alert('Fehler beim Abrufen der WordPress Zugangsdaten.');
    }
};

// Copy all credentials at once
const copyAllCredentials = async () => {
    if (!wordPressCredentials.value) return;
    
    const text = `
=== WordPress Installation Zugangsdaten ===

Website URL: ${wordPressCredentials.value.site_url}
Admin Panel: ${wordPressCredentials.value.admin_url}

WordPress Admin:
  Benutzername: ${wordPressCredentials.value.admin_user}
  Passwort: ${wordPressCredentials.value.admin_password}

Datenbank:
  Name: ${wordPressCredentials.value.db_name}
  Benutzer: ${wordPressCredentials.value.db_user}
  Passwort: ${wordPressCredentials.value.db_password}

==========================================
`.trim();
    
    try {
        await navigator.clipboard.writeText(text);
        alert('Alle Zugangsdaten in Zwischenablage kopiert!');
    } catch (err) {
        console.error('Failed to copy:', err);
        alert('Kopieren fehlgeschlagen.');
    }
};

const editSubdomain = (subdomain) => {
    editingSubdomain.value = { ...subdomain };
    editDocumentRootType.value = 'custom';
    editSubfolderName.value = '';
    showEditSubdomainModal.value = true;
};

const updateSubdomain = () => {
    if (!editingSubdomain.value) return;
    
    // Set document root based on type
    if (editDocumentRootType.value === 'subfolder' && editSubfolderName.value) {
        editingSubdomain.value.document_root = `${props.domain.document_root}/${editSubfolderName.value}`;
    }
    
    router.put(`/domains/${props.domain.id}/subdomains/${editingSubdomain.value.id}`, {
        php_version: editingSubdomain.value.php_version,
        document_root: editingSubdomain.value.document_root,
    }, {
        onSuccess: () => {
            showEditSubdomainModal.value = false;
            editingSubdomain.value = null;
            editDocumentRootType.value = 'custom';
            editSubfolderName.value = '';
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
