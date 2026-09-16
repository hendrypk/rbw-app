<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ChevronDown, Check } from '@lucide/vue';

interface Option {
    id: string | number;
    name: string;
}

const props = defineProps<{
    modelValue: (string | number)[];
    options: Option[];
    placeholder?: string;
}>();

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

const toggleOption = (id: string | number) => {
    const newValue = [...props.modelValue];
    const index = newValue.indexOf(id);
    if (index === -1) {
        newValue.push(id);
    } else {
        newValue.splice(index, 1);
    }
    emit('update:modelValue', newValue);
};

const selectedText = computed(() => {
    if (!props.modelValue || props.modelValue.length === 0) {
        return props.placeholder || 'Pilih opsi...';
    }
    return props.options
        .filter(opt => props.modelValue.includes(opt.id))
        .map(opt => opt.name)
        .join(', ');
});

const handleClickOutside = (event: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside));
</script>

<template>
    <div class="relative w-full" ref="dropdownRef">
        <!-- TRIGGER INPUT (Diselaraskan dengan input h-11, rounded-2xl, shadow-2xs) -->
        <div
            @click="toggleDropdown"
            class="flex items-center justify-between h-11 w-full min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50 px-4 py-2 text-sm shadow-2xs transition-all duration-200 hover:border-slate-300 dark:hover:border-zinc-700 cursor-pointer select-none outline-none"
            :class="{ 'border-primary ring-2 ring-primary/20 dark:border-primary': isOpen }"
        >
            <span
                class="truncate pr-4 transition-colors"
                :class="modelValue.length > 0 ? 'text-slate-900 dark:text-zinc-100 font-medium' : 'text-slate-400 dark:text-zinc-500'"
            >
                {{ selectedText }}
            </span>
            <ChevronDown
                class="w-4 h-4 text-slate-400 shrink-0 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
            />
        </div>

        <!-- DROPDOWN MENU -->
        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
        >
            <div
                v-if="isOpen"
                class="absolute z-50 w-full mt-2 p-1.5 bg-white dark:bg-[#18181b] border border-slate-200 dark:border-zinc-800 rounded-2xl shadow-lg max-h-[260px] overflow-y-auto no-scrollbar flex flex-col"
            >
                <div v-if="options.length === 0" class="py-3 text-center text-sm text-slate-500">
                    Tidak ada opsi.
                </div>

                <!-- ITEM LIST -->
                <div
                    v-for="opt in options"
                    :key="opt.id"
                    @click="toggleOption(opt.id)"
                    class="relative flex justify-between w-full cursor-pointer select-none items-center rounded-xl px-3 py-2.5 text-sm outline-none hover:bg-slate-100 dark:hover:bg-zinc-800/80 text-slate-700 dark:text-zinc-200 transition-colors"
                >
                    <span class="truncate pr-4" :class="{ 'font-medium text-slate-900 dark:text-white': modelValue.includes(opt.id) }">
                        {{ opt.name }}
                    </span>

                    <!-- Checkmark di sebelah kanan -->
                    <span v-if="modelValue.includes(opt.id)" class="flex items-center justify-center shrink-0">
                        <Check class="w-4 h-4 stroke-[2.5]" />
                    </span>
                </div>

                <!-- Indikator panah bawah (opsional, jika item banyak) -->
                <div v-if="options.length > 6" class="sticky bottom-0 left-0 right-0 flex justify-center pb-1 pt-3 bg-gradient-to-t from-white dark:from-[#18181b] to-transparent pointer-events-none rounded-b-xl">
                    <ChevronDown class="w-4 h-4 text-slate-400/70" />
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
