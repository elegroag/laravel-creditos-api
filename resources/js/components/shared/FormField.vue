<template>
    <label class="grid gap-1" v-bind="$attrs">
        <span class="text-sm font-medium text-muted-foreground">{{ label }}</span>
        <slot />
    </label>
</template>

<script setup lang="ts">
defineProps<{
    label: string;
}>();
</script>
