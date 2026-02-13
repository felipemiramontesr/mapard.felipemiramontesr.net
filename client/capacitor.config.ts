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
        },
        SplashScreen: {
            backgroundColor: "#000000",
            launchShowDuration: 0,
            showSpinner: false,
            androidScaleType: "CENTER_CROP",
            splashFullScreen: true,
            splashImmersive: true
        }
    }
};

export default config;
