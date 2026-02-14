# 🤖 PROTOCOLO DE CONSTRUCCIÓN ANDROID (VIGILANCIA MÓVIL)

Este documento detalla el procedimiento para lanzar la aplicación nativa "MAPARD" en un dispositivo Android físico o emulado.

## 📋 PRE-REQUISITOS

1.  **Android Studio** instalado (con SDK Tools y Platform Tools).
2.  **JDK (Java Development Kit)** instalado (generalmente viene con Android Studio).
3.  **Cable USB** de alta calidad (para depuración física).
4.  **Dispositivo Android** con "Depuración USB" activada (ver abajo).

---

## 🚀 INTELIGENCIA DE EJECUCIÓN (PASO A PASO)

### 1. Preparar el Entorno (Terminal)
Asegúrate de estar en la carpeta del cliente donde reside el código de la App.

```bash
cd client
```

*(Si acabas de hacer cambios en el código web, recuerda sincronizar primero: `npm run build` y luego `npx cap sync`)*

### 2. Iniciar Android Studio (Puente Nativo)
Ejecuta el comando maestro de Capacitor para abrir el proyecto nativo:

```bash
npx cap open android
```

**Si falla (Error "Unable to launch"):**
1.  Abre **Android Studio** manualmente desde tu menú de inicio.
2.  Dale a **"Open"** (o "Open an existing project").
3.  Navega y selecciona la carpeta carpeta `client/android` dentro de este proyecto.
4.  Dale a **OK**.

*   **Lo que sucederá:** Se abrirá una ventana de Android Studio cargando el proyecto ubicado en `client/android`.
*   **Tiempo de espera:** La primera vez, Android Studio tardará unos minutos "indexando" y descargando Gradle. **Espera a que la barra de progreso inferior termine.**

### 3. Conexión del Dispositivo (Target Link)

#### Opción A: Dispositivo Físico (Recomendado)
1.  Conecta tu celular al PC por USB.
2.  En el celular, acepta el diálogo "¿Permitir depuración por USB?".
3.  En la barra superior de Android Studio, deberías ver el nombre de tu modelo (ej. "Samsung SM-G991B").

*Nota: Si no lo detecta, asegúrate de tener los Drivers ADB instalados.*

#### Opción B: Emulador (Virtual Device)
1.  Si no tienes móvil a mano, ve a `Tools > Device Manager` en Android Studio.
2.  Crea un "Virtual Device" (ej. Pixel 6 API 33).
3.  Dale al botón de "Play" pequeño en el Device Manager para encenderlo.

### 4. Ejecución (Launch Sequence)
Una vez que Android Studio detecte tu dispositivo (físico o virtual):

1.  Localiza el **Botón de Play Verde (Run App)** en la barra de herramientas superior (o presiona `Shift + F10`).
2.  Android Studio compilará el APK e lo instalará automáticamente en el dispositivo.
3.  **¡ÉXITO!** La app se abrirá sola en modo Pantalla Completa.

---

## 🛠️ SOLUCIÓN DE PROBLEMAS (TROUBLESHOOTING)

*   **Error "SDK Location not found":** Ve a `File > Project Structure > SDK Location` y asegúrate de que la ruta sea correcta.
*   **Error de Gradle:** A veces requiere internet para bajar dependencias. Verifica tu conexión.
*   **Error "npx : No se puede cargar el archivo..." (PowerShell):**
    Esto es una restricción de Windows. Solución rápida: escribe `cmd /c` antes del comando o usa "Command Prompt" en lugar de PowerShell.
    *   Ejemplo: `cmd /c npx cap open android`

    ```bash
    npm run build
    npx cap sync
    ```
