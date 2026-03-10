<template>
  <div class="p-4">
    <Toast />
    <ConfirmDialog />

    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">KPMs</h2>
    </div>

    <!-- TabView: Métricas | Madurez | CSIRT | Reporters | Definiciones -->
    <TabView v-model:activeIndex="tabActivo" @tab-change="onTabChange">

      <!-- ─── Métricas ─── -->
      <TabPanel header="Métricas">
        <KpmTabla
          tipo="metricas"
          :kpms="kpms.metricas"
          :loading="loading.metricas"
          @lock="lockKpms('metricas', $event)"
          @unlock="unlockKpms('metricas', $event)"
          @delete="deleteKpms('metricas', $event)"
          @edit="editarKpm('metricas', $event)"
        />
      </TabPanel>

      <!-- ─── Madurez ─── -->
      <TabPanel header="Madurez">
        <KpmTabla
          tipo="madurez"
          :kpms="kpms.madurez"
          :loading="loading.madurez"
          @lock="lockKpms('madurez', $event)"
          @unlock="unlockKpms('madurez', $event)"
          @delete="deleteKpms('madurez', $event)"
          @edit="editarKpm('madurez', $event)"
        />
      </TabPanel>

      <!-- ─── CSIRT ─── -->
      <TabPanel header="CSIRT">
        <KpmTabla
          tipo="csirt"
          :kpms="kpms.csirt"
          :loading="loading.csirt"
          @lock="lockKpms('csirt', $event)"
          @unlock="unlockKpms('csirt', $event)"
          @delete="deleteKpms('csirt', $event)"
          @edit="editarKpm('csirt', $event)"
        />
      </TabPanel>

      <!-- ─── Reporters ─── -->
      <TabPanel header="Reporters">
        <div class="card">
          <div class="flex justify-content-between align-items-center mb-3">
            <h3 class="m-0">Reporters KPMs</h3>
            <Button label="Nuevo reporter" icon="pi pi-plus" @click="dialogReporter = true" />
          </div>

          <DataTable
            :value="reporters"
            :loading="loading.reporters"
            paginator
            :rows="10"
            row-hover
            striped-rows
          >
            <Column field="email" header="Usuario" />
            <Column field="activo_nombre" header="Activo" />
            <Column header="Acciones" style="width: 100px">
              <template #body="{ data }">
                <Button
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  text
                  v-tooltip="'Eliminar reporter'"
                  @click="eliminarReporter(data)"
                />
              </template>
            </Column>
          </DataTable>
        </div>
      </TabPanel>

    </TabView>

    <!-- ─── Dialog editar KPM ─── -->
    <Dialog
      v-model:visible="dialogEdit"
      header="Editar KPM"
      modal
      :style="{ width: '400px' }"
    >
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">Valor (0-4)</label>
        <InputNumber
          v-model="formEdit.valor"
          :min="0"
          :max="4"
          :step="0.5"
          class="w-full"
        />
      </div>
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">Comentario</label>
        <Textarea v-model="formEdit.comentario" rows="3" class="w-full" />
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="dialogEdit = false" />
        <Button label="Guardar" icon="pi pi-save" :loading="guardandoKpm" @click="guardarKpm" />
      </template>
    </Dialog>

    <!-- ─── Dialog nuevo reporter ─── -->
    <Dialog
      v-model:visible="dialogReporter"
      header="Nuevo Reporter KPM"
      modal
      :style="{ width: '400px' }"
    >
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">ID de usuario</label>
        <InputNumber v-model="formReporter.userId" class="w-full" :min="1" />
      </div>
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">ID de activo</label>
        <InputNumber v-model="formReporter.idActivo" class="w-full" :min="1" />
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text @click="dialogReporter = false" />
        <Button label="Crear" icon="pi pi-plus" :loading="creandoReporter" @click="crearReporter" />
      </template>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, reactive, onMounted, defineAsyncComponent } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import KpmsService from '@/services/kpms.service.js'

const toast = useToast()
const confirm = useConfirm()

// Componente inline para la tabla de KPMs (evita duplicación)
const KpmTabla = {
  name: 'KpmTabla',
  props: ['tipo', 'kpms', 'loading'],
  emits: ['lock', 'unlock', 'delete', 'edit'],
  template: `
    <div class="card">
      <div class="flex justify-content-between align-items-center mb-3">
        <h3 class="m-0">{{ tipo.charAt(0).toUpperCase() + tipo.slice(1) }}</h3>
        <div class="flex gap-2">
          <Button
            label="Bloquear sel."
            icon="pi pi-lock"
            severity="warning"
            size="small"
            :disabled="!seleccionados.length"
            @click="$emit('lock', seleccionados.map(r => r.id))"
          />
          <Button
            label="Desbloquear sel."
            icon="pi pi-lock-open"
            severity="info"
            size="small"
            :disabled="!seleccionados.length"
            @click="$emit('unlock', seleccionados.map(r => r.id))"
          />
          <Button
            label="Eliminar sel."
            icon="pi pi-trash"
            severity="danger"
            size="small"
            :disabled="!seleccionados.length"
            @click="$emit('delete', seleccionados.map(r => r.id))"
          />
        </div>
      </div>
      <DataTable
        v-model:selection="seleccionados"
        :value="kpms"
        :loading="loading"
        paginator
        :rows="15"
        row-hover
        striped-rows
        data-key="id"
      >
        <Column selection-mode="multiple" style="width: 3rem" />
        <Column field="id" header="ID" style="width: 70px" sortable />
        <Column field="reporter_email" header="Reporter" />
        <Column field="valor" header="Valor" style="width: 90px" sortable />
        <Column field="comentario" header="Comentario" />
        <Column field="locked" header="Estado" style="width: 100px">
          <template #body="{ data }">
            <Tag
              :value="data.locked ? 'Bloqueado' : 'Activo'"
              :severity="data.locked ? 'warning' : 'success'"
            />
          </template>
        </Column>
        <Column field="fecha" header="Fecha" sortable>
          <template #body="{ data }">
            {{ data.fecha ? new Date(data.fecha).toLocaleDateString('es-ES') : '—' }}
          </template>
        </Column>
        <Column header="Editar" style="width: 80px">
          <template #body="{ data }">
            <Button
              icon="pi pi-pencil"
              size="small"
              severity="secondary"
              text
              :disabled="!!data.locked"
              @click="$emit('edit', data)"
            />
          </template>
        </Column>
      </DataTable>
    </div>
  `,
  setup() {
    const seleccionados = ref([])
    return { seleccionados }
  },
}

// ---------------------------------------------------------------
// Estado
// ---------------------------------------------------------------
const tabActivo = ref(0)
const tiposPorTab = ['metricas', 'madurez', 'csirt', 'reporters']

const kpms = reactive({ metricas: [], madurez: [], csirt: [] })
const loading = reactive({ metricas: false, madurez: false, csirt: false, reporters: false })

const reporters = ref([])

const dialogEdit = ref(false)
const guardandoKpm = ref(false)
const kpmEditando = ref(null)
const tipoEditando = ref(null)
const formEdit = ref({ valor: null, comentario: '' })

const dialogReporter = ref(false)
const creandoReporter = ref(false)
const formReporter = ref({ userId: null, idActivo: null })

// ---------------------------------------------------------------
// Lifecycle
// ---------------------------------------------------------------
onMounted(() => cargarTab(0))

// ---------------------------------------------------------------
// Métodos
// ---------------------------------------------------------------
async function onTabChange(e) {
  cargarTab(e.index)
}

async function cargarTab(index) {
  const tipo = tiposPorTab[index]
  if (tipo === 'reporters') {
    await cargarReporters()
    return
  }
  if (kpms[tipo].length) return  // ya cargado
  loading[tipo] = true
  try {
    const res = await KpmsService.getKpms(tipo)
    kpms[tipo] = res.data?.kpms ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: `No se pudieron cargar los KPMs de ${tipo}.`, life: 3000 })
  } finally {
    loading[tipo] = false
  }
}

async function cargarReporters() {
  loading.reporters = true
  try {
    const res = await KpmsService.getReportersKpms()
    reporters.value = res.data?.reporters ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los reporters.', life: 3000 })
  } finally {
    loading.reporters = false
  }
}

async function lockKpms(tipo, ids) {
  try {
    await KpmsService.lockKpms(tipo, ids)
    toast.add({ severity: 'success', summary: 'Bloqueados', detail: `${ids.length} KPM(s) bloqueados.`, life: 3000 })
    kpms[tipo] = []  // fuerza recarga
    await cargarTab(tiposPorTab.indexOf(tipo))
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron bloquear los KPMs.', life: 3000 })
  }
}

async function unlockKpms(tipo, ids) {
  try {
    await KpmsService.unlockKpms(tipo, ids)
    toast.add({ severity: 'success', summary: 'Desbloqueados', detail: `${ids.length} KPM(s) desbloqueados.`, life: 3000 })
    kpms[tipo] = []
    await cargarTab(tiposPorTab.indexOf(tipo))
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron desbloquear los KPMs.', life: 3000 })
  }
}

async function deleteKpms(tipo, ids) {
  confirm.require({
    message: `¿Eliminar ${ids.length} KPM(s) de tipo ${tipo}?`,
    header: 'Confirmar eliminación',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await KpmsService.delKpms(tipo, ids)
        toast.add({ severity: 'success', summary: 'Eliminados', detail: `${ids.length} KPM(s) eliminados.`, life: 3000 })
        kpms[tipo] = []
        await cargarTab(tiposPorTab.indexOf(tipo))
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron eliminar los KPMs.', life: 3000 })
      }
    },
  })
}

function editarKpm(tipo, kpm) {
  tipoEditando.value = tipo
  kpmEditando.value = kpm
  formEdit.value = { valor: kpm.valor ?? null, comentario: kpm.comentario ?? '' }
  dialogEdit.value = true
}

async function guardarKpm() {
  guardandoKpm.value = true
  try {
    await KpmsService.editKpm(tipoEditando.value, kpmEditando.value.id, {
      valor: formEdit.value.valor,
      comentario: formEdit.value.comentario,
    })
    toast.add({ severity: 'success', summary: 'KPM actualizado', detail: 'Los cambios se guardaron correctamente.', life: 3000 })
    dialogEdit.value = false
    kpms[tipoEditando.value] = []
    await cargarTab(tiposPorTab.indexOf(tipoEditando.value))
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo actualizar el KPM.', life: 3000 })
  } finally {
    guardandoKpm.value = false
  }
}

async function crearReporter() {
  if (!formReporter.value.userId || !formReporter.value.idActivo) {
    toast.add({ severity: 'warn', summary: 'Campos requeridos', detail: 'Introduce el ID de usuario y de activo.', life: 3000 })
    return
  }
  creandoReporter.value = true
  try {
    await KpmsService.newReporterKpms(formReporter.value.userId, formReporter.value.idActivo)
    toast.add({ severity: 'success', summary: 'Reporter creado', detail: 'El reporter KPM se ha creado correctamente.', life: 3000 })
    dialogReporter.value = false
    formReporter.value = { userId: null, idActivo: null }
    await cargarReporters()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo crear el reporter.', life: 3000 })
  } finally {
    creandoReporter.value = false
  }
}

async function eliminarReporter(reporter) {
  confirm.require({
    message: `¿Eliminar el reporter de ${reporter.email} para el activo "${reporter.activo_nombre}"?`,
    header: 'Confirmar eliminación',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await KpmsService.deleteReporterKpms(reporter.id)
        toast.add({ severity: 'success', summary: 'Reporter eliminado', detail: 'El reporter se eliminó correctamente.', life: 3000 })
        await cargarReporters()
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el reporter.', life: 3000 })
      }
    },
  })
}
</script>
