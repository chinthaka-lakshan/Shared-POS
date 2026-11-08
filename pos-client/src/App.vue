<script setup>
import { ref, onMounted } from 'vue'
import CashierView from './views/CashierView.vue'
import SetupView from './views/SetupView.vue'

const isPaired = ref(false)
const isLoading = ref(true)

onMounted(async () => {
  if (window.posAPI) {
    const config = await window.posAPI.getTerminalConfig()
    if (config.device_token) {
      isPaired.value = true
      // Trigger sync on startup
      window.posAPI.triggerManualSync()
    }
  }
  isLoading.value = false
})

const onPaired = () => {
  isPaired.value = true
}
</script>

<template>
  <div class="h-screen w-screen bg-slate-100 text-slate-800 overflow-hidden font-sans">
    <div v-if="isLoading" class="flex items-center justify-center h-full">
      <div class="animate-pulse text-slate-500">Loading Configuration...</div>
    </div>
    <template v-else>
      <CashierView v-if="isPaired" />
      <SetupView v-else @paired="onPaired" />
    </template>
  </div>
</template>

<style>
/* Base styles */
</style>
