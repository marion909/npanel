<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    initialMetrics: Object,
    alerts: Array,
});

const metrics = ref(props.initialMetrics);
const alerts = ref(props.alerts);
const loading = ref(false);
const lastUpdate = ref(new Date());
let updateInterval = null;

// Format bytes to human readable
const formatBytes = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
};

// Format uptime
const formatUptime = (seconds) => {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    
    let result = [];
    if (days > 0) result.push(`${days}d`);
    if (hours > 0) result.push(`${hours}h`);
    if (minutes > 0) result.push(`${minutes}m`);
    
    return result.join(' ') || '0m';
};

// Fetch updated metrics
const fetchMetrics = async () => {
    try {
        const response = await fetch('/monitoring/stats');
        const data = await response.json();
        metrics.value = data.metrics;
        alerts.value = data.alerts;
        lastUpdate.value = new Date();
    } catch (error) {
        console.error('Failed to fetch metrics:', error);
    }
};

// Get gauge color based on percentage
const getGaugeColor = (percentage) => {
    if (percentage >= 90) return 'text-red-600';
    if (percentage >= 75) return 'text-orange-600';
    if (percentage >= 50) return 'text-yellow-600';
    return 'text-green-600';
};

// Get alert badge color
const getAlertColor = (level) => {
    if (level === 'critical') return 'bg-red-100 text-red-800';
    if (level === 'warning') return 'bg-orange-100 text-orange-800';
    return 'bg-blue-100 text-blue-800';
};

onMounted(() => {
    // Update metrics every 5 seconds
    updateInterval = setInterval(fetchMetrics, 5000);
});

onUnmounted(() => {
    if (updateInterval) {
        clearInterval(updateInterval);
    }
});
</script>

<template>
    <AppLayout title="System Monitoring">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                System Monitoring
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Alerts -->
                <div v-if="alerts.length > 0" class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4 text-red-600">⚠️ Alerts</h3>
                            <div class="space-y-2">
                                <div 
                                    v-for="(alert, index) in alerts" 
                                    :key="index"
                                    class="flex items-center justify-between p-3 rounded-lg"
                                    :class="getAlertColor(alert.level)"
                                >
                                    <div>
                                        <span class="font-semibold">{{ alert.message }}</span>
                                        <span class="ml-2 text-sm">
                                            ({{ alert.value }}% exceeds {{ alert.threshold }}%)
                                        </span>
                                    </div>
                                    <span class="text-xs uppercase font-bold">{{ alert.level }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Overview -->
                <div class="mb-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold">System Overview</h3>
                                <div class="text-sm text-gray-500">
                                    Last update: {{ lastUpdate.toLocaleTimeString() }}
                                    <span class="ml-2 inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <!-- CPU Usage -->
                                <div class="text-center">
                                    <div class="mb-2">
                                        <span class="text-4xl font-bold" :class="getGaugeColor(metrics.cpu)">
                                            {{ metrics.cpu }}%
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-2">CPU Usage</div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            class="h-2 rounded-full transition-all duration-300"
                                            :class="metrics.cpu >= 80 ? 'bg-red-600' : metrics.cpu >= 50 ? 'bg-yellow-600' : 'bg-green-600'"
                                            :style="{ width: metrics.cpu + '%' }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Memory Usage -->
                                <div class="text-center">
                                    <div class="mb-2">
                                        <span class="text-4xl font-bold" :class="getGaugeColor(metrics.memory.percentage)">
                                            {{ metrics.memory.percentage }}%
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-2">Memory Usage</div>
                                    <div class="text-xs text-gray-500 mb-1">
                                        {{ formatBytes(metrics.memory.used * 1024) }} / {{ formatBytes(metrics.memory.total * 1024) }}
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            class="h-2 rounded-full transition-all duration-300"
                                            :class="metrics.memory.percentage >= 90 ? 'bg-red-600' : metrics.memory.percentage >= 50 ? 'bg-yellow-600' : 'bg-green-600'"
                                            :style="{ width: metrics.memory.percentage + '%' }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Disk Usage -->
                                <div class="text-center">
                                    <div class="mb-2">
                                        <span class="text-4xl font-bold" :class="getGaugeColor(metrics.disk.percentage)">
                                            {{ metrics.disk.percentage }}%
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-2">Disk Usage</div>
                                    <div class="text-xs text-gray-500 mb-1">
                                        {{ formatBytes(metrics.disk.used) }} / {{ formatBytes(metrics.disk.total) }}
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div 
                                            class="h-2 rounded-full transition-all duration-300"
                                            :class="metrics.disk.percentage >= 85 ? 'bg-red-600' : metrics.disk.percentage >= 50 ? 'bg-yellow-600' : 'bg-green-600'"
                                            :style="{ width: metrics.disk.percentage + '%' }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- System Info -->
                                <div class="text-center">
                                    <div class="mb-2">
                                        <span class="text-2xl font-bold text-gray-700">
                                            {{ metrics.load['5min'] }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 mb-2">Load Average (5min)</div>
                                    <div class="text-xs text-gray-500">
                                        1min: {{ metrics.load['1min'] }} | 15min: {{ metrics.load['15min'] }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-2">
                                        Uptime: {{ formatUptime(metrics.uptime) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Network Statistics -->
                <div class="mb-6" v-if="Object.keys(metrics.network).length > 0">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Network Interfaces</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Interface</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">RX Bytes</th>
                                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">TX Bytes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(stats, iface) in metrics.network" :key="iface">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ iface }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatBytes(stats.rx_bytes) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatBytes(stats.tx_bytes) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PHP-FPM Pools -->
                <div class="mb-6" v-if="Object.keys(metrics.php_fpm).length > 0">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">PHP-FPM Pools</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Pool</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">PHP</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Active</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Idle</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Queue</th>
                                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Connections</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="(pool, name) in metrics.php_fpm" :key="name">
                                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ pool.pool }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">{{ pool.php_version }}</td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span v-if="!pool.error">{{ pool.active_processes }}</span>
                                                <span v-else class="text-red-500">{{ pool.error }}</span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ pool.idle_processes || '-' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ pool.total_processes || '-' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ pool.listen_queue || '0' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ pool.accepted_conn || '0' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nginx Statistics -->
                <div class="mb-6" v-if="!metrics.nginx.error">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Nginx Statistics</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-blue-600">{{ metrics.nginx.active_connections }}</div>
                                    <div class="text-sm text-gray-600">Active Connections</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-green-600">{{ metrics.nginx.accepts }}</div>
                                    <div class="text-sm text-gray-600">Accepts</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-purple-600">{{ metrics.nginx.handled }}</div>
                                    <div class="text-sm text-gray-600">Handled</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-orange-600">{{ metrics.nginx.requests }}</div>
                                    <div class="text-sm text-gray-600">Requests</div>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <div class="text-xl font-semibold">{{ metrics.nginx.reading }}</div>
                                        <div class="text-xs text-gray-600">Reading</div>
                                    </div>
                                    <div>
                                        <div class="text-xl font-semibold">{{ metrics.nginx.writing }}</div>
                                        <div class="text-xs text-gray-600">Writing</div>
                                    </div>
                                    <div>
                                        <div class="text-xl font-semibold">{{ metrics.nginx.waiting }}</div>
                                        <div class="text-xs text-gray-600">Waiting</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
