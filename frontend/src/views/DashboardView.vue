<template>
  <div class="p-4">
    <Toast />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Cuadro de Mando</h2>
      <div class="flex gap-2 align-items-center">
        <Tag value="Sprint 6" severity="success" />
        <Button icon="pi pi-refresh" text rounded @click="cargarTodo" :loading="loading" />
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid mb-4">
      <div class="col-12 md:col-6 lg:col-3">
        <div class="card text-center">
          <i class="pi pi-desktop text-3xl text-primary mb-2" />
          <div class="text-4xl font-bold">{{ kpiActivos.total ?? '—' }}</div>
          <div class="text-color-secondary mt-1">Activos totales</div>
          <div class="flex justify-content-center gap-3 mt-2 text-sm">
            <span class="text-red-500"><i class="pi pi-exclamation-triangle" /> {{ kpiActivos.expuestos ?? 0 }}</span>
            <span class="text-color-secondary"><i class="pi pi-archive" /> {{ kpiActivos.archivados ?? 0 }}</span>
          </div>
        </div>
      </div>
      <div class="col-12 md:col-6 lg:col-3">
        <div class="card text-center">
          <i class="pi pi-shield text-3xl text-orange-500 mb-2" />
          <div class="text-4xl font-bold">{{ kpiPentest.total ?? '—' }}</div>
          <div class="text-color-secondary mt-1">Pentests</div>
          <div class="flex justify-content-center gap-3 mt-2 text-sm">
            <Tag value="abierto" severity="info" />
            <Tag :value="String(kpiPentest.abierto ?? 0)" severity="info" />
          </div>
        </div>
      </div>
      <div class="col-12 md:col-6 lg:col-3">
        <div class="card text-center">
          <i class="pi pi-list-check text-3xl text-green-500 mb-2" />
          <div class="text-4xl font-bold">{{ kpiPac.total ?? '—' }}</div>
          <div class="text-color-secondary mt-1">Acciones PAC</div>
          <div class="flex justify-content-center gap-2 mt-2 text-sm">
            <Tag value="completadas" severity="success" />
            <Tag :value="String(kpiPac.completadas ?? 0)" severity="success" />
          </div>
        </div>
      </div>
      <div class="col-12 md:col-6 lg:col-3">
        <div class="card text-center">
          <i class="pi pi-chart-bar text-3xl text-purple-500 mb-2" />
          <div class="text-4xl font-bold">{{ kpiEcr.total ?? '—' }}</div>
          <div class="text-color-secondary mt-1">Evaluaciones ECR</div>
          <div class="text-sm text-color-secondary mt-2">
            Últimos 90 días: {{ kpiEcr.ultimos_90d ?? 0 }}
          </div>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="grid mb-4">
      <!-- BIA por nivel -->
      <div class="col-12 md:col-6">
        <div class="card">
          <h4 class="font-semibold mb-3">Activos por nivel BIA</h4>
          <Chart type="doughnut" :data="chartBia" :options="chartOpts" style="max-height:280px" />
        </div>
      </div>

      <!-- Pentests por estado -->
      <div class="col-12 md:col-6">
        <div class="card">
          <h4 class="font-semibold mb-3">Pentests por estado</h4>
          <Chart type="bar" :data="chartPentest" :options="chartOptsBar" style="max-height:280px" />
        </div>
      </div>
    </div>

    <!-- PAC seguimiento + Activos por tipo -->
    <div class="grid">
      <div class="col-12 md:col-6">
        <div class="card">
          <h4 class="font-semibold mb-3">PAC — seguimiento por estado</h4>
          <div v-if="!kpiPac.seguimiento_por_estado" class="text-color-secondary text-center py-3">Sin datos</div>
          <div v-else class="flex flex-column gap-2">
            <div v-for="(count, estado) in kpiPac.seguimiento_por_estado" :key="estado"
              class="flex align-items-center gap-3">
              <Tag :value="estado" :severity="pacEstadoSeverity(estado)" style="min-width:7rem" />
              <div class="flex-1">
                <div class="surface-200 border-round" style="height:8px">
                  <div class="border-round h-full" :class="pacBarColor(estado)"
                    :style="{ width: pacPct(count) + '%' }" />
                </div>
              </div>
              <span class="font-semibold" style="min-width:2rem">{{ count }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 md:col-6">
        <div class="card">
          <h4 class="font-semibold mb-3">Activos por tipo</h4>
          <Chart type="pie" :data="chartActiposTipo" :options="chartOpts" style="max-height:280px" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import DashboardService from '@/services/dashboard.service.js'

const toast   = useToast()
const loading = ref(false)

const kpiActivos = ref({})
const kpiBia     = ref({})
const kpiEcr     = ref({})
const kpiPentest = ref({})
const kpiPac     = ref({})

// ─── Colores PrimeVue ────────────────────────────────────
const COLORS = ['#6366f1', '#f59e0b', '#22c55e', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316']

const chartOpts    = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
const chartOptsBar = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }

// ─── Chart data BIA ──────────────────────────────────────
const chartBia = computed(() => {
  const d = kpiBia.value
  return {
    labels: ['Crítico', 'Alto', 'Medio', 'Bajo'],
    datasets: [{ data: [d.critico ?? 0, d.alto ?? 0, d.medio ?? 0, d.bajo ?? 0], backgroundColor: ['#ef4444','#f97316','#f59e0b','#22c55e'] }],
  }
})

// ─── Chart data Pentest ──────────────────────────────────
const chartPentest = computed(() => {
  const d = kpiPentest.value
  return {
    labels: Object.keys(d).filter(k => k !== 'total'),
    datasets: [{ label: 'Pentests', data: Object.entries(d).filter(([k]) => k !== 'total').map(([,v]) => v), backgroundColor: COLORS }],
  }
})

// ─── Chart data Activos por tipo ─────────────────────────
const chartActiposTipo = computed(() => {
  const d = kpiActivos.value.por_tipo ?? {}
  return {
    labels: Object.keys(d),
    datasets: [{ data: Object.values(d), backgroundColor: COLORS }],
  }
})

// ─── PAC helpers ─────────────────────────────────────────
function pacEstadoSeverity(e) {
  return { pendiente: 'secondary', en_curso: 'warning', completado: 'success', cancelado: 'danger' }[e] ?? 'secondary'
}
function pacBarColor(e) {
  return { pendiente: 'bg-gray-400', en_curso: 'bg-yellow-500', completado: 'bg-green-500', cancelado: 'bg-red-500' }[e] ?? 'bg-primary'
}
function pacPct(count) {
  const total = Object.values(kpiPac.value.seguimiento_por_estado ?? {}).reduce((a, b) => a + b, 0)
  return total > 0 ? Math.round((count / total) * 100) : 0
}

// ─── Carga ───────────────────────────────────────────────
async function cargarTodo() {
  loading.value = true
  try {
    const [activos, bia, ecr, pentest, pac] = await Promise.allSettled([
      DashboardService.getDashboardActivos(),
      DashboardService.getDashboardBia(),
      DashboardService.getDashboardEcr(),
      DashboardService.getDashboardPentest(),
      DashboardService.getDashboardPac(),
    ])
    if (activos.status  === 'fulfilled') kpiActivos.value  = activos.value.data  ?? {}
    if (bia.status      === 'fulfilled') kpiBia.value      = bia.value.data      ?? {}
    if (ecr.status      === 'fulfilled') kpiEcr.value      = ecr.value.data      ?? {}
    if (pentest.status  === 'fulfilled') kpiPentest.value  = pentest.value.data  ?? {}
    if (pac.status      === 'fulfilled') kpiPac.value      = pac.value.data      ?? {}
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el dashboard', life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(cargarTodo)
</script>
