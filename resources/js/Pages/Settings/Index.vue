<template>
    <Head title="Settings" />

    <AppLayout :user="$page.props.auth.user">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900">Panel Settings</h2>
                <p class="mt-1 text-sm text-gray-600">Configure nPanel system settings and your profile</p>
            </div>

            <!-- Success/Error Messages -->
            <div v-if="$page.props.flash?.success" class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md">
                {{ $page.props.flash.error }}
            </div>

            <!-- Tabs -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button
                            @click="activeTab = 'system'"
                            :class="[
                                activeTab === 'system' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                            </svg>
                            System
                        </button>
                        <button
                            @click="activeTab = 'ssl'"
                            :class="[
                                activeTab === 'ssl' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            SSL
                        </button>
                        <button
                            @click="activeTab = 'security'"
                            :class="[
                                activeTab === 'security' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            Security
                        </button>
                        <button
                            @click="activeTab = 'profile'"
                            :class="[
                                activeTab === 'profile' 
                                    ? 'border-indigo-500 text-indigo-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                            ]"
                        >
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Profile
                        </button>
                    </nav>
                </div>
            </div>

            <!-- System Tab -->
            <div v-show="activeTab === 'system'" class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">System Configuration</h3>
                    
                    <form @submit.prevent="saveSettings" class="space-y-6">
                        <!-- Base Path -->
                        <div>
                            <label for="base_path" class="block text-sm font-medium text-gray-700">
                                Base Path for Domains
                            </label>
                            <input
                                type="text"
                                id="base_path"
                                v-model="systemForm.base_path"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <p class="mt-1 text-sm text-gray-500">Directory where all domain directories will be created (default: /home)</p>
                        </div>

                        <!-- Default PHP Version -->
                        <div>
                            <label for="default_php_version" class="block text-sm font-medium text-gray-700">
                                Default PHP Version
                            </label>
                            <select
                                id="default_php_version"
                                v-model="systemForm.default_php_version"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option v-for="version in settings.php_versions" :key="version" :value="version">
                                    PHP {{ version }}
                                </option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Default PHP version for new domains</p>
                        </div>

                        <!-- Nginx Sites Available -->
                        <div>
                            <label for="nginx_sites_available" class="block text-sm font-medium text-gray-700">
                                Nginx Sites Available Path
                            </label>
                            <input
                                type="text"
                                id="nginx_sites_available"
                                v-model="systemForm.nginx_sites_available"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                readonly
                                disabled
                            />
                            <p class="mt-1 text-sm text-gray-500">Path for Nginx virtual host configuration files (read-only)</p>
                        </div>

                        <!-- Roundcube URL -->
                        <div>
                            <label for="roundcube_url" class="block text-sm font-medium text-gray-700">
                                Roundcube Webmail URL
                            </label>
                            <input
                                type="url"
                                id="roundcube_url"
                                v-model="systemForm.roundcube_url"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <p class="mt-1 text-sm text-gray-500">URL where Roundcube webmail is accessible</p>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="saving"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                {{ saving ? 'Saving...' : 'Save System Settings' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SSL Tab -->
            <div v-show="activeTab === 'ssl'" class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">SSL/TLS Configuration</h3>
                    
                    <form @submit.prevent="saveSettings" class="space-y-6">
                        <!-- ACME.sh Path -->
                        <div>
                            <label for="acme_sh_path" class="block text-sm font-medium text-gray-700">
                                acme.sh Path
                            </label>
                            <input
                                type="text"
                                id="acme_sh_path"
                                v-model="sslForm.acme_sh_path"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <p class="mt-1 text-sm text-gray-500">Full path to acme.sh script for Let's Encrypt certificates</p>
                        </div>

                        <!-- SSL Provider -->
                        <div>
                            <label for="ssl_provider" class="block text-sm font-medium text-gray-700">
                                SSL Provider
                            </label>
                            <select
                                id="ssl_provider"
                                v-model="sslForm.ssl_provider"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="letsencrypt">Let's Encrypt</option>
                                <option value="manual">Manual</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">SSL certificate provider</p>
                        </div>

                        <!-- SSL Cert Base Path -->
                        <div>
                            <label for="ssl_cert_base_path" class="block text-sm font-medium text-gray-700">
                                SSL Certificate Base Path
                            </label>
                            <input
                                type="text"
                                id="ssl_cert_base_path"
                                v-model="sslForm.ssl_cert_base_path"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                readonly
                                disabled
                            />
                            <p class="mt-1 text-sm text-gray-500">Base directory for SSL certificates (read-only)</p>
                        </div>

                        <!-- Auto Renew -->
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input
                                    id="ssl_auto_renew"
                                    v-model="sslForm.ssl_auto_renew"
                                    type="checkbox"
                                    class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                />
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="ssl_auto_renew" class="font-medium text-gray-700">Enable Auto-Renewal</label>
                                <p class="text-gray-500">Automatically renew SSL certificates before expiration</p>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="saving"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                {{ saving ? 'Saving...' : 'Save SSL Settings' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div v-show="activeTab === 'security'" class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">Security & User Management</h3>
                    
                    <form @submit.prevent="saveSettings" class="space-y-6">
                        <!-- Default User -->
                        <div>
                            <label for="default_user" class="block text-sm font-medium text-gray-700">
                                Default Unix User
                            </label>
                            <input
                                type="text"
                                id="default_user"
                                v-model="securityForm.default_user"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <p class="mt-1 text-sm text-gray-500">Unix user that will own domain directories (default: www-data)</p>
                        </div>

                        <!-- Default Group -->
                        <div>
                            <label for="default_group" class="block text-sm font-medium text-gray-700">
                                Default Unix Group
                            </label>
                            <input
                                type="text"
                                id="default_group"
                                v-model="securityForm.default_group"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <p class="mt-1 text-sm text-gray-500">Unix group for domain directories (default: www-data)</p>
                        </div>

                        <!-- Config Backup Retention -->
                        <div>
                            <label for="config_backup_retention" class="block text-sm font-medium text-gray-700">
                                Configuration Backup Retention
                            </label>
                            <input
                                type="number"
                                id="config_backup_retention"
                                v-model.number="securityForm.config_backup_retention"
                                min="1"
                                max="100"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                            <p class="mt-1 text-sm text-gray-500">Number of configuration backups to keep before cleanup</p>
                        </div>

                        <!-- User Registration -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-base font-medium text-gray-900">User Registration</h4>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Control whether new users can register accounts. 
                                        <span class="font-medium">Total users: {{ settings.total_users }}</span>
                                    </p>
                                </div>
                                <div class="ml-4">
                                    <button
                                        @click.prevent="toggleRegistration"
                                        type="button"
                                        :class="[
                                            securityForm.registration_enabled 
                                                ? 'bg-indigo-600' 
                                                : 'bg-gray-200',
                                            'relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                securityForm.registration_enabled 
                                                    ? 'translate-x-5' 
                                                    : 'translate-x-0',
                                                'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200'
                                            ]"
                                        ></span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="!securityForm.registration_enabled" class="mt-3 bg-yellow-50 border border-yellow-200 rounded-md p-3">
                                <div class="flex">
                                    <svg class="h-5 w-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <p class="text-sm text-yellow-700">
                                        Registration is currently <strong>disabled</strong>. New users cannot create accounts.
                                    </p>
                                </div>
                            </div>
                            <div v-else class="mt-3 bg-green-50 border border-green-200 rounded-md p-3">
                                <div class="flex">
                                    <svg class="h-5 w-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <p class="text-sm text-green-700">
                                        Registration is currently <strong>enabled</strong>. New users can create accounts.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="saving"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                {{ saving ? 'Saving...' : 'Save Security Settings' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Profile Tab -->
            <div v-show="activeTab === 'profile'" class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-6">Your Profile</h3>
                    
                    <form @submit.prevent="saveProfile" class="space-y-6">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">
                                Name
                            </label>
                            <input
                                type="text"
                                id="name"
                                v-model="profileForm.name"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                Email Address
                            </label>
                            <input
                                type="email"
                                id="email"
                                v-model="profileForm.email"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            />
                        </div>

                        <!-- Change Password Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-base font-medium text-gray-900 mb-4">Change Password</h4>
                            
                            <!-- Current Password -->
                            <div class="mb-4">
                                <label for="current_password" class="block text-sm font-medium text-gray-700">
                                    Current Password
                                </label>
                                <input
                                    type="password"
                                    id="current_password"
                                    v-model="profileForm.current_password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>

                            <!-- New Password -->
                            <div class="mb-4">
                                <label for="new_password" class="block text-sm font-medium text-gray-700">
                                    New Password
                                </label>
                                <input
                                    type="password"
                                    id="new_password"
                                    v-model="profileForm.new_password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                                <p class="mt-1 text-sm text-gray-500">Minimum 8 characters</p>
                            </div>

                            <!-- Confirm New Password -->
                            <div>
                                <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700">
                                    Confirm New Password
                                </label>
                                <input
                                    type="password"
                                    id="new_password_confirmation"
                                    v-model="profileForm.new_password_confirmation"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="submit"
                                :disabled="savingProfile"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                            >
                                {{ savingProfile ? 'Saving...' : 'Save Profile' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: Object,
});

const activeTab = ref('system');
const saving = ref(false);
const savingProfile = ref(false);

// Forms
const systemForm = ref({
    base_path: props.settings.base_path,
    default_php_version: props.settings.default_php_version,
    nginx_sites_available: props.settings.nginx_sites_available,
    roundcube_url: props.settings.roundcube_url,
});

const sslForm = ref({
    acme_sh_path: props.settings.acme_sh_path,
    ssl_provider: props.settings.ssl_provider,
    ssl_cert_base_path: props.settings.ssl_cert_base_path,
    ssl_auto_renew: props.settings.ssl_auto_renew,
});

const securityForm = ref({
    default_user: props.settings.default_user,
    default_group: props.settings.default_group,
    config_backup_retention: props.settings.config_backup_retention,
    registration_enabled: props.settings.registration_enabled,
});

const profileForm = ref({
    name: '',
    email: '',
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

// Check URL hash on mount to set active tab
onMounted(() => {
    if (window.location.hash === '#profile') {
        activeTab.value = 'profile';
    }
});

const saveSettings = () => {
    saving.value = true;

    let data = {};
    
    if (activeTab.value === 'system') {
        data = { ...systemForm.value };
    } else if (activeTab.value === 'ssl') {
        data = { ...sslForm.value };
    } else if (activeTab.value === 'security') {
        data = { ...securityForm.value };
    }

    router.post('/settings', data, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
};

const saveProfile = () => {
    savingProfile.value = true;

    router.post('/settings/profile', profileForm.value, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Clear password fields on success
            profileForm.value.current_password = '';
            profileForm.value.new_password = '';
            profileForm.value.new_password_confirmation = '';
        },
        onFinish: () => {
            savingProfile.value = false;
        },
    });
};

const toggleRegistration = () => {
    securityForm.value.registration_enabled = !securityForm.value.registration_enabled;
};
</script>
