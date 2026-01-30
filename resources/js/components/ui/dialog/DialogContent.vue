<script setup lang="ts">
import { computed } from 'vue';
import { DialogPortal, DialogContent as RadixDialogContent, DialogOverlay } from 'radix-vue';
import { cn } from '@/lib/utils';

interface DialogContentProps {
    class?: string;
}

interface DialogContentEmits {
    (e: 'openAutoFocus', event: Event): void;
    (e: 'closeAutoFocus', event: Event): void;
    (e: 'escapeKeyDown', event: KeyboardEvent): void;
    (e: 'pointerDownOutside', event: Event): void;
    (e: 'focusOutside', event: Event): void;
    (e: 'interactOutside', event: Event): void;
}

const props = defineProps<DialogContentProps>();
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <DialogPortal>
        <DialogOverlay />
        <RadixDialogContent
            v-bind="delegatedProps"
            :class="cn(
                'fixed left-1/2 top-1/2 z-50 grid w-full max-w-lg -translate-x-1/2 -translate-y-1/2 gap-4 border bg-background p-6 shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] sm:rounded-lg md:w-full',
                props.class
            )"
            @open-auto-focus="emits('openAutoFocus', $event)"
            @close-auto-focus="emits('closeAutoFocus', $event)"
            @escape-key-down="emits('escapeKeyDown', $event)"
            @pointer-down-outside="emits('pointerDownOutside', $event)"
            @focus-outside="emits('focusOutside', $event)"
            @interact-outside="emits('interactOutside', $event)"
        >
            <slot />
        </RadixDialogContent>
    </DialogPortal>
</template>
