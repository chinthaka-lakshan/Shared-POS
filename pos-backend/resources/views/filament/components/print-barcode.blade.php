<div class="flex flex-col items-center justify-center p-6 space-y-4">
    <div class="p-4 bg-white rounded-lg shadow-sm border text-center">
        <!-- Using an external API for simplicity, or we could generate SVGs -->
        <img src="https://barcode.tec-it.com/barcode.ashx?data={{ urlencode($record->barcode) }}&code=Code128&translate-esc=true" alt="Barcode for {{ $record->barcode }}" class="mx-auto" />
        <p class="mt-2 text-sm font-bold text-gray-800">{{ $record->barcode }}</p>
    </div>
    
    <button onclick="window.print()" class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-500 font-medium text-sm">
        Print Barcode
    </button>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .filament-modal-window * {
            visibility: visible;
        }
        .filament-modal-window {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
    }
</style>
