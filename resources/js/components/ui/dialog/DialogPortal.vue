<script setup lang="ts">
import { DialogPortal as RadixDialogPortal } from 'radix-vue';

interface DialogPortalProps {
    to?: string | HTMLElement;
    forceMount?: boolean;
}

const props = defineProps<DialogPortalProps>();
</script>

<template>
    <RadixDialogPortal v-bind="props">
        <slot />
    </RadixDialogPortal>
</template>
