<template>
  <footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container text-center text-muted small">
      <span>© 2024–2026 SecLensCore — </span>
      <RouterLink :to="{ name: 'terminos' }">Términos de uso</RouterLink>
      <span> · </span>
      <RouterLink :to="{ name: 'privacidad' }">Privacidad</RouterLink>
      <span> · v{{ version }}</span>
    </div>
  </footer>
</template>

<script setup>
const version = import.meta.env.VITE_APP_VERSION || '2.0.0'
</script>
