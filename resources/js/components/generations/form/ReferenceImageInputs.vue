<script setup lang="ts">
const urls = defineModel<string[]>({ required: true });

withDefaults(
    defineProps<{
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);
</script>

<template>
    <fieldset :disabled="disabled" class="flex flex-col gap-3">
        <legend class="mb-2 font-medium">Reference images (optional)</legend>

        <p class="text-sm text-muted-foreground">
            Add up to four HTTP or HTTPS image URLs. Each reference affects the
            estimated charge.
        </p>

        <div
            v-for="(_, index) in urls"
            :key="index"
            class="flex items-center gap-2"
        >
            <input
                v-model="urls[index]"
                type="url"
                :aria-label="`Reference image ${index + 1} URL`"
                placeholder="https://example.com/reference.png"
                class="min-w-0 flex-1 rounded-md border border-input bg-background p-3 disabled:opacity-50"
            />

            <button
                type="button"
                :aria-label="`Remove reference image ${index + 1}`"
                class="text-sm underline disabled:opacity-50"
                @click="urls = urls.filter((_, position) => position !== index)"
                :style="{ cursor: 'pointer' }"
            >
                Remove
            </button>
        </div>

        <button
            type="button"
            :disabled="urls.length >= 4"
            class="self-start text-sm underline disabled:opacity-50"
            @click="urls = [...urls, '']"
            :style="{ cursor: 'pointer' }"
        >
            Add URL
        </button>
    </fieldset>
</template>
