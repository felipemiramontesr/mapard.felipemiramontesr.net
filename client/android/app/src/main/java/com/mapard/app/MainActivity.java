package com.mapard.app;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // Switch to AppTheme.NoActionBar to enable status bar and keyboard resize
        setTheme(R.style.AppTheme_NoActionBar);
    }
}
