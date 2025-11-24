<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="text-lg font-semibold mb-4">Welcome to NPanel!</h3>
                        <p class="mb-4">Manage your domains, subdomains, and DNS records.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                            <Link 
                                :href="route('domains.index')"
                                class="block p-6 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition"
                            >
                                <h4 class="text-xl font-bold text-blue-900 mb-2">Domains</h4>
                                <p class="text-blue-700">Manage your domains and wildcard SSL certificates</p>
                            </Link>
                            
                            <Link 
                                :href="route('dns-records.index')"
                                class="block p-6 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition"
                            >
                                <h4 class="text-xl font-bold text-green-900 mb-2">DNS Records</h4>
                                <p class="text-green-700">View and manage all DNS records</p>
                            </Link>
                            
                            <Link 
                                :href="route('nginx-config-logs.index')"
                                class="block p-6 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition"
                            >
                                <h4 class="text-xl font-bold text-purple-900 mb-2">Nginx Logs</h4>
                                <p class="text-purple-700">View Nginx configuration deployment logs</p>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
