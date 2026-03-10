<template>
  <div class="p-4">
    <Toast />
    <ConfirmDialog />
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-bold m-0">Plan de Continuidad</h2>
      <Tag value="Sprint 5" severity="success" />
    </div>

    <Toolbar class="mb-3">
      <template #start>
        <Button label="Nuevo Plan" icon="pi pi-plus" @click="openDialog()" />
      </template>
    </Toolbar>

    <DataTable :value="planes" :loading="loading" paginator :rows="10" stripedRows class="p-datatable-sm">
      <template #empty>
        <div class="text-center py-4 text-color-secondary">No hay planes de continuidad registrados.</div>
      </template>
      <Column field="nombre" header="Nombre" sortable />
      <Column field="activo_nombre" header="Activo" />
      <Column field="rto" header="RTO (h)" style="width:7rem" />
      <Column field="rpo" header="RPO (h)" style="width:7rem" />
      <Column field="estado" header="Estado">
        <template #body="{ data }">
          <Tag :value="data.estado" :severity="estadoSeverity(data.estado)" />
        </template>
      </Column>
      <Column field="fecha_revision" header="Revisión" sortable />
      <Column header="Acciones" style="width:9rem">
        <template #body="{ data }">
          <Button icon="pi pi-pencil" text rounded class="mr-1" @click="openDialog(data)" />
          <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminar(data)" />
        </template>
      </Column>
    </DataTable>

    <!-- ──────── DIALOG ──────── -->
    <Dialog v-model:visible="dialog" :header="editing?.id ? 'Editar Plan' : 'Nuevo Plan de Continuidad'"
      :style="{ width: '520px' }" modal>
      <div class="flex flex-column gap-3 mt-2">
        <div>
          <label class="font-semibold mb-1 block">Nombre *</label>
          <InputText v-model="form.nombre" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Activo ID</label>
          <InputNumber v-model="form.activo_id" class="w-full" />
        </div>
        <div class="grid">
          <div class="col-6">
            <label class="font-semibold mb-1 block">RTO (horas)</label>
            <InputNumber v-model="form.rto" class="w-full" :min="0" />
          </div>
          <div class="col-6">
            <label class="font-semibold mb-1 block">RPO (horas)</label>
            <InputNumber v-model="form.rpo" class="w-full" :min="0" />
          </div>
        </div>
        <div>
          <label class="font-semibold mb-1 block">Estado</label>
          <Dropdown v-model="form.estado" :options="estados" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Fecha de revisión</label>
          <Calendar v-model="form.fecha_revision" dateFormat="yy-mm-dd" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Descripción</label>
          <Textarea v-model="form.descripcion" rows="3" class="w-full" />
        </div>
        <div>
          <label class="font-semibold mb-1 block">Procedimiento de recuperación</label>
          <Textarea v-model="form.procedimiento" rows="3" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" severity="secondary" @click="dialog = false" />
        <Button label="Guardar" icon="pi pi-save" @click="guardar" :loading="saving" />
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

const planes  = ref([])
const loading = ref(false)
const dialog  = ref(false)
const editing = ref(null)
const saving  = ref(false)
const form    = ref({})

const estados = ['activo', 'en_revision', 'desactualizado', 'archivado']

function estadoSeverity(e) {
  return { activo: 'success', en_revision: 'warning', desactualizado: 'danger', archivado: 'secondary' }[e] ?? 'secondary'
}

async function cargar() {
  loading.value = true
  try {
    const { data } = await PacService.getProductosContinuidad()
    planes.value = Array.isArray(data) ? data : data.planes ?? []
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los planes', life: 3000 })
  } finally {
    loading.value = false
  }
}

function openDialog(plan = null) {
  editing.value = plan
  form.value = plan
    ? { ...plan }
    : { nombre: '', activo_id: null, rto: 4, rpo: 4, estado: 'activo', fecha_revision: '', descripcion: '', procedimiento: '' }
  dialog.value = true
}

async function guardar() {
  if (!form.value.nombre) {
    toast.add({ severity: 'warn', summary: 'Validación', detail: 'El nombre es obligatorio', life: 3000 })
    return
  }
  saving.value = true
  try {
    if (editing.value?.id) {
      await PacService.editPlan({ id: editing.value.id, ...form.value })
    } else {
      await PacService.newPlan(form.value)
    }
    dialog.value = false
    toast.add({ severity: 'success', summary: 'OK', detail: 'Plan guardado', life: 2000 })
    cargar()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar el plan', life: 3000 })
  } finally {
    saving.value = false
  }
}

function confirmarEliminar(plan) {
  confirm.require({
    message: `¿Eliminar el plan "${plan.nombre}"?`,
    header: 'Confirmar eliminación',
    icon: 'pi pi-trash',
    acceptSeverity: 'danger',
    accept: async () => {
      try {
        await PacService.deletePlan({ id: plan.id })
        toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 })
        cargar()
      } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar', life: 3000 })
      }
    },
  })
}

onMounted(cargar)
</script>
