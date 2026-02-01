import { readonly, ref } from 'vue';
import type { SimuladorStorageData } from '@/types/simulador';

const STORAGE_KEY = 'comfaca_simulador_data';

const simuladorData = ref<SimuladorStorageData | null>(null);

const canUseStorage = (): boolean => typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';

const loadSimuladorData = (): SimuladorStorageData | null => {
    if (!canUseStorage()) return null;

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (!stored) return null;

        const data = JSON.parse(stored) as SimuladorStorageData;
        simuladorData.value = data;
        return data;
    } catch {
        window.localStorage.removeItem(STORAGE_KEY);
        simuladorData.value = null;
        return null;
    }
};

const saveSimuladorData = (data: SimuladorStorageData): void => {
    if (!canUseStorage()) return;

    try {
        if (!data?.lineaCredito) return;

        const dataToStore: SimuladorStorageData = {
            ...data,
            fechaSimulacion: data.fechaSimulacion || new Date().toISOString()
        };

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(dataToStore));
        simuladorData.value = dataToStore;
    } catch {
    }
};

const saveSimuladorDataSilent = (data: SimuladorStorageData): void => {
    if (!canUseStorage()) return;

    try {
        if (!data?.lineaCredito) return;

        const dataToStore: SimuladorStorageData = {
            ...data,
            fechaSimulacion: data.fechaSimulacion || new Date().toISOString()
        };

        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(dataToStore));
    } catch {
    }
};

const clearSimuladorData = (): void => {
    if (!canUseStorage()) return;

    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } finally {
        simuladorData.value = null;
    }
};

const updateSimuladorData = (updates: Partial<SimuladorStorageData>): void => {
    if (!simuladorData.value) return;
    saveSimuladorData({ ...simuladorData.value, ...updates });
};

const hasSimuladorData = (): boolean => simuladorData.value !== null;

export const useSimuladorStorage = () => {
    if (simuladorData.value === null) {
        loadSimuladorData();
    }

    return {
        simuladorData: readonly(simuladorData),
        loadSimuladorData,
        saveSimuladorData,
        saveSimuladorDataSilent,
        clearSimuladorData,
        updateSimuladorData,
        hasSimuladorData
    };
};
