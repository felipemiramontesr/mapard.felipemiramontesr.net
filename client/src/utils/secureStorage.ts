import {
    getItemFromSecureStorage,
    setItemInSecureStorage,
    removeItemFromSecureStorage,
    clearSecureStorage
} from 'capacitor-secure-storage';

export const secureStorage = {
    async set(key: string, value: string): Promise<void> {
        try {
            await setItemInSecureStorage(key, value);
        } catch (e) {
            console.error('SecureStorage Error (SET):', e);
            localStorage.setItem(key, value);
        }
    },

    async get(key: string): Promise<string | null> {
        try {
            return await getItemFromSecureStorage(key);
        } catch (e) {
            console.warn('SecureStorage Error (GET):', e);
            return localStorage.getItem(key);
        }
    },

    async remove(key: string): Promise<void> {
        try {
            await removeItemFromSecureStorage(key);
        } catch (e) {
            console.error('SecureStorage Error (REMOVE):', e);
            localStorage.removeItem(key);
        }
    },

    async clear(): Promise<void> {
        try {
            await clearSecureStorage();
        } catch (e) {
            localStorage.clear();
        }
    }
};
