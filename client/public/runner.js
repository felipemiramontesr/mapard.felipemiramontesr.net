addEventListener('setContext', async (event) => {
    const { email, checksum } = event.details;
    if (email) await CapacitorKV.set({ key: 'target_email', value: email });
    if (checksum) await CapacitorKV.set({ key: 'dossier_checksum', value: checksum });
    console.log('Background Sync: Context updated via setContext');
});

addEventListener('checkSecurity', async (event) => {
    console.log('MAPARD Background Sync: Checking for security updates...');

    try {
        // 1. Retrieve sync context from the background-runner key-value store
        const emailResult = await CapacitorKV.get({ key: 'target_email' });
        const oldChecksumResult = await CapacitorKV.get({ key: 'dossier_checksum' });

        const email = emailResult ? emailResult.value : null;
        const oldChecksum = oldChecksumResult ? oldChecksumResult.value : null;

        if (!email) {
            console.log('Background Sync: No target email configured.');
            return;
        }

        // 2. Tactical Query to API
        const response = await fetch(`https://mapard.felipemiramontesr.net/api/user/status?email=${encodeURIComponent(email)}`);
        if (!response.ok) {
            console.error('Background Sync: API connection failure.');
            return;
        }

        const data = await response.json();

        // 3. Integrity Check
        if (data.has_scans && data.checksum && data.checksum !== oldChecksum) {
            console.log('Background Sync: MISMATCH DETECTED. Sending notification.');

            await CapacitorNotifications.schedule({
                notifications: [
                    {
                        title: 'MAPARD: ALERTA DE SEGURIDAD 🛡️',
                        body: 'Se han detectado cambios o nuevas brechas en su dossier. Abra la terminal inmediatamente.',
                        id: 1,
                        schedule: { at: new Date(Date.now() + 1000) }, // Immediate
                        sound: 'default',
                        attachments: [],
                        actionTypeId: '',
                        extra: null
                    }
                ]
            });

            // Note: We don't update the local checksum here to ensure the user 
            // sees the alert in the app's main dashboard as well.
        } else {
            console.log('Background Sync: Integrity verified. No changes.');
        }

    } catch (e) {
        console.error('Background Sync: Critical failure', e);
    }
});
