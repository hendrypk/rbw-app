<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { useDateFilter, type DateRangePreset } from '../composables/useDateFilter';

const emit = defineEmits<{
    (e: 'change', range: { start: string; end: string }): void
}>();

const { getRange } = useDateFilter();
const selectedPreset = ref<DateRangePreset>('today');

const presets: { label: string; value: DateRangePreset }[] = [
    { label: 'Hari Ini', value: 'today' },
    { label: 'Kemarin', value: 'yesterday' },
    { label: 'Minggu Ini', value: 'this_week' },
    { label: 'Minggu Lalu', value: 'last_week' },
    { label: 'Bulan Ini', value: 'this_month' },
    { label: 'Bulan Lalu', value: 'last_month' },
    { label: '3 Bulan Terakhir', value: 'last_3_months' },
    { label: '6 Bulan Terakhir', value: 'last_6_months' },
    { label: 'Tahun Ini', value: 'this_year' },
    { label: 'Tahun Lalu', value: 'last_year' },
];

watch(selectedPreset, (newValue) => {
    emit('change', getRange(newValue));
});

onMounted(() => {
    emit('change', getRange(selectedPreset.value));
});
</script>

<template>
    <select 
        v-model="selectedPreset" 
        class="w-full px-2 py-1 rounded-md border border-input bg-secondary text-foreground font-medium text-[11px] outline-none focus:ring-1 focus:ring-ring cursor-pointer truncate"
    >
        <option v-for="preset in presets" :key="preset.value" :value="preset.value">
            {{ preset.label }}
        </option>
    </select>
</template>