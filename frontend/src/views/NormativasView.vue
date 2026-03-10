<template>
  <div class="d-flex flex-column min-vh-100">
    <AppNavbar />

    <div class="container-fluid py-4 flex-grow-1">

      <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <h4 class="mb-0">Marco Normativo</h4>
        <Button label="Nueva Normativa" icon="pi pi-plus" size="small" @click="abrirDialogoNueva" />
      </div>

      <div v-if="cargando" class="text-center py-5">
        <ProgressSpinner />
      </div>

      <!-- Acordeón de normativas -->
      <Accordion v-else :multiple="true">
        <AccordionPanel v-for="norm in normativas" :key="norm.id" :value="String(norm.id)">
          <AccordionHeader>
            <div class="d-flex align-items-center gap-3 w-100">
              <span class="fw-semibold">{{ norm.nombre }}</span>
              <Tag :value="`v${norm.version}`" severity="secondary" />
              <Tag :value="norm.enabled ? 'Activa' : 'Inactiva'" :severity="norm.enabled ? 'success' : 'warning'" />
              <div class="ms-auto d-flex gap-1" @click.stop>
                <Button icon="pi pi-pencil" size="small" text rounded @click="abrirDialogoEditar(norm)" />
                <Button icon="pi pi-trash"  size="small" text rounded severity="danger" @click="confirmarEliminar(norm)" />
              </div>
            </div>
          </AccordionHeader>

          <AccordionContent>
            <!-- Controles de la normativa -->
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Controles: {{ norm.controles?.length ?? 0 }}</span>
              <Button
                label="Añadir Control"
                icon="pi pi-plus"
                size="small"
                severity="secondary"
                @click="abrirDialogoNuevoControl(norm)"
              />
            </div>

            <DataTable
              :value="norm.controles || []"
              class="p-datatable-sm"
              :rows="10"
              paginator
              empty-message="No hay controles en esta normativa."
            >
              <Column field="cod"         header="Código"   sortable style="width: 8rem" />
              <Column field="nombre"      header="Nombre"   sortable />
              <Column field="dominio"     header="Dominio"  sortable style="width: 10rem" />
              <Column field="descripcion" header="Descripción" />
              <Column header="Relaciones" style="width: 8rem">
                <template #body="{ data }">
                  <Badge :value="data.relacion?.length ?? 0" severity="info" />
                </template>
              </Column>
              <Column header="" style="width: 4rem">
                <template #body="{ data }">
                  <Button
                    icon="pi pi-trash"
                    size="small"
                    text
                    rounded
                    severity="danger"
                    @click="confirmarEliminarControl(data)"
                  />
                </template>
              </Column>
            </DataTable>
          </AccordionContent>
        </AccordionPanel>
      </Accordion>

      <div v-if="!cargando && normativas.length === 0" class="text-center py-5 text-muted">
        No hay normativas registradas. Crea la primera con el botón superior.
      </div>
    </div>

    <!-- ---------------------------------------------------------------
         Diálogo Nueva / Editar Normativa
         --------------------------------------------------------------- -->
    <Dialog v-model:visible="mostrarDialogo" :header="modoEdicion ? 'Editar Normativa' : 'Nueva Normativa'" modal style="width: 420px">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre *</label>
          <InputText v-model="form.nombre" class="w-100" :class="{ 'p-invalid': errores.nombre }" />
          <small v-if="errores.nombre" class="p-error">{{ errores.nombre }}</small>
        </div>
        <div class="col-6">
          <label class="form-label fw-semibold">Versión *</label>
          <InputNumber v-model="form.version" class="w-100" :min="1" />
        </div>
        <div class="col-6" v-if="modoEdicion">
          <label class="form-label fw-semibold">Estado</label>
          <div class="form-check form-switch mt-2">
            <input v-model="form.enabled" class="form-check-input" type="checkbox" />
            <label class="form-check-label">Activa</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="mostrarDialogo = false" />
        <Button :label="modoEdicion ? 'Guardar' : 'Crear'" :loading="guardando" @click="guardar" />
      </template>
    </Dialog>

    <!-- ---------------------------------------------------------------
         Diálogo Nuevo Control
         --------------------------------------------------------------- -->
    <Dialog v-model:visible="mostrarDialogoControl" header="Nuevo Control" modal style="width: 500px">
      <div class="row g-3">
        <div class="col-4">
          <label class="form-label fw-semibold">Código *</label>
          <InputText v-model="formControl.codigo" class="w-100" placeholder="ISO-A1" />
        </div>
        <div class="col-8">
          <label class="form-label fw-semibold">Nombre *</label>
          <InputText v-model="formControl.nombre" class="w-100" />
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Dominio</label>
          <InputText v-model="formControl.dominio" class="w-100" />
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Descripción</label>
          <Textarea v-model="formControl.descripcion" class="w-100" rows="3" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="mostrarDialogoControl = false" />
        <Button label="Crear Control" :loading="guardandoControl" @click="guardarControl" />
      </template>
    </Dialog>

    <ConfirmDialog />
    <AppFooter />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useConfirm } from 'primevue/useconfirm'
import { useToast }   from 'primevue/usetoast'
import AppNavbar from '@/components/layout/AppNavbar.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import NormativasService from '@/services/normativas.service.js'

const confirm = useConfirm()
const toast   = useToast()

// -----------------------------------------------------------------------
// Estado
// -----------------------------------------------------------------------
const normativas = ref([])
const cargando   = ref(false)

// -----------------------------------------------------------------------
// Lifecycle
// -----------------------------------------------------------------------
onMounted(cargarNormativas)

async function cargarNormativas() {
  cargando.value = true
  try {
    const res = await NormativasService.getNormativas()
    normativas.value = res.data?.normativas ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las normativas', life: 3000 })
  } finally {
    cargando.value = false
  }
}

// -----------------------------------------------------------------------
// CRUD Normativas
// -----------------------------------------------------------------------
const mostrarDialogo = ref(false)
const modoEdicion    = ref(false)
const guardando      = ref(false)
const form           = ref({ id: null, nombre: '', version: 1, enabled: true })
const errores        = ref({})

function abrirDialogoNueva() {
  modoEdicion.value = false
  form.value = { id: null, nombre: '', version: 1, enabled: true }
  errores.value = {}
  mostrarDialogo.value = true
}

function abrirDialogoEditar(norm) {
  modoEdicion.value = true
  form.value = {
    id:      norm.id,
    nombre:  norm.nombre,
    version: norm.version,
    enabled: !!norm.enabled,
  }
  errores.value = {}
  mostrarDialogo.value = true
}

async function guardar() {
  errores.value = {}
  if (!form.value.nombre?.trim()) {
    errores.value.nombre = 'El nombre es obligatorio'
    return
  }

  guardando.value = true
  try {
    if (modoEdicion.value) {
      await NormativasService.editNormativa(form.value.id, form.value.nombre, form.value.enabled)
      toast.add({ severity: 'success', summary: 'Normativa actualizada', life: 2000 })
    } else {
      await NormativasService.newNormativa(form.value.nombre, form.value.version)
      toast.add({ severity: 'success', summary: 'Normativa creada', life: 2000 })
    }
    mostrarDialogo.value = false
    cargarNormativas()
  } catch (e) {
    const msg = e.response?.data?.detail ?? 'Error al guardar'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 })
  } finally {
    guardando.value = false
  }
}

function confirmarEliminar(norm) {
  confirm.require({
    message:     `¿Eliminar "${norm.nombre}" y todos sus controles? Esta acción no se puede deshacer.`,
    header:      'Confirmar eliminación',
    icon:        'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Eliminar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        await NormativasService.deleteNormativa(norm.id)
        toast.add({ severity: 'success', summary: 'Normativa eliminada', life: 2000 })
        cargarNormativas()
      } catch {
        toast.add({ severity: 'error', summary: 'Error al eliminar', life: 3000 })
      }
    },
  })
}

// -----------------------------------------------------------------------
// CRUD Controles
// -----------------------------------------------------------------------
const mostrarDialogoControl = ref(false)
const guardandoControl      = ref(false)
const formControl           = ref({ codigo: '', nombre: '', descripcion: '', dominio: '', idNormativa: null })

function abrirDialogoNuevoControl(norm) {
  formControl.value = { codigo: '', nombre: '', descripcion: '', dominio: '', idNormativa: norm.id }
  mostrarDialogoControl.value = true
}

async function guardarControl() {
  if (!formControl.value.codigo?.trim() || !formControl.value.nombre?.trim()) {
    toast.add({ severity: 'warn', summary: 'Código y nombre son obligatorios', life: 2000 })
    return
  }

  guardandoControl.value = true
  try {
    await NormativasService.newControl(
      formControl.value.codigo,
      formControl.value.nombre,
      formControl.value.descripcion,
      formControl.value.dominio,
      formControl.value.idNormativa,
    )
    toast.add({ severity: 'success', summary: 'Control creado', life: 2000 })
    mostrarDialogoControl.value = false
    cargarNormativas()
  } catch {
    toast.add({ severity: 'error', summary: 'Error al crear control', life: 3000 })
  } finally {
    guardandoControl.value = false
  }
}

function confirmarEliminarControl(control) {
  confirm.require({
    message:     `¿Eliminar el control "${control.nombre}"?`,
    header:      'Confirmar eliminación',
    icon:        'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Eliminar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        await NormativasService.deleteControl(control.id)
        toast.add({ severity: 'success', summary: 'Control eliminado', life: 2000 })
        cargarNormativas()
      } catch {
        toast.add({ severity: 'error', summary: 'Error al eliminar', life: 3000 })
      }
    },
  })
}
</script>
