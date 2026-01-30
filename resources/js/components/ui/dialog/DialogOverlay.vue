<script setup lang="ts">
import { DialogOverlay as RadixDialogOverlay } from 'radix-vue';
import { cn } from '@/lib/utils';

interface DialogOverlayProps {
    class?: string;
}

const props = defineProps<DialogOverlayProps>();
</script>

<template>
    <RadixDialogOverlay
        v-bind="props"
        :class="cn('fixed inset-0 z-50 bg-background/80 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0', props.class)"
    >
        <slot />
    </RadixDialogOverlay>
</template>
