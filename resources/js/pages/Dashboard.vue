<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import GenerationForm from "@/components/generations/GenerationForm.vue";
import { dashboard } from "@/routes";

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: "Dashboard",
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
        <h1 class="text-2xl font-semibold">Generate an image</h1>
        <GenerationForm />
    </div>
</template>
