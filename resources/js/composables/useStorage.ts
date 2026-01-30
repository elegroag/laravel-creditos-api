export class StorageAdapter {
    private static instance: StorageAdapter;
    private storeName: string;

    private constructor() {
        this.storeName = 'comfaca_credito_storage';
    }

    static getInstance(): StorageAdapter {
        if (!StorageAdapter.instance) {
            StorageAdapter.instance = new StorageAdapter();
        }
        return StorageAdapter.instance;
    }

    async getItem(key: string): Promise<string | null> {
        if (typeof window === 'undefined') return null;
        try {
            return localStorage.getItem(key);
        } catch (error) {
            console.error('Error getting item from localStorage:', error);
            return null;
        }
    }

    async setItem(key: string, value: string): Promise<void> {
        if (typeof window === 'undefined') return;
        try {
            localStorage.setItem(key, value);
        } catch (error) {
            console.error('Error setting item in localStorage:', error);
            throw error;
        }
    }

    async removeItem(key: string): Promise<void> {
        if (typeof window === 'undefined') return;
        try {
            localStorage.removeItem(key);
        } catch (error) {
            console.error('Error removing item from localStorage:', error);
            throw error;
        }
    }

    async clear(): Promise<void> {
        if (typeof window === 'undefined') return;
        try {
            localStorage.clear();
        } catch (error) {
            console.error('Error clearing localStorage:', error);
            throw error;
        }
    }

    async keys(): Promise<string[]> {
        if (typeof window === 'undefined') return [];
        try {
            const keys: string[] = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key) keys.push(key);
            }
            return keys;
        } catch (error) {
            console.error('Error getting keys from localStorage:', error);
            return [];
        }
    }

    async close(): Promise<void> {
        // No es necesario cerrar localStorage
    }
}

export const storage = StorageAdapter.getInstance();
