<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import GenerationForm from "@/components/generations/GenerationForm.vue";
</script>

<template>
    <Head title="Generate an Image" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-semibold">Generate an Image</h1>
            <p class="text-sm text-muted-foreground">
                Describe your image and review the estimated charge before
                generating.
            </p>
        </div>

        <GenerationForm />
    </div>
</template>
