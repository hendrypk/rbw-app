<script setup lang="ts">
import { ref, watch } from 'vue';
import Button from '@/components/ui/button/Button.vue';
import Input from '@/components/ui/input/Input.vue';

interface Mapping {
    id: string;
    transaction_event: string;
    debit_code: string;
    debit_name: string;
    credit_code: string;
    credit_name: string;
    template: string;
}

const props = defineProps<{
    show: boolean;
    mapping: Mapping | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'save', value: Mapping): void;
}>();

const form = ref<Mapping>({
    id: '',
    transaction_event: '',
    debit_code: '',
    debit_name: '',
    credit_code: '',
    credit_name: '',
    template: '',
});

watch(
    () => props.mapping,
    (value) => {
        if (value) {
            form.value = { ...value };
        }
    },
    { immediate: true }
);

const submit = () => {
    emit('save', form.value);
    emit('close');
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
        <div class="w-full max-w-3xl rounded-xl bg-background shadow-xl">

            <div class="flex items-center justify-between border-b p-5">
                <h2 class="text-lg font-semibold">
                    Edit Account Mapping
                </h2>

                <button
                    @click="emit('close')"
                    class="text-xl"
                >
                    ×
                </button>
            </div>

            <form
                @submit.prevent="submit"
                class="space-y-5 p-6"
            >

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Transaction Event
                    </label>

                    <Input
                        v-model="form.transaction_event"
                        disabled
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Debit Account Code
                        </label>

                        <Input
                            v-model="form.debit_code"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Debit Account Name
                        </label>

                        <Input
                            v-model="form.debit_name"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Credit Account Code
                        </label>

                        <Input
                            v-model="form.credit_code"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            Credit Account Name
                        </label>

                        <Input
                            v-model="form.credit_name"
                        />
                    </div>

                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Journal Description Template
                    </label>

                    <textarea
                        v-model="form.template"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>

                <div class="flex justify-end gap-2 border-t pt-4">

                    <Button
                        variant="outline"
                        type="button"
                        @click="emit('close')"
                    >
                        Cancel
                    </Button>

                    <Button type="submit">
                        Save Changes
                    </Button>

                </div>

            </form>

        </div>
    </div>
</template>