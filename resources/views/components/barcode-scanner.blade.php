@props([
    'action' => 'scanBarcode',
    'label' => 'Scan',
])

{{-- Camera barcode scanner. Calls the Livewire method named by `action` with the scanned code. --}}
<div x-data="barcodeScanner(code => $wire.{{ $action }}(code))" @keydown.escape.window="if (open) close()" {{ $attributes->only('class') }}>
    <button
        type="button"
        @click="start()"
        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-amber-50 hover:text-amber-700 dark:hover:bg-amber-900/30 dark:hover:text-amber-300 transition-colors whitespace-nowrap"
        title="Scan a barcode"
    >
        <x-icon name="barcode" size="4" />
        @if($label)<span>{{ $label }}</span>@endif
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/70 p-4" @click.self="close()">
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Scan a barcode</h2>
                    <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-icon name="x-mark" size="5" />
                    </button>
                </div>

                <div x-show="!error" class="relative bg-black aspect-[4/3]">
                    <video x-ref="video" class="w-full h-full object-cover" playsinline muted></video>
                    <div class="pointer-events-none absolute inset-x-8 top-1/2 -translate-y-1/2 h-24 border-2 border-amber-400 rounded-lg"></div>
                </div>

                <div class="p-4 space-y-3">
                    <p x-show="error" x-text="error" class="text-sm text-red-600 dark:text-red-400"></p>
                    <p x-show="!error" class="text-xs text-gray-500 dark:text-gray-400">Point the camera at the barcode on the bottle or can.</p>

                    <form @submit.prevent="submitManual()" class="flex gap-2">
                        <input
                            x-model="manualCode"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="Or type the barcode digits"
                            class="flex-1 px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-sm text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                        />
                        <button type="submit" class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors">Go</button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
