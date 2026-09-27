import Swal from 'sweetalert2';

export function useSwal() {
    const success = (title: string, text?: string): void => {
        const isDark: boolean = document.documentElement.classList.contains('dark');

        Swal.fire({
            title: title,
            text: text,
            icon: 'success',
            toast: true,
            position: 'top',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true,
            background: isDark ? '#18181b' : '#ffffff',
            color: isDark ? '#f4f4f5' : '#0f172a',
            customClass: {
                popup: 'rounded-2xl border border-slate-100 dark:border-zinc-800 shadow-lg p-3 text-xs !items-center',
                title: 'text-xs font-bold text-slate-900 dark:text-zinc-100 m-0',
                htmlContainer: 'text-[11px] text-slate-500 dark:text-zinc-400 m-0 mt-0.5',
                timerProgressBar: 'bg-emerald-500 rounded-full h-0.5'
            }
        });
    };

    const error = (title: string = 'Terjadi kesalahan', text?: string): void => {
        const isDark: boolean = document.documentElement.classList.contains('dark');

        Swal.fire({
            title: title,
            text: text,
            icon: 'error',
            toast: true,
            position: 'top',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: isDark ? '#18181b' : '#ffffff',
            color: isDark ? '#f4f4f5' : '#0f172a',
            customClass: {
                popup: 'rounded-2xl border border-slate-100 dark:border-zinc-800 shadow-lg p-3 text-xs !items-center',
                title: 'text-xs font-bold text-slate-900 dark:text-zinc-100 m-0',
                htmlContainer: 'text-[11px] text-slate-500 dark:text-zinc-400 m-0 mt-0.5',
                timerProgressBar: 'bg-red-500 rounded-full h-0.5'
            }
        });
    };

    const confirm = async (
        title: string,
        text?: string,
        confirmText: string = 'Ya',
        cancelText: string = 'Batal'
    ): Promise<boolean> => {
        const isDark: boolean = document.documentElement.classList.contains('dark');

        const result = await Swal.fire({
            title: title,
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            reverseButtons: true, // Tombol aksi utama di kanan
            background: isDark ? '#18181b' : '#ffffff',
            color: isDark ? '#f4f4f5' : '#0f172a',
            buttonsStyling: false, // Matikan styling default Swal agar Tailwind berfungsi
            customClass: {
                popup: 'rounded-2xl border border-slate-100 dark:border-zinc-800 shadow-lg p-6',
                title: 'text-lg font-bold text-slate-900 dark:text-zinc-100',
                htmlContainer: 'text-sm text-slate-500 dark:text-zinc-400 mt-2',
                actions: 'mt-6 w-full flex justify-end gap-2',
                confirmButton: 'bg-black dark:bg-white text-white dark:text-black hover:opacity-90 px-4 py-2 rounded-md font-medium text-sm',
                cancelButton: 'bg-neutral-100 dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 hover:bg-neutral-200 dark:hover:bg-neutral-700 px-4 py-2 rounded-md font-medium text-sm'
            }
        });

        return result.isConfirmed;
    };

    return { success, error, confirm };
}
