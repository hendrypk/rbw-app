<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';

import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

const opened = ref<string[]>([]);

const toggle = (title: string) => {
    if (opened.value.includes(title)) {
        opened.value = opened.value.filter(i => i !== title);
    } else {
        opened.value.push(title);
    }
};

const isOpen = (title: string) => opened.value.includes(title);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>

        <SidebarMenu>

            <SidebarMenuItem
                v-for="item in items"
                :key="item.title"
            >

                <!-- MENU BIASA -->
                <template v-if="!item.children">

                    <SidebarMenuButton
                        as-child
                        :tooltip="item.title"
                        :is-active="item.href ? isCurrentUrl(item.href) : false"
                    >
                        <Link :href="item.href!">
                            <component
                                v-if="item.icon"
                                :is="item.icon"
                            />

                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>

                </template>

                <!-- MENU PARENT -->
                <template v-else>

                    <SidebarMenuButton
                        @click="toggle(item.title)"
                        class="cursor-pointer justify-between"
                    >
                        <div class="flex items-center gap-2">

                            <component
                                v-if="item.icon"
                                :is="item.icon"
                                class="h-4 w-4 shrink-0"
                            />

                            <span>{{ item.title }}</span>

                        </div>

                        <ChevronDown
                            class="h-4 w-4 transition-transform"
                            :class="{
                                'rotate-180': isOpen(item.title)
                            }"
                        />
                    </SidebarMenuButton>

                    <div
                        v-show="isOpen(item.title)"
                        class="ml-6 mt-1 space-y-1"
                    >

                        <SidebarMenuButton
                            v-for="child in item.children"
                            :key="child.title"
                            as-child
                            :is-active="child.href ? isCurrentUrl(child.href) : false"
                        >
                            <Link :href="child.href!">

                                <component
                                    v-if="child.icon"
                                    :is="child.icon"
                                />

                                <span>{{ child.title }}</span>

                            </Link>

                        </SidebarMenuButton>

                    </div>

                </template>

            </SidebarMenuItem>

        </SidebarMenu>

    </SidebarGroup>
</template>