<script setup lang="ts">
import { DialogRoot as RadixDialogRoot } from 'radix-vue';

interface DialogRootProps {
    open?: boolean;
    defaultOpen?: boolean;
    modal?: boolean;
}

interface DialogRootEmits {
    (e: 'update:open', value: boolean): void;
}

const props = defineProps<DialogRootProps>();
const emits = defineEmits<DialogRootEmits>();
</script>

<template>
    <RadixDialogRoot v-bind="props" @update:open="emits('update:open', $event)">
        <slot />
    </RadixDialogRoot>
</template>
