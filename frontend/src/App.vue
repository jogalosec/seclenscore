<template>
  <RouterView v-slot="{ Component, route }">
    <Transition name="fade" mode="out-in">
      <component :is="Component" :key="route.path" />
    </Transition>
  </RouterView>
  <Toast position="top-right" />
  <ConfirmDialog />
</template>

<script setup>
import Toast         from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
</script>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>
