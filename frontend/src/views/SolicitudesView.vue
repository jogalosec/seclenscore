<template>
  <div class="p-4">
    <Toast />
    <ConfirmDialog />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Solicitudes de Servicios</h2>
      <div class="flex gap-2">
        <Tag value="Sprint 5" severity="success" />
        <Button label="Nueva solicitud" icon="pi pi-plus" @click="$router.push({ name: 'pentest-request' })" />
      </div>
    </div>

    <TabView @tab-change="onTab">
      <!-- ─── Mis solicitudes ─── -->
      <TabPanel header="Mis solicitudes">
        <DataTable :value="misSolicitudes" :loading="loading" paginator :rows="15" stripedRows
          class="p-datatable-sm" sortField="fecha" :sortOrder="-1">
          <template #empty>
            <div class="text-center py-4 text-color-secondary">No tienes solicitudes registradas.</div>
          </template>
          <Column field="id" header="ID" style="width:5rem" />
          <Column field="tipo" header="Tipo">
            <template #body="{ data }">
              <Tag :value="data.tipo ?? 'Pentest'" severity="info" />
            </template>
          </Column>
          <Column field="activo_nombre" header="Activo" />
          <Column field="estado" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
            </template>
          </Column>
          <Column field="fecha" header="Fecha" sortable style="width:10rem" />
          <Column field="comentario" header="Comentario" />
        </DataTable>
      </TabPanel>

      <!-- ─── Todas (admin) ─── -->
      <TabPanel header="Todas las solicitudes">
        <Toolbar class="mb-3">
          <template #start>
            <Dropdown v-model="filtroEstado" :options="['', 'pendiente', 'aceptado', 'rechazado']"
              placeholder="Filtrar por estado" class="p-inputtext-sm mr-2" @change="filtrar" />
            <Button icon="pi pi-refresh" text @click="cargarTodas" :loading="loadingTodas" />
          </template>
        </Toolbar>
        <DataTable :value="solicitudesFiltradas" :loading="loadingTodas" paginator :rows="15" stripedRows
          class="p-datatable-sm" sortField="fecha" :sortOrder="-1">
          <template #empty>
            <div class="text-center py-4 text-color-secondary">Sin solicitudes.</div>
          </template>
          <Column field="id" header="ID" style="width:5rem" />
          <Column field="tipo" header="Tipo">
            <template #body="{ data }">
              <Tag :value="data.tipo ?? 'Pentest'" severity="info" />
            </template>
          </Column>
          <Column field="activo_nombre" header="Activo" />
          <Column field="solicitante" header="Solicitante" />
          <Column field="estado" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
            </template>
          </Column>
          <Column field="fecha" header="Fecha" sortable style="width:10rem" />
          <Column header="Acciones" style="width:10rem">
            <template #body="{ data }">
              <template v-if="data.estado === 'pendiente'">
                <Button icon="pi pi-check" text rounded severity="success" class="mr-1"
                  v-tooltip.top="'Aceptar'" @click="accionSolicitud(data, 'aceptar')" />
                <Button icon="pi pi-times" text rounded severity="danger"
                  v-tooltip.top="'Rechazar'" @click="abrirRechazar(data)" />
              </template>
              <span v-else class="text-color-secondary text-sm">—</span>
            </template>
          </Column>
        </DataTable>
      </TabPanel>
    </TabView>

    <!-- Dialog rechazar con comentario -->
    <Dialog v-model:visible="dialogRechazar" header="Rechazar solicitud" :style="{ width: '400px' }" modal>
      <div class="mt-2">
        <label class="font-semibold block mb-1">Motivo del rechazo</label>
        <Textarea v-model="comentarioRechazo" rows="3" class="w-full" />
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogRechazar = false" />
        <Button label="Confirmar rechazo" severity="danger" icon="pi pi-times"
          @click="confirmarRechazo" :loading="procesando" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import EvsService from '@/services/evs.service.js'

const toast = useToast()

const misSolicitudes     = ref([])
const todasSolicitudes   = ref([])
const loading            = ref(false)
const loadingTodas       = ref(false)
const filtroEstado       = ref('')
const dialogRechazar     = ref(false)
const comentarioRechazo  = ref('')
const solicitudSeleccionada = ref(null)
const procesando         = ref(false)

const solicitudesFiltradas = computed(() => {
  if (!filtroEstado.value) return todasSolicitudes.value
  return todasSolicitudes.value.filter(s => s.estado === filtroEstado.value)
})

function estadoSeverity(e) {
  return { pendiente: 'warning', aceptado: 'success', rechazado: 'danger' }[e] ?? 'secondary'
}

function onTab(e) {
  if (e.index === 1 && !todasSolicitudes.value.length) cargarTodas()
}

async function cargarMias() {
  loading.value = true
  try {
    const { data } = await EvsService.getSolicitudes()
    misSolicitudes.value = Array.isArray(data) ? data : data.solicitudes ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las solicitudes', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function cargarTodas() {
  loadingTodas.value = true
  try {
    const { data } = await EvsService.getSolicitudes({ all: true })
    todasSolicitudes.value = Array.isArray(data) ? data : data.solicitudes ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las solicitudes', life: 3000 })
  } finally {
    loadingTodas.value = false
  }
}

function filtrar() { /* computed hace el trabajo */ }

async function accionSolicitud(solicitud, accion) {
  try {
    if (accion === 'aceptar') await EvsService.aceptarSolicitud({ id: solicitud.id })
    toast.add({ severity: 'success', summary: 'OK', detail: 'Solicitud aceptada', life: 2000 })
    cargarTodas()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo procesar la solicitud', life: 3000 })
  }
}

function abrirRechazar(solicitud) {
  solicitudSeleccionada.value = solicitud
  comentarioRechazo.value = ''
  dialogRechazar.value = true
}

async function confirmarRechazo() {
  procesando.value = true
  try {
    await EvsService.rechazarSolicitud({ id: solicitudSeleccionada.value.id, comentario: comentarioRechazo.value })
    dialogRechazar.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'Solicitud rechazada', life: 2000 })
    cargarTodas()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo rechazar la solicitud', life: 3000 })
  } finally {
    procesando.value = false
  }
}

onMounted(cargarMias)
</script>
