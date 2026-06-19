<script setup lang="ts">
import { computed } from 'vue';
import { Monitor, Moon, Sun } from '@lucide/vue';

import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

// Menentukan ikon berdasarkan mode yang sedang aktif
const CurrentIcon = computed(() => {
    if (appearance.value === 'dark') return Moon;
    if (appearance.value === 'light') return Sun;
    return Monitor;
});

// Fungsi untuk siklus (cycle) antar mode: light -> dark -> system -> light
const toggleAppearance = () => {
    if (appearance.value === 'light') updateAppearance('dark');
    else if (appearance.value === 'dark') updateAppearance('system');
    else updateAppearance('light');
};
</script>

<template>
    <button
        @click="toggleAppearance"
        class="rounded-lg p-2 text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
        aria-label="Toggle theme"
    >
        <component :is="CurrentIcon" class="h-5 w-5" />
    </button>
</template>