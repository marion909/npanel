<template>
    <div class="fixed z-10 inset-0 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form @submit.prevent="submit">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 w-full sm:mt-0 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Create New Mailbox
                                </h3>

                                <!-- Domain Selection -->
                                <div class="mb-4">
                                    <label for="domain" class="block text-sm font-medium text-gray-700">Domain</label>
                                    <select
                                        id="domain"
                                        v-model="form.domain_id"
                                        required
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                    >
                                        <option value="">Select a domain...</option>
                                        <option v-for="domain in domains" :key="domain.id" :value="domain.id">
                                            {{ domain.domain_name }}
                                        </option>
                                    </select>
                                    <p v-if="errors.domain_id" class="mt-2 text-sm text-red-600">{{ errors.domain_id }}</p>
                                </div>

                                <!-- Localpart Input -->
                                <div class="mb-4">
                                    <label for="localpart" class="block text-sm font-medium text-gray-700">Email Address</label>
                                    <div class="mt-1 flex rounded-md shadow-sm">
                                        <input
                                            type="text"
                                            id="localpart"
                                            v-model="form.localpart"
                                            required
                                            pattern="[a-zA-Z0-9._-]+"
                                            placeholder="username"
                                            class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-gray-300"
                                        />
                                        <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                            @{{ selectedDomainName }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">Only letters, numbers, dots, underscores, and hyphens allowed.</p>
                                    <p v-if="errors.localpart" class="mt-2 text-sm text-red-600">{{ errors.localpart }}</p>
                                </div>

                                <!-- Password Input -->
                                <div class="mb-4">
                                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <input
                                            :type="showPassword ? 'text' : 'password'"
                                            id="password"
                                            v-model="form.password"
                                            required
                                            minlength="8"
                                            class="block w-full pr-10 px-3 py-2 border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                        />
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <button
                                                type="button"
                                                @click="showPassword = !showPassword"
                                                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                                            >
                                                <svg v-if="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">Minimum 8 characters.</p>
                                    <p v-if="errors.password" class="mt-2 text-sm text-red-600">{{ errors.password }}</p>
                                </div>

                                <!-- Quota Slider -->
                                <div class="mb-4">
                                    <label for="quota" class="block text-sm font-medium text-gray-700">
                                        Quota: {{ form.quota_mb }}MB ({{ (form.quota_mb / 1024).toFixed(2) }}GB)
                                    </label>
                                    <input
                                        type="range"
                                        id="quota"
                                        v-model.number="form.quota_mb"
                                        min="100"
                                        max="10240"
                                        step="100"
                                        class="mt-2 w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                    />
                                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                                        <span>100MB</span>
                                        <span>10GB</span>
                                    </div>
                                    <p v-if="errors.quota_mb" class="mt-2 text-sm text-red-600">{{ errors.quota_mb }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                        >
                            {{ processing ? 'Creating...' : 'Create Mailbox' }}
                        </button>
                        <button
                            type="button"
                            @click="$emit('close')"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    domains: Array,
});

const emit = defineEmits(['close', 'created']);

const form = ref({
    domain_id: '',
    localpart: '',
    password: '',
    quota_mb: 1000,
});

const errors = ref({});
const processing = ref(false);
const showPassword = ref(false);

const selectedDomainName = computed(() => {
    const domain = props.domains.find(d => d.id === form.value.domain_id);
    return domain ? domain.domain_name : 'domain.com';
});

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post('/mail/mailboxes', form.value, {
        preserveState: true,
        onSuccess: () => {
            emit('created');
        },
        onError: (responseErrors) => {
            errors.value = responseErrors;
            processing.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>
