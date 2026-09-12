import { ref } from 'vue';
import axios from 'axios';
import { useOutlet } from './useOutlet';

export interface Account {
    id: string;
    category: string;
    account_number: string;
    code: string;
    name: string;
    normal_balance: 'debit' | 'credit';
    balance?: number | string;
    opening_balance?: number | string;
    is_active: boolean;
}

interface AccountFilters {
    search?: string;
    category?: string;
    [key: string]: any;
}

export function useAccount(currentOutletId?: any) {
    const accounts = ref<Account[]>([]);
    const loading = ref(false);
    const { getOutletParam } = useOutlet(currentOutletId);

    const fetchAccounts = async (filters: AccountFilters = {}) => {
        loading.value = true;
        try {
            const outletParam = getOutletParam();
            const queryParams = { ...filters, ...outletParam };
            const { data } = await axios.get('/api/finance/accounts', {
                params: queryParams,
            });

            accounts.value = data.data ?? data;
        } finally {
            loading.value = false;
        }
    };

    const updateOpeningBalances = async (effectiveDate: string, balances: Array<{ id: string; opening_balance: number }>) => {
        const outletParam = getOutletParam();
        const payload = {
            effective_date: effectiveDate,
            balances,
            ...outletParam,
        };
        const response = await axios.post('/api/finance/accounts/opening-balances', payload);
        await fetchAccounts();
        return response.data;
    };

    return {
        accounts,
        loading,
        fetchAccounts,
        updateOpeningBalances,
    };
}