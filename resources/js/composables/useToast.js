import { ref } from 'vue';

const toasts = ref([]);
let toastId = 0;

export function useToast() {
    const show = (message, type = 'info', duration = 3500) => {
        const id = ++toastId;
        toasts.value.push({ id, message, type });

        setTimeout(() => {
            remove(id);
        }, duration);
    };

    const success = (msg, duration) => show(msg, 'success', duration);
    const error = (msg, duration) => show(msg, 'error', duration || 4500);
    const warning = (msg, duration) => show(msg, 'warning', duration);
    const info = (msg, duration) => show(msg, 'info', duration);

    const remove = (id) => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    };

    return {
        toasts,
        show,
        success,
        error,
        warning,
        info,
        remove,
    };
}
