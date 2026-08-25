<script setup lang="ts">
    import { ref, watch, computed } from 'vue';
    import axios from 'axios';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import InputError from '@/components/InputError.vue';
    import { useCategories } from '@/composables/useCategories';
    import { useMaterials } from '@/composables/useMaterials';

    const props = defineProps<{ 
        show: boolean, 
        menu?: any | null, 
        masterOverhead?: number 
    }>();
    
    const emit = defineEmits(['close', 'saved']);

    const processing = ref(false);
    const errors = ref<Record<string, string>>({});
    const isEdit = computed(() => !!props.menu);

    const { categories, fetchCategories } = useCategories();
    const { materialOptions, fetchMaterialOptions } = useMaterials();

    const userCategories = computed(() => {
        return categories.value.filter((c: any) => c.is_visible);
    });

    const materialMap = computed(() => {
        return Object.fromEntries(
            materialOptions.value.map(m => [m.id, m])
        );
    });

    const currency = (n: number) => new Intl.NumberFormat('id-ID', { 
        style: 'currency', currency: 'IDR', minimumFractionDigits: 0 
    }).format(n ?? 0);

    const formatNumber = (n: number | string) => new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(n) ?? 0);

    const PLATFORM_FEES: Record<string, number> = {
        offline: 0,
        shopeefood: 30,
        grabfood: 30,
        gofood: 30
    };

    interface Recipe {
        raw_material_id: string | number;
        qty_usage: number;
    }

    interface PriceChannel {
        channel: string;
        margin_percent: number;
        selling_price: number;
    }
    
    const form = ref({
        name: '',
        category_id: '',
        overhead_cost: 0,
        recipes: [{ raw_material_id: '', qty_usage: 1 }] as Recipe[],
        prices: [
            { channel: 'offline', margin_percent: 30 },
            { channel: 'shopeefood', margin_percent: 30 },
            { channel: 'grabfood', margin_percent: 30 },
            { channel: 'gofood', margin_percent: 30 },
        ] as PriceChannel[]
    });

    const totalHpp = computed<number>(() => {
        return form.value.recipes.reduce((sum: number, rec: any) => {
            const material = materialMap.value[rec.raw_material_id];
            const cost = material ? Number(material.avg_cost) : 0; // Diubah ke avg_cost
            const qty = Number(rec.qty_usage);
            return sum + (cost * qty);
        }, 0);
    });

    const totalBaseCost = computed<number>(() => {
        return totalHpp.value + (Number(form.value.overhead_cost) || 0);
    });

    const toggleOverhead = () => {
        if (form.value.overhead_cost > 0) {
            form.value.overhead_cost = 0;
        } else {
            form.value.overhead_cost = props.masterOverhead || 0;
        }
    };

// Fungsi untuk menghitung Nett Profit secara murni tanpa menimpa harga jual
const calculateCleanProfit = (priceObj: PriceChannel) => {
    const baseCost = totalBaseCost.value;
    const feePercent = PLATFORM_FEES[priceObj.channel] ?? 0;
    const sellingPrice = Number(priceObj.selling_price) || 0;

    if (!sellingPrice || !baseCost) return 0;

    const nettPrice = sellingPrice * (1 - feePercent / 100);
    const cleanProfit = nettPrice - baseCost;

    return Number(cleanProfit.toFixed(2));
};

// Fungsi update margin saat user mengetik harga jual
const updateMarginFromPrice = (priceObj: PriceChannel) => {
    const baseCost = totalBaseCost.value;
    const feePercent = PLATFORM_FEES[priceObj.channel] ?? 0;
    const newSellingPrice = Number(priceObj.selling_price) || 0;
    
    if (!newSellingPrice || !baseCost || baseCost <= 0) {
        priceObj.margin_percent = 0;
        return; 
    }

    let targetPriceBeforeOjol = newSellingPrice;
    if (priceObj.channel !== 'offline' && feePercent > 0) {
        targetPriceBeforeOjol = newSellingPrice * (1 - feePercent / 100);
    }

    if (targetPriceBeforeOjol < baseCost) {
        priceObj.margin_percent = 0;
        return;
    }

    const calculatedMargin = ((targetPriceBeforeOjol - baseCost) / baseCost) * 100;
    priceObj.margin_percent = Number(calculatedMargin.toFixed(2));
};

    watch(
        () => props.show,
        async (newVal) => {
            if (!newVal) return;

            await Promise.all([
                fetchCategories(),
                fetchMaterialOptions(),
            ]);

            if (props.menu) {
                form.value = {
                    name: props.menu.name,
                    category_id: props.menu.category_id || '',
                    overhead_cost: Number(props.menu.overhead_cost ?? props.masterOverhead ?? 0),
                    recipes: props.menu.recipes.map((r: any) => ({
                        raw_material_id: r.raw_material_id,
                        qty_usage: Number(r.qty_usage),
                    })),
                    prices: props.menu.prices.map((p: any) => ({
                        channel: p.channel,
                        margin_percent: Number(p.margin_percent),
                        selling_price: Math.round(Number(p.selling_price || 0)),
                    })),
                };
            } else {
                form.value = {
                    name: '',
                    category_id: '',
                    overhead_cost: props.masterOverhead || 0,
                    recipes: [{ raw_material_id: '', qty_usage: 1 }],
                    prices: [
                        { channel: 'offline', margin_percent: 30, selling_price: 0 },
                        { channel: 'shopeefood', margin_percent: 30, selling_price: 0 },
                        { channel: 'grabfood', margin_percent: 30, selling_price: 0 },
                        { channel: 'gofood', margin_percent: 30, selling_price: 0 },
                    ],
                };
            }

            errors.value = {};
        }
    );

    const submit = async () => {
        processing.value = true;
        errors.value = {};

        try {
            const url = isEdit.value ? `/api/menus/${props.menu.id}` : '/api/menus';
            const method = isEdit.value ? 'put' : 'post';
            await axios[method](url, form.value);
            emit('saved');
            emit('close');
        } catch (e: any) {
            if (e.response?.status === 422) {
                errors.value = e.response.data.errors;
            }
        } finally {
            processing.value = false;
        }
    };

    const addRecipe = () => form.value.recipes.push({ raw_material_id: '', qty_usage: 1 });
    const removeRecipe = (index: number) => form.value.recipes.splice(index, 1);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-6xl bg-card rounded-xl shadow-xl p-6 border max-h-[90vh] overflow-y-auto">
            
            <div class="flex items-center justify-between border-b pb-3 mb-4">
                <h2 class="text-xl font-bold">{{ menu ? 'Edit Menu Produksi' : 'Tambah Menu Baru' }}</h2>
                <button @click="$emit('close')" class="text-muted-foreground hover:text-foreground text-xl">&times;</button>
            </div>
            
            <form @submit.prevent="submit" class="space-y-6">
                <!-- DATA UTAMA -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <Label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Nama Menu</Label>
                        <Input v-model="form.name" placeholder="Contoh: Ayam Bakar Madu" required />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">Kategori</Label>
                        <select 
                            v-model="form.category_id" 
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <option value="">-- Pilih Kategori Menu --</option>
                            <option v-for="cat in userCategories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                        <InputError :message="errors.category_id" class="mt-1" />
                    </div>
                </div>

                <!-- INDUK LAYOUT GRID SPLIT -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    
                    <!-- KELOMPOK KIRI: RESEP & SUMMARY BIAYA -->
                    <div class="lg:col-span-7 space-y-4 border rounded-xl p-4 bg-background shadow-sm">
                        <div class="border-b pb-2">
                            <h3 class="font-bold text-sm tracking-tight text-foreground">Komposisi Resep & Bahan</h3>
                        </div>
                
                        <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
                            <div v-for="(rec, index) in form.recipes" :key="index" class="grid grid-cols-12 gap-2 items-center bg-muted/20 p-1.5 rounded-lg border border-border/40">
                                <select v-model="rec.raw_material_id" class="col-span-5 rounded-md border p-1.5 text-xs bg-background">
                                    <option value="" disabled>Pilih Bahan</option>
                                    <option 
                                        v-for="mat in materialOptions" 
                                        :key="mat.id" 
                                        :value="mat.id"
                                        :disabled="form.recipes.some((r: any) => r.raw_material_id === mat.id && r !== rec)"
                                    >
                                        {{ mat.name }}
                                    </option>
                                </select>
                                
                                <div class="col-span-3 text-[11px] text-muted-foreground text-center leading-tight">
                                    {{ formatNumber(materialMap[rec.raw_material_id]?.avg_cost || 0) }}<br/>
                                    <span class="opacity-60">/ {{ materialMap[rec.raw_material_id]?.base_unit || '-' }}</span>
                                </div>
                                
                                <Input v-model="rec.qty_usage" type="number" step="0.0001" class="col-span-2 h-8 text-xs text-center px-1" placeholder="Qty" />
                                
                                <div class="col-span-1.5 font-semibold text-right text-xs pr-1">
                                    {{ currency((Number(materialMap[rec.raw_material_id]?.avg_cost) || 0) * Number(rec.qty_usage)) }}
                                </div>
                                
                                <div class="col-span-0.5 text-right">
                                    <button type="button" class="text-muted-foreground hover:text-destructive font-bold text-sm p-1" @click="removeRecipe(index)">&times;</button>
                                </div>
                            </div>

                            <div class="pt-1">
                                <Button type="button" variant="outline" size="sm" class="w-full h-8 text-xs border-dashed" @click="addRecipe">
                                    + Tambah Bahan Lain
                                </Button>
                            </div>
                        </div>

                        <!-- SUMMARY COST -->
                        <div class="pt-2 border-t space-y-2">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-muted-foreground">Total HPP Bahan (Food Cost):</span>
                                <span class="font-semibold text-foreground">{{ currency(totalHpp) }}</span>
                            </div>
                            
                            <div class="flex justify-between items-center gap-4 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground">Overhead Cost Proporsional:</span>
                                    <button 
                                        type="button" 
                                        @click="toggleOverhead"
                                        :class="form.overhead_cost > 0 
                                            ? 'text-red-500 bg-red-50 dark:bg-red-950/20 px-2 py-0.5 rounded text-[10px] font-medium border border-red-200/40' 
                                            : 'text-blue-500 bg-blue-50 dark:bg-blue-950/20 px-2 py-0.5 rounded text-[10px] font-medium border border-blue-200/40'"
                                    >
                                        {{ form.overhead_cost > 0 ? '✕ Remove Overhead' : '✓ Apply Master Overhead' }}
                                    </button>
                                </div>

                                <div class="relative flex items-center w-32">
                                    <span class="absolute left-2 text-[10px] font-bold text-muted-foreground">Rp</span>
                                    <Input v-model.number="form.overhead_cost" type="number" min="0" class="h-7 pl-6 pr-1 text-right text-xs font-semibold text-red-600 bg-muted/30" />
                                </div>
                            </div>

                            <div class="flex justify-between items-center p-2.5 bg-primary/5 rounded-lg border border-primary/10 mt-2">
                                <span class="font-bold text-xs text-primary uppercase tracking-wider">Modal Dasar per Porsi:</span>
                                <span class="font-extrabold text-base text-primary">{{ currency(totalBaseCost) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- KELOMPOK KANAN: PRICING PER CHANNEL (Dinamis Dua Arah) -->
<!-- KELOMPOK KANAN: PRICING PER CHANNEL -->
<div class="lg:col-span-5 space-y-3 border rounded-xl p-4 bg-background shadow-sm">
    <div class="border-b pb-2">
        <h3 class="font-bold text-sm tracking-tight text-foreground">Pricing per Channel</h3>
    </div>
    
    <div class="space-y-2">
        <div v-for="price in form.prices" :key="price.channel" class="flex flex-col p-2.5 bg-muted/40 rounded-xl border border-border/60">
            <div class="flex items-center justify-between border-b border-border/40 pb-1.5 mb-1.5">
                <span class="font-bold text-xs capitalize text-foreground flex items-center gap-1">
                    <span class="w-1 h-2 rounded bg-primary/50"></span>
                    {{ price.channel }}
                </span>
                <div class="flex items-center gap-1">
                    <span class="text-[10px] text-muted-foreground">Margin:</span>
                    <!-- Input margin didisable agar otomatis terhitung dari harga jual -->
                    <Input 
                        :model-value="price.margin_percent" 
                        type="number" 
                        disabled 
                        class="w-16 h-6 text-center font-semibold text-xs p-0 bg-muted text-muted-foreground cursor-not-allowed opacity-100" 
                    />
                    <span class="text-[10px] font-semibold text-muted-foreground">%</span>
                </div>
            </div>
            
            <div class="flex items-center justify-between text-xs gap-2">
                <div class="flex-1">
                    <span class="text-[10px] text-muted-foreground block mb-0.5">Harga Jual (Manual)</span>
                    <div class="relative flex items-center">
                        <span class="absolute left-2 text-[10px] font-bold text-muted-foreground">Rp</span>
                        <Input 
                            v-model.number="price.selling_price"
                            @input="updateMarginFromPrice(price)"
                            type="number" 
                            step="1"
                            class="h-7 pl-6 pr-1 text-left text-xs font-bold text-foreground bg-background" 
                        />
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-muted-foreground block">Nett Profit</span>
                    <span class="font-bold text-green-600 dark:text-green-400">
                        +{{ currency(calculateCleanProfit(price)) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
                </div>

                <!-- BUTTON FOOTER -->
                <div class="flex justify-end gap-2 pt-4 border-t">
                    <Button variant="outline" type="button" @click="$emit('close')">Batal</Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Menyimpan...' : 'Simpan Menu' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>