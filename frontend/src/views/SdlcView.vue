<template>
  <div class="sdlc-view p-4">
    <div class="flex align-items-center justify-content-between mb-4">
      <h2 class="text-2xl font-semibold m-0">Gestión SDLC</h2>
      <Button label="Sincronizar Kiuwan" icon="pi pi-refresh" :loading="sincronizando"
              @click="sincronizarKiuwan" />
    </div>

    <TabView v-model:activeIndex="tabActivo" @tab-change="onTabChange">

      <!-- ── Kiuwan ─────────────────────────────────────────────────────── -->
      <TabPanel header="Kiuwan">
        <DataTable :value="appsKiuwan" :loading="cargandoKiuwan"
                   paginator :rows="20" filterDisplay="row"
                   sortField="app" :sortOrder="1"
                   class="p-datatable-sm">
          <template #empty>No hay aplicaciones Kiuwan registradas.</template>
          <Column field="app" header="Aplicación" sortable :style="{ minWidth: '200px' }">
            <template #filter="{ filterModel, filterCallback }">
              <InputText v-model="filterModel.value" @input="filterCallback()"
                         placeholder="Filtrar..." class="p-column-filter" />
            </template>
          </Column>
          <Column field="CMM" header="CMM" sortable :style="{ minWidth: '100px' }" />
          <Column field="fecha_analisis_kiuwan" header="Último análisis" sortable :style="{ minWidth: '130px' }" />
          <Column field="cumple_kpm" header="Cumple KPM" :style="{ minWidth: '110px' }">
            <template #body="{ data }">
              <ToggleButton v-model="data._cumple" onLabel="Sí" offLabel="No"
                            onIcon="pi pi-check" offIcon="pi pi-times"
                            class="p-button-sm"
                            @change="cambiarCumpleKpm(data)" />
            </template>
          </Column>
          <Column header="Acciones" :style="{ minWidth: '80px' }">
            <template #body="{ data }">
              <Button icon="pi pi-trash" severity="danger" text rounded size="small"
                      @click="confirmarEliminar(data)" v-tooltip="'Eliminar'" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ── SonarQube ──────────────────────────────────────────────────── -->
      <TabPanel header="SonarQube">
        <DataTable :value="appsSonar" :loading="cargandoSonar"
                   paginator :rows="20"
                   class="p-datatable-sm">
          <template #empty>No hay slots SonarQube registrados.</template>
          <Column field="sonarqube_slot" header="Slot" sortable :style="{ minWidth: '200px' }" />
          <Column field="CMM" header="CMM" sortable />
          <Column field="url_sonar" header="URL" :style="{ minWidth: '220px' }">
            <template #body="{ data }">
              <a v-if="data.url_sonar" :href="data.url_sonar" target="_blank"
                 class="text-primary">{{ data.url_sonar }}</a>
              <span v-else class="text-400">—</span>
            </template>
          </Column>
          <Column field="cumple_kpm_sonar" header="Cumple KPM" :style="{ minWidth: '110px' }">
            <template #body="{ data }">
              <ToggleButton v-model="data._cumpleSonar" onLabel="Sí" offLabel="No"
                            onIcon="pi pi-check" offIcon="pi pi-times"
                            class="p-button-sm"
                            @change="cambiarCumpleSonar(data)" />
            </template>
          </Column>
          <Column header="Acciones">
            <template #body="{ data }">
              <Button icon="pi pi-trash" severity="danger" text rounded size="small"
                      @click="confirmarEliminar(data)" v-tooltip="'Eliminar'" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <!-- ── Registro SDLC ──────────────────────────────────────────────── -->
      <TabPanel header="Registrar">
        <div class="max-w-3xl mx-auto">
          <div class="formgrid grid">
            <div class="field col-12 md:col-6">
              <label class="font-medium">Herramienta *</label>
              <SelectButton v-model="form.app" :options="['Kiuwan', 'Sonarqube']" class="w-full mt-1" />
            </div>
            <div class="field col-12 md:col-6">
              <label class="font-medium">CMM *</label>
              <InputText v-model="form.CMM" class="w-full mt-1" placeholder="Nivel de madurez" />
            </div>
            <div class="field col-12 md:col-6">
              <label class="font-medium">Dirección (ID activo) *</label>
              <InputNumber v-model="form.Direccion" class="w-full mt-1" :min="1" />
            </div>
            <div class="field col-12 md:col-6">
              <label class="font-medium">Área (ID activo) *</label>
              <InputNumber v-model="form.Area" class="w-full mt-1" :min="1" />
            </div>
            <div class="field col-12 md:col-6">
              <label class="font-medium">Producto (ID activo) *</label>
              <InputNumber v-model="form.Producto" class="w-full mt-1" :min="1" />
            </div>
            <div class="field col-12 md:col-6">
              <label class="font-medium">Tipo de análisis *</label>
              <InputText v-model="form.Analisis" class="w-full mt-1" placeholder="Manual / Automático" />
            </div>

            <!-- Kiuwan-specific -->
            <template v-if="form.app === 'Kiuwan'">
              <div class="field col-12 md:col-6">
                <label class="font-medium">Kiuwan ID</label>
                <InputNumber v-model="form.kiuwan_id" class="w-full mt-1" />
              </div>
              <div class="field col-12 md:col-6">
                <label class="font-medium">Fecha análisis Kiuwan</label>
                <InputText v-model="form.fecha_analisis_kiuwan" type="date" class="w-full mt-1" />
              </div>
            </template>

            <!-- Sonar-specific -->
            <template v-if="form.app === 'Sonarqube'">
              <div class="field col-12 md:col-6">
                <label class="font-medium">Slot SonarQube</label>
                <InputText v-model="form.sonarqube_slot" class="w-full mt-1" placeholder="proyecto-sonar" />
              </div>
              <div class="field col-12">
                <label class="font-medium">URL SonarQube</label>
                <InputText v-model="form.url_sonar" class="w-full mt-1" placeholder="https://sonar.ejemplo.com/..." />
              </div>
            </template>

            <div class="field col-12">
              <label class="font-medium">Comentarios</label>
              <Textarea v-model="form.Comentarios" rows="3" class="w-full mt-1" />
            </div>
          </div>

          <div class="flex gap-2 justify-content-end">
            <Button label="Limpiar" severity="secondary" outlined @click="limpiarForm" />
            <Button label="Registrar" icon="pi pi-save" :loading="guardando"
                    :disabled="!formValido" @click="registrarSDLC" />
          </div>
        </div>
      </TabPanel>

    </TabView>

    <!-- Confirmación eliminar -->
    <Dialog v-model:visible="dialogEliminar" header="Confirmar eliminación"
            :modal="true" :style="{ width: '380px' }">
      <p>¿Eliminar la aplicación <strong>{{ appAEliminar?.app }}</strong>?</p>
      <p class="text-sm text-color-secondary">Esta acción no se puede deshacer.</p>
      <template #footer>
        <Button label="Cancelar" text @click="dialogEliminar = false" />
        <Button label="Eliminar" severity="danger" :loading="eliminando" @click="eliminarApp" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'
import KiuwanService from '@/services/kiuwan.service.js'

const toast = useToast()

const tabActivo     = ref(0)
const cargandoKiuwan = ref(false)
const cargandoSonar  = ref(false)
const sincronizando  = ref(false)
const guardando      = ref(false)
const eliminando     = ref(false)
const dialogEliminar = ref(false)
const appAEliminar   = ref(null)

const appsKiuwan = ref([])
const appsSonar  = ref([])

const form = reactive({
  app: 'Kiuwan',
  CMM: '',
  Direccion: null,
  Area: null,
  Producto: null,
  Analisis: '',
  Comentarios: '',
  kiuwan_id: null,
  fecha_analisis_kiuwan: '',
  sonarqube_slot: '',
  url_sonar: '',
})

const formValido = computed(() =>
  form.app && form.CMM && form.Direccion && form.Area && form.Producto && form.Analisis
)

// ── Carga inicial ─────────────────────────────────────────────────────────

onMounted(cargarKiuwan)

async function cargarKiuwan() {
  cargandoKiuwan.value = true
  cargandoSonar.value  = true
  try {
    const { data } = await KiuwanService.obtenerSDLC()
    const all = Array.isArray(data) ? data : []
    appsKiuwan.value = all
      .filter(r => r.app === 'Kiuwan')
      .map(r => ({ ...r, _cumple: r.cumple_kpm === 1 }))
    appsSonar.value = all
      .filter(r => r.app === 'Sonarqube')
      .map(r => ({ ...r, _cumpleSonar: r.cumple_kpm_sonar === 1 }))
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los datos SDLC', life: 3000 })
  } finally {
    cargandoKiuwan.value = false
    cargandoSonar.value  = false
  }
}

async function sincronizarKiuwan() {
  sincronizando.value = true
  try {
    await KiuwanService.getKiuwanApps()
    await cargarKiuwan()
    toast.add({ severity: 'success', summary: 'Sincronizado', detail: 'Datos actualizados desde Kiuwan', life: 3000 })
  } catch {
    toast.add({ severity: 'warn', summary: 'Aviso', detail: 'No se pudo conectar con la API de Kiuwan', life: 4000 })
  } finally {
    sincronizando.value = false
  }
}

function onTabChange({ index }) {
  tabActivo.value = index
}

// ── KPM toggles ───────────────────────────────────────────────────────────

async function cambiarCumpleKpm(row) {
  try {
    await KiuwanService.updateCumpleKpm(row.app, row._cumple ? 1 : 0)
    toast.add({ severity: 'success', summary: 'KPM actualizado', life: 2000 })
  } catch {
    row._cumple = !row._cumple  // revert
    toast.add({ severity: 'error', summary: 'Error al actualizar KPM', life: 3000 })
  }
}

async function cambiarCumpleSonar(row) {
  try {
    await KiuwanService.updateSonarKPM(row.sonarqube_slot, row._cumpleSonar ? 1 : 0)
    toast.add({ severity: 'success', summary: 'KPM Sonar actualizado', life: 2000 })
  } catch {
    row._cumpleSonar = !row._cumpleSonar  // revert
    toast.add({ severity: 'error', summary: 'Error al actualizar KPM', life: 3000 })
  }
}

// ── Registrar ─────────────────────────────────────────────────────────────

async function registrarSDLC() {
  guardando.value = true
  try {
    const payload = { ...form }
    await KiuwanService.crearAppSDLC(payload)
    toast.add({ severity: 'success', summary: 'Registrado', detail: 'Aplicación SDLC creada', life: 3000 })
    limpiarForm()
    await cargarKiuwan()
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo registrar la aplicación', life: 3000 })
  } finally {
    guardando.value = false
  }
}

function limpiarForm() {
  Object.assign(form, {
    app: 'Kiuwan', CMM: '', Direccion: null, Area: null, Producto: null,
    Analisis: '', Comentarios: '', kiuwan_id: null,
    fecha_analisis_kiuwan: '', sonarqube_slot: '', url_sonar: '',
  })
}

// ── Eliminar ──────────────────────────────────────────────────────────────

function confirmarEliminar(row) {
  appAEliminar.value = row
  dialogEliminar.value = true
}

async function eliminarApp() {
  eliminando.value = true
  const row = appAEliminar.value
  try {
    await KiuwanService.eliminarAppSDLC(row.id, row.app, row.kiuwan_id ?? null)
    toast.add({ severity: 'success', summary: 'Eliminado', life: 2500 })
    dialogEliminar.value = false
    await cargarKiuwan()
  } catch {
    toast.add({ severity: 'error', summary: 'Error al eliminar', life: 3000 })
  } finally {
    eliminando.value = false
  }
}
</script>
