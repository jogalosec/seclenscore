<template>
  <div class="p-4">
    <Toast />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Planificación de Seguridad</h2>
      <Tag value="Sprint 5" severity="success" />
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
      <div class="flex flex-wrap gap-3 align-items-end">
        <div>
          <label class="font-semibold block mb-1 text-sm">Desde</label>
          <Calendar v-model="filtros.desde" dateFormat="yy-mm-dd" class="w-full" showIcon />
        </div>
        <div>
          <label class="font-semibold block mb-1 text-sm">Hasta</label>
          <Calendar v-model="filtros.hasta" dateFormat="yy-mm-dd" class="w-full" showIcon />
        </div>
        <div>
          <label class="font-semibold block mb-1 text-sm">Tipo</label>
          <Dropdown v-model="filtros.tipo" :options="['', 'Pentest', 'Revisión', 'Evaluación', 'PAC']"
            placeholder="Todos" class="p-inputtext-sm" />
        </div>
        <Button label="Cargar" icon="pi pi-search" @click="cargar" :loading="loading" />
        <Button icon="pi pi-times" severity="secondary" @click="limpiar" v-tooltip.top="'Limpiar'" />
        <div class="ml-auto">
          <SelectButton v-model="vista" :options="vistas" optionLabel="label" optionValue="value">
            <template #option="{ option }">
              <i :class="option.icon" />
            </template>
          </SelectButton>
        </div>
      </div>
    </div>

    <!-- Vista tabla -->
    <div v-if="vista === 'tabla'">
      <DataTable :value="actividadesFiltradas" :loading="loading" paginator :rows="20" stripedRows
        class="p-datatable-sm" sortField="fecha_inicio" :sortOrder="1">
        <template #empty>
          <div class="text-center py-4 text-color-secondary">Sin actividades para el período.</div>
        </template>
        <Column field="tipo" header="Tipo">
          <template #body="{ data }">
            <Tag :value="data.tipo" :severity="tipoSeverity(data.tipo)" />
          </template>
        </Column>
        <Column field="nombre" header="Nombre" sortable />
        <Column field="responsable" header="Responsable" />
        <Column field="estado" header="Estado">
          <template #body="{ data }">
            <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
          </template>
        </Column>
        <Column field="fecha_inicio" header="Inicio" sortable style="width:9rem" />
        <Column field="fecha_fin" header="Fin" style="width:9rem" />
        <Column header="Dur." style="width:5rem">
          <template #body="{ data }">{{ duracion(data.fecha_inicio, data.fecha_fin) }}</template>
        </Column>
        <Column header="" style="width:4rem">
          <template #body="{ data }">
            <Button icon="pi pi-external-link" text rounded size="small" @click="irA(data)" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Vista timeline -->
    <div v-if="vista === 'timeline'">
      <div v-if="!actividadesFiltradas.length && !loading"
        class="text-center py-6 text-color-secondary">
        Sin actividades. Ajusta los filtros y pulsa "Cargar".
      </div>
      <Timeline :value="actividadesFiltradas" align="left" class="mt-2">
        <template #marker="{ item }">
          <span class="flex w-2rem h-2rem align-items-center justify-content-center border-round-full"
            :class="tipoMarkerClass(item.tipo)">
            <i :class="tipoIcon(item.tipo)" style="font-size:0.8rem" />
          </span>
        </template>
        <template #opposite="{ item }">
          <div class="text-sm text-color-secondary text-right">
            <div>{{ item.fecha_inicio }}</div>
            <div v-if="item.fecha_fin && item.fecha_fin !== item.fecha_inicio">→ {{ item.fecha_fin }}</div>
          </div>
        </template>
        <template #content="{ item }">
          <div class="surface-100 border-round p-3 mb-3">
            <div class="flex align-items-center gap-2 mb-1">
              <Tag :value="item.tipo" :severity="tipoSeverity(item.tipo)" />
              <span class="font-semibold">{{ item.nombre }}</span>
            </div>
            <div class="flex gap-3 text-sm text-color-secondary">
              <span v-if="item.responsable"><i class="pi pi-user mr-1" />{{ item.responsable }}</span>
              <Tag :value="item.estado" :severity="estadoSeverity(item.estado)" />
            </div>
          </div>
        </template>
      </Timeline>
    </div>

    <!-- KPI cards -->
    <div class="grid mt-4">
      <div v-for="(count, tipo) in resumenPorTipo" :key="tipo" class="col-6 md:col-3">
        <div class="card text-center p-3">
          <i :class="[tipoIcon(tipo), 'text-2xl mb-1', tipoIconColor(tipo)]" />
          <div class="text-2xl font-bold">{{ count }}</div>
          <div class="text-sm text-color-secondary">{{ tipo }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import EvsService from '@/services/evs.service.js'
import PacService from '@/services/pac.service.js'

const toast  = useToast()
const router = useRouter()

const actividades = ref([])
const loading     = ref(false)
const vista       = ref('tabla')

const vistas = [
  { label: 'Tabla',    value: 'tabla',    icon: 'pi pi-list' },
  { label: 'Timeline', value: 'timeline', icon: 'pi pi-clock' },
]

const filtros = ref({ desde: null, hasta: null, tipo: '' })

const actividadesFiltradas = computed(() => {
  let list = actividades.value
  if (filtros.value.tipo) list = list.filter(a => a.tipo === filtros.value.tipo)
  if (filtros.value.desde) {
    const d = filtros.value.desde.toString().substring(0, 10)
    list = list.filter(a => (a.fecha_inicio ?? '') >= d)
  }
  if (filtros.value.hasta) {
    const h = filtros.value.hasta.toString().substring(0, 10)
    list = list.filter(a => (a.fecha_inicio ?? '') <= h)
  }
  return list
})

const resumenPorTipo = computed(() => {
  const map = {}
  for (const a of actividadesFiltradas.value) {
    map[a.tipo] = (map[a.tipo] ?? 0) + 1
  }
  return map
})

function tipoSeverity(t) {
  return { Pentest: 'danger', Revisión: 'warning', Evaluación: 'info', PAC: 'success' }[t] ?? 'secondary'
}
function estadoSeverity(e) {
  return { abierto: 'info', en_curso: 'warning', cerrado: 'success', pendiente: 'secondary', completado: 'success', cancelado: 'danger' }[e] ?? 'secondary'
}
function tipoMarkerClass(t) {
  return { Pentest: 'bg-red-100 text-red-700', Revisión: 'bg-yellow-100 text-yellow-700', Evaluación: 'bg-blue-100 text-blue-700', PAC: 'bg-green-100 text-green-700' }[t] ?? 'bg-primary-100 text-primary-700'
}
function tipoIcon(t) {
  return { Pentest: 'pi pi-shield', Revisión: 'pi pi-eye', Evaluación: 'pi pi-chart-bar', PAC: 'pi pi-list-check' }[t] ?? 'pi pi-circle'
}
function tipoIconColor(t) {
  return { Pentest: 'text-red-500', Revisión: 'text-yellow-500', Evaluación: 'text-blue-500', PAC: 'text-green-500' }[t] ?? 'text-color-secondary'
}
function duracion(inicio, fin) {
  if (!inicio || !fin) return '—'
  const d = Math.round((new Date(fin) - new Date(inicio)) / 86400000)
  return d >= 0 ? `${d}d` : '—'
}
function irA(actividad) {
  const routes = { Pentest: 'evs', PAC: 'pac', Revisión: 'evs', Evaluación: 'evaluacion' }
  router.push({ name: routes[actividad.tipo] ?? 'servicios' })
}

async function cargar() {
  loading.value = true
  actividades.value = []
  try {
    const [ptRes, pacRes] = await Promise.allSettled([
      EvsService.getPentests(),
      PacService.getListPac({}),
    ])
    if (ptRes.status === 'fulfilled') {
      const pts = ptRes.value.data
      const lista = Array.isArray(pts) ? pts : pts.pentests ?? []
      actividades.value.push(...lista.map(p => ({ ...p, tipo: 'Pentest' })))
    }
    if (pacRes.status === 'fulfilled') {
      const pacs = pacRes.value.data
      const lista = Array.isArray(pacs) ? pacs : pacs.pac ?? []
      actividades.value.push(...lista.map(p => ({
        nombre: p.accion, responsable: p.responsable, estado: p.estado,
        fecha_inicio: p.fecha_limite, fecha_fin: p.fecha_limite, tipo: 'PAC',
      })))
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las actividades', life: 3000 })
  } finally {
    loading.value = false
  }
}

function limpiar() {
  filtros.value = { desde: null, hasta: null, tipo: '' }
}

onMounted(cargar)
</script>
