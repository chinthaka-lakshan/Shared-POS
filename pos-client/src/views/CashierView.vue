<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useCartStore } from '../stores/cart'
import { useSyncStore } from '../stores/sync'

const cart = useCartStore()
const syncStore = useSyncStore()

const barcodeInput = ref('')
const searchInput = ref('')
const searchResults = ref([])
const focusedProductIndex = ref(-1)

const selectedProductForBatch = ref(null)
const showParkedBills = ref(false)
const showCreditModal = ref(false)
const showWasteModal = ref(false)

const wasteData = ref({
  product: null,
  batch: null,
  quantity: 1,
  reason: ''
})

const submitWaste = async () => {
  if (!wasteData.value.product) return alert('Select a product')
  if (wasteData.value.quantity <= 0) return alert('Invalid quantity')

  const product = wasteData.value.product
  const batch = wasteData.value.batch
  
  const payload = {
    uuid: crypto.randomUUID(),
    product_id: product.server_id || product.id,
    batch_number: batch ? batch.batch_number : null,
    quantity: wasteData.value.quantity,
    cost_price: batch ? batch.cost_price : product.cost_price,
    reason: wasteData.value.reason
  }

  const res = await window.posAPI.saveWasteToOutbox(payload)
  if (res.success) {
    alert('Waste logged pending approval')
    showWasteModal.value = false
    wasteData.value = { product: null, batch: null, quantity: 1, reason: '' }
    window.posAPI.triggerManualSync()
  } else {
    alert('Failed to log waste: ' + res.error)
  }
}

const searchInputRef = ref(null)
const discountInputRef = ref(null)

const showCashModal = ref(false)
const showManagerPinModal = ref(false)
const managerPinRef = ref(null)
const managerPin = ref('')
const pendingCheckoutMethod = ref('')
const isManagerAuthorized = ref(false)
const cashGiven = ref('')

const changeAmount = computed(() => {
  const given = parseFloat(cashGiven.value) || 0
  return given - cart.totalPayable
})

const checkout = async (method) => {
  // If selling below cost, require manager PIN first
  if (cart.totalPayable < cart.totalCost && !isManagerAuthorized.value) {
    pendingCheckoutMethod.value = method
    showManagerPinModal.value = true
    managerPin.value = ''
    nextTick(() => {
      managerPinRef.value?.focus()
    })
    return
  }

  if (method === 'cash' && !showCashModal.value) {
    showCashModal.value = true
    cashGiven.value = '' // reset
    return
  }

  if (method === 'cash' && changeAmount.value < 0) {
    alert('Insufficient cash given!')
    return
  }

  if (method === 'credit') {
    cart.paymentStatus = 'credit'
  } else {
    cart.paymentStatus = 'paid'
  }
  
  const result = await cart.checkout({ [method]: cart.totalPayable })
  
  // Reset authorization for next sale
  isManagerAuthorized.value = false
  
  if (result) {
    showCreditModal.value = false
    showCashModal.value = false
    
    // Print receipt
    window.posAPI.printReceipt({
      printerIp: '192.168.1.100', // Mock IP, should come from config
      receiptLines: [
        { type: 'center', text: 'MY STORE' },
        { type: 'text', text: 'Invoice: ' + result.invoice_number },
        { type: 'text', text: 'Status: ' + (method === 'credit' ? 'CREDIT' : 'PAID') },
        { type: 'text', text: '--------------------------------' },
        ...result.items.map(i => ({ type: 'text', text: `${i.name} x${i.quantity}  $${i.subtotal.toFixed(2)}` })),
        { type: 'text', text: '--------------------------------' },
        { type: 'bold', text: `TOTAL: $${result.total_payable.toFixed(2)}` },
        ...(method === 'cash' ? [
          { type: 'text', text: `Cash Given: $${parseFloat(cashGiven.value).toFixed(2)}` },
          { type: 'text', text: `Change: $${changeAmount.value.toFixed(2)}` }
        ] : []),
        { type: 'cut' }
      ]
    })
  }
}

let barcodeBuffer = ''
let barcodeTimeout = null

// Hardware barcode listener (<30ms threshold)
const handleGlobalKeydown = (e) => {
  // Allow Escape to close modals anywhere
  if (e.key === 'Escape') {
    showParkedBills.value = false;
    showCreditModal.value = false;
    showWasteModal.value = false;
    showCashModal.value = false;
    showManagerPinModal.value = false;
    selectedProductForBatch.value = null;
    return
  }

  // Ignore if typing in an input explicitly, except for F-keys
  if ((e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') && !e.key.startsWith('F')) {
    return
  }

  // Handle Enter key for Parked Bills modal
  if (e.key === 'Enter' && showParkedBills.value) {
    e.preventDefault()
    if (cart.parkedBills.length > 0) {
      // Recall the most recent parked bill
      cart.recallBill(cart.parkedBills[cart.parkedBills.length - 1].id)
      showParkedBills.value = false
    }
    return
  }

  // F-key Shortcuts
  if (e.key === 'F1') { e.preventDefault(); checkout('cash') }
  if (e.key === 'F2') { e.preventDefault(); checkout('card') }
  if (e.key === 'F3') { e.preventDefault(); searchInputRef.value?.focus() }
  if (e.key === 'F4') { e.preventDefault(); cart.clearCart() }
  if (e.key === 'F5') { e.preventDefault(); discountInputRef.value?.focus() }
  if (e.key === 'F6') { e.preventDefault(); cart.discountType = cart.discountType === 'fixed' ? 'percentage' : 'fixed' }
  if (e.key === 'F7') { e.preventDefault(); cart.parkBill() }
  if (e.key === 'F8') { e.preventDefault(); showParkedBills.value = true }
  if (e.key === 'F9') { e.preventDefault(); showCreditModal.value = true }
  if (e.key === 'F10') { e.preventDefault(); showWasteModal.value = true }

  // Barcode scanner logic & Grid Navigation
  if (e.key === 'ArrowRight' && searchResults.value.length > 0) {
    e.preventDefault()
    if (focusedProductIndex.value < searchResults.value.length - 1) focusedProductIndex.value++
    return
  }
  if (e.key === 'ArrowLeft' && searchResults.value.length > 0) {
    e.preventDefault()
    if (focusedProductIndex.value > 0) focusedProductIndex.value--
    return
  }
  if (e.key === 'ArrowDown' && searchResults.value.length > 0) {
    e.preventDefault()
    if (focusedProductIndex.value + 4 < searchResults.value.length) {
      focusedProductIndex.value += 4
    } else {
      focusedProductIndex.value = searchResults.value.length - 1
    }
    return
  }
  if (e.key === 'ArrowUp' && searchResults.value.length > 0) {
    e.preventDefault()
    if (focusedProductIndex.value - 4 >= 0) {
      focusedProductIndex.value -= 4
    } else {
      focusedProductIndex.value = 0
    }
    return
  }

  if (e.key !== 'Enter') {
    barcodeBuffer += e.key
    if (barcodeTimeout) clearTimeout(barcodeTimeout)
    barcodeTimeout = setTimeout(() => {
      barcodeBuffer = ''
    }, 30) // 30ms threshold for rapid hardware scanner typing
  } else {
    // If we have a focused product in the grid and the buffer is empty or too short for a barcode
    if (barcodeBuffer.length <= 2 && focusedProductIndex.value >= 0 && focusedProductIndex.value < searchResults.value.length) {
      e.preventDefault()
      handleProductSelect(searchResults.value[focusedProductIndex.value])
      return
    }

    if (barcodeBuffer.length > 2) {
      handleBarcodeScanned(barcodeBuffer)
      barcodeBuffer = ''
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown)
  loadProducts() // Load initial products grid
  
  if (window.posAPI.onCatalogUpdated) {
    window.posAPI.onCatalogUpdated(() => {
      // Refresh current product view when background sync finds new products
      loadProducts(searchInput.value)
    })
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

async function loadProducts(term = '') {
  const products = await window.posAPI.queryProducts(term)
  searchResults.value = products
  focusedProductIndex.value = products.length > 0 ? 0 : -1
}

async function handleBarcodeScanned(barcode) {
  const products = await window.posAPI.queryProducts(barcode)
  if (products && products.length > 0) {
    handleProductSelect(products[0])
  } else {
    alert('Product not found: ' + barcode)
  }
}

function handleProductSelect(product) {
  if (product.batches && product.batches.length > 1) {
    selectedProductForBatch.value = product
  } else {
    cart.addItem(product)
  }
}

function selectBatch(product, batch) {
  cart.addBatchItem(product, batch)
  selectedProductForBatch.value = null
}

const handleSearch = async () => {
  loadProducts(searchInput.value)
}

const handleSearchEnter = (e) => {
  e.preventDefault()
  if (searchResults.value.length > 0) {
    searchInputRef.value?.blur()
    if (focusedProductIndex.value < 0) focusedProductIndex.value = 0
  }
}

const handleSearchDown = (e) => {
  e.preventDefault()
  if (searchResults.value.length > 0) {
    searchInputRef.value?.blur()
    if (focusedProductIndex.value < 0) focusedProductIndex.value = 0
  }
}

const verifyManagerPin = async () => {
  // Fetch real PIN from local sqlite config (synced from backend)
  const config = await window.posAPI.getTerminalConfig()
  const realPin = config['manager_pin']

  if (!realPin) {
    alert('Manager PIN is not configured in Admin settings!')
    return
  }

  if (managerPin.value === realPin) {
    isManagerAuthorized.value = true
    showManagerPinModal.value = false
    const method = pendingCheckoutMethod.value
    pendingCheckoutMethod.value = ''
    checkout(method) // proceed to checkout
  } else {
    alert('Invalid Manager PIN')
  }
}
</script>

<template>
  <div class="flex h-full w-full">
    <!-- Left Panel: Products Grid -->
    <div class="flex-1 flex flex-col bg-slate-50 border-r border-slate-200">
      <!-- Header / Search -->
      <div class="p-4 bg-white border-b border-slate-200 flex justify-between items-center shadow-sm">
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Point of Sale</h1>
        <div class="flex items-center gap-4">
          <!-- Sync Status Badge -->
          <div class="flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
               :class="{
                 'bg-green-100 text-green-700': syncStore.status === 'online',
                 'bg-amber-100 text-amber-700': syncStore.status === 'offline',
                 'bg-blue-100 text-blue-700 animate-pulse': syncStore.status === 'syncing'
               }">
            <div class="w-2 h-2 rounded-full" 
                 :class="{
                   'bg-green-500': syncStore.status === 'online',
                   'bg-amber-500': syncStore.status === 'offline',
                   'bg-blue-500': syncStore.status === 'syncing'
                 }"></div>
            {{ syncStore.status === 'offline' ? `Offline (${syncStore.pendingCount} pending)` : 
               syncStore.status === 'syncing' ? 'Syncing...' : 'Online & Synced' }}
          </div>
          
          <input type="text" ref="searchInputRef" v-model="searchInput" @input="handleSearch" 
                 @keydown.enter="handleSearchEnter"
                 @keydown.down="handleSearchDown"
                 placeholder="Search products (F3)..." 
                 class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none w-64 bg-slate-50" />
          
          <button @click="showWasteModal = true" class="bg-red-50 text-red-600 px-4 py-2 rounded-lg font-medium hover:bg-red-100 transition-colors">
            Log Waste (F10)
          </button>
          
          <button @click="showParkedBills = true" class="relative bg-indigo-50 text-indigo-600 px-4 py-2 rounded-lg font-medium hover:bg-indigo-100 transition-colors">
            Recall Bills (F8)
            <span v-if="cart.parkedBills.length > 0" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold w-5 h-5 flex items-center justify-center rounded-full">
              {{ cart.parkedBills.length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Product Grid -->
      <div class="flex-1 p-4 overflow-y-auto">
        <div class="grid grid-cols-4 gap-4">
          <div v-for="(product, index) in searchResults" :key="product.id" 
               @click="handleProductSelect(product); focusedProductIndex = index"
               :class="{'ring-2 ring-blue-500 bg-blue-50 border-transparent': focusedProductIndex === index}"
               class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 cursor-pointer transition-all active:scale-95 flex flex-col justify-between h-32">
            <div class="font-medium text-slate-700 line-clamp-2 leading-tight">{{ product.name }}</div>
            <div class="flex justify-between items-end mt-2">
              <span class="text-xs text-slate-400">{{ product.barcode || product.sku }}</span>
              <span class="font-bold text-blue-600 text-lg">${{ product.selling_price.toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Panel: Cart -->
    <div class="w-96 bg-white flex flex-col shadow-[-4px_0_15px_rgba(0,0,0,0.03)] z-10">
      <!-- Cart Header -->
      <div class="p-4 border-b border-slate-200 bg-slate-800 text-white">
        <h2 class="text-lg font-semibold flex justify-between">
          Current Sale
          <span class="bg-slate-700 px-2 py-0.5 rounded text-sm">{{ cart.items.length }} items</span>
        </h2>
      </div>

      <!-- Cart Items -->
      <div class="flex-1 overflow-y-auto p-2">
        <div v-if="cart.items.length === 0" class="flex flex-col items-center justify-center h-full text-slate-400">
          <svg class="w-16 h-16 mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
          <p>Scan barcode or select items</p>
        </div>
        
        <div v-for="(item, index) in cart.items" :key="index" class="flex justify-between items-center p-3 border-b border-slate-100 hover:bg-slate-50 rounded-lg group">
          <div class="flex-1">
            <div class="font-medium text-slate-800">{{ item.name }}</div>
            <div class="text-sm text-slate-500">${{ item.unit_price.toFixed(2) }} x {{ item.quantity }}</div>
          </div>
          <div class="font-semibold text-slate-700">${{ item.subtotal.toFixed(2) }}</div>
          <button @click="cart.removeItem(index)" class="ml-3 text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition-opacity p-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
          </button>
        </div>
      </div>

      <!-- Totals & Actions -->
      <div class="p-4 bg-slate-50 border-t border-slate-200">
        <div class="space-y-2 mb-4 text-sm">
          <div class="flex justify-between text-slate-600">
            <span>Subtotal</span>
            <span>${{ cart.subtotal.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between items-center text-slate-600">
            <span>Discount (F6 to toggle)</span>
            <div class="flex items-center gap-2">
              <select v-model="cart.discountType" class="text-xs border rounded p-1 outline-none bg-white">
                <option value="fixed">$</option>
                <option value="percentage">%</option>
              </select>
              <input type="number" ref="discountInputRef" v-model="cart.discountValue" class="w-16 text-right border rounded p-1 text-sm outline-none focus:ring-2 focus:ring-blue-500" min="0" placeholder="F5" />
              <span class="text-red-500 font-medium w-16 text-right">-${{ cart.discountAmount.toFixed(2) }}</span>
            </div>
          </div>
          <div class="flex justify-between font-bold text-xl text-slate-800 mt-2 pt-2 border-t border-slate-200">
            <span>Total</span>
            <span>${{ cart.totalPayable.toFixed(2) }}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <button @click="checkout('cash')" class="bg-emerald-500 hover:bg-emerald-600 text-white py-3 rounded-lg font-semibold flex items-center justify-center gap-2 transition-colors shadow-sm">
            Cash (F1)
          </button>
          <button @click="checkout('card')" class="bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg font-semibold flex items-center justify-center gap-2 transition-colors shadow-sm">
            Card (F2)
          </button>
          <button @click="showCreditModal = true" class="col-span-2 bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-lg font-semibold flex items-center justify-center gap-2 transition-colors shadow-sm">
            Credit Sale (F9)
          </button>
          <div class="grid grid-cols-2 gap-2 col-span-2 mt-2">
            <button @click="cart.parkBill()" class="bg-amber-100 hover:bg-amber-200 text-amber-700 py-2 rounded-lg font-semibold transition-colors">
              Park Bill (F7)
            </button>
            <button @click="cart.clearCart()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 py-2 rounded-lg font-semibold transition-colors">
              Void Cart (F4)
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Batch Selection Modal -->
    <div v-if="selectedProductForBatch" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-bold">Select Batch for {{ selectedProductForBatch.name }}</h2>
          <button @click="selectedProductForBatch = null" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        <div class="space-y-3">
          <div v-for="batch in selectedProductForBatch.batches" :key="batch.id" 
               @click="selectBatch(selectedProductForBatch, batch)"
               class="p-4 border rounded-lg hover:bg-blue-50 hover:border-blue-300 cursor-pointer transition-all flex justify-between items-center">
            <div>
              <div class="font-bold text-slate-800">Batch: {{ batch.batch_number }}</div>
              <div class="text-sm text-slate-500">Exp: {{ batch.expiry_date || 'N/A' }} | Stock: {{ batch.quantity }}</div>
            </div>
            <div class="text-right">
              <div class="font-bold text-blue-600">${{ batch.selling_price }}</div>
              <div v-if="batch.discount_percentage > 0" class="text-xs text-red-500 font-bold">-{{ batch.discount_percentage }}% OFF</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Parked Bills Modal -->
    <div v-if="showParkedBills" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 flex flex-col max-h-[80vh]">
        <div class="flex justify-between items-center mb-4 border-b pb-4">
          <h2 class="text-xl font-bold text-slate-800">Parked Bills</h2>
          <button @click="showParkedBills = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-2 space-y-3">
          <div v-if="cart.parkedBills.length === 0" class="text-center text-slate-500 py-8">
            No parked bills.
          </div>
          <div v-for="bill in cart.parkedBills" :key="bill.id" class="p-4 border rounded-lg hover:bg-slate-50 transition-colors flex justify-between items-center">
            <div>
              <div class="font-bold text-slate-800">{{ bill.note }} - {{ new Date(bill.id).toLocaleTimeString() }}</div>
              <div class="text-sm text-slate-500">{{ bill.items.length }} items</div>
            </div>
            <button @click="cart.recallBill(bill.id); showParkedBills = false" class="px-4 py-2 bg-indigo-600 text-white rounded font-medium hover:bg-indigo-700">
              Recall
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Credit Sale Modal -->
    <div v-if="showCreditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4 border-b pb-4">
          <h2 class="text-xl font-bold text-slate-800">Credit Sale Details</h2>
          <button @click="showCreditModal = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Customer Name *</label>
            <input type="text" v-model="cart.customerName" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Required" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
            <input type="tel" v-model="cart.customerPhone" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-purple-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Note</label>
            <textarea v-model="cart.saleNote" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-purple-500 outline-none" placeholder="Any details..."></textarea>
          </div>
          
          <button @click="checkout('credit')" :disabled="!cart.customerName" class="w-full bg-purple-600 text-white font-bold py-3 rounded-lg hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Confirm Credit Sale
          </button>
        </div>
      </div>
    </div>

    <!-- Waste Logging Modal -->
    <div v-if="showWasteModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex justify-between items-center mb-4 border-b pb-4">
          <h2 class="text-xl font-bold text-slate-800">Log Damaged/Wasted Product</h2>
          <button @click="showWasteModal = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Select Product</label>
            <div class="border rounded p-2 max-h-40 overflow-y-auto bg-slate-50">
              <input type="text" v-model="searchInput" @input="handleSearch" placeholder="Search product to waste..." class="w-full px-2 py-1 mb-2 border rounded text-sm" />
              <div v-for="p in searchResults.slice(0,10)" :key="p.id" @click="wasteData.product = p; wasteData.batch = (p.batches && p.batches.length > 0) ? p.batches[0] : null" 
                   class="p-2 hover:bg-red-50 cursor-pointer text-sm rounded flex justify-between"
                   :class="{'bg-red-100 border-red-200': wasteData.product?.id === p.id}">
                <span>{{ p.name }}</span>
                <span v-if="p.batches && p.batches.length > 0" class="text-xs text-slate-500">Batch: {{ p.batches[0].batch_number }}</span>
              </div>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Quantity</label>
              <input type="number" v-model="wasteData.quantity" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-red-500 outline-none" min="1" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Reason for Waste</label>
            <textarea v-model="wasteData.reason" rows="2" class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-red-500 outline-none" placeholder="e.g. Expired, damaged in store..."></textarea>
          </div>
          
          <button @click="submitWaste" :disabled="!wasteData.product" class="w-full bg-red-600 text-white font-bold py-3 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Submit Waste Log
          </button>
        </div>
      </div>
    </div>
    <!-- Cash Payment Modal -->
    <div v-if="showCashModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4 border-b pb-4">
          <h2 class="text-xl font-bold text-slate-800">Cash Payment</h2>
          <button @click="showCashModal = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="space-y-4">
          <div class="flex justify-between items-center text-lg">
            <span class="text-slate-600">Total Payable:</span>
            <span class="font-bold text-slate-800">${{ cart.totalPayable.toFixed(2) }}</span>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Cash Given *</label>
            <input type="number" v-model="cashGiven" class="w-full px-3 py-3 border rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none text-2xl font-bold text-center" placeholder="0.00" autofocus @keydown.enter="checkout('cash')" />
          </div>
          
          <div class="flex justify-between items-center text-xl pt-4 border-t" :class="{'text-red-500': changeAmount < 0, 'text-emerald-600 font-bold': changeAmount >= 0}">
            <span>Balance/Change:</span>
            <span>${{ changeAmount.toFixed(2) }}</span>
          </div>
          
          <button @click="checkout('cash')" :disabled="changeAmount < 0" class="w-full bg-emerald-600 text-white font-bold py-3 rounded-lg hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Complete Sale (Enter)
          </button>
        </div>
      </div>
    </div>

    <!-- Manager PIN Modal -->
    <div v-if="showManagerPinModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex justify-between items-center mb-4 border-b pb-4">
          <h2 class="text-xl font-bold text-slate-800">Manager Authorization</h2>
          <button @click="showManagerPinModal = false" class="text-slate-400 hover:text-slate-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>
        
        <div class="space-y-4">
          <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm font-medium border border-red-200">
            Warning: The total payable is lower than the cost price. Manager authorization is required to proceed.
          </div>
          
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Manager PIN</label>
            <input type="password" ref="managerPinRef" v-model="managerPin" class="w-full px-3 py-3 border rounded-lg focus:ring-2 focus:ring-red-500 outline-none text-2xl font-bold text-center tracking-widest" placeholder="****" @keydown.enter="verifyManagerPin" />
          </div>
          
          <button @click="verifyManagerPin" :disabled="!managerPin" class="w-full bg-red-600 text-white font-bold py-3 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
            Authorize Sale
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
