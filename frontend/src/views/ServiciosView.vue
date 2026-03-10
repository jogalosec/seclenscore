<template>
  <div class="d-flex flex-column min-vh-100">
    <AppNavbar />

    <div class="container-fluid py-4 flex-grow-1">

      <!-- Cabecera -->
      <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <h4 class="mb-0">Servicios / Activos</h4>
        <div class="d-flex gap-2">
          <Button
            label="Exportar Excel"
            icon="pi pi-file-excel"
            severity="secondary"
            size="small"
            :disabled="!activoSeleccionado"
            @click="exportarArbol"
          />
          <Button
            label="Nuevo Activo"
            icon="pi pi-plus"
            size="small"
            @click="abrirDialogoNuevo"
          />
        </div>
      </div>

      <!-- Filtros -->
      <div class="row g-2 mb-3 px-2">
        <div class="col-md-3">
          <Select
            v-model="filtroTipo"
            :options="tiposActivo"
            option-label="nombre"
            option-value="id"
            placeholder="Tipo de activo"
            class="w-100"
            @change="cargarActivos"
          />
        </div>
        <div class="col-md-2">
          <Select
            v-model="filtroArchivado"
            :options="opcionesArchivado"
            option-label="label"
            option-value="value"
            class="w-100"
            @change="cargarActivos"
          />
        </div>
        <div class="col-md-2">
          <Select
            v-model="filtroEstado"
            :options="opcionesEstado"
            option-label="label"
            option-value="value"
            class="w-100"
            @change="cargarActivos"
          />
        </div>
        <div class="col-md-4">
          <InputText
            v-model="textoBusqueda"
            placeholder="Buscar por nombre..."
            class="w-100"
            @input="buscarPorNombre"
          />
        </div>
        <div class="col-md-1">
          <Button
            icon="pi pi-refresh"
            severity="secondary"
            class="w-100"
            @click="cargarActivos"
          />
        </div>
      </div>

      <!-- Tabla principal -->
      <DataTable
        v-model:selection="activoSeleccionado"
        :value="activos"
        :loading="cargando"
        selection-mode="single"
        data-key="id"
        paginator
        :rows="25"
        :rows-per-page-options="[10, 25, 50, 100]"
        removable-sort
        sort-field="nombre"
        :sort-order="1"
        filter-display="menu"
        :global-filter-fields="['nombre', 'tipo_nombre']"
        empty-message="No hay activos para los filtros seleccionados."
        class="p-datatable-sm"
        @row-dblclick="verArbol($event.data)"
      >
        <Column selection-mode="single" header-style="width: 2.5rem" />

        <Column field="nombre" header="Nombre" sortable>
          <template #body="{ data }">
            <span
              class="text-primary cursor-pointer fw-semibold"
              @click="verArbol(data)"
            >
              {{ data.nombre }}
            </span>
          </template>
        </Column>

        <Column field="tipo_nombre" header="Tipo" sortable />

        <Column field="archivado" header="Archivado" sortable style="width: 8rem">
          <template #body="{ data }">
            <Tag
              :value="data.archivado ? 'Sí' : 'No'"
              :severity="data.archivado ? 'warning' : 'success'"
            />
          </template>
        </Column>

        <Column field="expuesto" header="Expuesto" sortable style="width: 8rem">
          <template #body="{ data }">
            <Tag
              :value="data.expuesto ? 'Sí' : 'No'"
              :severity="data.expuesto ? 'danger' : 'secondary'"
            />
          </template>
        </Column>

        <Column header="Acciones" style="width: 12rem">
          <template #body="{ data }">
            <div class="d-flex gap-1">
              <Button
                icon="pi pi-pencil"
                size="small"
                text
                rounded
                @click="abrirDialogoEditar(data)"
              />
              <Button
                icon="pi pi-copy"
                size="small"
                text
                rounded
                severity="secondary"
                title="Clonar"
                @click="clonarActivo(data)"
              />
              <Button
                icon="pi pi-archive"
                size="small"
                text
                rounded
                :severity="data.archivado ? 'warning' : 'secondary'"
                :title="data.archivado ? 'Desarchivar' : 'Archivar'"
                @click="toggleArchivado(data)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                text
                rounded
                severity="danger"
                @click="confirmarEliminar(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- ---------------------------------------------------------------
         Panel lateral: árbol del activo seleccionado
         --------------------------------------------------------------- -->
    <Drawer v-model:visible="mostrarArbol" position="right" style="width: 420px">
      <template #header>
        <span class="fw-semibold">
          <i class="pi pi-sitemap me-2" />
          Árbol: {{ activoArbol?.nombre }}
        </span>
      </template>

      <div v-if="cargandoArbol" class="text-center py-5">
        <ProgressSpinner />
      </div>

      <Tree
        v-else
        :value="nodosArbol"
        class="w-100 border-0"
      />
    </Drawer>

    <!-- ---------------------------------------------------------------
         Diálogo: Nuevo / Editar activo
         --------------------------------------------------------------- -->
    <Dialog
      v-model:visible="mostrarDialogo"
      :header="modoEdicion ? 'Editar Activo' : 'Nuevo Activo'"
      modal
      style="width: 480px"
    >
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre *</label>
          <InputText v-model="form.nombre" class="w-100" :class="{ 'p-invalid': errores.nombre }" />
          <small v-if="errores.nombre" class="p-error">{{ errores.nombre }}</small>
        </div>

        <div class="col-12" v-if="!modoEdicion">
          <label class="form-label fw-semibold">Clase / Tipo *</label>
          <Select
            v-model="form.clase"
            :options="clasesActivo"
            option-label="nombre"
            option-value="id"
            placeholder="Selecciona una clase"
            class="w-100"
            :class="{ 'p-invalid': errores.clase }"
          />
          <small v-if="errores.clase" class="p-error">{{ errores.clase }}</small>
        </div>

        <div class="col-12" v-if="!modoEdicion">
          <label class="form-label fw-semibold">Activo padre (opcional)</label>
          <Select
            v-model="form.padre"
            :options="padresDisponibles"
            option-label="nombre"
            option-value="id"
            placeholder="Sin padre (raíz)"
            class="w-100"
            show-clear
          />
        </div>

        <div class="col-12" v-if="modoEdicion">
          <label class="form-label fw-semibold">Descripción</label>
          <Textarea v-model="form.descripcion" class="w-100" rows="3" />
        </div>

        <div class="col-6" v-if="modoEdicion">
          <div class="form-check form-switch">
            <input v-model="form.expuesto" class="form-check-input" type="checkbox" />
            <label class="form-check-label">Expuesto</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="cerrarDialogo" />
        <Button
          :label="modoEdicion ? 'Guardar' : 'Crear'"
          :loading="guardando"
          @click="guardarActivo"
        />
      </template>
    </Dialog>

    <!-- ---------------------------------------------------------------
         Confirmación de eliminar
         --------------------------------------------------------------- -->
    <ConfirmDialog />

    <AppFooter />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast }   from 'primevue/usetoast'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import ActivosService from '@/services/activos.service.js'

const confirm = useConfirm()
const toast   = useToast()

// -----------------------------------------------------------------------
// Estado principal
// -----------------------------------------------------------------------
const activos           = ref([])
const cargando          = ref(false)
const activoSeleccionado = ref(null)

const tiposActivo       = ref([])
const clasesActivo      = ref([])
const padresDisponibles = ref([])

const filtroTipo      = ref(42)
const filtroArchivado = ref('0')
const filtroEstado    = ref('Todos')
const textoBusqueda   = ref('')

const opcionesArchivado = [
  { label: 'No archivados', value: '0' },
  { label: 'Archivados',    value: '1' },
  { label: 'Todos',         value: 'All' },
]
const opcionesEstado = [
  { label: 'Todos',            value: 'Todos' },
  { label: 'Sin actividades',  value: 'NoAct' },
  { label: 'Sin ECR',          value: 'NoECR' },
]

// -----------------------------------------------------------------------
// Árbol lateral
// -----------------------------------------------------------------------
const mostrarArbol  = ref(false)
const cargandoArbol = ref(false)
const activoArbol   = ref(null)
const nodosArbol    = ref([])

// -----------------------------------------------------------------------
// Diálogo nuevo/editar
// -----------------------------------------------------------------------
const mostrarDialogo = ref(false)
const modoEdicion    = ref(false)
const guardando      = ref(false)
const form           = ref({ id: null, nombre: '', clase: null, padre: null, descripcion: '', expuesto: false })
const errores        = ref({})

// -----------------------------------------------------------------------
// Lifecycle
// -----------------------------------------------------------------------
onMounted(async () => {
  await Promise.all([cargarTipos(), cargarClases()])
  await cargarActivos()
})

// -----------------------------------------------------------------------
// Carga de datos
// -----------------------------------------------------------------------
async function cargarActivos() {
  cargando.value = true
  try {
    const tipo = filtroTipo.value ?? 42
    const res  = await ActivosService.getActivos(String(tipo), filtroArchivado.value, filtroEstado.value)
    activos.value = res.data?.activos ?? res.data ?? []
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los activos', life: 3000 })
  } finally {
    cargando.value = false
  }
}

async function cargarTipos() {
  try {
    const res = await ActivosService.obtainAllTypeActivos()
    tiposActivo.value = res.data?.tipos ?? []
  } catch { /* non-blocking */ }
}

async function cargarClases() {
  try {
    const res = await ActivosService.getClaseActivos()
    clasesActivo.value = res.data?.clases ?? []
  } catch { /* non-blocking */ }
}

let buscarTimeout = null
function buscarPorNombre() {
  clearTimeout(buscarTimeout)
  if (!textoBusqueda.value.trim()) {
    cargarActivos()
    return
  }
  buscarTimeout = setTimeout(async () => {
    cargando.value = true
    try {
      const res = await ActivosService.getActivosByNombre(textoBusqueda.value.trim())
      activos.value = res.data?.activos ?? []
    } finally {
      cargando.value = false
    }
  }, 350)
}

// -----------------------------------------------------------------------
// Árbol
// -----------------------------------------------------------------------
async function verArbol(activo) {
  activoArbol.value  = activo
  mostrarArbol.value = true
  cargandoArbol.value = true
  nodosArbol.value    = []

  try {
    const res = await ActivosService.getTree(activo.id)
    nodosArbol.value = mapearNodos(res.data?.arbol ?? [])
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el árbol', life: 3000 })
  } finally {
    cargandoArbol.value = false
  }
}

function mapearNodos(nodos) {
  return nodos.map(n => ({
    key:      String(n.id),
    label:    n.nombre,
    icon:     'pi pi-box',
    children: n.hijos ? mapearNodos(n.hijos) : [],
  }))
}

async function exportarArbol() {
  if (!activoSeleccionado.value) return
  try {
    await ActivosService.descargarArbol(activoSeleccionado.value.id)
    toast.add({ severity: 'success', summary: 'Descarga iniciada', life: 2000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo exportar el árbol', life: 3000 })
  }
}

// -----------------------------------------------------------------------
// CRUD — Nuevo
// -----------------------------------------------------------------------
async function abrirDialogoNuevo() {
  modoEdicion.value = false
  form.value = { id: null, nombre: '', clase: null, padre: null, descripcion: '', expuesto: false }
  errores.value = {}

  try {
    const res = await ActivosService.obtainFathersActivo(0)
    padresDisponibles.value = res.data?.padres ?? []
  } catch { padresDisponibles.value = [] }

  mostrarDialogo.value = true
}

function abrirDialogoEditar(activo) {
  modoEdicion.value = true
  form.value = {
    id:          activo.id,
    nombre:      activo.nombre,
    clase:       activo.clase ?? activo.tipo,
    padre:       activo.padre ?? null,
    descripcion: activo.descripcion ?? '',
    expuesto:    !!activo.expuesto,
  }
  errores.value = {}
  mostrarDialogo.value = true
}

function cerrarDialogo() {
  mostrarDialogo.value = false
}

function validarForm() {
  const e = {}
  if (!form.value.nombre?.trim()) e.nombre = 'El nombre es obligatorio'
  if (!modoEdicion.value && !form.value.clase) e.clase = 'Selecciona una clase'
  errores.value = e
  return Object.keys(e).length === 0
}

async function guardarActivo() {
  if (!validarForm()) return
  guardando.value = true
  try {
    if (modoEdicion.value) {
      await ActivosService.editActivo({
        id:          form.value.id,
        nombre:      form.value.nombre,
        descripcion: form.value.descripcion,
        expuesto:    form.value.expuesto ? 1 : 0,
      })
      toast.add({ severity: 'success', summary: 'Activo actualizado', life: 2000 })
    } else {
      await ActivosService.newActivo(form.value.nombre, form.value.clase, form.value.padre)
      toast.add({ severity: 'success', summary: 'Activo creado', life: 2000 })
    }
    cerrarDialogo()
    cargarActivos()
  } catch (e) {
    const msg = e.response?.data?.detail ?? 'Error al guardar'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 })
  } finally {
    guardando.value = false
  }
}

// -----------------------------------------------------------------------
// CRUD — Clonar / Archivar / Eliminar
// -----------------------------------------------------------------------
async function clonarActivo(activo) {
  try {
    await ActivosService.cloneActivo(activo.id)
    toast.add({ severity: 'success', summary: 'Activo clonado', life: 2000 })
    cargarActivos()
  } catch {
    toast.add({ severity: 'error', summary: 'Error al clonar', life: 3000 })
  }
}

async function toggleArchivado(activo) {
  const nuevoEstado = activo.archivado ? 0 : 1
  try {
    await ActivosService.updateArchivados(activo.id, nuevoEstado)
    toast.add({ severity: 'success', summary: nuevoEstado ? 'Activo archivado' : 'Activo desarchivado', life: 2000 })
    cargarActivos()
  } catch {
    toast.add({ severity: 'error', summary: 'Error al cambiar estado', life: 3000 })
  }
}

function confirmarEliminar(activo) {
  confirm.require({
    message:      `¿Eliminar el activo "${activo.nombre}"? Esta acción no se puede deshacer.`,
    header:       'Confirmar eliminación',
    icon:         'pi pi-exclamation-triangle',
    acceptClass:  'p-button-danger',
    acceptLabel:  'Eliminar',
    rejectLabel:  'Cancelar',
    accept: async () => {
      try {
        await ActivosService.deleteActivo(activo.id)
        toast.add({ severity: 'success', summary: 'Activo eliminado', life: 2000 })
        cargarActivos()
      } catch {
        toast.add({ severity: 'error', summary: 'Error al eliminar', life: 3000 })
      }
    },
  })
}
</script>
