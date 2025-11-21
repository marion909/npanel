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
                                    Create Email Alias / Forwarder
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

                                <!-- Type Selection -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                                    <div class="space-y-2">
                                        <div class="flex items-center">
                                            <input
                                                id="type-alias"
                                                v-model="form.type"
                                                type="radio"
                                                value="alias"
                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                                            />
                                            <label for="type-alias" class="ml-3 block text-sm font-medium text-gray-700">
                                                Normal Alias
                                                <span class="block text-xs text-gray-500">Forward specific email address to another</span>
                                            </label>
                                        </div>
                                        <div class="flex items-center">
                                            <input
                                                id="type-catchall"
                                                v-model="form.type"
                                                type="radio"
                                                value="catchall"
                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                                            />
                                            <label for="type-catchall" class="ml-3 block text-sm font-medium text-gray-700">
                                                Catch-All
                                                <span class="block text-xs text-gray-500">Forward all unmatched emails to one address</span>
                                            </label>
                                        </div>
                                    </div>
                                    <p v-if="errors.type" class="mt-2 text-sm text-red-600">{{ errors.type }}</p>
                                </div>

                                <!-- Source Input (Normal Alias) -->
                                <div v-if="form.type === 'alias'" class="mb-4">
                                    <label for="source" class="block text-sm font-medium text-gray-700">Source Email</label>
                                    <div class="mt-1 flex rounded-md shadow-sm">
                                        <input
                                            type="text"
                                            id="source"
                                            v-model="sourceLocalpart"
                                            required
                                            pattern="[a-zA-Z0-9._-]+"
                                            placeholder="alias"
                                            class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-gray-300"
                                        />
                                        <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                                            @{{ selectedDomainName }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">The email address to forward from.</p>
                                    <p v-if="errors.source" class="mt-2 text-sm text-red-600">{{ errors.source }}</p>
                                </div>

                                <!-- Source Display (Catch-All) -->
                                <div v-else class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Source</label>
                                    <div class="mt-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-900">
                                        @{{ selectedDomainName }}
                                    </div>
                                    <p class="mt-2 text-sm text-gray-500">All unmatched emails for this domain will be forwarded.</p>
                                </div>

                                <!-- Destination Input -->
                                <div class="mb-4">
                                    <label for="destination" class="block text-sm font-medium text-gray-700">Destination Email</label>
                                    <input
                                        type="email"
                                        id="destination"
                                        v-model="form.destination"
                                        required
                                        placeholder="user@example.com"
                                        class="mt-1 block w-full px-3 py-2 border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                    />
                                    <p class="mt-2 text-sm text-gray-500">The email address to forward to.</p>
                                    <p v-if="errors.destination" class="mt-2 text-sm text-red-600">{{ errors.destination }}</p>
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
                            {{ processing ? 'Creating...' : 'Create Alias' }}
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
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    domains: Array,
});

const emit = defineEmits(['close', 'created']);

const form = ref({
    domain_id: '',
    type: 'alias',
    source: '',
    destination: '',
});

const sourceLocalpart = ref('');
const errors = ref({});
const processing = ref(false);

const selectedDomainName = computed(() => {
    const domain = props.domains.find(d => d.id === form.value.domain_id);
    return domain ? domain.domain_name : 'domain.com';
});

// Update form.source based on type and localpart
watch([() => form.value.type, () => form.value.domain_id, sourceLocalpart], () => {
    const domain = props.domains.find(d => d.id === form.value.domain_id);
    if (!domain) return;

    if (form.value.type === 'catchall') {
        form.value.source = `@${domain.domain_name}`;
    } else {
        form.value.source = sourceLocalpart.value ? `${sourceLocalpart.value}@${domain.domain_name}` : '';
    }
});

const submit = () => {
    processing.value = true;
    errors.value = {};

    router.post('/mail/aliases', form.value, {
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
