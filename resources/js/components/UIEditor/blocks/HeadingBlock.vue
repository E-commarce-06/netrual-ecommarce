<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ settings: Record<string, unknown>; editorMode?: boolean }>()

const usesDefaultBrandColor = computed(() =>
  ['#041735', '#0f172a', '#111827'].includes(String(props.settings.color ?? '').toLowerCase()),
)
</script>

<template>
  <component
    :is="(settings.level as string) || 'h2'"
    class="uie-block-heading font-bold leading-tight"
    :data-bneo-heading="usesDefaultBrandColor ? 'true' : undefined"
    :class="{
      'text-left':   settings.align === 'left',
      'text-center': settings.align === 'center',
      'text-right':  settings.align === 'right',
      'text-4xl': settings.level === 'h1',
      'text-3xl': settings.level === 'h2',
      'text-2xl': settings.level === 'h3',
      'text-xl':  settings.level === 'h4',
      'text-lg':  settings.level === 'h5',
      'text-base':settings.level === 'h6',
    }"
    :style="settings.color ? { color: settings.color as string } : {}"
  >
    {{ (settings.text as string) || (editorMode ? 'Your Heading' : '') }}
  </component>
</template>
