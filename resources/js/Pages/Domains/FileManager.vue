<template>
    <Head :title="`Files: ${domain.domain_name}`" />

    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">File Manager</h2>
                                <p class="text-sm text-gray-600 mt-1">{{ domain.domain_name }}</p>
                            </div>
                            <div class="flex space-x-3">
                                <button @click="showUploadModal = true" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" />
                                    </svg>
                                    Upload
                                </button>
                                <button @click="showCreateDirModal = true" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                    </svg>
                                    New Folder
                                </button>
                                <Link :href="`/domains/${domain.id}`" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                                    Back
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breadcrumbs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-4">
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                                <li v-for="(crumb, index) in breadcrumbs" :key="index" class="inline-flex items-center">
                                    <Link v-if="index < breadcrumbs.length - 1" 
                                          :href="`/domains/${domain.id}/files?path=${crumb.path}`"
                                          class="text-blue-600 hover:text-blue-800">
                                        {{ crumb.name }}
                                    </Link>
                                    <span v-else class="text-gray-700">{{ crumb.name }}</span>
                                    <svg v-if="index < breadcrumbs.length - 1" class="w-6 h-6 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </li>
                            </ol>
                        </nav>
                    </div>
                </div>

                <!-- File List -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modified</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in items" :key="item.path" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <svg v-if="item.type === 'directory'" class="w-5 h-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                        </svg>
                                        <svg v-else class="w-5 h-5 text-gray-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                                        </svg>
                                        <Link v-if="item.type === 'directory'" 
                                              :href="`/domains/${domain.id}/files?path=${item.path}`"
                                              class="text-blue-600 hover:text-blue-800 font-medium">
                                            {{ item.name }}
                                        </Link>
                                        <span v-else class="text-gray-900">{{ item.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ item.type === 'directory' ? '-' : formatSize(item.size) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ formatDate(item.modified) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                    {{ item.permissions }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button v-if="item.type === 'file' && isEditable(item.extension)" 
                                            @click="editFile(item)" 
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                        Edit
                                    </button>
                                    <a v-if="item.type === 'file'" 
                                       :href="`/domains/${domain.id}/files/download?path=${item.path}`"
                                       class="text-green-600 hover:text-green-900 mr-3">
                                        Download
                                    </a>
                                    <button @click="confirmDelete(item)" class="text-red-600 hover:text-red-900">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="items.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    This folder is empty
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <div v-if="showUploadModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showUploadModal = false"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Upload Files</h3>
                        <input type="file" multiple @change="handleFileSelect" class="block w-full text-sm text-gray-500
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-md file:border-0
                            file:text-sm file:font-semibold
                            file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100" />
                        <div v-if="selectedFiles.length > 0" class="mt-4">
                            <p class="text-sm text-gray-600 mb-2">Selected files:</p>
                            <ul class="text-sm text-gray-800">
                                <li v-for="(file, index) in selectedFiles" :key="index">{{ file.name }} ({{ formatSize(file.size) }})</li>
                            </ul>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="uploadFiles" :disabled="uploading || selectedFiles.length === 0"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                            {{ uploading ? 'Uploading...' : 'Upload' }}
                        </button>
                        <button @click="showUploadModal = false" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Directory Modal -->
        <div v-if="showCreateDirModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateDirModal = false"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Create New Folder</h3>
                        <input v-model="newDirName" type="text" placeholder="Folder name"
                               class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md" />
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="createDirectory" :disabled="!newDirName"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                            Create
                        </button>
                        <button @click="showCreateDirModal = false" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Editor Modal -->
        <div v-if="showEditorModal" class="fixed z-10 inset-0 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeEditor"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit: {{ editingFile?.name }}</h3>
                        <textarea v-model="fileContent" rows="20"
                                  class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md font-mono"></textarea>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="saveFile" :disabled="saving"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50">
                            {{ saving ? 'Saving...' : 'Save' }}
                        </button>
                        <button @click="closeEditor" type="button"
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
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    domain: Object,
    currentPath: String,
    items: Array,
    breadcrumbs: Array,
});

const showUploadModal = ref(false);
const showCreateDirModal = ref(false);
const showEditorModal = ref(false);
const selectedFiles = ref([]);
const uploading = ref(false);
const newDirName = ref('');
const editingFile = ref(null);
const fileContent = ref('');
const saving = ref(false);

const formatSize = (bytes) => {
    if (!bytes) return '-';
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + ' ' + sizes[i];
};

const formatDate = (timestamp) => {
    return new Date(timestamp * 1000).toLocaleString('de-DE');
};

const isEditable = (extension) => {
    const editableExtensions = ['txt', 'php', 'js', 'css', 'html', 'json', 'xml', 'md', 'yaml', 'yml', 'ini', 'conf', 'log'];
    return editableExtensions.includes(extension?.toLowerCase());
};

const handleFileSelect = (event) => {
    selectedFiles.value = Array.from(event.target.files);
};

const uploadFiles = async () => {
    if (selectedFiles.value.length === 0) return;
    
    uploading.value = true;
    const formData = new FormData();
    selectedFiles.value.forEach(file => {
        formData.append('files[]', file);
    });
    formData.append('path', props.currentPath);

    try {
        await axios.post(`/domains/${props.domain.id}/files/upload`, formData);
        showUploadModal.value = false;
        selectedFiles.value = [];
        router.reload();
    } catch (error) {
        alert('Upload failed: ' + (error.response?.data?.error || error.message));
    } finally {
        uploading.value = false;
    }
};

const createDirectory = async () => {
    if (!newDirName.value) return;

    try {
        await axios.post(`/domains/${props.domain.id}/files/directory`, {
            name: newDirName.value,
            path: props.currentPath,
        });
        showCreateDirModal.value = false;
        newDirName.value = '';
        router.reload();
    } catch (error) {
        alert('Failed to create directory: ' + (error.response?.data?.error || error.message));
    }
};

const editFile = async (item) => {
    editingFile.value = item;
    try {
        const response = await axios.get(`/domains/${props.domain.id}/files/content?path=${item.path}`);
        fileContent.value = response.data.content;
        showEditorModal.value = true;
    } catch (error) {
        alert('Failed to load file: ' + (error.response?.data?.error || error.message));
    }
};

const saveFile = async () => {
    if (!editingFile.value) return;

    saving.value = true;
    try {
        await axios.post(`/domains/${props.domain.id}/files/save`, {
            path: editingFile.value.path,
            content: fileContent.value,
        });
        closeEditor();
        router.reload();
    } catch (error) {
        alert('Failed to save file: ' + (error.response?.data?.error || error.message));
    } finally {
        saving.value = false;
    }
};

const closeEditor = () => {
    showEditorModal.value = false;
    editingFile.value = null;
    fileContent.value = '';
};

const confirmDelete = async (item) => {
    if (!confirm(`Are you sure you want to delete "${item.name}"?`)) return;

    try {
        await axios.delete(`/domains/${props.domain.id}/files/delete`, {
            data: { path: item.path }
        });
        router.reload();
    } catch (error) {
        alert('Failed to delete: ' + (error.response?.data?.error || error.message));
    }
};
</script>
