<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img
        src="/logos/bneo-app-icon.png"
        alt="BNEO logo"
        :class="['object-contain', className]"
        v-bind="$attrs"
    />
</template>
