import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.mapard.app',
    appName: 'MAPARD',
    webDir: 'dist',
    server: {
        androidScheme: 'https'
    },
    plugins: {
        StatusBar: {
            overlaysWebView: true,
            style: 'DARK'
        }
    }
};

export default config;
