<template>
  <div class="p-4 max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-4">Términos de Uso</h1>
    <p class="text-color-secondary mb-4">Última actualización: {{ fecha }}</p>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">1. Aceptación de los términos</h2>
      <p>
        El uso de la plataforma SecLensCore implica la aceptación de los presentes términos de uso.
        Si no está de acuerdo con alguno de los términos, le rogamos que no utilice la plataforma.
      </p>
    </div>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">2. Uso permitido</h2>
      <p>
        SecLensCore es una plataforma de gestión de seguridad de la información destinada
        exclusivamente a usuarios autorizados de la organización. Queda prohibido su uso
        para fines no autorizados o que vulneren la legislación vigente.
      </p>
    </div>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">3. Confidencialidad</h2>
      <p>
        Toda la información gestionada en la plataforma tiene carácter confidencial.
        Los usuarios se comprometen a no divulgar información a terceros no autorizados.
      </p>
    </div>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">4. Responsabilidad</h2>
      <p>
        El usuario es responsable del uso que haga de la plataforma y de la información
        a la que accede. Cualquier uso indebido podrá dar lugar a medidas disciplinarias
        o legales.
      </p>
    </div>

    <RouterLink to="/login" class="p-button p-button-primary">Volver al inicio</RouterLink>
  </div>
</template>

<script setup>
const fecha = new Date().toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' })
</script>
