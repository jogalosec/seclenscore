<template>
  <div class="p-4">
    <Toast />
    <ConfirmDialog />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Plan de Acciones Correctivas</h2>
      <Tag value="Sprint 5" severity="success" />
    </div>

    <!-- Selector de activo -->
    <div class="card mb-3">
      <div class="flex align-items-center gap-3">
        <label class="font-semibold">Activo:</label>
        <Dropdown v-model="activoSeleccionado" :options="activos" optionLabel="nombre" optionValue="id"
          placeholder="Seleccionar activo…" class="flex-1" @change="cargarPac" />
        <Button label="Nuevo PAC" icon="pi pi-plus" :disabled="!activoSeleccionado" @click="openPacDialog()" />
      </div>
    </div>

    <!-- DataTable PAC con expansión de seguimiento -->
    <DataTable :value="pacList" :loading="loadingPac" paginator :rows="10" stripedRows
      v-model:expandedRows="expandedRows" dataKey="id" class="p-datatable-sm">
      <template #empty>
        <div class="text-center py-4 text-color-secondary">
          {{ activoSeleccionado ? 'No hay PAC para este activo.' : 'Seleccione un activo para ver su PAC.' }}
        </div>
      </template>
      <Column expander style="width:3rem" />
      <Column field="accion" header="Acción" />
      <Column field="responsable" header="Responsable" />
      <Column field="prioridad" header="Prioridad">
        <template #body="{ data }">
          <Tag :value="data.prioridad" :severity="prioridadSeverity(data.prioridad)" />
        </template>
      </Column>
      <Column field="fecha_limite" header="Fecha límite" sortable />
      <Column field="estado" header="Estado">
        <template #body="{ data }">
          <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
        </template>
      </Column>
      <Column header="Acciones" style="width:10rem">
        <template #body="{ data }">
          <Button icon="pi pi-download" text rounded severity="secondary" class="mr-1"
            @click="descargarPac(data)" v-tooltip.top="'Descargar PAC Word'" />
          <Button icon="pi pi-pencil" text rounded class="mr-1" @click="openPacDialog(data)" />
          <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminar(data)" />
        </template>
      </Column>

      <!-- Expansión: seguimiento -->
      <template #expansion="{ data }">
        <div class="p-3">
          <div class="flex align-items-center justify-content-between mb-2">
            <span class="font-semibold">Seguimiento</span>
            <Button label="Añadir seguimiento" icon="pi pi-plus" size="small" @click="openSeguimientoDialog(data.id)" />
          </div>
          <DataTable :value="seguimientoMap[data.id] || []" :loading="loadingSeguimiento[data.id]"
            class="p-datatable-sm" size="small">
            <Column field="fecha" header="Fecha" />
            <Column field="nota" header="Nota" />
            <Column field="estado" header="Estado">
              <template #body="{ data: seg }">
                <Dropdown v-model="seg.estado" :options="estadosSeguimiento" class="p-inputtext-sm"
                  @change="cambiarEstadoSeguimiento(seg)" />
              </template>
            </Column>
            <Column header="" style="width:6rem">
              <template #body="{ data: seg }">
                <Button icon="pi pi-pencil" text rounded size="small" class="mr-1"
                  @click="openEditSeguimientoDialog(seg)" />
                <Button icon="pi pi-trash" text rounded severity="danger" size="small"
                  @click="confirmarEliminarSeguimiento(seg)" />
              </template>
            </Column>
          </DataTable>
        </div>
      </template>
    </DataTable>

    <!-- ──────── DIALOG PAC ──────── -->
    <Dialog v-model:visible="dialogPac" :header="editingPac?.id ? 'Editar PAC' : 'Nuevo PAC'"
      :style="{ width: '520px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Acción *</label>
          <InputText v-model="formPac.accion" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Responsable</label>
          <InputText v-model="formPac.responsable" class="w-full" />
        </div>
        <div class="grid">
          <div class="col-6">
            <label class="font-semibold mb-1 block">Prioridad</label>
            <Dropdown v-model="formPac.prioridad" :options="['alta', 'media', 'baja']" class="w-full" />
          </div>
          <div class="col-6">
            <label class="font-semibold mb-1 block">Estado</label>
            <Dropdown v-model="formPac.estado" :options="estadosSeguimiento" class="w-full" />
          </div>
        </div>
        <div>
          <label class="font-semibold mb-1 block">Fecha límite</label>
          <Calendar v-model="formPac.fecha_limite" dateFormat="yy-mm-dd" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Descripción</label>
          <Textarea v-model="formPac.descripcion" rows="3" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogPac = false" />
        <Button label="Guardar" icon="pi pi-save" @click="guardarPac" :loading="savingPac" />
      </template>
    </Dialog>

    <!-- ──────── DIALOG SEGUIMIENTO ──────── -->
    <Dialog v-model:visible="dialogSeguimiento"
      :header="editingSeguimiento?.id ? 'Editar Seguimiento' : 'Nuevo Seguimiento'"
      :style="{ width: '440px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Nota *</label>
          <Textarea v-model="formSeguimiento.nota" rows="3" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Estado</label>
          <Dropdown v-model="formSeguimiento.estado" :options="estadosSeguimiento" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Fecha</label>
          <Calendar v-model="formSeguimiento.fecha" dateFormat="yy-mm-dd" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogSeguimiento = false" />
        <Button label="Guardar" icon="pi pi-save" @click="guardarSeguimiento" :loading="savingSeguimiento" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import PacService from '@/services/pac.service.js'

const toast   = useToast()
const confirm = useConfirm()

// ─── State ───────────────────────────────────────────────
const activos            = ref([])
const activoSeleccionado = ref(null)
const pacList            = ref([])
const loadingPac         = ref(false)
const expandedRows       = ref([])
const seguimientoMap     = ref({})
const loadingSeguimiento = ref({})

const estadosSeguimiento = ['pendiente', 'en_curso', 'completado', 'cancelado']

// Dialogs
const dialogPac          = ref(false)
const dialogSeguimiento  = ref(false)
const editingPac         = ref(null)
const editingSeguimiento = ref(null)
const savingPac          = ref(false)
const savingSeguimiento  = ref(false)
const currentPacId       = ref(null)

const formPac         = ref({})
const formSeguimiento = ref({})

// ─── Helpers ─────────────────────────────────────────────
function prioridadSeverity(p) {
  return { alta: 'danger', media: 'warning', baja: 'success' }[p] ?? 'secondary'
}
function estadoSeverity(e) {
  return { pendiente: 'secondary', en_curso: 'warning', completado: 'success', cancelado: 'danger' }[e] ?? 'secondary'
}

// ─── Load activos (para selector) ────────────────────────
async function cargarActivos() {
  try {
    // Reutilizamos el endpoint de productos de continuidad que devuelve activos
    const { data } = await PacService.getProductosContinuidad()
    activos.value = data
  } catch {
    // Si falla, dejamos lista vacía — el selector seguirá funcional con id manual
  }
}

// ─── Load PAC ────────────────────────────────────────────
async function cargarPac() {
  if (!activoSeleccionado.value) return
  loadingPac.value = true
  try {
    const { data } = await PacService.getListPac({ activo_id: activoSeleccionado.value })
    pacList.value = Array.isArray(data) ? data : data.pac ?? []
    // Precargar seguimiento para cada PAC expandido
    for (const pac of pacList.value) {
      cargarSeguimiento(pac.id)
    }
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el PAC', life: 3000 })
  } finally {
    loadingPac.value = false
  }
}

async function cargarSeguimiento(pacId) {
  loadingSeguimiento.value[pacId] = true
  try {
    const { data } = await PacService.getSeguimiento(pacId)
    seguimientoMap.value[pacId] = Array.isArray(data) ? data : data.seguimiento ?? []
  } catch {
    seguimientoMap.value[pacId] = []
  } finally {
    loadingSeguimiento.value[pacId] = false
  }
}

// ─── PAC CRUD ────────────────────────────────────────────
function openPacDialog(pac = null) {
  editingPac.value = pac
  formPac.value = pac
    ? { ...pac }
    : { accion: '', responsable: '', prioridad: 'media', estado: 'pendiente', fecha_limite: '', descripcion: '' }
  dialogPac.value = true
}

async function guardarPac() {
  if (!formPac.value.accion) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'La acción es obligatoria', life: 3000 })
    return
  }
  savingPac.value = true
  try {
    if (editingPac.value?.id) {
      await PacService.editSeguimiento({ id: editingPac.value.id, ...formPac.value })
    } else {
      await PacService.createPac({ activo_id: activoSeleccionado.value, ...formPac.value })
    }
    dialogPac.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'PAC guardado', life: 2000 })
    cargarPac()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar el PAC', life: 3000 })
  } finally {
    savingPac.value = false
  }
}

function confirmarEliminar(pac) {
  confirm.require({
    message: `¿Eliminar el PAC "${pac.accion}"?`,
    header: 'Confirmar eliminación',
    icon: 'pi pi-trash',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await PacService.deleteSeguimiento({ id: pac.id })
        toast.add({ severity: 'success', summary: 'Eliminado', detail: 'PAC eliminado', life: 2000 })
        cargarPac()
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar', life: 3000 })
      }
    },
  })
}

async function descargarPac(pac) {
  try {
    const response = await PacService.downloadPac(pac.id)
    const url  = URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href  = url
    link.download = `PAC_${pac.id}.docx`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo descargar el documento', life: 3000 })
  }
}

// ─── Seguimiento CRUD ────────────────────────────────────
function openSeguimientoDialog(pacId) {
  currentPacId.value = pacId
  editingSeguimiento.value = null
  formSeguimiento.value = { nota: '', estado: 'pendiente', fecha: '' }
  dialogSeguimiento.value = true
}

function openEditSeguimientoDialog(seg) {
  editingSeguimiento.value = seg
  formSeguimiento.value = { ...seg }
  dialogSeguimiento.value = true
}

async function guardarSeguimiento() {
  if (!formSeguimiento.value.nota) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'La nota es obligatoria', life: 3000 })
    return
  }
  savingSeguimiento.value = true
  try {
    if (editingSeguimiento.value?.id) {
      await PacService.editSeguimiento({ id: editingSeguimiento.value.id, ...formSeguimiento.value })
    } else {
      await PacService.modEstadoSeguimiento({ pac_id: currentPacId.value, ...formSeguimiento.value })
    }
    dialogSeguimiento.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'Seguimiento guardado', life: 2000 })
    cargarSeguimiento(currentPacId.value ?? editingSeguimiento.value?.pac_id)
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar', life: 3000 })
  } finally {
    savingSeguimiento.value = false
  }
}

async function cambiarEstadoSeguimiento(seg) {
  try {
    await PacService.modEstadoSeguimiento({ id: seg.id, estado: seg.estado })
    toast.add({ severity: 'success', summary: 'Estado actualizado', life: 2000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cambiar el estado', life: 3000 })
  }
}

function confirmarEliminarSeguimiento(seg) {
  confirm.require({
    message: '¿Eliminar este seguimiento?',
    header: 'Confirmar',
    icon: 'pi pi-trash',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await PacService.deleteSeguimiento({ id: seg.id })
        toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 })
        cargarSeguimiento(seg.pac_id)
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar', life: 3000 })
      }
    },
  })
}

// ─── Init ─────────────────────────────────────────────────
onMounted(cargarActivos)
</script>
