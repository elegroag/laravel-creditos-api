<template>
    <div class="min-h-dvh bg-background text-foreground flex items-center justify-center p-4">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight text-foreground">Comfaca Crédito</h2>
            </div>

            <div class="bg-card text-card-foreground rounded-xl border border-border shadow-sm p-6 sm:p-8">
                <slot />
            </div>

            <div class="text-center text-sm text-muted-foreground">
                <Link href="/" class="underline hover:text-primary transition-colors">Ir al inicio</Link>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>
