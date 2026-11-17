<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { dashboard, login, register } from "@/routes";

const steps = [
    {
        number: "01",
        title: "Describe your image",
        description:
            "Write a prompt, choose your settings, and optionally add reference image URLs.",
    },
    {
        number: "02",
        title: "Preview the price",
        description:
            "Get an estimated charge before submitting your generation.",
    },
    {
        number: "03",
        title: "Watch it take shape",
        description:
            "Follow progress from your dashboard and view the completed image and final charge.",
    },
];
</script>

<template>
    <Head title="Image generation" />

    <div
        class="min-h-screen bg-stone-50 text-stone-950 dark:bg-stone-950 dark:text-stone-50"
    >
        <div class="mx-auto max-w-6xl px-6 md:px-10">
            <header
                class="flex items-center justify-between gap-6 border-b border-stone-200 py-6 dark:border-stone-800"
            >
                <Link href="/" class="text-sm font-semibold tracking-tight">
                    Prompt Edit Demo<span class="text-orange-600">.</span>
                </Link>

                <nav
                    aria-label="Main navigation"
                    class="flex items-center gap-5"
                >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="text-sm font-medium hover:text-orange-600"
                    >
                        Dashboard ↗
                    </Link>

                    <template v-else>
                        <Link
                            :href="login()"
                            class="text-sm font-medium hover:text-orange-600"
                        >
                            Log in
                        </Link>

                        <Link
                            :href="register()"
                            class="rounded-full bg-stone-950 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 dark:bg-stone-50 dark:text-stone-950 dark:hover:bg-stone-200"
                        >
                            Get started
                        </Link>
                    </template>
                </nav>
            </header>

            <main>
                <section class="flex flex-col items-start gap-8 py-24 md:py-36">
                    <p
                        class="text-xs font-semibold tracking-widest text-orange-700 uppercase dark:text-orange-400"
                    >
                        From a few words to a new image
                    </p>

                    <h1
                        class="max-w-4xl text-5xl leading-tight font-medium tracking-tight sm:text-7xl md:text-8xl"
                    >
                        A little imagination.<br />
                        <span class="text-stone-400">A new perspective.</span>
                    </h1>

                    <p
                        class="max-w-xl text-lg leading-relaxed text-stone-600 dark:text-stone-400"
                    >
                        Turn your ideas into images. Explore different sizes and
                        qualities, preview the price, and keep track of every
                        generation in one place.
                    </p>

                    <Link
                        :href="$page.props.auth.user ? dashboard() : register()"
                        class="inline-flex items-center gap-6 rounded-full bg-orange-600 px-7 py-4 font-medium text-white transition-colors hover:bg-orange-700"
                    >
                        {{
                            $page.props.auth.user
                                ? "Open your dashboard"
                                : "Start creating"
                        }}
                        <span aria-hidden="true">↗</span>
                    </Link>

                    <p class="text-sm text-stone-500 dark:text-stone-400">
                        Estimate before submitting. View the actual charge when
                        complete.
                    </p>
                </section>

                <section
                    aria-labelledby="how-it-works"
                    class="border-t border-stone-200 py-16 dark:border-stone-800"
                >
                    <h2
                        id="how-it-works"
                        class="text-2xl font-medium tracking-tight"
                    >
                        Your idea, three steps away.
                    </h2>

                    <div class="mt-10 grid gap-10 md:grid-cols-3">
                        <article
                            v-for="step in steps"
                            :key="step.number"
                            class="flex flex-col gap-4"
                        >
                            <span class="font-mono text-sm text-orange-600">
                                {{ step.number }}
                            </span>

                            <h3 class="text-lg font-medium">
                                {{ step.title }}
                            </h3>

                            <p
                                class="leading-relaxed text-stone-600 dark:text-stone-400"
                            >
                                {{ step.description }}
                            </p>
                        </article>
                    </div>
                </section>
            </main>

            <footer
                class="flex flex-wrap justify-between gap-3 border-t border-stone-200 py-6 text-xs text-stone-500 dark:border-stone-800 dark:text-stone-400"
            >
                <span>Prompt Edit · Assessment demo</span>
                <span>Image generation powered by PixelForge</span>
            </footer>
        </div>
    </div>
</template>
