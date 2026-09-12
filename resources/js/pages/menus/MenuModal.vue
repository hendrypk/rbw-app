<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useCategories } from '@/composables/useCategories';
import { useMaterials } from '@/composables/useMaterials';
import Select from '@/components/ui/select/Select.vue';
import SelectTrigger from '@/components/ui/select/SelectTrigger.vue';
import SelectValue from '@/components/ui/select/SelectValue.vue';
import SelectContent from '@/components/ui/select/SelectContent.vue';
import SelectItem from '@/components/ui/select/SelectItem.vue';

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
    category_ids: [] as string[],
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
        const cost = material ? Number(material.avg_cost) : 0;
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

const calculateCleanProfit = (priceObj: PriceChannel) => {
    const baseCost = totalBaseCost.value;
    const feePercent = PLATFORM_FEES[priceObj.channel] ?? 0;
    const sellingPrice = Number(priceObj.selling_price) || 0;

    if (!sellingPrice || !baseCost) return 0;

    const nettPrice = sellingPrice * (1 - feePercent / 100);
    const cleanProfit = nettPrice - baseCost;

    return Number(cleanProfit.toFixed(2));
};

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

        const activeOutletId = localStorage.getItem('active_outlet_id');
        const outletParam = activeOutletId && activeOutletId !== 'all' ? activeOutletId : null;

        await Promise.all([
            fetchCategories(outletParam ? { outlet_id: outletParam } : {}),
            fetchMaterialOptions()
        ]);

        if (props.menu) {
            let mappedCategoryIds: string[] = [];
            if (props.menu.categories && Array.isArray(props.menu.categories)) {
                mappedCategoryIds = props.menu.categories.map((c: any) => c.id);
            } else if (props.menu.category_id) {
                mappedCategoryIds = [props.menu.category_id];
            }

            form.value = {
                name: props.menu.name,
                category_ids: mappedCategoryIds,
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
                category_ids: [],
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
        const activeOutletId = localStorage.getItem('active_outlet_id');
        const url = isEdit.value ? `/api/menus/${props.menu.id}` : '/api/menus';
        const method = isEdit.value ? 'put' : 'post';
        
        await axios[method](url, {
            ...form.value,
            outlet_id: activeOutletId && activeOutletId !== 'all' ? activeOutletId : null
        });

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
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/40 backdrop-blur-md">
        <div class="w-full max-w-5xl bg-white dark:bg-zinc-950 rounded-3xl shadow-2xl border border-slate-200/60 dark:border-zinc-800 flex flex-col max-h-[90vh] overflow-hidden text-slate-900 dark:text-zinc-50 animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Header Minimalis ala Apple -->
            <div class="px-7 py-5 border-b border-slate-100 dark:border-zinc-900/80 flex items-center justify-between shrink-0 bg-white/80 dark:bg-zinc-950/80 backdrop-blur-xl">
                <div>
                    <h2 class="text-base font-semibold tracking-tight">{{ menu ? 'Edit Menu Produksi' : 'Tambah Menu Baru' }}</h2>
                    <p class="text-[11px] text-slate-400 font-medium mt-0.5">Konfigurasi resep, kalkulasi HPP, dan struktur harga jual multi-channel.</p>
                </div>
                <button type="button" @click="$emit('close')" class="size-7 rounded-full bg-slate-100 dark:bg-zinc-900 hover:bg-slate-200 dark:hover:bg-zinc-800 flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 transition-all cursor-pointer">
                    ✕
                </button>
            </div>
            
            <form @submit.prevent="submit" class="flex flex-col flex-1 overflow-hidden">
                <div class="p-6 sm:p-8 overflow-y-auto space-y-6 flex-1 custom-scrollbar">
                    
                    <!-- BAGIAN 1: INFORMASI UTAMA -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                        <div class="md:col-span-5 space-y-2">
                            <Label class="text-xs font-semibold text-slate-500 tracking-wide">Nama Menu</Label>
                            <Input v-model="form.name" placeholder="Contoh: Ayam Bakar Madu" required class="h-11 rounded-2xl border-slate-200 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-900/50 px-4 text-xs font-semibold shadow-2xs focus:ring-2 focus:ring-slate-900 dark:focus:ring-zinc-100" />
                            <InputError :message="errors.name" />
                        </div>
                        
                        <div class="md:col-span-7 space-y-2">
                            <Label class="text-xs font-semibold text-slate-500 tracking-wide">Kategori Menu (Multi-select)</Label>
                            <div class="flex flex-wrap gap-1.5 p-2.5 border border-slate-200 dark:border-zinc-800 rounded-2xl bg-slate-50/60 dark:bg-zinc-900/50 min-h-[44px] max-h-32 overflow-y-auto shadow-2xs">
                                <label 
                                    v-for="cat in userCategories" 
                                    :key="cat.id" 
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-zinc-900 hover:bg-slate-100 dark:hover:bg-zinc-800 text-xs cursor-pointer border border-slate-200/80 dark:border-zinc-800 select-none transition-all shadow-2xs"
                                >
                                    <input 
                                        type="checkbox" 
                                        :value="cat.id" 
                                        v-model="form.category_ids"
                                        class="rounded border-slate-300 dark:border-zinc-700 text-slate-900 dark:text-zinc-100 focus:ring-0 size-3.5 cursor-pointer"
                                    />
                                    <span class="font-semibold text-slate-700 dark:text-zinc-200">{{ cat.name }}</span>
                                </label>
                                <span v-if="userCategories.length === 0" class="text-xs text-slate-400 italic py-1 px-2">Tidak ada kategori aktif.</span>
                            </div>
                            <InputError :message="errors.category_ids" />
                        </div>
                    </div>

                    <div class="h-px bg-slate-100 dark:bg-zinc-900 w-full my-2"></div>

                    <!-- BAGIAN 2: KONTEN UTAMA (RESEP & PRICING DALAM 2 KOLOM BERSIH) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        
                        <!-- KOLOM KIRI: KOMPOSISI RESEP & BIAYA DASAR -->
                        <div class="lg:col-span-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Komposisi Resep & Bahan</h3>
                                <span class="text-[11px] font-semibold text-slate-400 font-mono">Total HPP: {{ currency(totalHpp) }}</span>
                            </div>
                    
                            <div class="space-y-2 max-h-[300px] overflow-y-auto pr-1 custom-scrollbar">
                                <div v-for="(rec, index) in form.recipes" :key="index" class="flex items-center gap-2 bg-slate-50/60 dark:bg-zinc-900/40 p-2.5 rounded-2xl border border-slate-200/80 dark:border-zinc-800/80 shadow-2xs">
                                    <Select v-model="rec.raw_material_id">
                                        <SelectTrigger class="h-9 rounded-xl border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900 px-3 text-xs font-bold hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors w-[180px] shadow-sm">
                                            <div class="flex items-center gap-2 truncate text-slate-700 dark:text-zinc-200">
                                                <SelectValue placeholder="Pilih Bahan" class="truncate" />
                                            </div>
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem 
                                            v-for="mat in materialOptions" 
                                            :key="mat.id" 
                                            :value="mat.id"
                                            :disabled="form.recipes.some((r: any) => r.raw_material_id === mat.id && r !== rec)"
                                            >
                                                {{ mat.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    
                                    <Input v-model="rec.qty_usage" type="number" step="0.0001" class="w-20 h-9 text-xs text-center rounded-xl  bg-white dark:bg-zinc-900 font-mono font-bold shadow-2xs" placeholder="Qty" />
                                    
                                    <div class="w-24 text-right text-xs font-bold font-mono text-slate-800 dark:text-zinc-200">
                                        {{ currency((Number(materialMap[rec.raw_material_id]?.avg_cost) || 0) * Number(rec.qty_usage)) }}
                                    </div>
                                    
                                    <button type="button" class="size-7 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center justify-center font-bold text-sm transition-all cursor-pointer" @click="removeRecipe(index)">×</button>
                                </div>
                            </div>

                            <Button type="button" variant="outline" size="sm" class="w-full h-10 text-xs font-semibold rounded-2xl border-dashed border-slate-300 dark:border-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-900 transition-all cursor-pointer shadow-2xs" @click="addRecipe">
                                + Tambah Bahan Baku
                            </Button>

                            <!-- RINGKASAN BIAYA MODAL -->
                            <div class="p-4 bg-slate-50 dark:bg-zinc-900/60 rounded-2xl border border-slate-200/80 dark:border-zinc-800 space-y-3 shadow-2xs">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-slate-500 font-medium">Overhead Cost Proporsional:</span>
                                    <div class="flex items-center gap-2">
                                        <button 
                                            type="button" 
                                            @click="toggleOverhead"
                                            class="text-[10px] font-bold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer"
                                        >
                                            {{ form.overhead_cost > 0 ? '✕ Hapus' : '✓ Pakai Master' }}
                                        </button>
                                        <div class="relative flex items-center w-28">
                                            <span class="absolute left-2 text-[10px] font-bold text-slate-400 font-mono">Rp</span>
                                            <Input v-model.number="form.overhead_cost" type="number" min="0" class="h-8 pl-6 pr-2 text-right text-xs font-bold text-slate-900 dark:text-zinc-100 bg-white dark:bg-zinc-900 rounded-xl border-slate-200 dark:border-zinc-800 font-mono shadow-2xs" />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center pt-2 border-t border-slate-200/60 dark:border-zinc-800">
                                    <span class="font-bold text-xs uppercase tracking-wider text-slate-900 dark:text-zinc-100">Total Modal Dasar:</span>
                                    <span class="font-extrabold text-sm text-slate-900 dark:text-zinc-100 font-mono">{{ currency(totalBaseCost) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- KOLOM KANAN: PRICING & CHANNEL -->
                        <div class="lg:col-span-6 space-y-4">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Harga Jual & Margin (Multi-Channel)</h3>
                            
                            <div class="space-y-2.5 max-h-[350px] overflow-y-auto pr-1 custom-scrollbar">
                                <div v-for="price in form.prices" :key="price.channel" class="flex items-center justify-between p-3 bg-slate-50/60 dark:bg-zinc-900/40 rounded-2xl border border-slate-200/80 dark:border-zinc-800/80 shadow-2xs gap-3">
                                    <div class="w-28 shrink-0">
                                        <span class="font-bold text-xs capitalize text-slate-800 dark:text-zinc-200 block">{{ price.channel }}</span>
                                        <span class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 font-mono">+{{ currency(calculateCleanProfit(price)) }}</span>
                                    </div>

                                    <div class="flex items-center gap-2 flex-1 justify-end">
                                        <div class="relative flex items-center w-36">
                                            <span class="absolute left-2.5 text-[10px] font-bold text-slate-400 font-mono">Rp</span>
                                            <Input 
                                                v-model.number="price.selling_price"
                                                @input="updateMarginFromPrice(price)"
                                                type="number" 
                                                step="1"
                                                class="h-9 pl-7 pr-2 text-left text-xs font-bold text-slate-900 dark:text-zinc-100 bg-white dark:bg-zinc-900 rounded-xl border-slate-200 dark:border-zinc-800 font-mono shadow-2xs" 
                                                placeholder="Harga Jual"
                                            />
                                        </div>

                                        <div class="flex items-center gap-1 bg-white dark:bg-zinc-900 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-zinc-800 shadow-2xs shrink-0 w-20 justify-center">
                                            <span class="text-[11px] font-bold text-slate-700 dark:text-zinc-300 font-mono">{{ price.margin_percent }}</span>
                                            <span class="text-[10px] text-slate-400 font-bold">%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                <!-- Footer Minimalis -->
                <div class="px-7 py-4 border-t border-slate-100 dark:border-zinc-900 flex justify-end gap-2.5 shrink-0 bg-white/80 dark:bg-zinc-950/80 backdrop-blur-xl">
                    <Button variant="outline" type="button" @click="$emit('close')" class="h-10 px-5 rounded-2xl text-xs font-bold border-slate-200 dark:border-zinc-800 cursor-pointer shadow-2xs">Batal</Button>
                    <Button type="submit" :disabled="processing" class="h-10 px-6 rounded-2xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white dark:bg-zinc-100 dark:hover:bg-zinc-200 dark:text-zinc-900 shadow-sm cursor-pointer transition-all">
                        {{ processing ? 'Menyimpan...' : 'Simpan Menu' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(150, 150, 150, 0.2);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(150, 150, 150, 0.4);
}
</style>