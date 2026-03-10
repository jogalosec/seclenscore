<template>
  <div class="p-4 max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-4">Política de Privacidad</h1>
    <p class="text-color-secondary mb-4">Última actualización: {{ fecha }}</p>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">1. Responsable del tratamiento</h2>
      <p>
        Los datos personales recogidos en la plataforma SecLensCore son tratados por la organización
        responsable de su despliegue, de conformidad con el Reglamento (UE) 2016/679 (RGPD)
        y la legislación nacional aplicable.
      </p>
    </div>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">2. Datos recogidos</h2>
      <p>
        La plataforma recoge únicamente los datos necesarios para la autenticación y el uso
        de sus funcionalidades: dirección de correo electrónico, roles asignados y registros
        de actividad (logs de auditoría).
      </p>
    </div>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">3. Finalidad del tratamiento</h2>
      <p>
        Los datos se utilizan exclusivamente para gestionar el acceso a la plataforma,
        garantizar la trazabilidad de las acciones realizadas y cumplir con las obligaciones
        de seguridad de la información.
      </p>
    </div>

    <div class="card mb-4">
      <h2 class="text-xl font-semibold mb-2">4. Derechos del usuario</h2>
      <p>
        Puede ejercer sus derechos de acceso, rectificación, supresión y portabilidad
        contactando con el administrador de la plataforma.
      </p>
    </div>

    <RouterLink to="/login" class="p-button p-button-primary">Volver al inicio</RouterLink>
  </div>
</template>

<script setup>
const fecha = new Date().toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' })
</script>
