<template>
  <div class="p-4">
    <Toast />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Gestor de Evaluaciones</h2>
      <Tag value="Sprint 5" severity="success" />
    </div>

    <!-- Selector de activo -->
    <div class="card mb-3">
      <div class="flex align-items-center gap-3">
        <label class="font-semibold">Activo ID:</label>
        <InputNumber v-model="activoId" class="w-10rem" :min="1" />
        <Button label="Cargar evaluaciones" icon="pi pi-search" @click="cargar" :loading="loading" />
      </div>
    </div>

    <div v-if="evaluaciones.length || loading">
      <TabView>
        <!-- ─── Historial de evaluaciones ─── -->
        <TabPanel header="Historial">
          <DataTable :value="evaluaciones" :loading="loading" paginator :rows="10" stripedRows
            v-model:selection="seleccionadas" selectionMode="multiple" dataKey="id"
            class="p-datatable-sm">
            <template #header>
              <div class="flex gap-2">
                <Button label="Generar ECR" icon="pi pi-file-word" severity="secondary"
                  :disabled="seleccionadas.length === 0" @click="generarDocumento('ecr')" />
                <Button label="Generar ERS" icon="pi pi-file-word"
                  :disabled="seleccionadas.length === 0" @click="generarDocumento('ers')" />
              </div>
            </template>
            <Column selectionMode="multiple" style="width:3rem" />
            <Column field="id" header="ID" style="width:5rem" />
            <Column field="fecha" header="Fecha" sortable />
            <Column field="evaluador" header="Evaluador" />
            <Column field="score" header="Score">
              <template #body="{ data }">
                <Tag :value="String(data.score ?? '—')" :severity="scoreSeverity(data.score)" />
              </template>
            </Column>
            <Column field="normativa" header="Normativa" />
            <Column header="Acciones" style="width:9rem">
              <template #body="{ data }">
                <Button icon="pi pi-file-word" text rounded severity="secondary" class="mr-1"
                  @click="descargarEcr(data)" v-tooltip.top="'Descargar ECR'" :loading="descargando === 'ecr_'+data.id" />
                <Button icon="pi pi-file-word" text rounded
                  @click="descargarErs(data)" v-tooltip.top="'Descargar ERS'" :loading="descargando === 'ers_'+data.id" />
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- ─── Comparación de versiones ─── -->
        <TabPanel header="Comparar versiones">
          <div class="grid mb-3">
            <div class="col-6">
              <label class="font-semibold block mb-1">Versión base</label>
              <Dropdown v-model="versionBase" :options="evaluaciones" optionLabel="fecha" optionValue="id"
                placeholder="Seleccionar evaluación" class="w-full" />
            </div>
            <div class="col-6">
              <label class="font-semibold block mb-1">Versión nueva</label>
              <Dropdown v-model="versionNueva" :options="evaluaciones" optionLabel="fecha" optionValue="id"
                placeholder="Seleccionar evaluación" class="w-full" />
            </div>
          </div>
          <Button label="Comparar" icon="pi pi-arrows-h" @click="comparar"
            :disabled="!versionBase || !versionNueva || versionBase === versionNueva" />

          <div v-if="comparacion" class="mt-4">
            <DataTable :value="comparacion" class="p-datatable-sm" stripedRows>
              <Column field="pregunta" header="Pregunta" />
              <Column field="respuesta_base" header="Base">
                <template #body="{ data }">
                  <Tag :value="data.respuesta_base" :severity="respuestaSeverity(data.respuesta_base)" />
                </template>
              </Column>
              <Column field="respuesta_nueva" header="Nueva">
                <template #body="{ data }">
                  <Tag :value="data.respuesta_nueva" :severity="respuestaSeverity(data.respuesta_nueva)" />
                </template>
              </Column>
              <Column field="cambio" header="Cambio">
                <template #body="{ data }">
                  <i v-if="data.mejora" class="pi pi-arrow-up text-green-500 font-bold" />
                  <i v-else-if="data.empeora" class="pi pi-arrow-down text-red-500 font-bold" />
                  <i v-else class="pi pi-minus text-color-secondary" />
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <!-- ─── Resumen BIA ─── -->
        <TabPanel header="BIA">
          <div v-if="bia" class="grid">
            <div class="col-12 md:col-6">
              <div class="card">
                <h4 class="font-semibold mb-3">Dimensiones</h4>
                <div v-for="(val, key) in bia.dimensiones" :key="key"
                  class="flex justify-content-between mb-2">
                  <span class="capitalize">{{ key }}</span>
                  <Tag :value="String(val)" :severity="scoreSeverity(val * 25)" />
                </div>
              </div>
            </div>
            <div class="col-12 md:col-6">
              <div class="card">
                <h4 class="font-semibold mb-3">Global</h4>
                <div class="flex align-items-center gap-3">
                  <span class="text-4xl font-bold">{{ bia.global?.toFixed(1) }}</span>
                  <Tag :value="bia.severity" :severity="biaSeverity(bia.severity)" class="text-lg" />
                </div>
                <Divider />
                <p class="text-sm text-color-secondary">
                  Calculado a partir de {{ bia.total_preguntas }} preguntas respondidas.
                </p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-4 text-color-secondary">
            Seleccione un activo para ver el BIA.
          </div>
        </TabPanel>
      </TabView>
    </div>

    <div v-else-if="!loading && activoId" class="card">
      <div class="flex flex-column align-items-center py-6 gap-2 text-color-secondary">
        <i class="pi pi-inbox text-4xl" />
        <span>No hay evaluaciones para este activo.</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useToast } from 'primevue/usetoast'

const toast = useToast()

// ─── State ───────────────────────────────────────────────
const activoId     = ref(null)
const loading      = ref(false)
const evaluaciones = ref([])
const seleccionadas = ref([])
const descargando  = ref(null)
const versionBase  = ref(null)
const versionNueva = ref(null)
const comparacion  = ref(null)
const bia          = ref(null)

// ─── Helpers ─────────────────────────────────────────────
function scoreSeverity(score) {
  if (score === null || score === undefined) return 'secondary'
  if (score >= 75) return 'success'
  if (score >= 50) return 'warning'
  return 'danger'
}
function biaSeverity(s) {
  return { critico: 'danger', alto: 'warning', medio: 'info', bajo: 'success' }[s] ?? 'secondary'
}
function respuestaSeverity(r) {
  return { si: 'success', no: 'danger', parcial: 'warning', na: 'secondary' }[r?.toLowerCase()] ?? 'secondary'
}

// ─── Carga ───────────────────────────────────────────────
async function cargar() {
  if (!activoId.value) return
  loading.value = true
  evaluaciones.value = []
  comparacion.value  = null
  bia.value          = null
  try {
    // Importación dinámica para no crear dependencias circulares
    const { default: EvalService } = await import('@/services/evaluaciones.service.js')
    const [evalRes, biaRes] = await Promise.allSettled([
      EvalService.getEvaluacionesSistema({ activo_id: activoId.value }),
      EvalService.getBia(activoId.value),
    ])
    if (evalRes.status === 'fulfilled') evaluaciones.value = evalRes.value.data ?? []
    if (biaRes.status === 'fulfilled')  bia.value = biaRes.value.data ?? null
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las evaluaciones', life: 3000 })
  } finally {
    loading.value = false
  }
}

// ─── Descarga documentos ─────────────────────────────────
async function descargarEcr(evaluacion) {
  descargando.value = 'ecr_' + evaluacion.id
  try {
    const { default: EvalService } = await import('@/services/evaluaciones.service.js')
    const response = await EvalService.getEcr({ activo_id: activoId.value, eval_id: evaluacion.id })
    _descargarBlob(response.data, `ECR_${activoId.value}_${evaluacion.id}.docx`)
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo generar el ECR', life: 3000 })
  } finally {
    descargando.value = null
  }
}

async function descargarErs(evaluacion) {
  descargando.value = 'ers_' + evaluacion.id
  try {
    const { default: EvalService } = await import('@/services/evaluaciones.service.js')
    const response = await EvalService.getErs({ activo_id: activoId.value, eval_id: evaluacion.id })
    _descargarBlob(response.data, `ERS_${activoId.value}_${evaluacion.id}.docx`)
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo generar el ERS', life: 3000 })
  } finally {
    descargando.value = null
  }
}

async function generarDocumento(tipo) {
  if (!seleccionadas.value.length) return
  const ev = seleccionadas.value[0]
  if (tipo === 'ecr') await descargarEcr(ev)
  else await descargarErs(ev)
}

function _descargarBlob(data, nombre) {
  const url  = URL.createObjectURL(new Blob([data]))
  const link = document.createElement('a')
  link.href  = url
  link.download = nombre
  link.click()
  URL.revokeObjectURL(url)
}

// ─── Comparación ─────────────────────────────────────────
function comparar() {
  const base  = evaluaciones.value.find(e => e.id === versionBase.value)
  const nueva = evaluaciones.value.find(e => e.id === versionNueva.value)
  if (!base?.preguntas || !nueva?.preguntas) {
    toast.add({ severity: 'info', summary: 'Sin datos', detail: 'Las evaluaciones no tienen respuestas detalladas', life: 3000 })
    return
  }
  const result = base.preguntas.map(p => {
    const pb = p.respuesta
    const pn = nueva.preguntas.find(q => q.id === p.id)?.respuesta
    return {
      pregunta: p.texto ?? p.id,
      respuesta_base: pb,
      respuesta_nueva: pn,
      mejora:  pb === 'no'  && pn === 'si',
      empeora: pb === 'si'  && pn === 'no',
    }
  })
  comparacion.value = result
}
</script>
