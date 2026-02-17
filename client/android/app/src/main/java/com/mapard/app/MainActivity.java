package com.mapard.app;

import android.os.Bundle;
import android.net.http.SslError;
import android.webkit.SslErrorHandler;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.BridgeWebViewClient;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setTheme(R.style.AppTheme_NoActionBar);

        // --- DEBUG: BYPASS SSL ERRORS ---
        // Warning: Do not use in production for sensitive data
        if (getBridge() != null && getBridge().getWebView() != null) {
             getBridge().getWebView().setWebViewClient(new BridgeWebViewClient(getBridge()) {
                 @Override
                 public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                     handler.proceed(); // Force continue
                 }
             });
        }
    }
}
