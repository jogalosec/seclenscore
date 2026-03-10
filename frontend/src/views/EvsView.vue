<template>
  <div class="p-4">
    <Toast />
    <ConfirmDialog />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Pentest — EVS</h2>
      <Tag value="Sprint 5" severity="success" />
    </div>

    <TabView @tab-change="onTabChange">
      <!-- ─────────────────── PENTESTS ─────────────────── -->
      <TabPanel header="Pentests">
        <Toolbar class="mb-3">
          <template #start>
            <Button label="Nuevo Pentest" icon="pi pi-plus" @click="openPentestDialog()" />
          </template>
        </Toolbar>
        <DataTable :value="pentests" :loading="loadingPentests" paginator :rows="10" stripedRows
          class="p-datatable-sm">
          <Column field="nombre" header="Nombre" sortable />
          <Column field="estado" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
            </template>
          </Column>
          <Column field="fecha_inicio" header="Inicio" sortable />
          <Column field="fecha_fin" header="Fin" sortable />
          <Column field="responsable" header="Responsable" />
          <Column header="Acciones" style="width:12rem">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded class="mr-1" @click="openPentestDialog(data)" />
              <Button icon="pi pi-shield" text rounded severity="secondary" class="mr-1"
                @click="openAsignarDialog(data)" v-tooltip.top="'Asignar pentester'" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminarPentest(data)" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ─────────────────── ISSUES JIRA ─────────────────── -->
      <TabPanel header="Issues JIRA">
        <Toolbar class="mb-3">
          <template #start>
            <Dropdown v-model="pentestSeleccionado" :options="pentests" optionLabel="nombre" optionValue="id"
              placeholder="Seleccionar pentest" class="mr-2" @change="cargarIssues" />
            <Button label="Nuevo Issue" icon="pi pi-plus" @click="openIssueDialog()" :disabled="!pentestSeleccionado" />
          </template>
        </Toolbar>
        <DataTable :value="issues" :loading="loadingIssues" paginator :rows="10" stripedRows class="p-datatable-sm">
          <Column field="key" header="Clave" style="width:8rem" />
          <Column field="summary" header="Resumen" />
          <Column field="status" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="estadoSeverity(data.status)" />
            </template>
          </Column>
          <Column field="priority" header="Prioridad" />
          <Column field="assignee" header="Asignado" />
          <Column header="Acciones" style="width:8rem">
            <template #body="{ data }">
              <Button icon="pi pi-eye" text rounded class="mr-1" @click="verIssue(data)" />
              <Button icon="pi pi-pencil" text rounded @click="openIssueDialog(data)" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ─────────────────── SOLICITUDES ─────────────────── -->
      <TabPanel header="Solicitudes">
        <Toolbar class="mb-3">
          <template #start>
            <Button label="Nueva Solicitud" icon="pi pi-plus" @click="openSolicitudDialog()" />
          </template>
        </Toolbar>
        <DataTable :value="solicitudes" :loading="loadingSolicitudes" paginator :rows="10" stripedRows
          class="p-datatable-sm">
          <Column field="activo_nombre" header="Activo" />
          <Column field="solicitante" header="Solicitante" />
          <Column field="estado" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
            </template>
          </Column>
          <Column field="fecha" header="Fecha" sortable />
          <Column header="Acciones" style="width:12rem">
            <template #body="{ data }">
              <Button v-if="data.estado === 'pendiente'" icon="pi pi-check" text rounded severity="success"
                class="mr-1" @click="accionSolicitud(data, 'aceptar')" v-tooltip.top="'Aceptar'" />
              <Button v-if="data.estado === 'pendiente'" icon="pi pi-times" text rounded severity="danger"
                @click="accionSolicitud(data, 'rechazar')" v-tooltip.top="'Rechazar'" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ─────────────────── REVISIONES PRISMA ─────────────────── -->
      <TabPanel header="Revisiones Prisma">
        <Toolbar class="mb-3">
          <template #start>
            <Button label="Nueva Revisión" icon="pi pi-plus" @click="openRevisionDialog()" />
          </template>
        </Toolbar>
        <DataTable :value="revisiones" :loading="loadingRevisiones" paginator :rows="10" stripedRows
          class="p-datatable-sm">
          <Column field="nombre" header="Nombre" />
          <Column field="tenant" header="Tenant" />
          <Column field="estado" header="Estado">
            <template #body="{ data }">
              <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
            </template>
          </Column>
          <Column field="fecha" header="Fecha" sortable />
          <Column header="Alertas" style="width:6rem">
            <template #body="{ data }">
              <Badge :value="data.alertas_count ?? 0" severity="warning" />
            </template>
          </Column>
          <Column header="Acciones" style="width:10rem">
            <template #body="{ data }">
              <Button icon="pi pi-eye" text rounded class="mr-1" @click="verRevision(data)" />
              <Button icon="pi pi-pencil" text rounded class="mr-1" @click="openRevisionDialog(data)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminarRevision(data)" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>
    </TabView>

    <!-- ──────── DIALOG PENTEST ──────── -->
    <Dialog v-model:visible="dialogPentest" :header="editingPentest?.id ? 'Editar Pentest' : 'Nuevo Pentest'"
      :style="{ width: '500px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Nombre *</label>
          <InputText v-model="formPentest.nombre" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Responsable</label>
          <InputText v-model="formPentest.responsable" class="w-full" />
        </div>
        <div class="grid">
          <div class="col-6">
            <label class="font-semibold mb-1 block">Fecha inicio</label>
            <Calendar v-model="formPentest.fecha_inicio" dateFormat="yy-mm-dd" class="w-full" />
          </div>
          <div class="col-6">
            <label class="font-semibold mb-1 block">Fecha fin</label>
            <Calendar v-model="formPentest.fecha_fin" dateFormat="yy-mm-dd" class="w-full" />
          </div>
        </div>
        <div>
          <label class="font-semibold mb-1 block">Estado</label>
          <Dropdown v-model="formPentest.estado" :options="estadosPentest" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Descripción</label>
          <Textarea v-model="formPentest.descripcion" rows="3" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogPentest = false" />
        <Button label="Guardar" icon="pi pi-save" @click="guardarPentest" :loading="savingPentest" />
      </template>
    </Dialog>

    <!-- ──────── DIALOG ISSUE ──────── -->
    <Dialog v-model:visible="dialogIssue" :header="editingIssue?.id ? 'Editar Issue' : 'Nuevo Issue JIRA'"
      :style="{ width: '520px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Título *</label>
          <InputText v-model="formIssue.titulo" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Severidad</label>
          <Dropdown v-model="formIssue.severidad" :options="severidades" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Descripción</label>
          <Textarea v-model="formIssue.descripcion" rows="4" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogIssue = false" />
        <Button label="Guardar" icon="pi pi-save" @click="guardarIssue" :loading="savingIssue" />
      </template>
    </Dialog>

    <!-- ──────── DIALOG SOLICITUD ──────── -->
    <Dialog v-model:visible="dialogSolicitud" header="Nueva Solicitud de Pentest" :style="{ width: '480px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Activo ID *</label>
          <InputNumber v-model="formSolicitud.activo_id" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Justificación</label>
          <Textarea v-model="formSolicitud.justificacion" rows="3" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogSolicitud = false" />
        <Button label="Enviar" icon="pi pi-send" @click="guardarSolicitud" :loading="savingSolicitud" />
      </template>
    </Dialog>

    <!-- ──────── DIALOG REVISIÓN ──────── -->
    <Dialog v-model:visible="dialogRevision" :header="editingRevision?.id ? 'Editar Revisión' : 'Nueva Revisión Prisma'"
      :style="{ width: '480px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Nombre *</label>
          <InputText v-model="formRevision.nombre" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Tenant ID</label>
          <InputText v-model="formRevision.tenant_id" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Descripción</label>
          <Textarea v-model="formRevision.descripcion" rows="3" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialogRevision = false" />
        <Button label="Guardar" icon="pi pi-save" @click="guardarRevision" :loading="savingRevision" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import EvsService from '@/services/evs.service.js'

const toast   = useToast()
const confirm = useConfirm()

// ─── State ───────────────────────────────────────────────
const pentests            = ref([])
const issues              = ref([])
const solicitudes         = ref([])
const revisiones          = ref([])
const loadingPentests     = ref(false)
const loadingIssues       = ref(false)
const loadingSolicitudes  = ref(false)
const loadingRevisiones   = ref(false)

const pentestSeleccionado = ref(null)

// Dialogs
const dialogPentest   = ref(false)
const dialogIssue     = ref(false)
const dialogSolicitud = ref(false)
const dialogRevision  = ref(false)

const editingPentest  = ref(null)
const editingIssue    = ref(null)
const editingRevision = ref(null)

const savingPentest   = ref(false)
const savingIssue     = ref(false)
const savingSolicitud = ref(false)
const savingRevision  = ref(false)

const estadosPentest = ['abierto', 'en_curso', 'cerrado']
const severidades    = ['Critical', 'High', 'Medium', 'Low', 'Informational']

const formPentest = ref({})
const formIssue   = ref({})
const formSolicitud = ref({})
const formRevision  = ref({})

// ─── Helpers ─────────────────────────────────────────────
function estadoSeverity(estado) {
  const map = {
    abierto: 'info', open: 'info',
    en_curso: 'warning', 'In Progress': 'warning',
    cerrado: 'success', closed: 'success', done: 'success',
    pendiente: 'secondary',
    rechazado: 'danger',
    Critical: 'danger', High: 'danger',
    Medium: 'warning', Low: 'info', Informational: 'secondary',
  }
  return map[estado] ?? 'secondary'
}

// ─── Load ─────────────────────────────────────────────────
async function cargarPentests() {
  loadingPentests.value = true
  try {
    const { data } = await EvsService.getPentests()
    pentests.value = data
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los pentests', life: 3000 }) }
  finally { loadingPentests.value = false }
}

async function cargarIssues() {
  if (!pentestSeleccionado.value) return
  loadingIssues.value = true
  try {
    const { data } = await EvsService.getIssuesPentest(pentestSeleccionado.value)
    issues.value = data
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los issues', life: 3000 }) }
  finally { loadingIssues.value = false }
}

async function cargarSolicitudes() {
  loadingSolicitudes.value = true
  try {
    const { data } = await EvsService.getSolicitudes()
    solicitudes.value = data
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las solicitudes', life: 3000 }) }
  finally { loadingSolicitudes.value = false }
}

async function cargarRevisiones() {
  loadingRevisiones.value = true
  try {
    const { data } = await EvsService.getRevisiones()
    revisiones.value = data
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las revisiones', life: 3000 }) }
  finally { loadingRevisiones.value = false }
}

function onTabChange(e) {
  const idx = e.index
  if (idx === 0) cargarPentests()
  else if (idx === 1 && pentests.value.length === 0) cargarPentests()
  else if (idx === 2) cargarSolicitudes()
  else if (idx === 3) cargarRevisiones()
}

// ─── Pentest CRUD ─────────────────────────────────────────
function openPentestDialog(pentest = null) {
  editingPentest.value = pentest
  formPentest.value = pentest ? { ...pentest } : { nombre: '', responsable: '', fecha_inicio: '', fecha_fin: '', estado: 'abierto', descripcion: '' }
  dialogPentest.value = true
}

async function guardarPentest() {
  if (!formPentest.value.nombre) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'El nombre es obligatorio', life: 3000 })
    return
  }
  savingPentest.value = true
  try {
    if (editingPentest.value?.id) {
      await EvsService.editarPentest({ id: editingPentest.value.id, ...formPentest.value })
    } else {
      await EvsService.crearPentest(formPentest.value)
    }
    dialogPentest.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'Pentest guardado', life: 2000 })
    cargarPentests()
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar el pentest', life: 3000 }) }
  finally { savingPentest.value = false }
}

function confirmarEliminarPentest(pentest) {
  confirm.require({
    message: `¿Eliminar el pentest "${pentest.nombre}"?`,
    header: 'Confirmar eliminación',
    icon: 'pi pi-trash',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await EvsService.eliminarPentest({ id: pentest.id })
        toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Pentest eliminado', life: 2000 })
        cargarPentests()
      } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar', life: 3000 }) }
    },
  })
}

function openAsignarDialog(pentest) {
  // Placeholder: could open a dialog to assign pentester
  toast.add({ severity: 'info', summary: 'Pendiente', detail: 'Asignación de pentester — próximamente', life: 3000 })
}

// ─── Issue CRUD ───────────────────────────────────────────
function openIssueDialog(issue = null) {
  editingIssue.value = issue
  formIssue.value = issue ? { ...issue } : { titulo: '', severidad: 'Medium', descripcion: '' }
  dialogIssue.value = true
}

async function guardarIssue() {
  if (!formIssue.value.titulo) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'El título es obligatorio', life: 3000 })
    return
  }
  savingIssue.value = true
  try {
    if (editingIssue.value?.id) {
      await EvsService.editIssue({ id: editingIssue.value.id, ...formIssue.value })
    } else {
      await EvsService.newIssue({ pentest_id: pentestSeleccionado.value, ...formIssue.value })
    }
    dialogIssue.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'Issue guardado', life: 2000 })
    cargarIssues()
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar el issue', life: 3000 }) }
  finally { savingIssue.value = false }
}

async function verIssue(issue) {
  try {
    const { data } = await EvsService.getIssue(issue.key)
    toast.add({ severity: 'info', summary: issue.key, detail: data.fields?.summary ?? 'Issue cargado', life: 4000 })
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el issue', life: 3000 }) }
}

// ─── Solicitudes ─────────────────────────────────────────
function openSolicitudDialog() {
  formSolicitud.value = { activo_id: null, justificacion: '' }
  dialogSolicitud.value = true
}

async function guardarSolicitud() {
  if (!formSolicitud.value.activo_id) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'El activo es obligatorio', life: 3000 })
    return
  }
  savingSolicitud.value = true
  try {
    await EvsService.crearSolicitud(formSolicitud.value)
    dialogSolicitud.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'Solicitud enviada', life: 2000 })
    cargarSolicitudes()
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo enviar la solicitud', life: 3000 }) }
  finally { savingSolicitud.value = false }
}

async function accionSolicitud(solicitud, accion) {
  try {
    if (accion === 'aceptar') await EvsService.aceptarSolicitud({ id: solicitud.id })
    else await EvsService.rechazarSolicitud({ id: solicitud.id })
    toast.add({ severity: 'success', summary: 'OK', detail: `Solicitud ${accion === 'aceptar' ? 'aceptada' : 'rechazada'}`, life: 2000 })
    cargarSolicitudes()
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo procesar la solicitud', life: 3000 }) }
}

// ─── Revisiones ───────────────────────────────────────────
function openRevisionDialog(revision = null) {
  editingRevision.value = revision
  formRevision.value = revision ? { ...revision } : { nombre: '', tenant_id: '', descripcion: '' }
  dialogRevision.value = true
}

async function guardarRevision() {
  if (!formRevision.value.nombre) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'El nombre es obligatorio', life: 3000 })
    return
  }
  savingRevision.value = true
  try {
    if (editingRevision.value?.id) {
      await EvsService.cerrarRevision({ id: editingRevision.value.id, ...formRevision.value })
    } else {
      await EvsService.crearRevision(formRevision.value)
    }
    dialogRevision.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'Revisión guardada', life: 2000 })
    cargarRevisiones()
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar la revisión', life: 3000 }) }
  finally { savingRevision.value = false }
}

function confirmarEliminarRevision(revision) {
  confirm.require({
    message: `¿Eliminar la revisión "${revision.nombre}"?`,
    header: 'Confirmar eliminación',
    icon: 'pi pi-trash',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await EvsService.eliminarRevision({ id: revision.id })
        toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Revisión eliminada', life: 2000 })
        cargarRevisiones()
      } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar', life: 3000 }) }
    },
  })
}

async function verRevision(revision) {
  try {
    const { data } = await EvsService.getRevisionById(revision.id)
    toast.add({ severity: 'info', summary: revision.nombre, detail: `Alertas: ${data.alertas?.length ?? 0}`, life: 4000 })
  } catch { toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar la revisión', life: 3000 }) }
}

// ─── Init ─────────────────────────────────────────────────
onMounted(cargarPentests)
</script>
