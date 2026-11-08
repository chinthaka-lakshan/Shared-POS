import net from 'net'

export function setupPrinterIpc(ipcMain) {
  ipcMain.handle('printer:printReceipt', async (event, { printerIp, printerPort = 9100, receiptLines }) => {
    return new Promise((resolve, reject) => {
      if (!printerIp) {
        return resolve({ success: false, error: 'Printer IP not configured' })
      }

      const client = new net.Socket()

      client.on('error', (err) => {
        console.error('Printer connection error:', err)
        resolve({ success: false, error: err.message })
        client.destroy()
      })

      client.connect(printerPort, printerIp, () => {
        // Initialize Printer
        client.write(Buffer.from([0x1B, 0x40]))

        // Build Buffer
        // We'll write the receiptLines array
        // receiptLines format: [{type: 'text', text: '...'}, {type: 'bold', text: '...'}, {type: 'cut'}, {type: 'drawer'}]
        
        for (const line of receiptLines) {
          if (line.type === 'text') {
            client.write(line.text + '\n')
          } else if (line.type === 'bold') {
            client.write(Buffer.from([0x1B, 0x45, 0x01])) // bold on
            client.write(line.text + '\n')
            client.write(Buffer.from([0x1B, 0x45, 0x00])) // bold off
          } else if (line.type === 'center') {
            client.write(Buffer.from([0x1B, 0x61, 0x01])) // align center
            client.write(line.text + '\n')
            client.write(Buffer.from([0x1B, 0x61, 0x00])) // align left
          } else if (line.type === 'cut') {
            // Paper cut: \x1D\x56\x00 (Partial cut)
            client.write(Buffer.from([0x1D, 0x56, 0x00]))
          } else if (line.type === 'drawer') {
            // Cash drawer kick pulse: \x1B\x70\x00\x19\xFA
            client.write(Buffer.from([0x1B, 0x70, 0x00, 0x19, 0xFA]))
          }
        }

        // Send cut command at the end if not explicitly specified
        client.write(Buffer.from([0x1D, 0x56, 0x00]))

        client.destroy()
        resolve({ success: true })
      })
    })
  })
}
