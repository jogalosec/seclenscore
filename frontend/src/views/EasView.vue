<template>
  <div class="p-4">
    <Toast />
    <ConfirmDialog />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Evaluación de Arquitectura de Seguridad</h2>
      <Tag value="Sprint 5" severity="success" />
    </div>

    <Toolbar class="mb-3">
      <template #start>
        <Button label="Nuevo Issue EAS" icon="pi pi-plus" @click="openDialog()" />
        <Button label="Actualizar" icon="pi pi-refresh" text class="ml-2" @click="cargar" :loading="loading" />
      </template>
      <template #end>
        <InputText v-model="filtro" placeholder="Buscar…" class="p-inputtext-sm" />
      </template>
    </Toolbar>

    <DataTable :value="issuesFiltrados" :loading="loading" paginator :rows="15" stripedRows
      class="p-datatable-sm" :globalFilter="filtro">
      <template #empty>
        <div class="text-center py-4 text-color-secondary">No hay issues EAS.</div>
      </template>
      <Column field="key" header="Clave" style="width:9rem" sortable>
        <template #body="{ data }">
          <a class="text-primary font-semibold cursor-pointer" @click="verDetalle(data)">{{ data.key }}</a>
        </template>
      </Column>
      <Column field="summary" header="Resumen" sortable />
      <Column field="status" header="Estado">
        <template #body="{ data }">
          <Tag :value="data.status" :severity="estadoSeverity(data.status)" />
        </template>
      </Column>
      <Column field="priority" header="Prioridad">
        <template #body="{ data }">
          <Tag :value="data.priority" :severity="prioridadSeverity(data.priority)" />
        </template>
      </Column>
      <Column field="assignee" header="Asignado" />
      <Column field="created" header="Creado" sortable style="width:9rem">
        <template #body="{ data }">{{ data.created?.substring(0, 10) }}</template>
      </Column>
      <Column header="Acciones" style="width:8rem">
        <template #body="{ data }">
          <Button icon="pi pi-eye" text rounded class="mr-1" @click="verDetalle(data)" v-tooltip.top="'Ver detalle'" />
          <Button icon="pi pi-external-link" text rounded @click="abrirJira(data)" v-tooltip.top="'Abrir en JIRA'" />
        </template>
      </Column>
    </DataTable>

    <!-- ──────── DIALOG NUEVO ISSUE ──────── -->
    <Dialog v-model:visible="dialog" header="Nuevo Issue EAS" :style="{ width: '520px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Título *</label>
          <InputText v-model="form.titulo" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Severidad</label>
          <Dropdown v-model="form.severidad" :options="severidades" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Descripción</label>
          <Textarea v-model="form.descripcion" rows="4" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialog = false" />
        <Button label="Crear en JIRA" icon="pi pi-send" @click="crearIssue" :loading="saving" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import JiraService from '@/services/jira.service.js'

const toast  = useToast()
const router = useRouter()

const issues  = ref([])
const loading = ref(false)
const filtro  = ref('')
const dialog  = ref(false)
const saving  = ref(false)
const form    = ref({})

const severidades = ['Critical', 'High', 'Medium', 'Low', 'Informational']

const issuesFiltrados = computed(() => {
  if (!filtro.value) return issues.value
  const q = filtro.value.toLowerCase()
  return issues.value.filter(i =>
    (i.key ?? '').toLowerCase().includes(q) ||
    (i.summary ?? '').toLowerCase().includes(q) ||
    (i.assignee ?? '').toLowerCase().includes(q)
  )
})

function estadoSeverity(s) {
  return { 'To Do': 'secondary', 'In Progress': 'warning', Done: 'success', Open: 'info', Closed: 'success' }[s] ?? 'secondary'
}
function prioridadSeverity(p) {
  return { Critical: 'danger', High: 'danger', Medium: 'warning', Low: 'info', Lowest: 'secondary' }[p] ?? 'secondary'
}

async function cargar() {
  loading.value = true
  try {
    const { data } = await JiraService.getIssuesEas()
    issues.value = Array.isArray(data) ? data : data.issues ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los issues EAS', life: 3000 })
  } finally {
    loading.value = false
  }
}

function openDialog() {
  form.value = { titulo: '', severidad: 'Medium', descripcion: '' }
  dialog.value = true
}

async function crearIssue() {
  if (!form.value.titulo) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'El título es obligatorio', life: 3000 })
    return
  }
  saving.value = true
  try {
    await JiraService.newIssueArquitectura(form.value)
    dialog.value = false
    toast.add({ severity: 'success', summary: 'Issue creado en JIRA', life: 2000 })
    cargar()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo crear el issue', life: 3000 })
  } finally {
    saving.value = false
  }
}

function verDetalle(issue) {
  router.push({ name: 'issue-detail', query: { key: issue.key } })
}

function abrirJira(issue) {
  if (issue.url) window.open(issue.url, '_blank')
}

onMounted(cargar)
</script>
