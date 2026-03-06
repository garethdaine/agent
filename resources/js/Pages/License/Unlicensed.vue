<script setup>
import { Head } from '@inertiajs/vue3';
import { ShieldAlert, Terminal } from 'lucide-vue-next';
</script>

<template>
    <div>
        <Head title="License Required" />

        <div class="flex min-h-screen items-center justify-center bg-slate-950 px-4">
            <div class="w-full max-w-md text-center">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-amber-500/10">
                    <ShieldAlert class="h-8 w-8 text-amber-400" />
                </div>

                <h1 class="mb-2 text-2xl font-semibold text-slate-50">
                    License Required
                </h1>

                <p class="mb-6 text-slate-400">
                    This installation does not have a valid license.
                    Please set your license key in your <code class="rounded bg-slate-800 px-1.5 py-0.5 font-mono text-sm text-slate-300">.env</code> file and verify it.
                </p>

                <div class="rounded-lg border border-slate-800 bg-slate-900 p-4 text-left">
                    <div class="mb-2 flex items-center gap-2 text-sm text-slate-400">
                        <Terminal class="h-4 w-4" />
                        <span>Verify your license</span>
                    </div>
                    <code class="block font-mono text-sm text-emerald-400">
                        php artisan agent:check-license
                    </code>
                </div>

                <p class="mt-6 text-sm text-slate-500">
                    Contact your administrator if you believe this is an error.
                </p>
            </div>
        </div>
    </div>
</template>
