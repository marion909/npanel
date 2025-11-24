<script setup>
import { onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

const { props } = usePage()
const domainId = props.domainId
const domain = ref(null)
const subdomains = ref([])
const records = ref([])
const nginxLogs = ref([])
const hetznerLogs = ref([])
const loading = ref(true)
const error = ref(null)
const newSub = ref({ name: '', php_version: '', document_root: '', nginx_enabled: true })
const newRecord = ref({ type: 'A', name: '', value: '', ttl: 3600 })

async function load() {
  loading.value = true
  try {
    const { data: d } = await axios.get(`/api/domains/${domainId}`)
    domain.value = d
    subdomains.value = d.subdomains || []
    const recs = await axios.get(`/api/domains/${domainId}/records`)
    records.value = recs.data.data || []
    const nlogs = await axios.get(`/api/domains/${domainId}/nginx-logs`)
    nginxLogs.value = nlogs.data.data || []
    const hlogs = await axios.get(`/api/domains/${domainId}/hetzner-logs`)
    hetznerLogs.value = hlogs.data.data || []
  } catch (e) {
    error.value = 'Fehler beim Laden'
  } finally { loading.value = false }
}

async function addSubdomain() {
  try {
    const { data } = await axios.post(`/api/domains/${domainId}/subdomains`, newSub.value)
    subdomains.value.push(data)
    newSub.value = { name: '', php_version: '', document_root: '', nginx_enabled: true }
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler Subdomain'
  }
}

async function addRecord() {
  try {
    const { data } = await axios.post(`/api/domains/${domainId}/records`, newRecord.value)
    records.value.unshift(data)
    newRecord.value = { type: 'A', name: '', value: '', ttl: 3600 }
  } catch (e) {
    error.value = e.response?.data?.message || 'Fehler Record'
  }
}

async function verifyDomain() {
  await axios.post(`/api/domains/${domainId}/verify`)
  await load()
}

async function requestWildcard() {
  await axios.post(`/api/domains/${domainId}/request-wildcard`)
  await load()
}

onMounted(load)
</script>

<template>
  <div class="p-6 space-y-8" v-if="domain">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Domain: {{ domain.name }}</h1>
      <div class="space-x-2">
        <button @click="verifyDomain" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm">Verifizieren</button>
        <button @click="requestWildcard" class="px-3 py-1 bg-purple-600 text-white rounded text-sm">Wildcard SSL</button>
      </div>
    </div>
    <p class="text-sm text-gray-600">Status: <strong>{{ domain.status }}</strong> | PHP: {{ domain.php_version }} | SSL: {{ domain.wildcard_ssl_status || '-' }}</p>
    <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>

    <!-- Subdomains -->
    <section class="bg-white shadow rounded p-4 space-y-4">
      <h2 class="font-semibold">Subdomains</h2>
      <div class="flex flex-wrap gap-2">
        <input v-model="newSub.name" placeholder="app" class="border rounded p-2" />
        <input v-model="newSub.php_version" placeholder="optional PHP" class="border rounded p-2" />
        <input v-model="newSub.document_root" placeholder="optional DocRoot" class="border rounded p-2" />
        <label class="flex items-center space-x-1 text-sm">
          <input type="checkbox" v-model="newSub.nginx_enabled" /> <span>Nginx</span>
        </label>
        <button @click="addSubdomain" class="bg-blue-600 text-white rounded px-3 py-2">Hinzufügen</button>
      </div>
      <table class="w-full text-sm">
        <thead><tr class="bg-gray-100"><th class="p-2">Name</th><th class="p-2">PHP</th><th class="p-2">Nginx</th></tr></thead>
        <tbody>
          <tr v-for="s in subdomains" :key="s.id" class="border-t">
            <td class="p-2 font-mono">{{ s.full_name }}</td>
            <td class="p-2">{{ s.php_version || domain.php_version }}</td>
            <td class="p-2">{{ s.nginx_enabled ? 'Ja' : 'Nein' }}</td>
          </tr>
          <tr v-if="subdomains.length === 0"><td colspan="3" class="p-2 text-center text-gray-500">Keine Subdomains</td></tr>
        </tbody>
      </table>
    </section>

    <!-- DNS Records -->
    <section class="bg-white shadow rounded p-4 space-y-4">
      <h2 class="font-semibold">DNS Records</h2>
      <div class="flex flex-wrap gap-2">
        <select v-model="newRecord.type" class="border rounded p-2 text-sm">
          <option>A</option><option>AAAA</option><option>CNAME</option><option>TXT</option><option>MX</option><option>NS</option><option>SRV</option>
        </select>
        <input v-model="newRecord.name" placeholder="host" class="border rounded p-2" />
        <input v-model="newRecord.value" placeholder="value" class="border rounded p-2" />
        <input v-model.number="newRecord.ttl" placeholder="TTL" class="border rounded p-2 w-24" />
        <button @click="addRecord" class="bg-green-600 text-white rounded px-3 py-2">Record</button>
      </div>
      <table class="w-full text-xs">
        <thead><tr class="bg-gray-100"><th class="p-2">Typ</th><th class="p-2">Name</th><th class="p-2">Value</th><th class="p-2">TTL</th><th class="p-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="r in records" :key="r.id" class="border-t">
            <td class="p-2">{{ r.type }}</td>
            <td class="p-2 font-mono">{{ r.name }}</td>
            <td class="p-2 break-all">{{ r.value }}</td>
            <td class="p-2">{{ r.ttl }}</td>
            <td class="p-2">{{ r.status }}</td>
          </tr>
          <tr v-if="records.length === 0"><td colspan="5" class="p-2 text-center text-gray-500">Keine Records</td></tr>
        </tbody>
      </table>
    </section>

    <!-- Logs -->
    <section class="grid md:grid-cols-2 gap-6">
      <div class="bg-white shadow rounded p-4 space-y-2">
        <h2 class="font-semibold">Nginx Logs</h2>
        <ul class="text-xs space-y-1 max-h-64 overflow-auto">
          <li v-for="l in nginxLogs" :key="l.id"><span class="font-mono">{{ l.action }}</span> - {{ l.created_at }} - {{ l.success ? 'OK' : 'ERR' }}</li>
          <li v-if="nginxLogs.length===0" class="text-gray-500">Keine Einträge</li>
        </ul>
      </div>
      <div class="bg-white shadow rounded p-4 space-y-2">
        <h2 class="font-semibold">Hetzner API Logs</h2>
        <ul class="text-xs space-y-1 max-h-64 overflow-auto">
          <li v-for="l in hetznerLogs" :key="l.id"><span class="font-mono">{{ l.method }}</span> {{ l.endpoint }} ({{ l.response_code }})</li>
          <li v-if="hetznerLogs.length===0" class="text-gray-500">Keine Einträge</li>
        </ul>
      </div>
    </section>
  </div>
  <div v-else class="p-6">{{ loading ? 'Lade...' : 'Nicht gefunden' }}</div>
</template>
