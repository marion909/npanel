<script setup>
import { onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const domains = ref([])
const loading = ref(false)
const form = ref({ name: '', php_version: '8.2', wildcard_ssl_enabled: false })
const error = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/domains')
    domains.value = data.data || []
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally { loading.value = false }
}

async function createDomain() {
  error.value = null
  try {
    const { data } = await axios.post('/api/domains', form.value)
    domains.value.unshift(data)
    form.value = { name: '', php_version: '8.2', wildcard_ssl_enabled: false }
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler beim Erstellen'
  }
}

function openDomain(id) {
  router.get(`/domains/${id}`)
}

onMounted(load)
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">Domains</h1>
    <div class="bg-white shadow rounded p-4 space-y-4">
      <h2 class="font-semibold">Neue Domain</h2>
      <div class="grid md:grid-cols-4 gap-2">
        <input v-model="form.name" placeholder="example.com" class="border rounded p-2" />
        <input v-model="form.php_version" placeholder="8.2" class="border rounded p-2" />
        <label class="inline-flex items-center space-x-2">
          <input type="checkbox" v-model="form.wildcard_ssl_enabled" />
          <span>Wildcard SSL</span>
        </label>
        <button @click="createDomain" class="bg-blue-600 text-white rounded px-4 py-2">Speichern</button>
      </div>
      <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
    </div>
    <div class="bg-white shadow rounded">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="bg-gray-100 text-left">
            <th class="p-2">Name</th>
            <th class="p-2">Status</th>
            <th class="p-2">PHP</th>
            <th class="p-2">SSL</th>
            <th class="p-2">Aktion</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in domains" :key="d.id" class="border-t">
            <td class="p-2 font-mono">{{ d.name }}</td>
            <td class="p-2">{{ d.status }}</td>
            <td class="p-2">{{ d.php_version }}</td>
            <td class="p-2">{{ d.wildcard_ssl_status || '-' }}</td>
            <td class="p-2"><button @click="openDomain(d.id)" class="text-blue-600 hover:underline">Details</button></td>
          </tr>
          <tr v-if="!loading && domains.length===0">
            <td colspan="5" class="p-4 text-center text-gray-500">Keine Domains vorhanden</td>
          </tr>
          <tr v-if="loading"><td colspan="5" class="p-4">Lade...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
