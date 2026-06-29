import { ref } from 'vue';
import axios from 'axios';

export interface Account {
    id: string;
    category: string;
    account_number: string;
    code: string;
    name: string;
    normal_balance: 'debit' | 'credit';
    is_active: boolean;
}

interface AccountFilters {
    search?: string;
    category?: string;
}

export function useAccount() {
    const accounts = ref<Account[]>([]);
    const loading = ref(false);

    const fetchAccounts = async (filters: AccountFilters = {}) => {
        loading.value = true;

        try {
            const { data } = await axios.get('/api/finance/accounts', {
                params: filters,
            });

            accounts.value = data.data ?? data;
        } finally {
            loading.value = false;
        }
    };

    return {
        accounts,
        loading,
        fetchAccounts,
    };
}