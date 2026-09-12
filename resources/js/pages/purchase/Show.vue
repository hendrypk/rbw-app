<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { useAccount } from '@/composables/useAccount';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DatePicker from '@/components/pos/DatePicker.vue';
import { useSwal } from '@/composables/useSwal';
import axios from 'axios';
import { ArrowLeft, Building2, Calendar, FileText, CreditCard } from '@lucide/vue';

defineOptions({ layout: AppSidebarLayout });

const page = usePage();
// Mengambil ID dari URL path (misal: /purchase-orders/{id})
const currentId = window.location.pathname.split('/').filter(Boolean).pop();

const { accounts, fetchAccounts } = useAccount();
const { success, error } = useSwal();

const isLoading = ref(true);
const processing = ref(false);
const showPaymentForm = ref(false);
const errors = ref<Record<string, any>>({});
const activeTab = ref<'items' | 'payments'>('items');

const currentPo = ref<any>(null);

const fetchPoDetail = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(`/api/purchase-orders/${currentId}`);
        currentPo.value = response.data;
    } catch (e) {
        console.error('Gagal memuat detail purchase order', e);
        error('Gagal', 'Tidak dapat memuat data purchase order.');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchPoDetail();
    fetchAccounts();
});

const getTodayLocal = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const form = ref({
    payment_account_id: '',
    amount: 0,
    payment_date: getTodayLocal(),
});

const cashBankAccounts = computed(() => {
    return accounts.value.filter((acc: any) => acc.category === '1' || acc.category === 1);
});

const remainingDebt = computed(() => {
    if (!currentPo.value) return 0;
    return Math.max(0, Number(currentPo.value.total_amount) - Number(currentPo.value.total_payment));
});

const getPaymentStatusBadge = (status: string) => {
    if (status === 'paid') return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold border border-emerald-500/20';
    if (status === 'partial') return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold border border-amber-500/20';
    return 'bg-rose-500/10 text-rose-600 dark:text-rose-400 font-bold border border-rose-500/20';
};

const getStatusBadge = (status: string) => {
    return status === 'received' 
        ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 font-bold border border-sky-500/20' 
        : 'bg-secondary text-muted-foreground font-semibold border border-border/60';
};

const submitPayment = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const response = await axios.post(`/api/purchase-orders/${currentPo.value.id}/pay-order`, form.value);
        currentPo.value = response.data.data;
        success('Berhasil', response.data.message || 'Pembayaran berhasil dicatat.');
        showPaymentForm.value = false;
        activeTab.value = 'payments';
    } catch (e: any) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors;
            error('Validasi Gagal', 'Mohon periksa kembali form input Anda.');
        } else {
            error('Gagal', e.response?.data?.message || 'Terjadi kesalahan.');
        }
    } finally {
        processing.value = false;
    }
};

const goBack = () => {
    router.visit('/purchase');
};
</script>

<template>
    <Head :title="`Detail PO: ${currentPo?.po_number || 'Loading...'}`" />

    <div class="p-6 sm:p-10 space-y-8 max-w-6xl mx-auto overflow-x-hidden font-sans">
        
        <div v-if="isLoading" class="flex flex-col items-center justify-center py-32 text-center text-muted-foreground">
            <p class="text-sm font-semibold">Memuat detail purchase order...</p>
        </div>

        <template v-else>
            <!-- Header -->
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between pb-6 border-b border-border/60">
                <div class="flex items-start gap-4">
                    <button 
                        @click="goBack"
                        class="mt-1 size-10 rounded-2xl bg-secondary/80 hover:bg-secondary flex items-center justify-center text-foreground transition-colors cursor-pointer border border-border/60 shadow-xs"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </button>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-3 flex-wrap">
                            <h1 class="text-2xl font-black tracking-tight text-foreground">{{ currentPo?.po_number }}</h1>
                            <span :class="['px-3 py-1 rounded-full text-[11px] tracking-wide uppercase', getStatusBadge(currentPo?.status)]">
                                Logistik: {{ currentPo?.status }}
                            </span>
                            <span :class="['px-3 py-1 rounded-full text-[11px] tracking-wide uppercase', getPaymentStatusBadge(currentPo?.payment_status)]">
                                Keuangan: {{ currentPo?.payment_status }}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground flex items-center gap-1.5 font-medium">
                            <Calendar class="w-3.5 h-3.5 opacity-70" /> Tanggal Order: {{ currentPo?.order_date }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <Button 
                        v-if="currentPo?.status === 'received' && remainingDebt > 0 && !showPaymentForm" 
                        size="sm"
                        @click="showPaymentForm = true"
                        class="h-10 px-5 rounded-2xl text-xs font-bold shadow-sm bg-primary text-primary-foreground hover:opacity-95 transition-all cursor-pointer"
                    >
                        + Bayar Utang
                    </Button>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Bento Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div class="bg-card p-6 rounded-3xl border border-border/70 shadow-xs space-y-2 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-[11px] font-bold uppercase tracking-wider">Supplier</span>
                            <Building2 class="w-4 h-4 opacity-70" />
                        </div>
                        <div>
                            <p class="font-bold text-sm text-foreground">{{ currentPo?.supplier?.name }}</p>
                            <p class="text-muted-foreground text-xs mt-0.5 line-clamp-1">{{ currentPo?.supplier?.address || 'Tidak ada alamat' }}</p>
                        </div>
                    </div>

                    <div class="bg-card p-6 rounded-3xl border border-border/70 shadow-xs space-y-2 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-[11px] font-bold uppercase tracking-wider">Total Tagihan Bruto</span>
                            <FileText class="w-4 h-4 opacity-70" />
                        </div>
                        <div>
                            <span class="text-xl font-black text-foreground font-mono">Rp {{ Number(currentPo?.total_amount || 0).toLocaleString('id-ID') }}</span>
                            <p class="text-[11px] text-muted-foreground mt-0.5">Seluruh item pesanan</p>
                        </div>
                    </div>

                    <div class="bg-card p-6 rounded-3xl border border-border/70 shadow-xs space-y-2 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-[11px] font-bold uppercase tracking-wider">Status Keuangan</span>
                            <CreditCard class="w-4 h-4 opacity-70" />
                        </div>
                        <div class="space-y-0.5 font-medium">
                            <p class="text-muted-foreground">Terbayar: <span class="text-foreground font-mono font-bold">Rp {{ Number(currentPo?.total_payment || 0).toLocaleString('id-ID') }}</span></p>
                            <p v-if="remainingDebt > 0" class="text-rose-600 dark:text-rose-400 font-bold">Sisa Utang: <span class="font-mono">Rp {{ remainingDebt.toLocaleString('id-ID') }}</span></p>
                            <p v-else class="text-emerald-600 dark:text-emerald-400 font-bold font-mono">Lunas Bebas Utang</p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex items-center gap-1.5 p-1.5 bg-secondary/60 rounded-2xl w-fit text-xs font-semibold border border-border/60">
                    <button 
                        type="button" 
                        @click="activeTab = 'items'" 
                        :class="['px-5 py-2.5 rounded-xl transition-all cursor-pointer font-bold', activeTab === 'items' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground']"
                    >
                        Daftar Material & Catatan
                    </button>
                    <button 
                        type="button" 
                        @click="activeTab = 'payments'" 
                        :class="['px-5 py-2.5 rounded-xl transition-all flex items-center gap-2 cursor-pointer font-bold', activeTab === 'payments' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground']"
                    >
                        Riwayat Pembayaran Jurnal
                        <span class="px-2 py-0.5 rounded-full bg-secondary text-[10px] font-mono font-black">
                            {{ currentPo?.journal_entries?.length || 0 }}
                        </span>
                    </button>
                </div>

                <!-- Tab 1 -->
                <div v-show="activeTab === 'items'" class="space-y-6">
                    <div class="overflow-x-auto rounded-3xl border border-border/70 bg-card shadow-xs">
                        <table class="w-full text-left border-collapse text-xs min-w-[750px]">
                            <thead class="bg-secondary/60 text-muted-foreground font-bold border-b border-border/70 text-xs">
                                <tr>
                                    <th class="px-6 py-4">Nama Material / Bahan Baku</th>
                                    <th class="px-6 py-4 text-right w-32">Qty</th>
                                    <th class="px-6 py-4 text-right w-40">Harga Satuan</th>
                                    <th class="px-6 py-4 text-right w-40">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="item in currentPo?.items" :key="item.id" class="hover:bg-secondary/40 transition-colors">
                                    <td class="px-6 py-4 font-bold text-foreground">{{ item.raw_material?.name }}</td>
                                    <td class="px-6 py-4 text-right font-mono text-muted-foreground font-medium">{{ Number(item.qty).toLocaleString('id-ID') }}</td>
                                    <td class="px-6 py-4 text-right font-mono text-muted-foreground">Rp {{ Number(item.unit_price).toLocaleString('id-ID') }}</td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-foreground">Rp {{ Number(item.subtotal).toLocaleString('id-ID') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-card p-6 rounded-3xl border border-border/70 shadow-xs text-xs">
                        <span class="font-bold text-muted-foreground uppercase tracking-wider block mb-1.5">Catatan Internal</span>
                        <p class="text-foreground font-medium italic leading-relaxed">{{ currentPo?.notes || 'Tidak ada catatan tambahan untuk pesanan ini.' }}</p>
                    </div>
                </div>

                <!-- Tab 2 -->
                <div v-show="activeTab === 'payments'">
                    <div v-if="currentPo?.journal_entries?.length > 0" class="overflow-x-auto rounded-3xl border border-border/70 bg-card shadow-xs">
                        <table class="w-full text-left border-collapse text-xs min-w-[750px]">
                            <thead class="bg-secondary/60 text-muted-foreground font-bold border-b border-border/70 text-xs">
                                <tr>
                                    <th class="px-6 py-4">Tanggal</th>
                                    <th class="px-6 py-4">Keterangan Jurnal</th>
                                    <th class="px-6 py-4 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="entry in currentPo.journal_entries" :key="entry.id" class="hover:bg-secondary/40 transition-colors">
                                    <td class="px-6 py-4 font-mono text-muted-foreground font-medium">{{ entry.entry_date }}</td>
                                    <td class="px-6 py-4 text-foreground font-semibold">{{ entry.description }}</td>
                                    <td class="px-6 py-4 text-right font-mono font-bold text-foreground">Rp {{ Number(entry.total_amount).toLocaleString('id-ID') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-24 text-center border border-dashed rounded-3xl bg-card border-border/80 shadow-xs">
                        <div class="size-12 rounded-2xl bg-secondary/80 flex items-center justify-center text-muted-foreground mb-3">
                            <CreditCard class="w-6 h-6" />
                        </div>
                        <h3 class="size-sm font-bold text-foreground">Belum ada riwayat pembayaran</h3>
                        <p class="mt-1 text-xs text-muted-foreground max-w-sm">Belum ada transaksi jurnal pembayaran yang tercatat untuk purchase order ini.</p>
                    </div>
                </div>

                <!-- Form Bayar Utang -->
                <div v-if="showPaymentForm" class="p-8 rounded-3xl bg-card border border-border/80 shadow-sm space-y-6">
                    <div class="flex items-center justify-between border-b border-border/60 pb-4">
                        <h4 class="text-xs font-black text-foreground tracking-wider uppercase">Form Pengeluaran Kas (Bayar Utang)</h4>
                        <button type="button" @click="showPaymentForm = false" class="text-xs text-muted-foreground hover:text-foreground font-bold cursor-pointer">Batal</button>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 text-xs">
                        <div class="space-y-2">
                            <Label class="font-bold text-muted-foreground">Tanggal Bayar</Label>
                            <DatePicker v-model="form.payment_date" />
                            <InputError :message="errors.payment_date?.[0]" />
                        </div>
                        <div class="space-y-2">
                            <Label class="font-bold text-muted-foreground">Sumber Uang Keluar (Kredit)</Label>
                            <Select v-model="form.payment_account_id">
                                <SelectTrigger class="w-full h-10 rounded-2xl border-border bg-card px-4 text-xs font-semibold">
                                    <SelectValue placeholder="Pilih Kas / Bank" />
                                </SelectTrigger>
                                <SelectContent class="rounded-2xl">
                                    <SelectItem v-for="acc in cashBankAccounts" :key="acc.id" :value="acc.id" class="text-xs font-medium">
                                        [{{ acc.code }}] {{ acc.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.payment_account_id?.[0]" />
                        </div>
                        <div class="space-y-2">
                            <Label class="font-bold text-muted-foreground">Nominal Pembayaran (Rp)</Label>
                            <Input type="number" v-model="form.amount" class="h-10 rounded-2xl border-border bg-card px-4 font-mono font-bold text-xs" :max="remainingDebt" />
                            <InputError :message="errors.amount?.[0]" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-border/60">
                        <Button type="button" size="sm" variant="outline" @click="showPaymentForm = false" class="h-10 px-5 rounded-2xl text-xs font-semibold cursor-pointer">Batal</Button>
                        <Button type="button" size="sm" :disabled="processing" @click="submitPayment" class="h-10 px-6 rounded-2xl text-xs font-bold shadow-sm bg-primary text-primary-foreground hover:opacity-95 cursor-pointer">
                            {{ processing ? 'Memproses...' : 'Konfirmasi Pembayaran Jurnal' }}
                        </Button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>