<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useSwal } from '@/composables/useSwal';

const props = defineProps<{ show: boolean }>();
const emit = defineEmits(['close', 'updated']);

const { confirm, success, error } = useSwal();
const categories = ref<any[]>([]);
const isLoading = ref(false);

// State untuk Form Add/Edit
const newCategoryName = ref('');
const editingId = ref<string | null>(null);
const editingName = ref('');

const fetchCategories = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get('/api/categories');
        categories.value = res.data;
    } catch (err) {
        console.error('Gagal memuat kategori', err);
    } finally {
        isLoading.value = false;
    }
};

const handleAdd = async () => {
    if (!newCategoryName.value.trim()) return;
    try {
        await axios.post('/api/categories', { name: newCategoryName.value });
        newCategoryName.value = '';
        fetchCategories();
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
        await axios.put(`/api/categories/${id}`, { name: editingName.value });
        editingId.value = null;
        fetchCategories();
        emit('updated');
    } catch (err: any) {
        error('Gagal', err.response?.data?.message || 'Gagal mengubah nama kategori.');
    }
};

const toggleVisibility = async (item: any) => {
    try {
        await axios.put(`/api/categories/${item.id}`, { 
            name: item.name, 
            is_visible: !item.is_visible 
        });
        fetchCategories();
        emit('updated');
    } catch (err) {
        error('Gagal', 'Gagal merubah visibilitas kategori.');
    }
};

const handleDelete = async (item: any) => {
    if (await confirm('Hapus Kategori?', `Apakah Anda yakin ingin menghapus kategori "${item.name}"?`)) {
        try {
            await axios.delete(`/api/categories/${item.id}`);
            fetchCategories();
            emit('updated');
            success('Berhasil', 'Kategori berhasil dihapus.');
        } catch (err: any) {
            // Menerima response error 422 dari database constraint Laravel
            error('Gagal Menghapus', err.response?.data?.message || 'Kategori ini sedang digunakan.');
        }
    }
};

watch(() => props.show, (newVal) => {
    if (newVal) {
        fetchCategories();
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
                    v-for="item in categories" 
                    :key="item.id"
                    class="flex items-center justify-between p-2 rounded-lg bg-muted/40 border border-border/40 hover:bg-muted/70 transition-colors"
                >
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
                            @click="handleDelete(item)"
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
</template>