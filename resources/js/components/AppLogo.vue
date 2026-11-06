<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const brandName = computed(() => {
    const brand = page.props.brand as { name?: string } | undefined;
    return brand?.name || (page.props.name as string | undefined) || 'BNEO';
});
</script>

<template>
    <div class="flex size-8 items-center justify-center overflow-hidden rounded-lg">
        <AppLogoIcon class="size-full" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ brandName }}</span>
    </div>
</template>
