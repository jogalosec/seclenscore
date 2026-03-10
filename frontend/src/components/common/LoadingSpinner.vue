<template>
  <div class="loading-spinner text-center">
    <div class="spinner-border text-primary" role="status" aria-hidden="true" />
    <p v-if="texto" class="mt-2 text-muted small">{{ texto }}</p>
  </div>
</template>

<script setup>
defineProps({ texto: { type: String, default: '' } })
</script>

<style scoped>
.loading-spinner { padding: 1rem; }
</style>
