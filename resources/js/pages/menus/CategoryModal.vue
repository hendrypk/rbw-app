<script setup lang="ts">
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useSwal } from '@/composables/useSwal';
import { useCategories } from '@/composables/useCategories';
import ConifrmModal from '@/components/ConifrmModal.vue';

const props = defineProps<{ show: boolean }>();
const emit = defineEmits(['close', 'updated']);

const { success, error } = useSwal();
const { categories, isLoading, fetchCategories, createCategory, updateCategory, deleteCategory, sortCategories } = useCategories();

// State untuk Form Add/Edit
const newCategoryName = ref('');
const editingId = ref<string | null>(null);
const editingName = ref('');

// State untuk Drag and Drop
const draggedIndex = ref<number | null>(null);

// State untuk Confirm Modal
const isConfirmModalOpen = ref(false);
const isDeleting = ref(false);
const itemToDelete = ref<any>(null);

const loadData = async () => {
    await fetchCategories();
    categories.value = categories.value.sort((a: any, b: any) => (a.sort ?? 0) - (b.sort ?? 0));
};

const handleAdd = async () => {
    if (!newCategoryName.value.trim()) return;
    try {
        const nextSort = categories.value.length > 0 ? Math.max(...categories.value.map(c => c.sort || 0)) + 1 : 0;
        
        await createCategory({ 
            name: newCategoryName.value,
            sort: nextSort
        });

        newCategoryName.value = '';
        await loadData();
        emit('updated');
        success('Berhasil', 'Kategori baru berhasil ditambahkan.');
    } catch (err: any) {
        error('Gagal', err.response?.data?.message || 'Gagal menyimpan kategori baru.');
    }
};

const startEdit = (item: any) => {
    editingId.value = item.id;
    editingName.value = item.name;
};

const handleUpdateName = async (id: string) => {
    if (!editingName.value.trim()) return;
    try {
        await updateCategory(id, { name: editingName.value });
        editingId.value = null;
        await loadData();
        emit('updated');
        success('Berhasil', 'Nama kategori diperbarui.');
    } catch (err: any) {
        error('Gagal', err.response?.data?.message || 'Gagal mengubah nama kategori.');
    }
};

const toggleVisibility = async (item: any) => {
    try {
        await updateCategory(item.id, { 
            name: item.name, 
            is_visible: !item.is_visible 
        });
        await loadData();
        emit('updated');
    } catch (err) {
        error('Gagal', 'Gagal merubah visibilitas kategori.');
    }
};

// Handler Drag and Drop
const onDragStart = (index: number) => {
    draggedIndex.value = index;
};

const onDragOver = (event: DragEvent) => {
    event.preventDefault();
};

const onDrop = async (targetIndex: number) => {
    if (draggedIndex.value === null || draggedIndex.value === targetIndex) return;

    const movedItem = categories.value.splice(draggedIndex.value, 1)[0];
    categories.value.splice(targetIndex, 0, movedItem);
    draggedIndex.value = null;

    const payloadItems = categories.value.map((cat, idx) => ({
        id: cat.id,
        sort: idx
    }));

    try {
        await sortCategories(payloadItems);
        emit('updated');
    } catch (err) {
        error('Gagal', 'Gagal menyimpan urutan kategori.');
        await loadData();
    }
};

const confirmDelete = (item: any) => {
    itemToDelete.value = item;
    isConfirmModalOpen.value = true;
};

const handleDelete = async () => {
    if (!itemToDelete.value) return;
    isDeleting.value = true;
    try {
        await deleteCategory(itemToDelete.value.id);
        await loadData();
        emit('updated');
        success('Berhasil', 'Kategori berhasil dihapus.');
        isConfirmModalOpen.value = false;
    } catch (err: any) {
        error('Gagal Menghapus', err.response?.data?.message || 'Kategori ini sedang digunakan.');
    } finally {
        isDeleting.value = false;
        itemToDelete.value = null;
    }
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        loadData();
        newCategoryName.value = '';
        editingId.value = null;
    }
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-md bg-card rounded-xl shadow-xl p-5 border flex flex-col max-h-[80vh]">
            
            <div class="flex items-center justify-between border-b pb-2 mb-4">
                <h3 class="font-bold text-base text-foreground flex items-center gap-2">
                    <span class="w-2 h-2 rounded bg-primary"></span>
                    Manajemen Kategori Menu
                </h3>
                <button @click="$emit('close')" class="text-muted-foreground hover:text-foreground text-xl">&times;</button>
            </div>

            <div class="flex items-center gap-2 mb-4">
                <Input 
                    v-model="newCategoryName" 
                    placeholder="Nama kategori baru..." 
                    class="h-9 text-xs"
                    @keyup.enter="handleAdd"
                />
                <Button size="sm" class="h-9 text-xs shrink-0" @click="handleAdd">Tambah</Button>
            </div>

            <div class="flex-1 overflow-y-auto space-y-1.5 pr-1 text-xs">
                <div v-if="isLoading" class="text-center py-4 text-muted-foreground">Memuat list...</div>
                
                <div 
                    v-else-if="categories.length > 0"
                    v-for="(item, index) in categories" 
                    :key="item.id"
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover="onDragOver"
                    @drop="onDrop(index)"
                    class="flex items-center justify-between p-2 rounded-lg bg-muted/40 border border-border/40 hover:bg-muted/70 transition-colors cursor-grab active:cursor-grabbing"
                >
                    <div class="text-muted-foreground/50 hover:text-muted-foreground mr-2 shrink-0 flex items-center select-none">
                        ⠿
                    </div>

                    <div class="flex-1 mr-2">
                        <div v-if="editingId === item.id" class="flex items-center gap-1">
                            <Input v-model="editingName" class="h-7 text-xs font-medium px-2 py-0" @keyup.enter="handleUpdateName(item.id)" />
                            <button @click="handleUpdateName(item.id)" class="text-green-600 font-bold px-1 text-sm">✓</button>
                            <button @click="editingId = null" class="text-muted-foreground font-bold px-1 text-sm">&times;</button>
                        </div>
                        <span 
                            v-else 
                            :class="['font-medium transition-opacity', !item.is_visible ? 'opacity-40 line-through' : '']"
                            @dblclick="startEdit(item)"
                            title="Double klik untuk edit nama"
                        >
                            {{ item.name }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button 
                            type="button"
                            @click="toggleVisibility(item)"
                            :class="item.is_visible ? 'text-blue-500 hover:text-blue-700 bg-blue-50 dark:bg-blue-950/40' : 'text-gray-400 hover:text-gray-600 bg-gray-100 dark:bg-gray-800'"
                            class="px-2 py-0.5 rounded text-[10px] font-medium transition-all"
                        >
                            {{ item.is_visible ? 'Visible' : 'Hidden' }}
                        </button>
                        
                        <button 
                            type="button" 
                            @click="confirmDelete(item)"
                            class="text-muted-foreground hover:text-destructive font-semibold text-sm px-1.5"
                        >
                            &times;
                        </button>
                    </div>
                </div>

                <div v-else class="text-center py-6 text-muted-foreground italic">
                    Belum ada kategori yang dibuat.
                </div>
            </div>

            <div class="border-t pt-3 mt-4 flex justify-end">
                <Button variant="outline" size="sm" class="h-8 text-xs" @click="$emit('close')">Tutup</Button>
            </div>
        </div>
    </div>

    <ConifrmModal 
        :show="isConfirmModalOpen"
        title="Hapus Kategori?"
        :message="`Apakah Anda yakin ingin menghapus kategori '${itemToDelete?.name || ''}'? Tindakan ini tidak dapat dibatalkan.`"
        confirmText="Ya, Hapus"
        :loading="isDeleting"
        @close="isConfirmModalOpen = false"
        @confirm="handleDelete"
    />
</template>