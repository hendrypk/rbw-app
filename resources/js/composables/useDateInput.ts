import { ref, computed } from 'vue';

export function useDateInput(initialDate?: string | Date | null) {
    const formatDateToLocal = (dateInput?: string | Date | null): string => {
        if (!dateInput) return new Date().toLocaleDateString('en-CA');
        
        // Jika input sudah berupa string (misal: "2026-09-10"), ambil bagian depannya langsung
        if (typeof dateInput === 'string') {
            return dateInput.split('T')[0];
        }

        // Jika berupa objek Date, baru konversi lokal secara aman
        const d = new Date(dateInput);
        if (isNaN(d.getTime())) return String(dateInput).split('T')[0];
        
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const dateValue = ref<string>(formatDateToLocal(initialDate));

    const setDate = (val: string | Date | null) => {
        dateValue.value = formatDateToLocal(val);
    };

    const payloadDate = computed(() => dateValue.value);

    return {
        dateValue,
        setDate,
        payloadDate,
        formatDateToLocal
    };
}