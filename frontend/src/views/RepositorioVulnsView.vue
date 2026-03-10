<template>
  <div class="p-4">
    <Toast />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Repositorio de Vulnerabilidades</h2>
      <Tag value="Sprint 5" severity="success" />
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
      <div class="flex flex-wrap gap-3 align-items-end">
        <div>
          <label class="font-semibold block mb-1 text-sm">Severidad</label>
          <MultiSelect v-model="filtros.severidad" :options="severidades" placeholder="Todas"
            class="p-inputtext-sm" style="min-width:160px" />
        </div>
        <div>
          <label class="font-semibold block mb-1 text-sm">Estado</label>
          <Dropdown v-model="filtros.estado" :options="['', 'open', 'closed', 'dismissed']"
            placeholder="Todos" class="p-inputtext-sm" style="min-width:130px" />
        </div>
        <div class="flex-1">
          <label class="font-semibold block mb-1 text-sm">Buscar</label>
          <InputText v-model="filtros.q" placeholder="Nombre, clave, descripción…"
            class="w-full p-inputtext-sm" />
        </div>
        <Button icon="pi pi-times" severity="secondary" @click="limpiar" v-tooltip.top="'Limpiar'" />
      </div>
    </div>

    <TabView @tab-change="onTab">
      <!-- ─── Issues JIRA ─── -->
      <TabPanel header="Issues JIRA">
        <DataTable :value="issuesFiltrados" :loading="loadingIssues" paginator :rows="20" stripedRows
          class="p-datatable-sm" sortField="created" :sortOrder="-1">
          <template #empty>
            <div class="text-center py-4 text-color-secondary">Sin issues.</div>
          </template>
          <Column field="key" header="Clave" style="width:9rem">
            <template #body="{ data }">
              <a class="text-primary font-semibold cursor-pointer" @click="verIssue(data)">{{ data.key }}</a>
            </template>
          </Column>
          <Column field="summary" header="Resumen" />
          <Column field="priority" header="Severidad">
            <template #body="{ data }">
              <Tag :value="data.priority" :severity="severidadSeverity(data.priority)" />
            </template>
          </Column>
          <Column field="status" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="estadoSeverity(data.status)" />
            </template>
          </Column>
          <Column field="assignee" header="Asignado" style="width:10rem" />
          <Column field="created" header="Creado" sortable style="width:9rem">
            <template #body="{ data }">{{ data.created?.substring(0, 10) }}</template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ─── Alertas Prisma Cloud ─── -->
      <TabPanel header="Alertas Prisma Cloud">
        <Toolbar class="mb-3">
          <template #start>
            <Dropdown v-model="cloudSeleccionada" :options="clouds" optionLabel="name" optionValue="accountId"
              placeholder="Cuenta Cloud…" class="mr-2" @change="cargarAlertas" style="min-width:200px" />
            <Dropdown v-model="estadoPrisma" :options="['open', 'dismissed', 'resolved']"
              class="p-inputtext-sm" @change="cargarAlertas" />
          </template>
        </Toolbar>
        <DataTable :value="alertasFiltradas" :loading="loadingPrisma" paginator :rows="20" stripedRows
          class="p-datatable-sm">
          <template #empty>
            <div class="text-center py-4 text-color-secondary">
              {{ cloudSeleccionada ? 'Sin alertas.' : 'Selecciona una cuenta para ver alertas.' }}
            </div>
          </template>
          <Column field="id" header="ID" style="width:9rem" />
          <Column field="policy.name" header="Política" />
          <Column field="severity" header="Severidad">
            <template #body="{ data }">
              <Tag :value="data.severity" :severity="severidadSeverity(data.severity)" />
            </template>
          </Column>
          <Column field="status" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="estadoSeverity(data.status)" />
            </template>
          </Column>
          <Column field="resource.name" header="Recurso" />
          <Column header="Acciones" style="width:7rem">
            <template #body="{ data }">
              <Button v-if="data.status === 'open'" icon="pi pi-ban" text rounded severity="warning"
                v-tooltip.top="'Dismiss'" @click="dismissAlerta(data)" />
              <Button v-if="data.status === 'dismissed'" icon="pi pi-undo" text rounded severity="info"
                v-tooltip.top="'Reabrir'" @click="reabrirAlerta(data)" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ─── Resumen ─── -->
      <TabPanel header="Resumen">
        <div class="grid mt-2">
          <div class="col-12 md:col-6">
            <div class="card">
              <h4 class="font-semibold mb-3">Issues por severidad</h4>
              <div v-for="s in severidades" :key="s" class="flex align-items-center gap-3 mb-2">
                <Tag :value="s" :severity="severidadSeverity(s)" style="min-width:7rem" />
                <div class="flex-1 surface-200 border-round" style="height:8px">
                  <div class="border-round h-full"
                    :style="{ width: pctIssues(s) + '%', background: severidadColor(s) }" />
                </div>
                <span class="font-semibold" style="min-width:2rem">{{ countIssues(s) }}</span>
              </div>
            </div>
          </div>
          <div class="col-12 md:col-6">
            <div class="card">
              <h4 class="font-semibold mb-3">Alertas Prisma por severidad</h4>
              <div v-for="s in ['critical','high','medium','low','informational']" :key="s"
                class="flex align-items-center gap-3 mb-2">
                <Tag :value="s" :severity="severidadSeverity(s)" style="min-width:7rem" />
                <div class="flex-1 surface-200 border-round" style="height:8px">
                  <div class="border-round h-full"
                    :style="{ width: pctAlertas(s) + '%', background: severidadColor(s) }" />
                </div>
                <span class="font-semibold" style="min-width:2rem">{{ countAlertas(s) }}</span>
              </div>
            </div>
          </div>
        </div>
      </TabPanel>
    </TabView>

    <!-- Dialog dismiss -->
    <Dialog v-model:visible="dialogDismiss" header="Dismiss alerta" :style="{ width: '400px' }" modal>
      <div class="mt-2">
        <label class="font-semibold block mb-1">Razón</label>
        <Textarea v-model="razonDismiss" rows="3" class="w-full" />
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogDismiss = false" />
        <Button label="Confirmar dismiss" severity="warning" icon="pi pi-ban"
          @click="confirmarDismiss" :loading="procesandoDismiss" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import EvsService from '@/services/evs.service.js'

const toast  = useToast()
const router = useRouter()

const issues            = ref([])
const alertas           = ref([])
const clouds            = ref([])
const cloudSeleccionada = ref(null)
const estadoPrisma      = ref('open')
const loadingIssues     = ref(false)
const loadingPrisma     = ref(false)

const severidades = ['Critical', 'High', 'Medium', 'Low', 'Informational']
const filtros     = ref({ severidad: [], estado: '', q: '' })

const dialogDismiss      = ref(false)
const razonDismiss       = ref('')
const alertaSeleccionada = ref(null)
const procesandoDismiss  = ref(false)

// ─── Computed ────────────────────────────────────────────
const issuesFiltrados = computed(() => {
  let list = issues.value
  if (filtros.value.severidad.length)
    list = list.filter(i => filtros.value.severidad.includes(i.priority))
  if (filtros.value.estado)
    list = list.filter(i => i.status === filtros.value.estado)
  if (filtros.value.q) {
    const q = filtros.value.q.toLowerCase()
    list = list.filter(i => (i.key + i.summary + (i.assignee ?? '')).toLowerCase().includes(q))
  }
  return list
})

const alertasFiltradas = computed(() => {
  if (!filtros.value.q) return alertas.value
  const q = filtros.value.q.toLowerCase()
  return alertas.value.filter(a =>
    (a.id + (a.policy?.name ?? '') + (a.resource?.name ?? '')).toLowerCase().includes(q)
  )
})

// ─── Helpers ─────────────────────────────────────────────
function severidadSeverity(s) {
  const map = { Critical: 'danger', critical: 'danger', High: 'danger', high: 'danger', Medium: 'warning', medium: 'warning', Low: 'info', low: 'info', Informational: 'secondary', informational: 'secondary' }
  return map[s] ?? 'secondary'
}
function estadoSeverity(s) {
  return { open: 'info', Open: 'info', 'In Progress': 'warning', closed: 'success', Done: 'success', dismissed: 'secondary', resolved: 'success' }[s] ?? 'secondary'
}
function severidadColor(s) {
  const map = { Critical: '#ef4444', critical: '#ef4444', High: '#f97316', high: '#f97316', Medium: '#f59e0b', medium: '#f59e0b', Low: '#6366f1', low: '#6366f1' }
  return map[s] ?? '#94a3b8'
}
function countIssues(s) { return issues.value.filter(i => i.priority === s).length }
function pctIssues(s)   { return issues.value.length ? Math.round(countIssues(s) / issues.value.length * 100) : 0 }
function countAlertas(s){ return alertas.value.filter(a => a.severity?.toLowerCase() === s).length }
function pctAlertas(s)  { return alertas.value.length ? Math.round(countAlertas(s) / alertas.value.length * 100) : 0 }

// ─── Carga ───────────────────────────────────────────────
async function cargarIssues() {
  loadingIssues.value = true
  try {
    const { data } = await EvsService.getIssuesPentest()
    issues.value = Array.isArray(data) ? data : data.issues ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los issues', life: 3000 })
  } finally {
    loadingIssues.value = false
  }
}

async function cargarClouds() {
  try {
    const { data } = await EvsService.getPrismaCloud()
    clouds.value = Array.isArray(data) ? data : data.clouds ?? []
  } catch { /* silencioso */ }
}

async function cargarAlertas() {
  if (!cloudSeleccionada.value) return
  loadingPrisma.value = true
  try {
    const { data } = await EvsService.getPrismaAlertByCloud(cloudSeleccionada.value, estadoPrisma.value)
    alertas.value = Array.isArray(data) ? data : data.alerts ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las alertas', life: 3000 })
  } finally {
    loadingPrisma.value = false
  }
}

function onTab(e) {
  if (e.index === 1 && !clouds.value.length) cargarClouds()
}

function limpiar() {
  filtros.value = { severidad: [], estado: '', q: '' }
}

function verIssue(issue) {
  router.push({ name: 'issue-detail', query: { key: issue.key } })
}

// ─── Dismiss / Reabrir ───────────────────────────────────
function dismissAlerta(alerta) {
  alertaSeleccionada.value = alerta
  razonDismiss.value = ''
  dialogDismiss.value = true
}

async function confirmarDismiss() {
  procesandoDismiss.value = true
  try {
    await EvsService.dismissPrismaAlert({ alert_id: alertaSeleccionada.value.id, razon: razonDismiss.value })
    dialogDismiss.value = false
    toast.add({ severity: 'success', summary: 'Alerta dismissed', life: 2000 })
    cargarAlertas()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo hacer dismiss', life: 3000 })
  } finally {
    procesandoDismiss.value = false
  }
}

async function reabrirAlerta(alerta) {
  try {
    await EvsService.reopenPrismaAlert(alerta.id)
    toast.add({ severity: 'success', summary: 'Alerta reabierta', life: 2000 })
    cargarAlertas()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo reabrir', life: 3000 })
  }
}

onMounted(() => {
  cargarIssues()
  cargarClouds()
})
</script>
