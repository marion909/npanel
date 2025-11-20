<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    domain: Object,
    phpVersions: Array,
});

const form = useForm({
    document_root: props.domain.document_root,
    php_version: props.domain.php_version,
});

const submit = () => {
    form.put(`/domains/${props.domain.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            // Success message is handled by the controller
        },
    });
};

const cancel = () => {
    router.visit(`/domains/${props.domain.id}`);
};
</script>

<template>
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-2xl font-semibold text-gray-900">
                                Edit Domain: {{ domain.domain_name }}
                            </h2>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            <!-- Document Root -->
                            <div>
                                <label for="document_root" class="block text-sm font-medium text-gray-700">
                                    Document Root
                                </label>
                                <input
                                    type="text"
                                    id="document_root"
                                    v-model="form.document_root"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    placeholder="/home/www-data/domains/example.com/public_html"
                                />
                                <p class="mt-1 text-sm text-gray-500">
                                    The directory where your website files are located.
                                </p>
                                <div v-if="form.errors.document_root" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.document_root }}
                                </div>
                            </div>

                            <!-- PHP Version -->
                            <div>
                                <label for="php_version" class="block text-sm font-medium text-gray-700">
                                    PHP Version
                                </label>
                                <select
                                    id="php_version"
                                    v-model="form.php_version"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option v-for="version in phpVersions" :key="version" :value="version">
                                        PHP {{ version }}
                                    </option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500">
                                    The PHP version to use for this domain. Changing this will recreate the PHP-FPM pool.
                                </p>
                                <div v-if="form.errors.php_version" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.php_version }}
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-end space-x-3">
                                <button
                                    type="button"
                                    @click="cancel"
                                    class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                >
                                    <span v-if="form.processing">Saving...</span>
                                    <span v-else>Save Changes</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
