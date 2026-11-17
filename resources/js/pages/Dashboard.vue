<script setup lang="ts">
import { Head, Link, usePoll } from "@inertiajs/vue3";
import GenerationHistory from "@/components/generations/GenerationHistory.vue";
import { dashboard } from "@/routes";
import { create } from "@/routes/generations";
import type { GenerationPage } from "@/types/generation";

import { watch } from "vue";
import { toast } from "vue-sonner";

const props = defineProps<{
    generations: GenerationPage;
}>();

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

const { start, stop } = usePoll(
    1000,
    {
        only: ["generations"],
    },
    {
        autoStart: false,
    },
);

watch(
    () =>
        props.generations.data.map((generation) => ({
            id: generation.id,
            status: generation.status,
            errorMessage: generation.error_message,
        })),
    (currentGenerations, previousGenerations) => {
        const previousStatuses = new Map(
            (previousGenerations ?? []).map((generation) => [
                generation.id,
                generation.status,
            ]),
        );

        for (const generation of currentGenerations) {
            const previousStatus = previousStatuses.get(generation.id);

            // Notify only when a previously visible unfinished image finishes.
            if (
                previousStatus === undefined ||
                !["pending", "queued", "processing"].includes(previousStatus)
            ) {
                continue;
            }

            if (generation.status === "processing") {
                toast.info(`Image #${generation.id} started processing.`);
            }

            if (generation.status === "succeeded") {
                toast.success(`Image #${generation.id} is ready.`);
            }

            if (generation.status === "failed") {
                toast.error(
                    generation.errorMessage ??
                        `Image #${generation.id} failed.`,
                );
            }
        }

        const hasUnfinishedGenerations = currentGenerations.some((generation) =>
            ["pending", "queued", "processing"].includes(generation.status),
        );

        if (hasUnfinishedGenerations) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">Your generations</h1>

            <Link
                :href="create()"
                class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground"
            >
                Generate an Image
            </Link>
        </div>

        <GenerationHistory :generations="generations" />
    </div>
</template>
