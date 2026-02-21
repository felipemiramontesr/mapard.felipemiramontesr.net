import { NativeBiometric, BiometryType } from 'capacitor-native-biometric';
import { Capacitor } from '@capacitor/core';

export const biometricService = {
    async isAvailable(): Promise<boolean> {
        if (!Capacitor.isNativePlatform()) return false;
        try {
            const result = await NativeBiometric.isAvailable();
            return result.isAvailable;
        } catch (e) {
            console.error('Biometric check failed', e);
            return false;
        }
    },

    async getBiometryType(): Promise<BiometryType | null> {
        if (!Capacitor.isNativePlatform()) return null;
        try {
            const result = await NativeBiometric.isAvailable();
            return result.biometryType || null;
        } catch {
            return null;
        }
    },

    async authenticate(reason: string = 'Autenticación requerida para acceder al Dossier de Inteligencia'): Promise<boolean> {
        if (!Capacitor.isNativePlatform()) return true; // Bypass on web for dev

        try {
            const isAvail = await this.isAvailable();
            if (!isAvail) return true; // Fallback if no biometrics set up

            await NativeBiometric.verifyIdentity({
                reason,
                title: 'MAPARD SECURITY',
                subtitle: 'Confirme su identidad por hardware',
                description: 'Terminal Bloqueada: Se requiere biometría nativa.',
            });
            return true;
        } catch (e: any) {
            console.error('Biometric auth failed', e);
            // Error codes like -8 (too many attempts) or -108 (user cancel)
            return false;
        }
    }
};
