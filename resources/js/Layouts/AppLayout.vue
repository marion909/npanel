<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <h1 class="text-2xl font-bold text-gray-900">nPanel</h1>
                        </div>
                        <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                            <a href="/dashboard" class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Dashboard
                            </a>
                            <a href="/mail" class="border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Mail
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-700 mr-4">{{ user?.name }}</span>
                        <Link href="/logout" method="post" as="button" class="text-sm text-gray-600 hover:text-gray-900">
                            Logout
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="py-10">
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    user: Object
});
</script>
