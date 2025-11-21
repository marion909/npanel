<template>
    <AppLayout :user="$page.props.auth.user">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">Mail Settings</h2>
                        <p class="mt-1 text-sm text-gray-600">Configure mail server settings</p>
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

            <!-- Webmail Settings -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Webmail Access</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Access your email through the web interface</p>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div class="mb-4">
                        <label for="roundcube_url" class="block text-sm font-medium text-gray-700 mb-2">
                            Roundcube Webmail URL
                        </label>
                        <div class="flex rounded-md shadow-sm">
                            <input
                                type="url"
                                id="roundcube_url"
                                v-model="form.roundcube_url"
                                class="flex-1 min-w-0 block w-full px-3 py-2 rounded-l-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-gray-300"
                                placeholder="https://webmail.example.com"
                            />
                            <button
                                @click="saveSettings"
                                :disabled="saving"
                                class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ saving ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            The URL where Roundcube webmail is accessible (configured during mail server installation).
                        </p>
                    </div>

                    <div class="mt-6">
                        <a 
                            :href="settings.roundcube_url"
                            target="_blank"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Open Webmail
                            <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mail Server Status -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Mail Server Status</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Information about your mail server configuration</p>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div class="bg-gray-50 px-4 py-5 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">SMTP Server</dt>
                            <dd class="mt-1 text-sm text-gray-900">localhost:587 (TLS)</dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">IMAP Server</dt>
                            <dd class="mt-1 text-sm text-gray-900">localhost:993 (SSL)</dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">Authentication</dt>
                            <dd class="mt-1 text-sm text-gray-900">Use full email address as username</dd>
                        </div>
                        <div class="bg-gray-50 px-4 py-5 rounded-lg">
                            <dt class="text-sm font-medium text-gray-500">Mailbox Format</dt>
                            <dd class="mt-1 text-sm text-gray-900">Maildir at /var/vmail/</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Email Client Configuration -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Email Client Configuration</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Settings for desktop and mobile email clients</p>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div class="prose prose-sm max-w-none">
                        <h4 class="text-base font-medium text-gray-900 mb-3">IMAP (Incoming)</h4>
                        <ul class="space-y-2 text-sm text-gray-700">
                            <li><strong>Server:</strong> <code class="bg-gray-100 px-2 py-1 rounded">mail.yourdomain.com</code> (use your mail_hostname)</li>
                            <li><strong>Port:</strong> <code class="bg-gray-100 px-2 py-1 rounded">993</code></li>
                            <li><strong>Security:</strong> SSL/TLS</li>
                            <li><strong>Username:</strong> Your full email address (e.g., user@yourdomain.com)</li>
                            <li><strong>Password:</strong> Your mailbox password</li>
                        </ul>

                        <h4 class="text-base font-medium text-gray-900 mb-3 mt-6">SMTP (Outgoing)</h4>
                        <ul class="space-y-2 text-sm text-gray-700">
                            <li><strong>Server:</strong> <code class="bg-gray-100 px-2 py-1 rounded">mail.yourdomain.com</code> (use your mail_hostname)</li>
                            <li><strong>Port:</strong> <code class="bg-gray-100 px-2 py-1 rounded">587</code></li>
                            <li><strong>Security:</strong> STARTTLS</li>
                            <li><strong>Authentication:</strong> Required</li>
                            <li><strong>Username:</strong> Your full email address</li>
                            <li><strong>Password:</strong> Your mailbox password</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: Object,
});

const form = ref({
    roundcube_url: props.settings.roundcube_url,
});

const saving = ref(false);

const saveSettings = () => {
    saving.value = true;

    router.post('/mail/settings', form.value, {
        preserveState: true,
        onSuccess: () => {
            alert('Settings saved successfully!');
        },
        onError: () => {
            alert('Failed to save settings');
        },
        onFinish: () => {
            saving.value = false;
        },
    });
};
</script>
