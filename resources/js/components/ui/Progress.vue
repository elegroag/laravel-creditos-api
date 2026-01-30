<template>
    <div
        :class="cn('relative h-4 w-full overflow-hidden rounded-full bg-secondary/20', props.class)"
    >
        <div
            class="h-full w-full flex-1 bg-primary transition-all"
            :style="{ transform: `translateX(-${100 - (modelValue || 0)}%)` }"
        />
    </div>
</template>

<script setup lang="ts">
import { cn } from '@/lib/utils';

interface Props {
    modelValue?: number;
    class?: string;
}

const props = defineProps<Props>();
</script>
