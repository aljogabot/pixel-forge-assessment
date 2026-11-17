<script setup lang="ts">
import { router, useHttp } from "@inertiajs/vue3";
import { ref, watch } from "vue";
import { estimate, store } from "@/routes/generations";

const form = useHttp<
    {
        prompt: string;
        size: string;
        quality: string;
        reference_images: string[];
    },
    {
        estimated_charge_usd: string;
        currency: string;
    }
>({
    prompt: "",
    size: "1024x1024",
    quality: "medium",
    reference_images: [],
});

const isSubmitting = ref(false);
const estimatedCharge = ref<string | null>(null);
const estimateError = ref("");

function settingsSnapshot(): string {
    return JSON.stringify(form.data());
}

watch(settingsSnapshot, () => {
    estimatedCharge.value = null;
    estimateError.value = "";
    form.clearErrors();
});

async function getEstimate(): Promise<void> {
    if (form.processing || isSubmitting.value) {
        return;
    }

    estimatedCharge.value = null;
    estimateError.value = "";

    const requestedSettings = settingsSnapshot();

    try {
        const result = await form.post(estimate.url(), {
            onHttpException: () => false,
            onNetworkError: () => false,
        });

        if (settingsSnapshot() === requestedSettings) {
            estimatedCharge.value = result.estimated_charge_usd;
        }
    } catch {
        if (!form.hasErrors) {
            estimateError.value =
                "Unable to estimate the price. Please try again.";
        }
    }
}

function generate(): void {
    if (
        isSubmitting.value ||
        form.processing ||
        estimatedCharge.value === null
    ) {
        return;
    }

    isSubmitting.value = true;
    form.clearErrors();

    router.post(store.url(), form.data(), {
        onError: (errors) => {
            form.setError(errors);
        },
        onSuccess: () => {
            estimatedCharge.value = null;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <label for="prompt">Prompt</label>
            <textarea
                id="prompt"
                v-model="form.prompt"
                maxlength="4000"
                rows="5"
                class="rounded-md border border-input bg-background p-3"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
                <label for="size">Size</label>
                <select
                    id="size"
                    v-model="form.size"
                    class="rounded-md border border-input bg-background p-3"
                >
                    <option value="1024x1024">Square — 1024 × 1024</option>
                    <option value="1536x1024">Landscape — 1536 × 1024</option>
                    <option value="1024x1536">Portrait — 1024 × 1536</option>
                </select>
            </div>

            <div class="flex flex-col gap-2">
                <label for="quality">Quality</label>
                <select
                    id="quality"
                    v-model="form.quality"
                    class="rounded-md border border-input bg-background p-3"
                >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
        </div>

        <div class="flex flex-col gap-3 rounded-lg border p-4">
            <button
                type="button"
                :disabled="
                    form.processing || isSubmitting || !form.prompt.trim()
                "
                class="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
                @click="getEstimate"
            >
                {{ form.processing ? "Calculating…" : "Get estimate" }}
            </button>

            <ul
                v-if="form.hasErrors"
                role="alert"
                class="list-inside list-disc text-sm text-destructive"
            >
                <li v-for="(message, field) in form.errors" :key="field">
                    {{ message }}
                </li>
            </ul>

            <p
                v-if="estimateError"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ estimateError }}
            </p>

            <div aria-live="polite">
                <p v-if="estimatedCharge !== null" class="font-medium">
                    Estimated charge: ${{ estimatedCharge }} USD
                </p>
            </div>

            <p class="text-sm text-muted-foreground">
                Includes a 25% markup. Final charge depends on actual token
                usage. Failed generations are free.
            </p>
        </div>

        <button
            type="button"
            :disabled="
                isSubmitting || form.processing || estimatedCharge === null
            "
            class="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
            @click="generate"
        >
            {{ isSubmitting ? "Submitting…" : "Generate image" }}
        </button>
    </div>
</template>
