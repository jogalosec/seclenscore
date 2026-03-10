/**
 * Store Pinia para estado global de UI (loading, notificaciones).
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const isLoading     = ref(false)
  const notifications = ref([])

  function setLoading(value) {
    isLoading.value = value
  }

  function addNotification({ type = 'info', message, life = 4000 }) {
    notifications.value.push({ id: Date.now(), type, message, life })
  }

  function clearNotifications() {
    notifications.value = []
  }

  return { isLoading, notifications, setLoading, addNotification, clearNotifications }
})
