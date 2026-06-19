import Swal, { SweetAlertIcon } from 'sweetalert2';

export function useSwal() {
    const confirm = async (text: string, title = 'Anda yakin?') => {
        const result = await Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            reverseButtons: true,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal',
            
            customClass: {
                confirmButton: 'bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 px-4 py-2 rounded-md font-medium mx-2',
                cancelButton: 'bg-secondary text-secondary-foreground hover:bg-secondary/80 px-4 py-2 rounded-md font-medium mx-2'
            },
            
            buttonsStyling: false 
        });
        return result.isConfirmed;
    };
    const success = (title: string, text?: string) => {
        Swal.fire({
            title: title,
            text: text,
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            timerProgressBar: true
        });
    };

    // Fungsi untuk notifikasi error
    const error = (title = 'Terjadi kesalahan', text?: string) => {
        Swal.fire({
            title: title,
            text: text,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    };

    return { confirm, success, error };
}