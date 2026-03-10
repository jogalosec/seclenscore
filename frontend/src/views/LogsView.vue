<template>
  <div class="p-4">
    <Toast />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Logs del Sistema</h2>
      <Tag value="Sprint 6" severity="success" />
    </div>

    <!-- Filtros globales -->
    <div class="card mb-3">
      <div class="grid align-items-end">
        <div class="col-12 md:col-3">
          <label class="font-semibold block mb-1">Fecha inicio</label>
          <Calendar v-model="filtros.fecha_inicio" dateFormat="yy-mm-dd" class="w-full" showIcon />
        </div>
        <div class="col-12 md:col-3">
          <label class="font-semibold block mb-1">Fecha fin</label>
          <Calendar v-model="filtros.fecha_fin" dateFormat="yy-mm-dd" class="w-full" showIcon />
        </div>
        <div class="col-12 md:col-3">
          <label class="font-semibold block mb-1">Usuario</label>
          <InputText v-model="filtros.user_id" placeholder="ID o nombre" class="w-full" />
        </div>
        <div class="col-12 md:col-3 flex gap-2">
          <Button label="Buscar" icon="pi pi-search" @click="cargarActivo" :loading="loading" class="flex-1" />
          <Button icon="pi pi-times" severity="secondary" @click="limpiarFiltros" v-tooltip.top="'Limpiar'" />
        </div>
      </div>
    </div>

    <TabView @tab-change="onTab">
      <!-- ─── Logs de Activos ─── -->
      <TabPanel header="Activos">
        <DataTable :value="logsActivos" :loading="loading" paginator :rows="20" stripedRows
          class="p-datatable-sm" sortField="fecha" :sortOrder="-1">
          <template #empty><div class="text-center py-4 text-color-secondary">Sin resultados.</div></template>
          <Column field="fecha" header="Fecha" sortable style="width:12rem" />
          <Column field="tipo" header="Tipo">
            <template #body="{ data }">
              <Tag :value="data.tipo" :severity="tipoSeverity(data.tipo)" />
            </template>
          </Column>
          <Column field="activo_id" header="Activo ID" style="width:8rem" />
          <Column field="nombre" header="Nombre" />
          <Column field="usuario" header="Usuario" style="width:10rem" />
          <Column field="detalle" header="Detalle" />
        </DataTable>
      </TabPanel>

      <!-- ─── Logs de Relaciones ─── -->
      <TabPanel header="Relaciones">
        <DataTable :value="logsRelaciones" :loading="loadingRel" paginator :rows="20" stripedRows
          class="p-datatable-sm" sortField="fecha" :sortOrder="-1">
          <template #empty><div class="text-center py-4 text-color-secondary">Sin resultados.</div></template>
          <Column field="fecha" header="Fecha" sortable style="width:12rem" />
          <Column field="accion" header="Acción">
            <template #body="{ data }">
              <Tag :value="data.accion" :severity="accionSeverity(data.accion)" />
            </template>
          </Column>
          <Column field="tabla" header="Tabla" style="width:10rem" />
          <Column field="entidad_id" header="Entidad ID" style="width:8rem" />
          <Column field="usuario" header="Usuario" style="width:10rem" />
          <Column field="descripcion" header="Descripción" />
        </DataTable>
      </TabPanel>

      <!-- ─── Eventos / Accesos ─── -->
      <TabPanel header="Eventos">
        <DataTable :value="eventos" :loading="loadingEv" paginator :rows="20" stripedRows
          class="p-datatable-sm" sortField="fecha" :sortOrder="-1">
          <template #empty><div class="text-center py-4 text-color-secondary">Sin resultados.</div></template>
          <Column field="fecha" header="Fecha" sortable style="width:12rem" />
          <Column field="evento" header="Evento">
            <template #body="{ data }">
              <Tag :value="data.evento" :severity="eventoSeverity(data.evento)" />
            </template>
          </Column>
          <Column field="usuario" header="Usuario" style="width:10rem" />
          <Column field="ip" header="IP" style="width:10rem" />
          <Column field="ruta" header="Ruta" />
          <Column field="resultado" header="Resultado">
            <template #body="{ data }">
              <i :class="data.resultado === 'ok' ? 'pi pi-check-circle text-green-500' : 'pi pi-times-circle text-red-500'" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ─── Timeline ─── -->
      <TabPanel header="Timeline">
        <div v-if="loadingTimeline" class="flex justify-content-center py-6">
          <ProgressSpinner />
        </div>
        <div v-else-if="!timeline.length" class="text-center py-6 text-color-secondary">
          Aplique filtros y pulse "Buscar" para ver el timeline.
        </div>
        <Timeline v-else :value="timeline" align="left" class="mt-2">
          <template #marker="{ item }">
            <span class="flex w-2rem h-2rem align-items-center justify-content-center border-round-full"
              :class="timelineMarkerClass(item.tipo)">
              <i :class="timelineIcon(item.tipo)" style="font-size:0.8rem" />
            </span>
          </template>
          <template #content="{ item }">
            <div class="surface-100 border-round p-3 mb-3">
              <div class="flex justify-content-between mb-1">
                <span class="font-semibold">{{ item.nombre ?? item.activo_id }}</span>
                <span class="text-sm text-color-secondary">{{ item.fecha }}</span>
              </div>
              <div class="flex gap-2">
                <Tag :value="item.tipo" :severity="tipoSeverity(item.tipo)" />
                <span class="text-sm">{{ item.detalle }}</span>
              </div>
              <div v-if="item.usuario" class="text-xs text-color-secondary mt-1">
                <i class="pi pi-user mr-1" />{{ item.usuario }}
              </div>
            </div>
          </template>
        </Timeline>
      </TabPanel>
    </TabView>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'
import LogsService from '@/services/logs.service.js'

const toast = useToast()

// ─── State ───────────────────────────────────────────────
const logsActivos   = ref([])
const logsRelaciones = ref([])
const eventos        = ref([])
const timeline       = ref([])

const loading        = ref(false)
const loadingRel     = ref(false)
const loadingEv      = ref(false)
const loadingTimeline = ref(false)

const tabActivo = ref(0)

const filtros = ref({
  fecha_inicio: null,
  fecha_fin: null,
  user_id: '',
})

// ─── Helpers visuales ────────────────────────────────────
function tipoSeverity(t) {
  return { nuevo: 'success', modificado: 'warning', eliminado: 'danger', archivado: 'secondary' }[t] ?? 'secondary'
}
function accionSeverity(a) {
  return { INSERT: 'success', UPDATE: 'warning', DELETE: 'danger' }[a?.toUpperCase()] ?? 'secondary'
}
function eventoSeverity(e) {
  return { login: 'info', logout: 'secondary', error: 'danger', acceso: 'info' }[e?.toLowerCase()] ?? 'secondary'
}
function timelineMarkerClass(t) {
  return {
    nuevo: 'bg-green-100 text-green-700',
    modificado: 'bg-yellow-100 text-yellow-700',
    eliminado: 'bg-red-100 text-red-700',
  }[t] ?? 'bg-primary-100 text-primary-700'
}
function timelineIcon(t) {
  return { nuevo: 'pi pi-plus', modificado: 'pi pi-pencil', eliminado: 'pi pi-trash' }[t] ?? 'pi pi-circle'
}

// ─── Build params ────────────────────────────────────────
function buildParams() {
  const p = {}
  if (filtros.value.fecha_inicio) p.fecha_inicio = filtros.value.fecha_inicio
  if (filtros.value.fecha_fin)    p.fecha_fin    = filtros.value.fecha_fin
  if (filtros.value.user_id)      p.user_id      = filtros.value.user_id
  return p
}

// ─── Cargar según tab activo ─────────────────────────────
function onTab(e) {
  tabActivo.value = e.index
  if (e.index === 0) cargarActivo()
  else if (e.index === 1) cargarRelaciones()
  else if (e.index === 2) cargarEventos()
  else if (e.index === 3) cargarTimeline()
}

async function cargarActivo() {
  loading.value = true
  try {
    const { data } = await LogsService.getLogsActivosRaw(buildParams())
    logsActivos.value = Array.isArray(data) ? data : data.logs ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los logs de activos', life: 3000 })
  } finally {
    loading.value = false
  }
}

async function cargarRelaciones() {
  loadingRel.value = true
  try {
    const { data } = await LogsService.getLogsRelacion(buildParams())
    logsRelaciones.value = Array.isArray(data) ? data : data.logs ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los logs de relaciones', life: 3000 })
  } finally {
    loadingRel.value = false
  }
}

async function cargarEventos() {
  loadingEv.value = true
  try {
    const { data } = await LogsService.getEvents(buildParams())
    eventos.value = Array.isArray(data) ? data : data.events ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los eventos', life: 3000 })
  } finally {
    loadingEv.value = false
  }
}

async function cargarTimeline() {
  loadingTimeline.value = true
  try {
    const { data } = await LogsService.getLogsActivosProcessed(buildParams())
    timeline.value = Array.isArray(data) ? data : data.timeline ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el timeline', life: 3000 })
  } finally {
    loadingTimeline.value = false
  }
}

function limpiarFiltros() {
  filtros.value = { fecha_inicio: null, fecha_fin: null, user_id: '' }
}
</script>
