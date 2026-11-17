<script setup lang="ts">
defineProps<{
    charge: string | null;
    calculating: boolean;
    disabled: boolean;
    errorMessage: string;
}>();

defineEmits<{
    estimate: [];
}>();
</script>

<template>
    <section
        aria-label="Price estimate"
        class="flex flex-col gap-3 rounded-lg border p-4"
    >
        <button
            type="button"
            :disabled="disabled || calculating"
            class="rounded-md bg-primary px-4 py-2 text-primary-foreground disabled:opacity-50"
            @click="$emit('estimate')"
            :style="{ cursor: 'pointer' }"
        >
            {{ calculating ? "Calculating…" : "Get estimate" }}
        </button>

        <p v-if="errorMessage" role="alert" class="text-sm text-destructive">
            {{ errorMessage }}
        </p>

        <div aria-live="polite">
            <p v-if="charge !== null" class="font-medium">
                Estimated charge: ${{ charge }} USD
            </p>
        </div>

        <p class="text-sm text-muted-foreground">
            Includes a 25% markup. Final charge depends on actual token usage.
            Failed generations are free.
        </p>
    </section>
</template>
