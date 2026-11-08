import Database from 'better-sqlite3'
import path from 'path'
import { app } from 'electron'
import fs from 'fs'

const dbPath = path.join(app.getPath('userData'), 'pos_local.db')
const db = new Database(dbPath, {
  nativeBinding: path.join(__dirname, '../node_modules/better-sqlite3/prebuilds/' + process.platform + '-' + process.arch + '.node')
})

// Initialize DB schema
export function setupSqliteIpc(ipcMain) {
  // Setup DB performance pragmas
  db.pragma('journal_mode = WAL')
  db.pragma('foreign_keys = ON')

  // Run initial migrations
  createSchema()

  // Register IPC handlers
  ipcMain.handle('db:queryProducts', (event, searchTerm) => {
    if (!searchTerm) {
      return db.prepare('SELECT * FROM products WHERE is_active = 1 LIMIT 50').all()
    }
    return db.prepare('SELECT * FROM products WHERE (name LIKE ? OR barcode = ?) AND is_active = 1 LIMIT 50')
      .all(`%${searchTerm}%`, searchTerm)
  })

  ipcMain.handle('db:saveSaleToOutbox', (event, saleData) => {
    // We do this in a local transaction
    const insertSale = db.prepare(`
      INSERT INTO sales_outbox (uuid, invoice_number, customer_id, subtotal, discount_amount, tax_amount, total_payable, payments_json, items_json, billed_at, sync_status)
      VALUES (@uuid, @invoice_number, @customer_id, @subtotal, @discount_amount, @tax_amount, @total_payable, @payments_json, @items_json, @billed_at, 'pending')
    `)

    const decrementStock = db.prepare(`
      UPDATE products SET stock_qty = stock_qty - @qty WHERE server_id = @product_id
    `)

    const transaction = db.transaction((data) => {
      insertSale.run({
        uuid: data.uuid,
        invoice_number: data.invoice_number,
        customer_id: data.customer_id,
        subtotal: data.subtotal,
        discount_amount: data.discount_amount,
        tax_amount: data.tax_amount,
        total_payable: data.total_payable,
        payments_json: JSON.stringify(data.payment_method),
        items_json: JSON.stringify(data.items),
        billed_at: data.billed_at
      })

      for (const item of data.items) {
        decrementStock.run({ qty: item.quantity, product_id: item.product_id })
      }
    })

    try {
      transaction(saleData)
      return { success: true }
    } catch (error) {
      console.error('Failed to save sale to outbox', error)
      return { success: false, error: error.message }
    }
  })

  ipcMain.handle('db:saveWasteToOutbox', (event, wasteData) => {
    const insertWaste = db.prepare(`
      INSERT INTO wastes_outbox (uuid, product_id, batch_number, quantity, cost_price, reason, sync_status)
      VALUES (@uuid, @product_id, @batch_number, @quantity, @cost_price, @reason, 'pending')
    `)

    try {
      insertWaste.run({
        uuid: wasteData.uuid,
        product_id: wasteData.product_id,
        batch_number: wasteData.batch_number || null,
        quantity: wasteData.quantity,
        cost_price: wasteData.cost_price,
        reason: wasteData.reason || null
      })
      return { success: true }
    } catch (error) {
      console.error('Failed to save waste to outbox', error)
      return { success: false, error: error.message }
    }
  })

  ipcMain.handle('db:getConfig', () => {
    const config = db.prepare('SELECT key, value FROM app_config').all()
    const result = {}
    config.forEach(row => result[row.key] = row.value)
    return result
  })

  ipcMain.handle('db:setConfig', (event, {key, value}) => {
    db.prepare('INSERT INTO app_config (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
      .run(key, value)
    return true
  })
}

function createSchema() {
  db.exec(`
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY,
        server_id INTEGER UNIQUE,
        barcode TEXT UNIQUE,
        sku TEXT,
        name TEXT NOT NULL,
        selling_price REAL NOT NULL,
        cost_price REAL NOT NULL,
        stock_qty REAL DEFAULT 0,
        tax_rate REAL DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        updated_at TEXT
    );
    CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);
    CREATE INDEX IF NOT EXISTS idx_products_name ON products(name);

    CREATE TABLE IF NOT EXISTS sales_outbox (
        uuid TEXT PRIMARY KEY,
        invoice_number TEXT NOT NULL,
        customer_id INTEGER,
        subtotal REAL NOT NULL,
        discount_amount REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        total_payable REAL NOT NULL,
        payments_json TEXT NOT NULL,
        items_json TEXT NOT NULL,
        billed_at TEXT NOT NULL,
        sync_status TEXT DEFAULT 'pending',
        sync_attempts INTEGER DEFAULT 0,
        error_message TEXT,
        payment_status TEXT DEFAULT 'paid',
        customer_name TEXT,
        customer_phone TEXT,
        note TEXT
    );
    CREATE INDEX IF NOT EXISTS idx_outbox_status ON sales_outbox(sync_status);

    CREATE TABLE IF NOT EXISTS wastes_outbox (
        uuid TEXT PRIMARY KEY,
        product_id INTEGER NOT NULL,
        batch_number TEXT,
        quantity INTEGER NOT NULL,
        cost_price REAL NOT NULL,
        reason TEXT,
        sync_status TEXT DEFAULT 'pending',
        sync_attempts INTEGER DEFAULT 0,
        error_message TEXT
    );
    CREATE INDEX IF NOT EXISTS idx_waste_outbox_status ON wastes_outbox(sync_status);

    CREATE TABLE IF NOT EXISTS terminal_shifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        server_id INTEGER,
        opened_by_user_id INTEGER NOT NULL,
        opening_float REAL NOT NULL,
        closing_cash_actual REAL,
        closing_note TEXT,
        opened_at TEXT NOT NULL,
        closed_at TEXT,
        status TEXT DEFAULT 'open'
    );

    CREATE TABLE IF NOT EXISTS app_config (
        key TEXT PRIMARY KEY,
        value TEXT
    );
  `)
}

export function getDb() {
  return db
}
