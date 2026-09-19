<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useDateFilter, type DateRangePreset } from '../composables/useDateFilter';

const emit = defineEmits<{
    (e: 'change', range: { start: string; end: string }): void
}>();

const { getRange } = useDateFilter();
const isOpen = ref(false);
const selectedPreset = ref<DateRangePreset | 'custom'>('this_month');

const displayLabel = ref('Bulan Ini');
const currentMonthLeft = ref(new Date());
const selectedStartDate = ref<string>('');
const selectedEndDate = ref<string>('');

const presets: { label: string; value: DateRangePreset | 'custom' }[] = [
    { label: 'Hari Ini', value: 'today' },
    { label: 'Kemarin', value: 'yesterday' },
    { label: 'Minggu Ini', value: 'this_week' },
    { label: 'Minggu Lalu', value: 'last_week' },
    { label: 'Bulan Ini', value: 'this_month' },
    { label: 'Bulan Lalu', value: 'last_month' },
    { label: '7 Hari Lalu', value: 'last_7_days' },
    { label: '30 Hari Lalu', value: 'last_30_days' },
    { label: 'Tahun Ini', value: 'this_year' },
    { label: 'Tahun Lalu', value: 'last_year' },
];

const selectPreset = (preset: typeof presets[0]) => {
    selectedPreset.value = preset.value;
    displayLabel.value = preset.label;

    const range = getRange(preset.value as DateRangePreset);
    selectedStartDate.value = range.start;
    selectedEndDate.value = range.end;

    // Sinkronkan bulan tampilan kalendar kiri ke tanggal mulai preset
    currentMonthLeft.value = new Date(range.start);

    emit('change', range);
    isOpen.value = false;
};

const formatDateDisplay = (dateStr: string) => {
    if (!dateStr) return '';
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
};

// Kalendar Kanan otomatis 1 bulan setelah Kiri
const currentMonthRight = computed(() => {
    const d = new Date(currentMonthLeft.value);
    d.setMonth(d.getMonth() + 1);
    return d;
});

const getDaysInMonth = (date: Date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const days = [];

    const firstDayIndex = new Date(year, month, 1).getDay();
    const totalDays = new Date(year, month + 1, 0).getDate();
    const prevTotalDays = new Date(year, month, 0).getDate();

    // Perbaikan offset minggu (Senin = 0, Minggu = 6)
    const startDayOffset = firstDayIndex === 0 ? 6 : firstDayIndex - 1;

    // Padding hari bulan sebelumnya
    for (let i = startDayOffset - 1; i >= 0; i--) {
        const prevMonthDate = new Date(year, month - 1, prevTotalDays - i);
        days.push({
            day: prevTotalDays - i,
            isCurrentMonth: false,
            dateStr: prevMonthDate.toISOString().split('T')[0]
        });
    }

    // Hari bulan aktif
    for (let i = 1; i <= totalDays; i++) {
        const formattedMonth = String(month + 1).padStart(2, '0');
        const formattedDay = String(i).padStart(2, '0');
        days.push({
            day: i,
            isCurrentMonth: true,
            dateStr: `${year}-${formattedMonth}-${formattedDay}`
        });
    }

    // Padding hari bulan berikutnya agar genap 5-6 baris (total 42 sel)
    const remainingCells = 42 - days.length;
    for (let i = 1; i <= remainingCells; i++) {
        const nextMonthDate = new Date(year, month + 1, i);
        days.push({
            day: i,
            isCurrentMonth: false,
            dateStr: nextMonthDate.toISOString().split('T')[0]
        });
    }

    return days;
};

const daysLeft = computed(() => getDaysInMonth(currentMonthLeft.value));
const daysRight = computed(() => getDaysInMonth(currentMonthRight.value));

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

const handleDateClick = (dateStr: string) => {
    if (!dateStr) return;
    selectedPreset.value = 'custom';

    if (!selectedStartDate.value || (selectedStartDate.value && selectedEndDate.value)) {
        selectedStartDate.value = dateStr;
        selectedEndDate.value = '';
    } else if (selectedStartDate.value && !selectedEndDate.value) {
        if (dateStr < selectedStartDate.value) {
            selectedStartDate.value = dateStr;
        } else {
            selectedEndDate.value = dateStr;
            emit('change', { start: selectedStartDate.value, end: selectedEndDate.value });
            isOpen.value = false;
        }
    }
};

const isSelected = (dateStr: string) => dateStr === selectedStartDate.value || dateStr === selectedEndDate.value;
const isInRange = (dateStr: string) => {
    if (!selectedStartDate.value || !selectedEndDate.value) return false;
    return dateStr > selectedStartDate.value && dateStr < selectedEndDate.value;
};

const prevMonth = () => {
    const d = new Date(currentMonthLeft.value);
    d.setMonth(d.getMonth() - 1);
    currentMonthLeft.value = d;
};

const nextMonth = () => {
    const d = new Date(currentMonthLeft.value);
    d.setMonth(d.getMonth() + 1);
    currentMonthLeft.value = d;
};

const dropdownRef = ref<HTMLElement | null>(null);
const handleClickOutside = (e: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    const initial = getRange('this_month');
    selectedStartDate.value = initial.start;
    selectedEndDate.value = initial.end;
    emit('change', initial);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative inline-block text-left" ref="dropdownRef">

        <!-- Tombol Trigger Utama -->
        <div
            @click="isOpen = !isOpen"
            class="flex items-center gap-3 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 px-3.5 py-1.5 rounded-xl shadow-2xs cursor-pointer hover:border-orange-500/50 transition text-xs font-semibold select-none"
        >
            <div class="flex items-center gap-2 text-slate-700 dark:text-slate-200">
                <span>{{ formatDateDisplay(selectedStartDate) }}</span>
                <span class="text-slate-400">→</span>
                <span>{{ formatDateDisplay(selectedEndDate) }}</span>
            </div>
            <div class="w-6 h-6 rounded-lg bg-orange-50 dark:bg-orange-950/40 flex items-center justify-center text-orange-600 dark:text-orange-400">
                📅
            </div>
        </div>

        <!-- Popover Kalendar Mac Style -->
        <div v-if="isOpen" class="absolute right-0 mt-2 z-50 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl shadow-2xl flex overflow-hidden text-xs animate-in fade-in zoom-in-95 duration-150">

            <!-- Sidebar Preset Kiri (Warna Brand: Orange) -->
            <div class="w-44 bg-slate-50/75 dark:bg-zinc-950/50 border-r border-slate-200 dark:border-zinc-800 p-2 space-y-0.5 overflow-y-auto max-h-[380px] no-scrollbar">
                <button
                    v-for="preset in presets"
                    :key="preset.value"
                    @click="selectPreset(preset)"
                    :class="[
                        'w-full text-left px-3 py-1.5 rounded-xl font-medium transition text-xs',
                        selectedPreset === preset.value
                            ? 'bg-orange-500 text-white shadow-xs font-semibold'
                            : 'text-slate-600 dark:text-slate-400 hover:bg-orange-50 dark:hover:bg-zinc-800 hover:text-orange-600'
                    ]"
                >
                    {{ preset.label }}
                </button>
            </div>

            <!-- Panel Kalendar Ganda Kanan -->
            <div class="p-4 space-y-4">

                <!-- Header Navigasi Bulan -->
                <div class="flex justify-between items-center px-2">
                    <div class="flex items-center gap-2">
                        <button @click="prevMonth" class="p-1 hover:bg-orange-50 dark:hover:bg-zinc-800 rounded-lg text-slate-500 font-bold transition">‹</button>
                        <span class="font-bold text-slate-800 dark:text-slate-200 w-28 text-center">
                            {{ monthNames[currentMonthLeft.getMonth()] }} {{ currentMonthLeft.getFullYear() }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-800 dark:text-slate-200 w-28 text-center">
                            {{ monthNames[currentMonthRight.getMonth()] }} {{ currentMonthRight.getFullYear() }}
                        </span>
                        <button @click="nextMonth" class="p-1 hover:bg-orange-50 dark:hover:bg-zinc-800 rounded-lg text-slate-500 font-bold transition">›</button>
                    </div>
                </div>

                <!-- Grid Kalendar (Kiri & Kanan) -->
                <div class="grid grid-cols-2 gap-6">

                    <!-- Kalendar Bulan Kiri -->
                    <div class="space-y-2">
                        <div class="grid grid-cols-7 text-center font-bold text-[10px] text-slate-400">
                            <span>Sn</span><span>Sl</span><span>Rb</span><span>Km</span><span>Jm</span><span>Sb</span><span>Mg</span>
                        </div>
                        <div class="grid grid-cols-7 gap-y-1 text-center">
                            <button
                                v-for="(item, idx) in daysLeft"
                                :key="'left-' + idx"
                                @click="handleDateClick(item.dateStr)"
                                :class="[
                                    'h-7 w-7 mx-auto rounded-full flex items-center justify-center font-medium transition text-[11px]',
                                    !item.isCurrentMonth ? 'text-slate-300 dark:text-zinc-700' : 'text-slate-700 dark:text-slate-200 hover:bg-orange-50 dark:hover:bg-zinc-800',
                                    isSelected(item.dateStr) ? '!bg-orange-500 !text-white font-bold shadow-xs' : '',
                                    isInRange(item.dateStr) ? 'bg-orange-100/60 dark:bg-orange-950/40 rounded-none' : ''
                                ]"
                            >
                                {{ item.day }}
                            </button>
                        </div>
                    </div>

                    <!-- Kalendar Bulan Kanan -->
                    <div class="space-y-2 border-l border-slate-100 dark:border-zinc-800 pl-6">
                        <div class="grid grid-cols-7 text-center font-bold text-[10px] text-slate-400">
                            <span>Sn</span><span>Sl</span><span>Rb</span><span>Km</span><span>Jm</span><span>Sb</span><span>Mg</span>
                        </div>
                        <div class="grid grid-cols-7 gap-y-1 text-center">
                            <button
                                v-for="(item, idx) in daysRight"
                                :key="'right-' + idx"
                                @click="handleDateClick(item.dateStr)"
                                :class="[
                                    'h-7 w-7 mx-auto rounded-full flex items-center justify-center font-medium transition text-[11px]',
                                    !item.isCurrentMonth ? 'text-slate-300 dark:text-zinc-700' : 'text-slate-700 dark:text-slate-200 hover:bg-orange-50 dark:hover:bg-zinc-800',
                                    isSelected(item.dateStr) ? '!bg-orange-500 !text-white font-bold shadow-xs' : '',
                                    isInRange(item.dateStr) ? 'bg-orange-100/60 dark:bg-orange-950/40 rounded-none' : ''
                                ]"
                            >
                                {{ item.day }}
                            </button>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
