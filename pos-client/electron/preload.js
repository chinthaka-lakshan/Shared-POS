import { contextBridge, ipcRenderer } from 'electron'

contextBridge.exposeInMainWorld('posAPI', {
  // DB queries (Synchronous via IPC for fast reads if possible, or async)
  // For SQLite in Electron, using async IPC is safer for the main thread.
  queryProducts: (searchTerm) => ipcRenderer.invoke('db:queryProducts', searchTerm),
  saveSaleToOutbox: (data) => ipcRenderer.invoke('db:saveSaleToOutbox', data),
  saveWasteToOutbox: (data) => ipcRenderer.invoke('db:saveWasteToOutbox', data),
  
  // Terminal Status & Config
  getTerminalConfig: () => ipcRenderer.invoke('db:getConfig'),
  setTerminalConfig: (key, value) => ipcRenderer.invoke('db:setConfig', {key, value}),

  // Sync Listeners
  onSyncStatus: (callback) => ipcRenderer.on('sync:status', (_event, data) => callback(data)),
  onCatalogUpdated: (callback) => ipcRenderer.on('catalog:updated', () => callback()),
  triggerManualSync: () => ipcRenderer.invoke('sync:manualTrigger'),

  // Hardware/Printer
  printReceipt: (receiptData) => ipcRenderer.invoke('printer:printReceipt', receiptData)
})
