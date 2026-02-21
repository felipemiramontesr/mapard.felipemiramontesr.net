# MAPARD: Jurisprudencia de Ingeniería

Este documento establece las reglas permanentes de desarrollo para MAPARD. Antigravity y cualquier otro agente deben seguir estas directrices sin excepción.

## 🧱 Estándares de Código (PSR12)

### 1. Límite de Longitud de Línea
**CRÍTICO**: Ninguna línea de código PHP debe exceder los **120 caracteres**.
- **Razón**: El pipeline de CI/CD (GitHub Actions) fallará si se detectan líneas excesivas, bloqueando el despliegue a producción.
- **Acción**: Si una cadena de texto, SQL o condicional es larga, **debe fragmentarse**.

### 2. Formateo de SQL
- Mal: `$sql = "INSERT INTO table (col1, col2, col3, col4, col5, col6, col7) VALUES (?, ?, ?, ?, ?, ?)";` (Excede 120)
- Bien:
```php
$sql = "INSERT INTO table ";
$sql .= "(col1, col2, col3, col4, col5, col6, col7) ";
$sql .= "VALUES (?, ?, ?, ?, ?, ?)";
```

### 3. Biometría y Seguridad
- Siempre usar `useFallback: true` en desafíos biométricos.
- Siempre implementar guards (`isAuthenticating`) para evitar loops en Android.

---
*Establecido el 21 de Febrero, 2026.*
