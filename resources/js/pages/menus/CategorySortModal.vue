<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { useSwal } from '@/composables/useSwal';
import { useMenus } from '@/composables/useMenus';

const props = defineProps<{ show: boolean; category: any }>();
const emit = defineEmits(['close', 'updated']);

const { success, error } = useSwal();
const { menus, fetchMenus } = useMenus();
const localMenus = ref<any[]>([]);
const draggedIndex = ref<number | null>(null);

watch(() => props.show, async (newVal) => {
    if (newVal && props.category) {
        // Pastikan data menu terbaru sudah terambil
        await fetchMenus();
        
        // Filter menu yang memiliki kategori yang sesuai dengan ID kategori yang sedang dipilih
        const filteredMenus = menus.value.filter((menu: any) => {
            if (!menu.categories || !Array.isArray(menu.categories)) return false;
            return menu.categories.some((cat: any) => cat.id === props.category.id);
        });

        // Urutkan berdasarkan nilai sort pada tabel pivot
        localMenus.value = filteredMenus.sort((a: any, b: any) => {
            const pivotA = a.categories?.find((c: any) => c.id === props.category.id)?.pivot?.sort ?? 0;
            const pivotB = b.categories?.find((c: any) => c.id === props.category.id)?.pivot?.sort ?? 0;
            return pivotA - pivotB;
        });
    }
});

const onDragStart = (index: number) => {
    draggedIndex.value = index;
};

const onDragOver = (event: DragEvent) => {
    event.preventDefault();
};

const onDrop = (targetIndex: number) => {
    if (draggedIndex.value === null || draggedIndex.value === targetIndex) return;

    const movedItem = localMenus.value.splice(draggedIndex.value, 1)[0];
    localMenus.value.splice(targetIndex, 0, movedItem);
    draggedIndex.value = null;
};

const saveSorting = async () => {
    try {
        const payloadItems = localMenus.value.map((menu, idx) => ({
            menu_id: menu.id,
            sort: idx
        }));

        await axios.post(`/api/categories/${props.category.id}/menus/sort`, { items: payloadItems });
        success('Berhasil', 'Urutan menu dalam kategori berhasil disimpan.');
        emit('updated');
        emit('close');
    } catch (err) {
        error('Gagal', 'Gagal menyimpan urutan menu.');
    }
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-md bg-card rounded-xl shadow-xl p-5 border flex flex-col max-h-[80vh]">
            <div class="flex items-center justify-between border-b pb-2 mb-4">
                <h3 class="font-bold text-base text-foreground">
                    Urutkan Menu: <span class="text-primary">{{ category?.name }}</span>
                </h3>
                <button @click="$emit('close')" class="text-muted-foreground hover:text-foreground text-xl">&times;</button>
            </div>

            <p class="text-xs text-muted-foreground mb-3">Geser (Drag & Drop) baris menu di bawah ini untuk mengatur urutan tampilannya.</p>

            <div class="flex-1 overflow-y-auto space-y-1.5 pr-1 text-xs">
                <div 
                    v-for="(menu, index) in localMenus" 
                    :key="menu.id"
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover="onDragOver"
                    @drop="onDrop(index)"
                    class="flex items-center justify-between p-2.5 rounded-lg bg-muted/40 border border-border/40 hover:bg-muted/70 cursor-grab active:cursor-grabbing select-none"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground font-bold">⠿</span>
                        <span class="font-medium text-foreground">{{ menu.name }}</span>
                    </div>
                    <span class="text-[10px] text-muted-foreground bg-muted px-1.5 py-0.5 rounded">#{{ index + 1 }}</span>
                </div>

                <div v-if="localMenus.length === 0" class="text-center py-6 text-muted-foreground italic">
                    Tidak ada menu dalam kategori ini.
                </div>
            </div>

            <div class="border-t pt-3 mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" class="h-8 text-xs" @click="$emit('close')">Batal</Button>
                <Button size="sm" class="h-8 text-xs" @click="saveSorting">Simpan Urutan</Button>
            </div>
        </div>
    </div>
</template>