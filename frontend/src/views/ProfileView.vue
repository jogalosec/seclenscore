<template>
  <div class="p-4">
    <Toast />
    <h2 class="text-2xl font-bold mb-4">Mi Perfil</h2>

    <div class="grid">
      <!-- Info usuario -->
      <div class="col-12 md:col-6">
        <div class="card">
          <h3 class="mb-3">Información de cuenta</h3>
          <div v-if="cargando" class="flex justify-content-center py-4">
            <ProgressSpinner style="width: 40px; height: 40px" />
          </div>
          <div v-else-if="usuario">
            <div class="field mb-3">
              <label class="font-semibold block mb-1">Email</label>
              <p class="m-0">{{ usuario.email }}</p>
            </div>
            <div class="field mb-3">
              <label class="font-semibold block mb-1">Roles</label>
              <div class="flex flex-wrap gap-2">
                <Tag
                  v-for="rol in usuario.roles"
                  :key="rol.id"
                  :value="rol.name"
                  :style="{ backgroundColor: rol.color }"
                />
              </div>
            </div>
          </div>
          <p v-else class="text-color-secondary">No se pudo cargar la información del usuario.</p>
        </div>
      </div>

      <!-- Tokens API -->
      <div class="col-12 md:col-6">
        <div class="card">
          <div class="flex justify-content-between align-items-center mb-3">
            <h3 class="m-0">Tokens API Bearer</h3>
            <Button label="Nuevo token" icon="pi pi-plus" size="small" @click="dialogToken = true" />
          </div>

          <DataTable :value="tokens" :loading="cargandoTokens" row-hover striped-rows>
            <Column field="name" header="Nombre" />
            <Column field="created_at" header="Creado">
              <template #body="{ data }">
                {{ data.created_at ? new Date(data.created_at).toLocaleDateString('es-ES') : '—' }}
              </template>
            </Column>
            <Column header="" style="width: 60px">
              <template #body="{ data }">
                <Button
                  icon="pi pi-trash"
                  size="small"
                  severity="danger"
                  text
                  @click="eliminarToken(data)"
                />
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
    </div>

    <!-- Dialog nuevo token -->
    <Dialog v-model:visible="dialogToken" header="Nuevo Token API" modal :style="{ width: '380px' }">
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">Nombre del token</label>
        <InputText v-model="formToken.name" class="w-full" placeholder="CI/CD, Postman…" />
      </div>
      <div class="field mb-3">
        <label class="block mb-1 font-semibold">Expiración (timestamp Unix, 0 = sin expiración)</label>
        <InputNumber v-model="formToken.expired" class="w-full" :min="0" />
      </div>
      <Message v-if="nuevoToken" severity="success" class="mb-0">
        <strong>Copia este token ahora — no se mostrará de nuevo:</strong><br />
        <code class="text-sm">{{ nuevoToken }}</code>
      </Message>
      <template #footer>
        <Button label="Cerrar" severity="secondary" text @click="cerrarDialogToken" />
        <Button v-if="!nuevoToken" label="Crear" icon="pi pi-key" :loading="creandoToken" @click="crearToken" />
      </template>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import UsuariosService from '@/services/usuarios.service.js'

const toast = useToast()
const confirm = useConfirm()

const usuario = ref(null)
const cargando = ref(false)
const tokens = ref([])
const cargandoTokens = ref(false)

const dialogToken = ref(false)
const creandoToken = ref(false)
const nuevoToken = ref(null)
const formToken = ref({ name: '', expired: 0 })

onMounted(async () => {
  cargando.value = true
  cargandoTokens.value = true
  try {
    const [resUser, resTok] = await Promise.all([
      UsuariosService.getInfoUser(),
      UsuariosService.getTokensUser(),
    ])
    usuario.value = resUser.data
    tokens.value = resTok.data?.tokens ?? resTok.data ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el perfil.', life: 3000 })
  } finally {
    cargando.value = false
    cargandoTokens.value = false
  }
})

async function crearToken() {
  if (!formToken.value.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Campo requerido', detail: 'Introduce un nombre para el token.', life: 3000 })
    return
  }
  creandoToken.value = true
  try {
    const res = await UsuariosService.createToken(formToken.value.name, formToken.value.expired)
    nuevoToken.value = res.data?.token
    const resTok = await UsuariosService.getTokensUser()
    tokens.value = resTok.data?.tokens ?? resTok.data ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo crear el token.', life: 3000 })
  } finally {
    creandoToken.value = false
  }
}

function cerrarDialogToken() {
  dialogToken.value = false
  nuevoToken.value = null
  formToken.value = { name: '', expired: 0 }
}

async function eliminarToken(token) {
  confirm.require({
    message: `¿Eliminar el token "${token.name}"?`,
    header: 'Confirmar eliminación',
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await UsuariosService.deleteToken(token.id)
        const res = await UsuariosService.getTokensUser()
        tokens.value = res.data?.tokens ?? res.data ?? []
        toast.add({ severity: 'success', summary: 'Token eliminado', detail: 'El token se eliminó correctamente.', life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar el token.', life: 3000 })
      }
    },
  })
}
</script>
