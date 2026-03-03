import { reactive } from 'vue';

const defaultDialogState = {
    show: false,
    title: 'Please confirm',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    confirmVariant: 'default',
    closeable: true,
};

export const confirmDialogState = reactive({ ...defaultDialogState });

let pendingResolver = null;

const normalizeOptions = (messageOrOptions, overrideOptions = {}) => {
    if (typeof messageOrOptions === 'string') {
        return {
            ...overrideOptions,
            message: messageOrOptions,
        };
    }

    return {
        ...(messageOrOptions ?? {}),
        ...overrideOptions,
    };
};

export const confirmDialog = (messageOrOptions, overrideOptions = {}) => {
    const options = normalizeOptions(messageOrOptions, overrideOptions);

    return new Promise((resolve) => {
        if (pendingResolver) {
            pendingResolver(false);
        }

        pendingResolver = resolve;

        Object.assign(confirmDialogState, {
            ...defaultDialogState,
            ...options,
            show: true,
        });
    });
};

const settleDialog = (value) => {
    const resolver = pendingResolver;
    pendingResolver = null;

    confirmDialogState.show = false;

    if (resolver) {
        resolver(Boolean(value));
    }
};

export const acceptConfirmDialog = () => settleDialog(true);
export const dismissConfirmDialog = () => settleDialog(false);
