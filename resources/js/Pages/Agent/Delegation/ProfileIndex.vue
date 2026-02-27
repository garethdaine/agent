<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Plus, Pencil, Trash2, Power, ChevronLeft, ChevronRight, User } from 'lucide-vue-next';

const profiles = ref([]);
const loading = ref(true);
const error = ref('');
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 25 });

const load = async (page = 1) => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/delegation/delegatee-profiles', { params: { page } });
        profiles.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load profiles.';
    } finally {
        loading.value = false;
    }
};

const toggleActive = async (id) => {
    try {
        const profile = profiles.value.find(p => p.id === id);
        await axios.put(`/agent/api/v1/delegation/delegatee-profiles/${id}`, {
            is_active: !profile.is_active,
        });
        await load(meta.value.current_page);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to update profile.';
    }
};

const deleteProfile = async (id) => {
    if (!confirm('Are you sure you want to delete this profile?')) return;

    try {
        await axios.delete(`/agent/api/v1/delegation/delegatee-profiles/${id}`);
        await load(meta.value.current_page);
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete profile.';
    }
};

const setPage = async (page) => {
    await load(page);
};

onMounted(() => load());
</script>

<template>
    <AppLayout title="Delegatee Profiles">
        <Head title="Delegatee Profiles" />

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">Delegatee Profiles</h2>
                <Link :href="route('agent.delegation.profiles.create')">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        Create Profile
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-[1440px] space-y-4">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Runner Type</TableHead>
                                    <TableHead>Capabilities</TableHead>
                                    <TableHead>Active</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="loading">
                                    <TableCell colspan="5" class="py-8">
                                        <div class="flex flex-col items-center justify-center gap-4">
                                            <Skeleton class="h-4 w-48" />
                                            <Skeleton class="h-4 w-32" />
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-for="profile in profiles" :key="profile.id">
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <User class="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <div class="font-medium text-foreground">{{ profile.name }}</div>
                                                <div v-if="profile.description" class="text-xs text-muted-foreground">{{ profile.description }}</div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">{{ profile.runner_type }}</TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="cap in profile.capabilities"
                                                :key="cap.id"
                                                variant="secondary"
                                            >
                                                {{ cap.slug }}
                                            </Badge>
                                            <span v-if="!profile.capabilities || profile.capabilities.length === 0" class="text-muted-foreground">-</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="profile.is_active ? 'default' : 'outline'">
                                            {{ profile.is_active ? 'Yes' : 'No' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button variant="outline" size="sm" @click="toggleActive(profile.id)">
                                                <Power class="mr-1 h-3 w-3" />
                                                {{ profile.is_active ? 'Deactivate' : 'Activate' }}
                                            </Button>
                                            <Link :href="route('agent.delegation.profiles.edit', profile.id)">
                                                <Button variant="outline" size="sm">
                                                    <Pencil class="mr-1 h-3 w-3" />
                                                    Edit
                                                </Button>
                                            </Link>
                                            <Button variant="outline" size="sm" class="text-destructive hover:text-destructive" @click="deleteProfile(profile.id)">
                                                <Trash2 class="mr-1 h-3 w-3" />
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!loading && profiles.length === 0">
                                    <TableCell colspan="5" class="py-8 text-center text-muted-foreground">
                                        No profiles found.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div class="flex items-center justify-between text-sm text-muted-foreground">
                    <p>Showing page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="meta.current_page <= 1"
                            @click="setPage(meta.current_page - 1)"
                        >
                            <ChevronLeft class="mr-1 h-4 w-4" />
                            Prev
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="meta.current_page >= meta.last_page"
                            @click="setPage(meta.current_page + 1)"
                        >
                            Next
                            <ChevronRight class="ml-1 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
