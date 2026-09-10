<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Calendar as CalendarIcon, ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    modelValue: string; // Format YYYY-MM-DD
}>();

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const currentDate = ref(new Date());

// Sinkronisasi modelValue dengan kalender aktif
const selectedDate = computed({
    get: () => {
        if (!props.modelValue) return null;
        
        const [y, m, d] = props.modelValue.split('T')[0].split('-');
        if (y && m && d) {
            return new Date(Number(y), Number(m) - 1, Number(d)); 
        }
        return null;
    },
    set: (val: Date | null) => {
        if (!val) {
            emit('update:modelValue', '');
            return;
        }
        const year = val.getFullYear();
        const month = String(val.getMonth() + 1).padStart(2, '0');
        const day = String(val.getDate()).padStart(2, '0');
        emit('update:modelValue', `${year}-${month}-${day}`);
    }
});
// Format tampilan di input (DD/MM/YYYY)
const formattedDisplay = computed(() => {
    if (!props.modelValue) return 'Pilih Tanggal';
    const [y, m, d] = props.modelValue.split('-');
    if (!y || !m || !d) return props.modelValue;
    return `${d}/${m}/${y}`;
});

const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

const currentMonthName = computed(() => monthNames[currentDate.value.getMonth()]);
const currentYear = computed(() => currentDate.value.getFullYear());

// Hitung hari dalam grid kalender
const calendarDays = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    
    const firstDayIndex = new Date(year, month, 1).getDay();
    const totalDays = new Date(year, month + 1, 0).getDate();
    const prevTotalDays = new Date(year, month, 0).getDate();

    const days = [];

    // Hari dari bulan sebelumnya
    for (let i = firstDayIndex - 1; i >= 0; i--) {
        const d = new Date(year, month - 1, prevTotalDays - i);
        days.push({ date: d, isCurrentMonth: false });
    }

    // Hari bulan aktif
    for (let i = 1; i <= totalDays; i++) {
        const d = new Date(year, month, i);
        days.push({ date: d, isCurrentMonth: true });
    }

    // Sisa grid untuk bulan berikutnya (total 42 kotak / 6 baris)
    const remainingGrid = 42 - days.length;
    for (let i = 1; i <= remainingGrid; i++) {
        const d = new Date(year, month + 1, i);
        days.push({ date: d, isCurrentMonth: false });
    }

    return days;
});

const prevMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
};

const selectDate = (date: Date) => {
    selectedDate.value = date;
    isOpen.value = false;
};

const selectToday = () => {
    selectedDate.value = new Date();
    isOpen.value = false;
};

// Tutup dropdown saat klik di luar
const rootRef = ref<HTMLElement | null>(null);
const handleClickOutside = (e: MouseEvent) => {
    if (rootRef.value && !rootRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div ref="rootRef" class="relative w-full">
        <!-- Trigger Input -->
        <button 
            type="button" 
            @click="isOpen = !isOpen"
            class="w-full h-11 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900 px-4 text-xs font-semibold flex items-center justify-between text-slate-700 dark:text-zinc-200 cursor-pointer focus:outline-none focus:ring-1 focus:ring-primary"
        >
            <span>{{ formattedDisplay }}</span>
            <CalendarIcon class="size-4 text-slate-400" />
        </button>

        <!-- Dropdown Kalender -->
        <div v-if="isOpen" class="absolute z-50 mt-2 w-72 p-4 rounded-3xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
            <!-- Header Bulan & Tahun -->
            <div class="flex items-center justify-between mb-3 px-1">
                <span class="text-xs font-bold text-slate-800 dark:text-zinc-100">{{ currentMonthName }} {{ currentYear }}</span>
                <div class="flex items-center gap-1">
                    <button type="button" @click.stop="prevMonth" class="size-7 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-800 flex items-center justify-center text-slate-500 cursor-pointer">
                        <ChevronLeft class="size-4" />
                    </button>
                    <button type="button" @click.stop="nextMonth" class="size-7 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-800 flex items-center justify-center text-slate-500 cursor-pointer">
                        <ChevronRight class="size-4" />
                    </button>
                </div>
            </div>

            <!-- Hari dalam Seminggu -->
            <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 uppercase mb-2">
                <span>Min</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span>
            </div>

            <!-- Grid Tanggal -->
            <div class="grid grid-cols-7 gap-1 text-center text-xs">
                <button
                    v-for="(item, index) in calendarDays"
                    :key="index"
                    type="button"
                    @click="selectDate(item.date)"
                    :class="[
                        'h-8 rounded-xl flex items-center justify-center font-medium transition-colors cursor-pointer',
                        !item.isCurrentMonth ? 'text-slate-300 dark:text-zinc-600' : 'text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800',
                        selectedDate && item.date.toDateString() === selectedDate.toDateString() ? 'bg-primary text-primary-foreground font-bold hover:bg-primary' : ''
                    ]"
                >
                    {{ item.date.getDate() }}
                </button>
            </div>

            <!-- Footer Tombol Cepat -->
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between">
                <button type="button" @click="selectedDate = null; isOpen = false;" class="text-[11px] font-bold text-slate-400 hover:text-rose-600 cursor-pointer">Clear</button>
                <button type="button" @click="selectToday" class="text-[11px] font-bold text-primary hover:underline cursor-pointer">Hari Ini</button>
            </div>
        </div>
    </div>
</template>