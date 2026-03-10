<template>
  <div class="p-4">
    <Toast />
    <div class="flex align-items-center gap-2 mb-4">
      <Button icon="pi pi-arrow-left" text rounded @click="$router.back()" />
      <h2 class="text-2xl font-bold m-0">Detalle de Issue JIRA</h2>
    </div>

    <!-- Buscador por clave -->
    <div class="card mb-4" v-if="!issue">
      <div class="flex gap-2">
        <InputText v-model="claveInput" placeholder="Ej: SEC-123" class="flex-1"
          @keyup.enter="cargarIssue" />
        <Button label="Buscar" icon="pi pi-search" @click="cargarIssue" :loading="loading" />
      </div>
    </div>

    <!-- Cargando -->
    <div v-if="loading" class="flex justify-content-center py-6">
      <ProgressSpinner />
    </div>

    <!-- Detalle -->
    <div v-else-if="issue" class="grid">
      <div class="col-12 lg:col-8">
        <div class="card mb-3">
          <div class="flex align-items-start justify-content-between mb-3">
            <div>
              <Tag :value="issue.key" severity="info" class="mb-2" />
              <h3 class="text-xl font-semibold m-0">{{ issue.fields?.summary }}</h3>
            </div>
            <Button icon="pi pi-arrow-left" label="Volver a buscar" text @click="issue = null; claveInput = ''" />
          </div>
          <Divider />
          <div class="text-color-secondary" v-html="descripcionHtml" />
        </div>

        <!-- Comentarios -->
        <div class="card">
          <h4 class="font-semibold mb-3">Comentarios</h4>
          <div v-if="!comentarios.length" class="text-color-secondary">Sin comentarios.</div>
          <Timeline :value="comentarios" class="mb-3">
            <template #content="{ item }">
              <div class="surface-100 border-round p-3 mb-2">
                <div class="flex justify-content-between mb-1">
                  <span class="font-semibold">{{ item.author?.displayName }}</span>
                  <span class="text-sm text-color-secondary">{{ item.created?.substring(0, 10) }}</span>
                </div>
                <div class="text-sm">{{ item.body }}</div>
              </div>
            </template>
          </Timeline>
        </div>
      </div>

      <!-- Sidebar metadatos + transiciones -->
      <div class="col-12 lg:col-4">
        <div class="card mb-3">
          <h4 class="font-semibold mb-3">Detalles</h4>
          <div class="flex flex-column gap-2 text-sm">
            <div class="flex justify-content-between">
              <span class="text-color-secondary">Estado</span>
              <Tag :value="issue.fields?.status?.name" :severity="estadoSeverity(issue.fields?.status?.name)" />
            </div>
            <div class="flex justify-content-between">
              <span class="text-color-secondary">Prioridad</span>
              <span>{{ issue.fields?.priority?.name }}</span>
            </div>
            <div class="flex justify-content-between">
              <span class="text-color-secondary">Tipo</span>
              <span>{{ issue.fields?.issuetype?.name }}</span>
            </div>
            <div class="flex justify-content-between">
              <span class="text-color-secondary">Asignado</span>
              <span>{{ issue.fields?.assignee?.displayName ?? '—' }}</span>
            </div>
            <div class="flex justify-content-between">
              <span class="text-color-secondary">Creado</span>
              <span>{{ issue.fields?.created?.substring(0, 10) }}</span>
            </div>
            <div class="flex justify-content-between">
              <span class="text-color-secondary">Actualizado</span>
              <span>{{ issue.fields?.updated?.substring(0, 10) }}</span>
            </div>
          </div>
        </div>

        <!-- Transiciones -->
        <div class="card" v-if="transiciones.length">
          <h4 class="font-semibold mb-3">Transiciones</h4>
          <div class="flex flex-column gap-2">
            <Button v-for="t in transiciones" :key="t.id" :label="t.name" outlined
              @click="ejecutarTransicion(t)" :loading="transitioning === t.id" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import JiraService from '@/services/jira.service.js'

const toast   = useToast()
const route   = useRoute()

const claveInput  = ref(route.query.key ?? '')
const loading     = ref(false)
const issue       = ref(null)
const transiciones = ref([])
const transitioning = ref(null)

const comentarios = computed(() => issue.value?.fields?.comment?.comments ?? [])
const descripcionHtml = computed(() => {
  const desc = issue.value?.fields?.description
  if (!desc) return '<em>Sin descripción</em>'
  return typeof desc === 'string' ? desc : JSON.stringify(desc)
})

function estadoSeverity(estado) {
  const map = {
    'To Do': 'secondary', 'In Progress': 'warning', Done: 'success',
    Open: 'info', Closed: 'success', Resolved: 'success',
  }
  return map[estado] ?? 'secondary'
}

async function cargarIssue() {
  if (!claveInput.value.trim()) return
  loading.value = true
  issue.value   = null
  transiciones.value = []
  try {
    const [issueRes, transRes] = await Promise.all([
      JiraService.getIssueDetail(claveInput.value.trim()),
      JiraService.getTransitions(claveInput.value.trim()),
    ])
    issue.value = issueRes.data
    transiciones.value = transRes.data?.transitions ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el issue', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function ejecutarTransicion(t) {
  transitioning.value = t.id
  try {
    await JiraService.transitionIssue({ issue_key: issue.value.key, transition_id: t.id })
    toast.add({ severity: 'success', summary: 'OK', detail: `Transición "${t.name}" aplicada`, life: 2000 })
    cargarIssue()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo aplicar la transición', life: 3000 })
  } finally {
    transitioning.value = null
  }
}

// Auto-cargar si viene la clave en query string
if (claveInput.value) cargarIssue()
</script>
