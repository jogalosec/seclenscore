<template>
  <div class="p-4">
    <Toast />
    <ConfirmDialog />

    <!-- Header -->
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Evaluaciones</h2>
    </div>

    <!-- Selector de activo -->
    <div class="card mb-4">
      <div class="flex align-items-center gap-3">
        <label class="font-semibold">Activo:</label>
        <Dropdown
          v-model="activoSeleccionado"
          :options="activos"
          option-label="nombre"
          option-value="id"
          placeholder="Seleccionar activo…"
          filter
          class="w-full md:w-20rem"
          @change="cargarDatos"
        />
      </div>
    </div>

    <!-- TabView principal -->
    <TabView v-if="activoSeleccionado">

      <!-- Tab BIA -->
      <TabPanel header="BIA">
        <div class="grid">
          <div class="col-12 md:col-8">
            <div class="card">
              <div class="flex justify-content-between align-items-center mb-3">
                <h3 class="m-0">Business Impact Analysis</h3>
                <Button
                  label="Guardar BIA"
                  icon="pi pi-save"
                  :loading="guardandoBia"
                  @click="guardarBia"
                />
              </div>

              <!-- Preguntas BIA agrupadas -->
              <Accordion :multiple="true">
                <AccordionTab
                  v-for="grupo in gruposBia"
                  :key="grupo.dimension + grupo.subdimension"
                  :header="`${grupo.dimension} — ${grupo.subdimension}`"
                >
                  <div
                    v-for="p in grupo.preguntas"
                    :key="p.id"
                    class="field mb-3"
                  >
                    <label class="block mb-1 font-medium">{{ p.texto }}</label>
                    <SelectButton
                      v-model="respuestasBia[p.id]"
                      :options="opcionesBia"
                      option-label="label"
                      option-value="value"
                    />
                  </div>
                </AccordionTab>
              </Accordion>
            </div>
          </div>

          <!-- Panel resultado BIA -->
          <div class="col-12 md:col-4">
            <div class="card" v-if="resultadoBia">
              <h4 class="mb-3">Resultado BIA</h4>
              <div v-for="dim in ['Con', 'Int', 'Dis']" :key="dim" class="mb-3">
                <div class="flex justify-content-between mb-1">
                  <span class="font-semibold">{{ dimLabel[dim] }}</span>
                  <Tag :value="resultadoBia[dim]?.total?.toFixed(2)" :severity="severityBia(resultadoBia[dim]?.total)" />
                </div>
                <ProgressBar :value="(resultadoBia[dim]?.total / 4) * 100" class="h-1rem" />
              </div>
              <Divider />
              <div class="flex justify-content-between align-items-center">
                <span class="font-bold">Global</span>
                <Tag :value="resultadoBia.global?.toFixed(2)" :severity="severityBia(resultadoBia.global)" size="large" />
              </div>
            </div>
            <div class="card" v-else-if="biaCargado">
              <p class="text-center text-color-secondary">Sin datos BIA guardados.</p>
            </div>
          </div>
        </div>
      </TabPanel>

      <!-- Tab Evaluaciones -->
      <TabPanel header="Evaluaciones de Cumplimiento">
        <div class="card">
          <div class="flex justify-content-between align-items-center mb-3">
            <h3 class="m-0">Historial de evaluaciones</h3>
            <Button
              label="Nueva evaluación"
              icon="pi pi-plus"
              @click="abrirDialogEval"
            />
          </div>

          <DataTable
            :value="evaluaciones"
            :loading="cargandoEval"
            paginator
            :rows="10"
            row-hover
            sort-field="fecha"
            :sort-order="-1"
            striped-rows
          >
            <Column field="id" header="ID" style="width: 80px" />
            <Column field="meta_key" header="Tipo" style="width: 120px">
              <template #body="{ data }">
                <Tag :value="data.meta_key" />
              </template>
            </Column>
            <Column field="fecha" header="Fecha" sortable>
              <template #body="{ data }">
                {{ formatFecha(data.fecha) }}
              </template>
            </Column>
            <Column header="Acciones" style="width: 160px">
              <template #body="{ data }">
                <div class="flex gap-2">
                  <Button
                    icon="pi pi-eye"
                    size="small"
                    severity="info"
                    text
                    v-tooltip="'Ver preguntas'"
                    @click="verPreguntas(data)"
                  />
                  <Button
                    icon="pi pi-pencil"
                    size="small"
                    severity="warning"
                    text
                    v-tooltip="'Crear versión'"
                    @click="abrirEditarEval(data)"
                  />
                </div>
              </template>
            </Column>
          </DataTable>
        </div>
      </TabPanel>

    </TabView>

    <!-- Dialog nueva evaluación -->
    <Dialog
      v-model:visible="dialogEval"
      header="Nueva evaluación"
      modal
      :style="{ width: '500px' }"
    >
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">Tipo (meta_key)</label>
        <Dropdown
          v-model="formEval.meta_key"
          :options="tiposEval"
          placeholder="Seleccionar tipo…"
          class="w-full"
        />
      </div>
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">Datos (JSON)</label>
        <Textarea
          v-model="formEval.datosRaw"
          rows="6"
          class="w-full font-mono"
          placeholder='{"pregunta1": 3, "pregunta2": 2}'
        />
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="dialogEval = false" />
        <Button label="Guardar" icon="pi pi-save" :loading="guardandoEval" @click="guardarEval" />
      </template>
    </Dialog>

    <!-- Dialog ver preguntas -->
    <Dialog
      v-model:visible="dialogVerPreguntas"
      header="Preguntas de la evaluación"
      modal
      :style="{ width: '700px' }"
    >
      <pre class="overflow-auto max-h-30rem text-sm">{{ JSON.stringify(preguntasVistas, null, 2) }}</pre>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import EvaluacionesService from '@/services/evaluaciones.service.js'
import ActivosService from '@/services/activos.service.js'

const toast = useToast()
const confirm = useConfirm()

// ---------------------------------------------------------------
// Estado
// ---------------------------------------------------------------
const activos = ref([])
const activoSeleccionado = ref(null)

const respuestasBia = ref({})
const resultadoBia = ref(null)
const biaCargado = ref(false)
const guardandoBia = ref(false)

const evaluaciones = ref([])
const cargandoEval = ref(false)

const dialogEval = ref(false)
const guardandoEval = ref(false)
const formEval = ref({ meta_key: 'preguntas', datosRaw: '{}' })

const dialogVerPreguntas = ref(false)
const preguntasVistas = ref(null)

// ---------------------------------------------------------------
// Constantes
// ---------------------------------------------------------------
const opcionesBia = [
  { label: '0 — Sin impacto', value: 0 },
  { label: '1 — Bajo', value: 1 },
  { label: '2 — Medio', value: 2 },
  { label: '3 — Alto', value: 3 },
  { label: '4 — Crítico', value: 4 },
]

const dimLabel = { Con: 'Confidencialidad', Int: 'Integridad', Dis: 'Disponibilidad' }
const tiposEval = ['preguntas', 'pac', 'bia', 'osa']

// Preguntas BIA simplificadas (el servidor devuelve el JSON completo)
const gruposBia = [
  {
    dimension: 'Dis', subdimension: 'Fin',
    preguntas: Array.from({ length: 5 }, (_, i) => ({ id: `p${i + 1}`, texto: `Pregunta Disponibilidad-Financiero ${i + 1}` })),
  },
  {
    dimension: 'Dis', subdimension: 'Op',
    preguntas: Array.from({ length: 5 }, (_, i) => ({ id: `p${i + 6}`, texto: `Pregunta Disponibilidad-Operacional ${i + 1}` })),
  },
  {
    dimension: 'Con', subdimension: 'Fin',
    preguntas: Array.from({ length: 2 }, (_, i) => ({ id: `p${i + 18}`, texto: `Pregunta Confidencialidad-Financiero ${i + 1}` })),
  },
  {
    dimension: 'Int', subdimension: 'Fin',
    preguntas: Array.from({ length: 2 }, (_, i) => ({ id: `p${i + 30}`, texto: `Pregunta Integridad-Financiero ${i + 1}` })),
  },
]

// ---------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------
onMounted(async () => {
  try {
    const res = await ActivosService.getActivos()
    activos.value = res.data?.activos ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los activos.', life: 3000 })
  }
})

// ---------------------------------------------------------------
// Métodos
// ---------------------------------------------------------------
async function cargarDatos() {
  if (!activoSeleccionado.value) return
  await Promise.all([cargarBia(), cargarEvaluaciones()])
}

async function cargarBia() {
  biaCargado.value = false
  try {
    const res = await EvaluacionesService.getBia(activoSeleccionado.value)
    const bia = res.data?.bia
    biaCargado.value = true
    if (bia?.meta_value) {
      respuestasBia.value = typeof bia.meta_value === 'string'
        ? JSON.parse(bia.meta_value)
        : bia.meta_value
    }
  } catch {
    biaCargado.value = true
  }
}

async function guardarBia() {
  guardandoBia.value = true
  try {
    const res = await EvaluacionesService.saveBia(activoSeleccionado.value, respuestasBia.value)
    resultadoBia.value = res.data?.bia
    toast.add({ severity: 'success', summary: 'BIA guardado', detail: 'El BIA se ha guardado y calculado correctamente.', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar el BIA.', life: 3000 })
  } finally {
    guardandoBia.value = false
  }
}

async function cargarEvaluaciones() {
  cargandoEval.value = true
  try {
    const res = await EvaluacionesService.getEvaluaciones(activoSeleccionado.value)
    evaluaciones.value = res.data?.evaluaciones ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las evaluaciones.', life: 3000 })
  } finally {
    cargandoEval.value = false
  }
}

function abrirDialogEval() {
  formEval.value = { meta_key: 'preguntas', datosRaw: '{}' }
  dialogEval.value = true
}

async function guardarEval() {
  let datos
  try {
    datos = JSON.parse(formEval.value.datosRaw)
  } catch {
    toast.add({ severity: 'warn', summary: 'JSON inválido', detail: 'Revisa el formato de los datos.', life: 3000 })
    return
  }
  guardandoEval.value = true
  try {
    await EvaluacionesService.saveEvaluacion(activoSeleccionado.value, datos, formEval.value.meta_key)
    toast.add({ severity: 'success', summary: 'Evaluación guardada', detail: 'La evaluación se ha guardado correctamente.', life: 3000 })
    dialogEval.value = false
    await cargarEvaluaciones()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar la evaluación.', life: 3000 })
  } finally {
    guardandoEval.value = false
  }
}

async function verPreguntas(evaluacion) {
  try {
    const res = await EvaluacionesService.getPreguntasEvaluacion(evaluacion.id)
    preguntasVistas.value = res.data?.preguntas
    dialogVerPreguntas.value = true
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las preguntas.', life: 3000 })
  }
}

async function abrirEditarEval(evaluacion) {
  try {
    const res = await EvaluacionesService.getPreguntasEvaluacion(evaluacion.id)
    const datos = res.data?.preguntas?.preguntas ?? {}
    await EvaluacionesService.editEvaluacion({ evaluate: datos, version: 'Edición manual' }, evaluacion.id)
    toast.add({ severity: 'success', summary: 'Versión creada', detail: 'Se ha creado una nueva versión de la evaluación.', life: 3000 })
    await cargarEvaluaciones()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo crear la versión.', life: 3000 })
  }
}

// ---------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------
function formatFecha(fecha) {
  if (!fecha) return '—'
  return new Date(fecha).toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' })
}

function severityBia(val) {
  if (val === undefined || val === null) return 'secondary'
  if (val >= 3) return 'danger'
  if (val >= 2) return 'warning'
  if (val >= 1) return 'info'
  return 'success'
}
</script>
