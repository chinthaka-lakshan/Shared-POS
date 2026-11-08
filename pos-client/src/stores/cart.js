import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCartStore = defineStore('cart', () => {
  const items = ref([])
  const discountType = ref('fixed') // 'fixed' or 'percentage'
  const discountValue = ref(0)
  const taxRate = ref(0) // percentage
  const customerId = ref(null)

  const subtotal = computed(() => {
    return items.value.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0)
  })

  const discountAmount = computed(() => {
    if (discountType.value === 'percentage') {
      return subtotal.value * (discountValue.value / 100)
    }
    return discountValue.value
  })

  const taxAmount = computed(() => {
    return (subtotal.value - discountAmount.value) * (taxRate.value / 100)
  })

  const totalPayable = computed(() => {
    return subtotal.value - discountAmount.value + taxAmount.value
  })

  const totalCost = computed(() => {
    return items.value.reduce((sum, item) => sum + (item.unit_cost * item.quantity), 0)
  })

  function addItem(product) {
    if (product.batches && product.batches.length > 0) {
      // Just pick the first batch for simple addItem, or UI should call addBatchItem
      addBatchItem(product, product.batches[0])
      return
    }

    const existing = items.value.find(i => (i.product_id === product.server_id || i.product_id === product.id) && !i.batch_number)
    if (existing) {
      existing.quantity++
      existing.subtotal = existing.quantity * existing.unit_price
    } else {
      items.value.push({
        product_id: product.server_id || product.id,
        name: product.name,
        unit_price: product.selling_price || 0,
        unit_cost: product.cost_price || 0,
        quantity: 1,
        subtotal: product.selling_price || 0
      })
    }
  }

  function addBatchItem(product, batch) {
    const existing = items.value.find(i => (i.product_id === product.server_id || i.product_id === product.id) && i.batch_number === batch.batch_number)
    
    // Calculate price considering discount
    let finalPrice = Number(batch.selling_price)
    if (batch.discount_percentage > 0) {
       finalPrice = finalPrice - (finalPrice * (Number(batch.discount_percentage) / 100))
    }

    if (existing) {
      existing.quantity++
      existing.subtotal = existing.quantity * existing.unit_price
    } else {
      items.value.push({
        product_id: product.server_id || product.id,
        name: `${product.name} (Batch: ${batch.batch_number})`,
        batch_number: batch.batch_number,
        unit_price: finalPrice,
        unit_cost: batch.cost_price,
        quantity: 1,
        subtotal: finalPrice
      })
    }
  }

  function removeItem(index) {
    items.value.splice(index, 1)
  }

  const parkedBills = ref([])
  const paymentStatus = ref('paid')
  const customerName = ref('')
  const customerPhone = ref('')
  const saleNote = ref('')

  function parkBill(note = 'Parked Bill') {
    if (items.value.length === 0) return
    parkedBills.value.push({
      id: Date.now(),
      note: note,
      items: JSON.parse(JSON.stringify(items.value)),
      discountValue: discountValue.value,
      customerId: customerId.value
    })
    clearCart()
  }

  function recallBill(id) {
    const billIndex = parkedBills.value.findIndex(b => b.id === id)
    if (billIndex > -1) {
      const bill = parkedBills.value[billIndex]
      items.value = bill.items
      discountValue.value = bill.discountValue
      customerId.value = bill.customerId
      parkedBills.value.splice(billIndex, 1)
    }
  }

  function clearCart() {
    items.value = []
    discountValue.value = 0
    customerId.value = null
    paymentStatus.value = 'paid'
    customerName.value = ''
    customerPhone.value = ''
    saleNote.value = ''
  }

  async function checkout(paymentMethod) {
    if (items.value.length === 0) return false

    const saleData = {
      uuid: crypto.randomUUID(),
      invoice_number: 'INV-' + Date.now(),
      customer_id: customerId.value,
      subtotal: subtotal.value,
      discount_amount: discountAmount.value,
      tax_amount: taxAmount.value,
      total_payable: totalPayable.value,
      payment_method: paymentMethod,
      payment_status: paymentStatus.value,
      customer_name: customerName.value,
      customer_phone: customerPhone.value,
      note: saleNote.value,
      items: JSON.parse(JSON.stringify(items.value)),
      billed_at: new Date().toISOString()
    }

    try {
      const result = await window.posAPI.saveSaleToOutbox(saleData)
      if (result.success) {
        clearCart()
        // Trigger background sync manually for instant feeling, though interval handles it
        window.posAPI.triggerManualSync()
        return saleData
      } else {
        alert('Checkout failed: ' + result.error)
        return false
      }
    } catch (e) {
      console.error(e)
      alert('Checkout error')
      return false
    }
  }

  return {
    items, discountType, discountValue, taxRate, customerId,
    subtotal, discountAmount, taxAmount, totalPayable, totalCost,
    parkedBills, paymentStatus, customerName, customerPhone, saleNote,
    addItem, addBatchItem, removeItem, clearCart, checkout, parkBill, recallBill
  }
})
