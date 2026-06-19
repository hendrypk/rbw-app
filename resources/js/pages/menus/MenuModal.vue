<script setup lang="ts">
    import { computed, watch } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';

interface Recipe {
        raw_material_id: string;
        qty_usage: number | string;
    }

    interface Price {
        channel: string;
        margin_percent: number | string;
    }

    const props = defineProps<{ 
        show: boolean, 
        menu: any | null, 
        rawMaterials: any[] 
    }>();

    const emit = defineEmits(['close', 'success']);

    // Helper untuk mata uang
    const currency = (n: number) => new Intl.NumberFormat('id-ID', { 
        style: 'currency', currency: 'IDR', minimumFractionDigits: 0 
    }).format(n ?? 0);

    // Helper untuk angka desimal (2 digit)
    const formatNumber = (n: number | string) => new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(n) ?? 0);

    const materialMap = computed(() => {
        return Object.fromEntries(props.rawMaterials.map(m => [m.id, m]));
    });

    const form = useForm({
        name: props.menu?.name ?? '',
        category: props.menu?.category ?? '',
        recipes: (props.menu?.recipes?.map((r: any) => ({ raw_material_id: r.raw_material_id, qty_usage: Number(r.qty_usage) })) ?? [{ raw_material_id: '', qty_usage: 1 }]) as Recipe[],
        prices: (props.menu?.prices?.map((p: any) => ({ channel: p.channel, margin_percent: Number(p.margin_percent) })) ?? [
            { channel: 'offline', margin_percent: 30 },
            { channel: 'shopeefood', margin_percent: 30 },
            { channel: 'grabfood', margin_percent: 30 },
            { channel: 'gofood', margin_percent: 30 },
        ]) as Price[]
    });

    // Perhitungan HPP yang lebih aman
    const totalHpp = computed<number>(() => {
        return form.recipes.reduce((sum: number, rec: Recipe) => {
            const material = materialMap.value[rec.raw_material_id];
            // Mengambil avg_cost, default 0 jika material tidak ditemukan
            const cost = material ? Number(material.avg_cost) : 0; 
            const qty = Number(rec.qty_usage);
            return sum + (cost * qty);
        }, 0);
    });

    // Watcher untuk Edit mode
    watch(() => props.menu, (newMenu) => {
        if (newMenu) {
            form.defaults({
                name: newMenu.name,
                category: newMenu.category,
                recipes: newMenu.recipes.map((r: any) => ({ raw_material_id: r.raw_material_id, qty_usage: Number(r.qty_usage) })),
                prices: newMenu.prices.map((p: any) => ({ channel: p.channel, margin_percent: Number(p.margin_percent) }))
            });
            form.reset();
        } else {
            form.reset();
        }
    }, { immediate: true });

    const submit = () => {
        const url = props.menu ? `/api/menus/${props.menu.id}` : '/api/menus';
        const method = props.menu ? 'put' : 'post';
        form[method](url, { onSuccess: () => { emit('success'); emit('close'); } });
    };

    const addRecipe = () => form.recipes.push({ raw_material_id: '', qty_usage: 1 });
    const removeRecipe = (index: number) => form.recipes.splice(index, 1);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-3xl bg-card rounded-xl shadow-xl p-6 border max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold mb-4">{{ menu ? 'Edit Menu' : 'Tambah Menu Baru' }}</h2>
            
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <Input v-model="form.name" placeholder="Nama Menu" required />
                    <Input v-model="form.category" placeholder="Kategori" />
                </div>

<div class="border-t pt-4">
    <h3 class="font-semibold mb-3">Komposisi Resep</h3>
    
    <div class="space-y-2">
<div v-for="(rec, index) in form.recipes" :key="index" class="grid grid-cols-12 gap-2 items-center">
        <select v-model="rec.raw_material_id" class="col-span-5 rounded-md border p-2 text-sm">
            <option value="" disabled>Pilih Bahan</option>
            <option v-for="mat in rawMaterials" :key="mat.id" :value="mat.id">{{ mat.name }}</option>
        </select>
        
        <div class="col-span-2 text-xs text-muted-foreground text-center">
            {{ materialMap[rec.raw_material_id] ? formatNumber(materialMap[rec.raw_material_id].avg_cost) : '-' }}
        </div>
        
        <Input v-model="rec.qty_usage" type="number" step="0.01" class="col-span-2" placeholder="Qty" />
        
        <div class="col-span-2 font-medium text-right text-sm">
            {{ currency((Number(materialMap[rec.raw_material_id]?.avg_cost) || 0) * Number(rec.qty_usage)) }}
        </div>
        
        <Button type="button" variant="ghost" size="sm" class="col-span-1" @click="removeRecipe(index)">×</Button>
    </div>
    </div>

    <Button type="button" variant="outline" size="sm" class="mt-3" @click="addRecipe">+ Tambah Bahan</Button>

    <div class="mt-6 flex justify-between items-center p-4 bg-muted/50 rounded-lg border">
        <span class="font-semibold text-sm">Total HPP per Menu</span>
        <span class="font-bold text-lg text-primary">{{ currency(totalHpp) }}</span>
    </div>
</div>

                <div class="border-t pt-4">
                    <h3 class="font-semibold mb-3">Pricing per Channel</h3>
                    <div class="grid grid-cols-1 gap-2">
                        <div v-for="price in form.prices" :key="price.channel" class="grid grid-cols-4 gap-4 items-center bg-muted/30 p-2 rounded-md">
                            <div class="font-medium capitalize">{{ price.channel }}</div>
                            <div class="flex items-center gap-1">
                                <Input v-model="price.margin_percent" type="number" class="w-16" />
                                <span class="text-sm">%</span>
                            </div>
                            <div class="text-sm">
                                Jual: <strong>{{ currency(totalHpp * (1 + price.margin_percent / 100)) }}</strong>
                            </div>
                            <div class="text-sm text-green-600">
                                Margin: {{ currency(totalHpp * (price.margin_percent / 100)) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <Button variant="outline" type="button" @click="$emit('close')">Batal</Button>
                    <Button type="submit" :disabled="form.processing">Simpan Menu</Button>
                </div>
            </form>
        </div>
    </div>
</template>