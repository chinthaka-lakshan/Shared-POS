import { getDb } from './sqlite-service.js'

// Mock API URL for now
const API_URL = 'http://127.0.0.1:8000/api/v1/pos'
let syncInterval = null
let mainWindowRef = null

export function setupSyncRunner(ipcMain, mainWindow) {
  mainWindowRef = mainWindow
  
  // Start the background interval
  // Every 20 seconds
  syncInterval = setInterval(() => {
    runSync()
  }, 20000)

  ipcMain.handle('sync:manualTrigger', () => {
    return runSync()
  })
}

async function runSync() {
  const db = getDb()
  if (!db) return

  // Get config (token)
  const configRow = db.prepare("SELECT value FROM app_config WHERE key = 'device_token'").get()
  if (!configRow) {
    console.log('No device token found. Sync paused.')
    return { success: false, reason: 'No token' }
  }

  const token = configRow.value

  // 1. Get pending outbox records
  const pendingSales = db.prepare("SELECT * FROM sales_outbox WHERE sync_status = 'pending' LIMIT 50").all()
  
  // Notify UI of sync start
  if (mainWindowRef) {
    mainWindowRef.webContents.send('sync:status', { status: 'syncing', pendingCount: pendingSales.length })
  }

  let syncedSales = 0;

  if (pendingSales.length > 0) {
    // Format payload
    const payload = pendingSales.map(s => ({
      uuid: s.uuid,
      invoice_number: s.invoice_number,
      customer_id: s.customer_id,
      subtotal: s.subtotal,
      discount_amount: s.discount_amount,
      tax_amount: s.tax_amount,
      total_payable: s.total_payable,
      payment_method: JSON.parse(s.payments_json),
      payment_status: s.payment_status,
      customer_name: s.customer_name,
      customer_phone: s.customer_phone,
      note: s.note,
      items: JSON.parse(s.items_json),
      billed_at: s.billed_at
    }))

    try {
      const response = await fetch(`${API_URL}/sync/sales`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ sales: payload })
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      const data = await response.json()
      
      // 2. Acknowledge sync
      const markSynced = db.prepare("UPDATE sales_outbox SET sync_status = 'synced', sync_attempts = sync_attempts + 1 WHERE uuid = ?")
      
      const transaction = db.transaction((uuids) => {
        for (const uuid of uuids) {
          markSynced.run(uuid)
        }
      })

      transaction(data.synced || [])
      syncedSales = data.synced ? data.synced.length : 0;
    } catch (error) {
      console.error('Sales Sync failed:', error)
      
      // Increment attempts
      const markFailed = db.prepare("UPDATE sales_outbox SET sync_attempts = sync_attempts + 1, error_message = ? WHERE uuid = ?")
      db.transaction((sales) => {
        for (const s of sales) {
          markFailed.run(error.message, s.uuid)
        }
      })(pendingSales)
    }
  }

  try {
    const pendingWastes = db.prepare("SELECT * FROM wastes_outbox WHERE sync_status = 'pending' LIMIT 50").all()
    if (pendingWastes.length > 0) {
      const wastePayload = pendingWastes.map(w => ({
        uuid: w.uuid,
        product_id: w.product_id,
        batch_number: w.batch_number,
        quantity: w.quantity,
        cost_price: w.cost_price,
        reason: w.reason
      }))
      
      const wasteResponse = await fetch(`${API_URL}/sync/wastes`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ wastes: wastePayload })
      })
      
      if (wasteResponse.ok) {
        const wasteData = await wasteResponse.json()
        const markWasteSynced = db.prepare("UPDATE wastes_outbox SET sync_status = 'synced', sync_attempts = sync_attempts + 1 WHERE uuid = ?")
        db.transaction((uuids) => {
          for (const uuid of uuids) {
            markWasteSynced.run(uuid)
          }
        })(wasteData.synced || [])
      }
    }

    // 4. Download catalog (Products)
    const lastSyncStr = db.prepare("SELECT value FROM app_config WHERE key = 'last_catalog_sync'").get()?.value || ''
    const catalogUrl = lastSyncStr ? `${API_URL}/catalog/delta?since=${encodeURIComponent(lastSyncStr)}` : `${API_URL}/catalog/delta`
    
    const catalogResponse = await fetch(catalogUrl, {
      method: 'GET',
      headers: { 
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    })
    
    if (catalogResponse.ok) {
      const catalogData = await catalogResponse.json()
      if (catalogData.products && catalogData.products.length > 0) {
        const upsertProduct = db.prepare(`
          INSERT INTO products (server_id, barcode, sku, name, selling_price, cost_price, stock_qty, is_active, updated_at)
          VALUES (@server_id, @barcode, @sku, @name, @selling_price, @cost_price, @stock_qty, @is_active, @updated_at)
          ON CONFLICT(server_id) DO UPDATE SET
            barcode = excluded.barcode,
            sku = excluded.sku,
            name = excluded.name,
            selling_price = excluded.selling_price,
            cost_price = excluded.cost_price,
            stock_qty = excluded.stock_qty,
            is_active = excluded.is_active,
            updated_at = excluded.updated_at
        `)
        
        db.transaction((products) => {
          for (const p of products) {
            if (p.deleted_at) {
              db.prepare('UPDATE products SET is_active = 0 WHERE server_id = ?').run(p.id)
            } else {
              let qty = parseFloat(p.stock_quantity || 0)
              if (p.batches && p.batches.length) {
                 qty += p.batches.reduce((sum, b) => sum + parseFloat(b.quantity || 0), 0)
              }
              upsertProduct.run({
                server_id: p.id,
                barcode: p.barcode,
                sku: p.sku,
                name: p.name,
                selling_price: p.selling_price || 0,
                cost_price: p.cost_price || 0,
                stock_qty: qty,
                is_active: p.is_active ? 1 : 0,
                updated_at: p.updated_at
              })
            }
          }
        })(catalogData.products)
      }
      // Update last sync time
      db.prepare("INSERT INTO app_config (key, value) VALUES ('last_catalog_sync', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
        .run(catalogData.timestamp)
        
      if (mainWindowRef && catalogData.products.length > 0) {
        mainWindowRef.webContents.send('catalog:updated')
      }
    }

    if (mainWindowRef) {
      const remainingSales = db.prepare("SELECT COUNT(*) as count FROM sales_outbox WHERE sync_status = 'pending'").get().count
      const remainingWastes = db.prepare("SELECT COUNT(*) as count FROM wastes_outbox WHERE sync_status = 'pending'").get().count
      mainWindowRef.webContents.send('sync:status', { status: (remainingSales + remainingWastes) > 0 ? 'offline' : 'online', pendingCount: remainingSales + remainingWastes })
    }

    // 5. Fetch Settings
    try {
      const settingsResponse = await fetch(`${API_URL}/settings`, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
      })
      if (settingsResponse.ok) {
        const settingsData = await settingsResponse.json()
        if (settingsData.manager_pin !== undefined) {
          db.prepare("INSERT INTO app_config (key, value) VALUES ('manager_pin', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
            .run(settingsData.manager_pin || '')
        }
      }
    } catch (e) {
      console.error('Settings sync failed', e)
    }

    return { success: true, synced: syncedSales }

  } catch (error) {
    console.error('General Sync failed:', error)
    
    if (mainWindowRef) {
      const remainingSales = db.prepare("SELECT COUNT(*) as count FROM sales_outbox WHERE sync_status = 'pending'").get().count
      mainWindowRef.webContents.send('sync:status', { status: 'offline', pendingCount: remainingSales })
    }

    return { success: false, error: error.message }
  }
}
