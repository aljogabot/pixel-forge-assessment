<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import type { GenerationPage } from "@/types/generation";

defineProps<{
    generations: GenerationPage;
}>();

const unavailableImages = ref<Record<number, boolean>>({});
</script>

<template>
    <section class="flex flex-col gap-6" aria-labelledby="history-heading">
        <div class="flex items-center justify-between">
            <h2 id="history-heading" class="text-xl font-semibold">
                Generation history
            </h2>

            <span class="text-sm text-muted-foreground">
                {{ generations.total }} total
            </span>
        </div>

        <p
            v-if="generations.data.length === 0"
            class="rounded-lg border border-dashed p-8 text-center text-muted-foreground"
        >
            No generations yet.
        </p>

        <div v-else class="grid gap-6 sm:grid-cols-2">
            <article
                v-for="generation in generations.data"
                :key="generation.id"
                class="flex flex-col overflow-hidden rounded-lg border"
            >
                <div
                    class="flex aspect-square items-center justify-center bg-muted"
                >
                    <img
                        v-if="
                            generation.status === 'succeeded' &&
                            generation.image_url &&
                            !unavailableImages[generation.id]
                        "
                        :src="generation.image_url"
                        :alt="generation.prompt"
                        loading="lazy"
                        referrerpolicy="no-referrer"
                        class="h-full w-full object-contain"
                        @error="unavailableImages[generation.id] = true"
                    />

                    <p
                        v-else
                        class="p-6 text-center text-sm text-muted-foreground"
                    >
                        {{
                            generation.status === "succeeded"
                                ? "Image unavailable. Its URL may have expired."
                                : generation.status === "failed"
                                  ? "Generation failed"
                                  : "Your image is being prepared."
                        }}
                    </p>
                </div>

                <div class="flex flex-col gap-3 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-medium capitalize">
                            {{ generation.status }}
                        </span>

                        <span class="text-xs text-muted-foreground">
                            #{{ generation.id }}
                        </span>
                    </div>

                    <p class="break-words text-sm">
                        {{ generation.prompt }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        {{ generation.size }} · {{ generation.quality }}
                    </p>

                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-muted-foreground">Estimated charge</dt>
                        <dd class="text-right break-all">
                            ${{ generation.estimated_charge_usd }} USD
                        </dd>

                        <dt class="text-muted-foreground">Actual charge</dt>
                        <dd class="text-right break-all">
                            {{
                                generation.actual_charge_usd === null
                                    ? "Not settled"
                                    : `$${generation.actual_charge_usd} USD`
                            }}
                        </dd>
                    </dl>

                    <p
                        v-if="generation.error_message"
                        class="text-sm text-destructive"
                    >
                        {{ generation.error_message }}
                    </p>
                </div>
            </article>
        </div>

        <nav
            v-if="generations.last_page > 1"
            aria-label="Generation history pagination"
            class="flex items-center justify-between gap-4"
        >
            <Link
                v-if="generations.prev_page_url"
                :href="generations.prev_page_url"
                preserve-scroll
                class="text-sm underline"
            >
                Previous
            </Link>
            <span v-else />

            <span class="text-sm text-muted-foreground">
                Page {{ generations.current_page }} of
                {{ generations.last_page }}
            </span>

            <Link
                v-if="generations.next_page_url"
                :href="generations.next_page_url"
                preserve-scroll
                class="text-sm underline"
            >
                Next
            </Link>
            <span v-else />
        </nav>
    </section>
</template>
