<template>
    <svg
        viewBox="0 0 360 72"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        class="text-slate-900 dark:text-slate-100"
        aria-label="Agent Scheduler"
    >
        <defs>
            <linearGradient id="agentLogoGradient" x1="8" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
                <stop stop-color="#0EA5E9" />
                <stop offset="1" stop-color="#4F46E5" />
            </linearGradient>
        </defs>

        <rect x="4" y="4" width="56" height="56" rx="16" fill="url(#agentLogoGradient)" />
        <circle cx="32" cy="32" r="14.5" stroke="white" stroke-opacity="0.95" stroke-width="3.5" />
        <path d="M32 32V24" stroke="white" stroke-width="3.5" stroke-linecap="round" />
        <path d="M32 32L40 36" stroke="white" stroke-width="3.5" stroke-linecap="round" />
        <circle cx="20" cy="21" r="3" fill="#99F6E4" />
        <circle cx="46" cy="24" r="3" fill="#BFDBFE" />
        <circle cx="24" cy="46" r="3" fill="#C4B5FD" />
        <path d="M22 22L28.5 27.5" stroke="white" stroke-opacity="0.8" stroke-width="2" stroke-linecap="round" />
        <path d="M43.5 25L37.5 29.5" stroke="white" stroke-opacity="0.8" stroke-width="2" stroke-linecap="round" />
        <path d="M25.5 43.5L30 37.5" stroke="white" stroke-opacity="0.8" stroke-width="2" stroke-linecap="round" />

        <text
            x="78"
            y="44"
            fill="currentColor"
            font-family="Figtree, ui-sans-serif, system-ui"
            font-size="30"
            font-weight="700"
            letter-spacing="-0.5"
        >
            Agent Scheduler
        </text>
    </svg>
</template>
