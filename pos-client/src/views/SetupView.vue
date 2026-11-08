<script setup>
import { ref } from 'vue'

const emit = defineEmits(['paired'])
const pairingCode = ref('')
const isLoading = ref(false)
const errorMsg = ref('')

const handlePair = async () => {
  if (!pairingCode.value) return
  isLoading.value = true
  errorMsg.value = ''

  try {
    const res = await fetch('http://127.0.0.1:8000/api/v1/pos/pair', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ 
        terminal_code: pairingCode.value,
        device_uuid: crypto.randomUUID()
      })
    })

    const data = await res.json()

    if (res.ok && data.token) {
      await window.posAPI.setTerminalConfig('device_token', data.token)
      // Trigger an initial sync to get products
      window.posAPI.triggerManualSync()
      emit('paired')
    } else {
      errorMsg.value = data.message || 'Invalid pairing code'
    }
  } catch (err) {
    errorMsg.value = 'Failed to connect to the server.'
    console.error(err)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="h-screen w-screen flex items-center justify-center bg-slate-100">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
      <div class="text-center mb-6">
        <h1 class="text-3xl font-bold text-slate-800">Terminal Setup</h1>
        <p class="text-slate-500 mt-2">Enter the pairing code from the Web Admin Panel to connect this terminal.</p>
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Pairing Code</label>
          <input 
            type="text" 
            v-model="pairingCode" 
            placeholder="e.g. 123456" 
            class="w-full text-center tracking-widest text-2xl px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none uppercase" 
            @keydown.enter="handlePair"
          />
        </div>

        <div v-if="errorMsg" class="p-3 bg-red-50 text-red-600 rounded-lg text-sm text-center">
          {{ errorMsg }}
        </div>

        <button 
          @click="handlePair" 
          :disabled="isLoading || !pairingCode"
          class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
        >
          {{ isLoading ? 'Pairing...' : 'Connect Terminal' }}
        </button>
      </div>
    </div>
  </div>
</template>
