<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { useDateFilter, type DateRangePreset } from '../composables/useDateFilter';

import Select from '@/components/ui/select/Select.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';

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
    <Select v-model="selectedPreset">
        <SelectTrigger class="w-auto h-8 rounded-2xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-4 text-xs font-semibold">
            <SelectValue placeholder="Pilih Periode" />
        </SelectTrigger>
        <SelectContent class="rounded-2xl">
            <SelectItem v-for="preset in presets" :key="preset.value" :value="preset.value" class="text-xs font-medium">
                {{ preset.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>