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

    return { success, error };
}