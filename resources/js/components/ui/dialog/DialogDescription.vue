<script setup lang="ts">
import { computed } from 'vue';
import { DialogDescription as RadixDialogDescription } from 'radix-vue';
import { cn } from '@/lib/utils';

interface DialogDescriptionProps {
    class?: string;
}

const props = defineProps<DialogDescriptionProps>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <RadixDialogDescription
        v-bind="delegatedProps"
        :class="cn('text-sm text-muted-foreground', props.class)"
    >
        <slot />
    </RadixDialogDescription>
</template>
