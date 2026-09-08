export type DateRangePreset = 
    | 'today' | 'yesterday' | 'this_week' | 'last_week' | 'this_month' 
    | 'last_month' | 'last_3_months' | 'last_6_months' | 'this_year' | 'last_year';

export function useDateFilter() {
    const formatDate = (date: Date) => {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    };

    const getRange = (preset: DateRangePreset): { start: string; end: string } => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let start = new Date(today);
        let end = new Date(today);

        switch (preset) {
            case 'today':
                break;
            case 'yesterday':
                start.setDate(start.getDate() - 1);
                end.setDate(end.getDate() - 1);
                break;
            case 'this_week':
                const day = start.getDay() || 7;
                start.setDate(start.getDate() - day + 1);
                break;
            case 'last_week':
                const lastWeekDay = start.getDay() || 7;
                start.setDate(start.getDate() - lastWeekDay - 6);
                end = new Date(start);
                end.setDate(end.getDate() + 6);
                break;
            case 'this_month':
                start.setDate(1);
                end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
                break;
            case 'last_month':
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'last_3_months':
                start = new Date(today.getFullYear(), today.getMonth() - 3, today.getDate());
                break;
            case 'last_6_months':
                start = new Date(today.getFullYear(), today.getMonth() - 6, today.getDate());
                break;
            case 'this_year':
                start = new Date(today.getFullYear(), 0, 1);
                end = new Date(today.getFullYear(), 11, 31);
                break;
            case 'last_year':
                start = new Date(today.getFullYear() - 1, 0, 1);
                end = new Date(today.getFullYear() - 1, 11, 31);
                break;
        }

        return {
            start: formatDate(start),
            end: formatDate(end)
        };
    };

    return { getRange };
}