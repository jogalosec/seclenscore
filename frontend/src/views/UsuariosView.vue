<template>
  <div class="d-flex flex-column min-vh-100">
    <AppNavbar />

    <div class="container-fluid py-4 flex-grow-1">

      <!-- Cabecera + tabs -->
      <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <h4 class="mb-0">Gestión de Usuarios y Roles</h4>
        <Button label="Nuevo Usuario" icon="pi pi-user-plus" size="small" @click="abrirDialogoNuevoUsuario" />
      </div>

      <TabView v-model:activeIndex="tabActivo">

        <!-- ===================== TAB: USUARIOS ===================== -->
        <TabPanel header="Usuarios">
          <DataTable
            :value="usuarios"
            :loading="cargandoUsuarios"
            data-key="id"
            paginator
            :rows="20"
            class="p-datatable-sm"
            empty-message="No hay usuarios registrados."
          >
            <Column field="id"    header="ID"    sortable style="width: 5rem" />
            <Column field="email" header="Email" sortable />
            <Column header="Roles">
              <template #body="{ data }">
                <Tag
                  v-for="rol in data.roles"
                  :key="rol.id"
                  :value="rol.name"
                  :style="{ backgroundColor: rol.color || '#6c757d' }"
                  class="me-1"
                />
              </template>
            </Column>
            <Column header="Acciones" style="width: 10rem">
              <template #body="{ data }">
                <div class="d-flex gap-1">
                  <Button icon="pi pi-pencil" size="small" text rounded @click="abrirDialogoEditarUsuario(data)" />
                  <Button icon="pi pi-trash"  size="small" text rounded severity="danger" @click="confirmarEliminarUsuario(data)" />
                </div>
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- ===================== TAB: ROLES ===================== -->
        <TabPanel header="Roles">
          <div class="d-flex justify-content-end mb-2">
            <Button label="Nuevo Rol" icon="pi pi-plus" size="small" severity="secondary" @click="abrirDialogoNuevoRol" />
          </div>

          <DataTable
            :value="roles"
            :loading="cargandoRoles"
            data-key="id"
            class="p-datatable-sm"
            empty-message="No hay roles definidos."
          >
            <Column field="id"   header="ID"   style="width: 5rem" />
            <Column field="name" header="Nombre" sortable />
            <Column header="Color" style="width: 8rem">
              <template #body="{ data }">
                <span
                  class="px-2 py-1 rounded text-white text-sm"
                  :style="{ backgroundColor: data.color || '#6c757d' }"
                >
                  {{ data.color || 'N/A' }}
                </span>
              </template>
            </Column>
            <Column field="additional_access" header="Acceso Adicional" style="width: 10rem">
              <template #body="{ data }">
                <Tag :value="data.additional_access ? 'Sí' : 'No'" :severity="data.additional_access ? 'success' : 'secondary'" />
              </template>
            </Column>
            <Column header="Acciones" style="width: 12rem">
              <template #body="{ data }">
                <div class="d-flex gap-1">
                  <Button icon="pi pi-pencil"     size="small" text rounded @click="abrirDialogoEditarRol(data)" :disabled="!data.editable" />
                  <Button icon="pi pi-shield"     size="small" text rounded severity="info" @click="abrirPermisosRol(data)" title="Permisos" />
                  <Button icon="pi pi-trash"      size="small" text rounded severity="danger" @click="confirmarEliminarRol(data)" :disabled="!data.deletable" />
                </div>
              </template>
            </Column>
          </DataTable>
        </TabPanel>

      </TabView>
    </div>

    <!-- ---------------------------------------------------------------
         Diálogo Nuevo / Editar Usuario
         --------------------------------------------------------------- -->
    <Dialog v-model:visible="mostrarDialogoUsuario" :header="modoEdicionUsuario ? 'Editar Usuario' : 'Nuevo Usuario'" modal style="width: 420px">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Email *</label>
          <InputText v-model="formUsuario.email" class="w-100" :disabled="modoEdicionUsuario" :class="{ 'p-invalid': erroresUsuario.email }" />
          <small v-if="erroresUsuario.email" class="p-error">{{ erroresUsuario.email }}</small>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Roles</label>
          <MultiSelect
            v-model="formUsuario.rol"
            :options="roles"
            option-label="name"
            option-value="id"
            placeholder="Selecciona roles"
            class="w-100"
          />
        </div>
        <div v-if="!modoEdicionUsuario" class="col-12">
          <Message severity="info" :closable="false">
            Se generará una contraseña aleatoria y se enviará al usuario por email.
          </Message>
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="mostrarDialogoUsuario = false" />
        <Button :label="modoEdicionUsuario ? 'Guardar' : 'Crear'" :loading="guardandoUsuario" @click="guardarUsuario" />
      </template>
    </Dialog>

    <!-- ---------------------------------------------------------------
         Diálogo Nuevo / Editar Rol
         --------------------------------------------------------------- -->
    <Dialog v-model:visible="mostrarDialogoRol" :header="modoEdicionRol ? 'Editar Rol' : 'Nuevo Rol'" modal style="width: 400px">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold">Nombre *</label>
          <InputText v-model="formRol.name" class="w-100" :class="{ 'p-invalid': erroresRol.name }" />
          <small v-if="erroresRol.name" class="p-error">{{ erroresRol.name }}</small>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Color (hex)</label>
          <div class="d-flex gap-2 align-items-center">
            <InputText v-model="formRol.color" class="flex-grow-1" placeholder="#1F4E79" />
            <input type="color" v-model="formRol.color" style="width: 40px; height: 38px; border: none; cursor: pointer;" />
          </div>
        </div>
        <div class="col-12">
          <div class="form-check form-switch">
            <input v-model="formRol.additionalAccess" class="form-check-input" type="checkbox" />
            <label class="form-check-label">Acceso adicional a activos externos</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="mostrarDialogoRol = false" />
        <Button :label="modoEdicionRol ? 'Guardar' : 'Crear'" :loading="guardandoRol" @click="guardarRol" />
      </template>
    </Dialog>

    <!-- ---------------------------------------------------------------
         Panel: Permisos de endpoints por rol
         --------------------------------------------------------------- -->
    <Drawer v-model:visible="mostrarPermisosRol" position="right" style="width: 540px">
      <template #header>
        <span class="fw-semibold">
          <i class="pi pi-shield me-2" />
          Permisos: {{ rolSeleccionado?.name }}
        </span>
      </template>

      <div v-if="cargandoEndpoints" class="text-center py-5">
        <ProgressSpinner />
      </div>

      <div v-else>
        <div class="mb-3 d-flex gap-2">
          <Button label="Guardar permisos" size="small" :loading="guardandoPermisos" @click="guardarPermisos" />
          <small class="text-muted align-self-center">Marca/desmarca los endpoints asignados al rol</small>
        </div>

        <DataTable
          v-model:selection="endpointsSeleccionados"
          :value="todosEndpoints"
          selection-mode="multiple"
          data-key="endpoint_id"
          :rows="50"
          paginator
          class="p-datatable-sm"
          size="small"
        >
          <Column selection-mode="multiple" header-style="width: 2.5rem" />
          <Column field="route"  header="Ruta" sortable />
          <Column field="method" header="Método" style="width: 6rem" />
          <Column field="tags"   header="Módulo" sortable style="width: 9rem" />
        </DataTable>
      </div>
    </Drawer>

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
import UsuariosService from '@/services/usuarios.service.js'

const confirm = useConfirm()
const toast   = useToast()

// -----------------------------------------------------------------------
// Estado
// -----------------------------------------------------------------------
const tabActivo = ref(0)

const usuarios         = ref([])
const cargandoUsuarios = ref(false)
const roles            = ref([])
const cargandoRoles    = ref(false)

// -----------------------------------------------------------------------
// Lifecycle
// -----------------------------------------------------------------------
onMounted(async () => {
  await Promise.all([cargarUsuarios(), cargarRoles()])
})

async function cargarUsuarios() {
  cargandoUsuarios.value = true
  try {
    const res = await UsuariosService.getUsers()
    usuarios.value = res.data?.usuarios ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los usuarios', life: 3000 })
  } finally {
    cargandoUsuarios.value = false
  }
}

async function cargarRoles() {
  cargandoRoles.value = true
  try {
    const res = await UsuariosService.getRoles()
    roles.value = res.data?.roles ?? []
  } finally {
    cargandoRoles.value = false
  }
}

// -----------------------------------------------------------------------
// CRUD Usuarios
// -----------------------------------------------------------------------
const mostrarDialogoUsuario = ref(false)
const modoEdicionUsuario    = ref(false)
const guardandoUsuario      = ref(false)
const formUsuario           = ref({ id: null, email: '', rol: [] })
const erroresUsuario        = ref({})

function abrirDialogoNuevoUsuario() {
  modoEdicionUsuario.value = false
  formUsuario.value = { id: null, email: '', rol: [] }
  erroresUsuario.value = {}
  mostrarDialogoUsuario.value = true
}

function abrirDialogoEditarUsuario(u) {
  modoEdicionUsuario.value = true
  formUsuario.value = {
    id:    u.id,
    email: u.email,
    rol:   u.roles?.map(r => r.id) ?? [],
  }
  erroresUsuario.value = {}
  mostrarDialogoUsuario.value = true
}

async function guardarUsuario() {
  erroresUsuario.value = {}
  if (!formUsuario.value.email?.trim()) {
    erroresUsuario.value.email = 'El email es obligatorio'
    return
  }

  guardandoUsuario.value = true
  try {
    if (modoEdicionUsuario.value) {
      await UsuariosService.editUser(formUsuario.value.id, formUsuario.value.rol)
      toast.add({ severity: 'success', summary: 'Usuario actualizado', life: 2000 })
    } else {
      const res = await UsuariosService.newUser(formUsuario.value.email, formUsuario.value.rol)
      const tempPass = res.data?.data?.temp_password
      toast.add({
        severity: 'success',
        summary: 'Usuario creado',
        detail: tempPass ? `Contraseña temporal: ${tempPass}` : 'Se enviará email con credenciales',
        life: 8000,
      })
    }
    mostrarDialogoUsuario.value = false
    cargarUsuarios()
  } catch (e) {
    const msg = e.response?.data?.detail ?? 'Error al guardar'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 })
  } finally {
    guardandoUsuario.value = false
  }
}

function confirmarEliminarUsuario(u) {
  confirm.require({
    message:     `¿Eliminar al usuario "${u.email}"?`,
    header:      'Confirmar eliminación',
    icon:        'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Eliminar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        await UsuariosService.deleteUser(u.id)
        toast.add({ severity: 'success', summary: 'Usuario eliminado', life: 2000 })
        cargarUsuarios()
      } catch {
        toast.add({ severity: 'error', summary: 'Error al eliminar', life: 3000 })
      }
    },
  })
}

// -----------------------------------------------------------------------
// CRUD Roles
// -----------------------------------------------------------------------
const mostrarDialogoRol = ref(false)
const modoEdicionRol    = ref(false)
const guardandoRol      = ref(false)
const formRol           = ref({ id: null, name: '', color: '#1F4E79', additionalAccess: false })
const erroresRol        = ref({})

function abrirDialogoNuevoRol() {
  modoEdicionRol.value = false
  formRol.value = { id: null, name: '', color: '#1F4E79', additionalAccess: false }
  erroresRol.value = {}
  mostrarDialogoRol.value = true
}

function abrirDialogoEditarRol(rol) {
  modoEdicionRol.value = true
  formRol.value = {
    id:               rol.id,
    name:             rol.name,
    color:            rol.color || '#1F4E79',
    additionalAccess: !!rol.additional_access,
  }
  erroresRol.value = {}
  mostrarDialogoRol.value = true
}

async function guardarRol() {
  erroresRol.value = {}
  if (!formRol.value.name?.trim()) {
    erroresRol.value.name = 'El nombre es obligatorio'
    return
  }

  guardandoRol.value = true
  try {
    if (modoEdicionRol.value) {
      await UsuariosService.editRol(formRol.value.id, formRol.value.name, formRol.value.color, formRol.value.additionalAccess)
      toast.add({ severity: 'success', summary: 'Rol actualizado', life: 2000 })
    } else {
      await UsuariosService.newRol(formRol.value.name, formRol.value.color, formRol.value.additionalAccess)
      toast.add({ severity: 'success', summary: 'Rol creado', life: 2000 })
    }
    mostrarDialogoRol.value = false
    cargarRoles()
  } catch (e) {
    const msg = e.response?.data?.detail ?? 'Error al guardar el rol'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 })
  } finally {
    guardandoRol.value = false
  }
}

function confirmarEliminarRol(rol) {
  confirm.require({
    message:     `¿Eliminar el rol "${rol.name}"? Se desasignará de todos los usuarios.`,
    header:      'Confirmar eliminación',
    icon:        'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'Eliminar',
    rejectLabel: 'Cancelar',
    accept: async () => {
      try {
        await UsuariosService.deleteRol(rol.id)
        toast.add({ severity: 'success', summary: 'Rol eliminado', life: 2000 })
        cargarRoles()
      } catch (e) {
        const msg = e.response?.data?.detail ?? 'Error al eliminar el rol'
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 3000 })
      }
    },
  })
}

// -----------------------------------------------------------------------
// Gestión permisos (endpoints por rol)
// -----------------------------------------------------------------------
const mostrarPermisosRol      = ref(false)
const rolSeleccionado         = ref(null)
const todosEndpoints          = ref([])
const endpointsSeleccionados  = ref([])
const cargandoEndpoints       = ref(false)
const guardandoPermisos       = ref(false)

async function abrirPermisosRol(rol) {
  rolSeleccionado.value = rol
  mostrarPermisosRol.value = true
  cargandoEndpoints.value = true

  try {
    const res = await UsuariosService.getEndpointsByRole(rol.id, true)
    todosEndpoints.value = res.data?.endpoints ?? []
    endpointsSeleccionados.value = todosEndpoints.value.filter(e => e.assigned === 1)
  } finally {
    cargandoEndpoints.value = false
  }
}

async function guardarPermisos() {
  if (!rolSeleccionado.value) return
  guardandoPermisos.value = true

  try {
    const selIds   = new Set(endpointsSeleccionados.value.map(e => e.endpoint_id))
    const todosIds = todosEndpoints.value.map(e => e.endpoint_id)

    const aAñadir   = todosIds.filter(id => selIds.has(id))
    const aEliminar = todosIds.filter(id => !selIds.has(id))

    if (aAñadir.length)   await UsuariosService.editEndpointsByRole(rolSeleccionado.value.id, aAñadir, true)
    if (aEliminar.length) await UsuariosService.editEndpointsByRole(rolSeleccionado.value.id, aEliminar, false)

    toast.add({ severity: 'success', summary: 'Permisos actualizados', life: 2000 })
    mostrarPermisosRol.value = false
  } catch {
    toast.add({ severity: 'error', summary: 'Error al guardar permisos', life: 3000 })
  } finally {
    guardandoPermisos.value = false
  }
}
</script>
