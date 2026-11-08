import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useSyncStore = defineStore('sync', () => {
  const status = ref('online') // online, offline, syncing
  const pendingCount = ref(0)

  // Listen to IPC events from main process
  if (window.posAPI) {
    window.posAPI.onSyncStatus((data) => {
      status.value = data.status
      pendingCount.value = data.pendingCount
    })
  }

  return { status, pendingCount }
})
