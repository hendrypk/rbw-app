<!-- <script setup lang="ts">
import { ref } from 'vue';
import { Bluetooth, X, RefreshCw, Printer, CheckCircle2 } from '@lucide/vue';
import { useThermalPrinter } from '@/composables/useThermalPrinter';
import { toast } from 'vue-sonner';

defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const { connectedDeviceName, connectPrinter, connectToDevice, disconnectPrinter, availableDevices, scanDevices, isScanning } = useThermalPrinter();
const isConnectingPrinter = ref(false);

const handleScan = async () => {
    try {
        await scanDevices();
    } catch (error) {
        toast.error("Gagal melakukan pencarian perangkat Bluetooth. Pastikan Bluetooth aktif.");
    }
};

const handleSelectDevice = async (device: any) => {
    isConnectingPrinter.value = true;
    try {
        await connectToDevice(device);
        toast.success(`Printer "${connectedDeviceName.value}" berhasil dihubungkan!`);
    } catch (error) {
        toast.error("Gagal terhubung ke printer yang dipilih.");
    } finally {
        isConnectingPrinter.value = false;
    }
};

const handleDefaultConnect = async () => {
    isConnectingPrinter.value = true;
    try {
        await connectPrinter();
        toast.success(`Printer "${connectedDeviceName.value}" berhasil dihubungkan!`);
    } catch (error) {
        toast.error("Gagal menghubungkan printer.");
    } finally {
        isConnectingPrinter.value = false;
    }
};

const handleDisconnect = () => {
    disconnectPrinter();
    toast.info("Koneksi printer diputuskan.");
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-lg rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col">
            
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/40">
                <div class="flex items-center gap-2">
                    <Bluetooth class="h-5 w-5 text-blue-600" />
                    <h4 class="font-bold text-sm text-slate-900 dark:text-zinc-100">Pengaturan Printer Bluetooth</h4>
                </div>
                <button @click="emit('close')" class="p-1.5 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="p-6 space-y-5 text-xs max-h-[70vh] overflow-y-auto custom-scrollbar">
                
                <div class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl border border-slate-200/60 dark:border-zinc-700 space-y-3">
                    <span class="font-bold text-slate-400 uppercase text-[10px] block">Printer Aktif:</span>
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <span class="font-extrabold text-slate-800 dark:text-zinc-200 text-sm truncate block">
                                {{ connectedDeviceName || 'Belum ada printer terhubung' }}
                            </span>
                            <span class="text-[10px] font-black uppercase text-amber-600 dark:text-amber-400" v-if="!connectedDeviceName">
                                Terputus
                            </span>
                        </div>

                        <button 
                            v-if="connectedDeviceName"
                            @click="handleDisconnect" 
                            type="button" 
                            class="py-2 px-3 bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-950/40 dark:hover:bg-red-900/40 font-bold rounded-xl text-[11px] transition-all cursor-pointer shrink-0"
                        >
                            Putuskan
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-700 dark:text-zinc-300 uppercase tracking-wider text-[11px]">Daftar Perangkat Bluetooth</span>
                        <button 
                            @click="handleScan" 
                            :disabled="isScanning"
                            class="px-3 py-1.5 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 text-slate-700 dark:text-zinc-200 font-bold rounded-xl transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                        >
                            <RefreshCw :class="['h-3.5 w-3.5', isScanning ? 'animate-spin' : '']" />
                            {{ isScanning ? 'Memindai...' : 'Cari Perangkat' }}
                        </button>
                    </div>

                    <div class="border border-slate-200 dark:border-zinc-800 rounded-2xl overflow-hidden divide-y divide-slate-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900/50">
                        <div v-if="isScanning" class="p-6 text-center text-slate-400 animate-pulse">
                            Mencari printer thermal di sekitar...
                        </div>
                        <div v-else-if="!availableDevices || availableDevices.length === 0" class="p-6 text-center text-slate-400">
                            Belum ada perangkat yang dipindai. Klik tombol <b class="text-slate-600 dark:text-zinc-300">"Cari Perangkat"</b> di atas.
                        </div>
                        
                        <div 
                            v-for="(device, index) in (availableDevices || [])" 
                            :key="index"
                            @click="handleSelectDevice(device)"
                            class="p-3.5 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-zinc-800/60 cursor-pointer transition-colors"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 rounded-xl">
                                    <Printer class="h-4 w-4" />
                                </div>
                                <div>
                                    <h5 class="font-extrabold text-slate-800 dark:text-zinc-200">{{ device.name || 'Printer Tanpa Nama' }}</h5>
                                    <span class="text-[10px] text-slate-400 font-mono">ID: {{ device.id }}</span>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-primary flex items-center gap-1">
                                Hubungkan
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template> -->

<script setup lang="ts">
import { Bluetooth, X, Printer } from '@lucide/vue';
import { useThermalPrinter } from '@/composables/useThermalPrinter';
import { toast } from 'vue-sonner';

defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const { print } = useThermalPrinter();

const handleTestPrint = async () => {
    try {
        const testText = "TEST CETAK\nKASIR OK\n\n\n";
        await print(testText);
        toast.success("Perintah cetak dikirim ke aplikasi perantara.");
    } catch (error) {
        toast.error("Gagal mengirim perintah cetak.");
    }
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 w-full max-w-md rounded-3xl border border-slate-100 dark:border-zinc-800 overflow-hidden shadow-2xl flex flex-col">
            
            <!-- Header Modal -->
            <div class="p-5 border-b border-slate-100 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/40">
                <div class="flex items-center gap-2">
                    <Bluetooth class="h-5 w-5 text-blue-600" />
                    <h4 class="font-bold text-sm text-slate-900 dark:text-zinc-100">Pengaturan Printer</h4>
                </div>
                <button @click="emit('close')" class="p-1.5 rounded-full text-slate-400 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Body Modal -->
            <div class="p-6 space-y-4 text-xs">
                <p class="text-slate-500 dark:text-zinc-400 leading-relaxed">
                    Sistem menggunakan metode cetak jembatan (*Direct Intent*). Mode ini memastikan Manufix POS **tidak merebut koneksi Bluetooth**, sehingga bisa tetap berjalan berdampingan dengan aplikasi lain seperti ShopeeFood atau Grab.
                </p>

                <div class="p-4 bg-slate-50 dark:bg-zinc-800/50 rounded-2xl border border-slate-200/60 dark:border-zinc-700 space-y-2">
                    <span class="font-bold text-slate-400 uppercase text-[10px] block">Status Sistem Cetak:</span>
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold text-slate-800 dark:text-zinc-200 text-sm">
                            Anti-Konflik Bridge Aktif
                        </span>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400">
                            Ready
                        </span>
                    </div>
                </div>

                <!-- Tombol Test Print -->
                <button 
                    @click="handleTestPrint"
                    class="w-full py-3 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 font-bold rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer shadow-2xs"
                >
                    <Printer class="h-4 w-4 text-primary" /> Test Cetak Struk
                </button>
            </div>

            <!-- Footer Modal -->
            <div class="p-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/50 flex justify-end">
                <button 
                    @click="emit('close')" 
                    type="button" 
                    class="w-full py-3 bg-slate-900 hover:bg-slate-800 dark:bg-zinc-100 dark:text-zinc-900 text-white font-black rounded-2xl text-xs shadow-md transition-all cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>