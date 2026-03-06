<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import { Head, Link } from '@inertiajs/vue3';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import { Users, Calendar, Scale, AlertTriangle, DollarSign, Bot, GitBranch } from 'lucide-vue-next';
</script>

<template>
    <AppLayout title="Agents">
        <Head title="Agents" />

        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Bot class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground truncate">Agents</h2>
                    <HelpHint
                        ui-key="org.overview"
                        short-text="Navigate agents, rituals, councils, escalations, and cost governance."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="">
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Link :href="route('org.agents.index')">
                        <Card class="cursor-pointer transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-primary/10 p-2">
                                        <Users class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle>Agents</CardTitle>
                                        <CardDescription>Manage AI agent profiles</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link :href="route('org.builder')">
                        <Card class="cursor-pointer transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-primary/10 p-2">
                                        <GitBranch class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle>Workforce</CardTitle>
                                        <CardDescription>Visual reporting structure</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link :href="route('org.rituals.index')">
                        <Card class="cursor-pointer transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-primary/10 p-2">
                                        <Calendar class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle>Rituals</CardTitle>
                                        <CardDescription>Recurring workflow templates</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link :href="route('org.councils.index')">
                        <Card class="cursor-pointer transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-primary/10 p-2">
                                        <Scale class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle>Councils</CardTitle>
                                        <CardDescription>Multi-agent decision groups</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link :href="route('org.escalations.index')">
                        <Card class="cursor-pointer transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-primary/10 p-2">
                                        <AlertTriangle class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle>Escalations</CardTitle>
                                        <CardDescription>Pending approvals and alerts</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </Link>

                    <Link :href="route('org.costs.index')">
                        <Card class="cursor-pointer transition-colors hover:bg-muted/50">
                            <CardHeader>
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-primary/10 p-2">
                                        <DollarSign class="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <CardTitle>Costs</CardTitle>
                                        <CardDescription>Usage and cost tracking</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
