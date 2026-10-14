<script setup lang="ts">
defineProps<{
    href?: string;
    label?: string;
}>();
</script>

<template>
    <a
        :href="href || '/auth/google/redirect'"
        :aria-label="label || 'Continue with Google'"
        class="flex w-full items-center justify-center gap-3 rounded-md border border-[#dadce0] bg-white px-4 py-3 text-sm font-medium text-[#3c4043] shadow-sm transition hover:bg-[#f8faff] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
    >
        <svg aria-hidden="true" class="h-5 w-5 shrink-0" viewBox="0 0 48 48">
            <path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.7-.4-4H24v7.5h11a9.4 9.4 0 0 1-4.1 6.2v5h6.6c3.9-3.6 6.1-8.7 6.1-14.7Z" />
            <path fill="#34A853" d="M24 44c5.5 0 10.1-1.8 13.5-4.8l-6.6-5c-1.8 1.2-4.1 2-6.9 2-5.3 0-9.8-3.6-11.4-8.4H5.8v5.2A20 20 0 0 0 24 44Z" />
            <path fill="#FBBC05" d="M12.6 27.8a12 12 0 0 1 0-7.6V15H5.8a20 20 0 0 0 0 18Z" />
            <path fill="#EA4335" d="M24 11.8c3 0 5.7 1 7.8 3.1l5.8-5.8C34.1 5.8 29.5 4 24 4A20 20 0 0 0 5.8 15l6.8 5.2c1.6-4.8 6.1-8.4 11.4-8.4Z" />
        </svg>
        <span>{{ label || 'Continue with Google' }}</span>
    </a>
</template>
