<script setup>
import { MessageSquare } from 'lucide-vue-next';
</script>

<template>
    <div class="flex flex-1 items-center justify-center">
        <div class="text-center px-6">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 mb-4">
                <MessageSquare class="h-8 w-8 text-primary" />
            </div>
            <h3 class="text-lg font-medium text-foreground mb-1">
                Select a conversation
            </h3>
            <p class="text-sm text-muted-foreground max-w-xs mx-auto">
                Choose a chat session from the sidebar to view the conversation and interact with the agent.
            </p>
        </div>
    </div>
</template>
