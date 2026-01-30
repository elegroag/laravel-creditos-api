<script setup lang="ts">
import { computed } from 'vue';
import { DialogTitle as RadixDialogTitle } from 'radix-vue';
import { cn } from '@/lib/utils';

interface DialogTitleProps {
    class?: string;
}

const props = defineProps<DialogTitleProps>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <RadixDialogTitle
        v-bind="delegatedProps"
        :class="cn('text-lg font-semibold leading-none tracking-tight', props.class)"
    >
        <slot />
    </RadixDialogTitle>
</template>
