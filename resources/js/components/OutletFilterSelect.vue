<!-- resources/js/Components/OutletFilterSelect.vue -->
<script setup lang="ts">
import { useOutletFilter } from '@/composables/useOutletFilter';
import { onMounted } from 'vue';

const emit = defineEmits<{
    (e: 'change', outletId: string): void
}>();

const { outlets, selectedOutletId, fetchOutlets, setOutlet } = useOutletFilter();

const handleChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    setOutlet(target.value, (id) => {
        emit('change', id);
    });
};

onMounted(() => {
    fetchOutlets((id) => {
        emit('change', id);
    });
});
</script>

<template>
    <div class="flex items-center gap-2">
        <label class="text-xs font-medium uppercase tracking-wider text-muted-foreground hidden sm:block">Outlet:</label>
        <select 
            :value="selectedOutletId" 
            @change="handleChange"
            class="px-3 py-1.5 rounded-lg border border-input bg-secondary text-foreground font-medium text-xs outline-none focus:ring-2 focus:ring-ring cursor-pointer"
        >
            <option value="all">🌐 Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
            </option>
        </select>
    </div>
</template>