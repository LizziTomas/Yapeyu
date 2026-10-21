# Yapeyu — Sistema de Gestión para Local Comercial

Aplicación web desarrollada con **Laravel** para la gestión diaria de un local comercial
("Casa Yacobone"): control de stock, caja, ventas y reportes, con autenticación y roles.

Proyecto académico — Trabajo final de la carrera.

---

## Características principales

### Autenticación y seguridad
- Registro/Login con **Laravel Breeze**.
- Roles y permisos con **Spatie Laravel Permission** (`admin`, `vendedor`).
- **Políticas de autorización** por recurso (cajas, ventas, productos, movimientos de stock, usuarios).
- Verificación de permisos tanto en las vistas (`@can`, `Gate::authorize`) como en el backend.

### Productos
- Alta, edición, baja y listado de productos (solo administradores gestionan; todos consultan).
- Control de stock actual, stock mínimo, precio de costo y precio de venta.
- Estado activo/inactivo y alertas de stock bajo.

### Movimientos de stock (trazabilidad completa del inventario)
- **Ingreso** de mercadería (entrada manual de stock).
- **Venta**: cada venta descuenta stock y genera automáticamente su movimiento histórico.
- **Anulación de venta**: estorna el stock y registra el movimiento de devolución.
- **Descarte**: registra merma/rotura/vencimiento con un motivo (solo administradores).
- Historial con filtros por producto, usuario y tipo, con detalle de stock anterior/posterior.
- **El stock de cada producto se reconcilia exactamente con la suma de sus movimientos.**

### Caja
- Apertura y cierre de caja por turno, con monto inicial en efectivo.
- Arqueo: cálculo del **dinero físico esperado** (monto inicial + ventas en efectivo).
- Registro del monto real contado y cálculo automático de la **diferencia** (sobrante/faltante).
- Cierre congelado del arqueo: si una venta se anula luego del cierre, la caja se recalcula.
- **Ticket de cierre imprimible** con el resumen completo del turno.

### Ventas
- Registro de ventas con múltiples productos y control estricto de stock (transaccional con `lockForUpdate`).
- Medios de pago: efectivo, transferencia y tarjeta.
- Comprobante de venta en pantalla.
- **Anulación de ventas** con motivo y auditoría (quién y cuándo), restaurando el stock.
- Filtros por estado y por vendedor.

### Reportes
- Reporte de ventas por período con filtros (fechas, vendedor, medio de pago).
- Resumen consolidado: cantidad de operaciones, total facturado y desglose por medio de pago.
- Contador de ventas anuladas del período (no afectan los totales).
- **Exportación del detalle a CSV** compatible con Excel (UTF-8, separador `;`, decimales con coma).

### Asistente IA
- Chat de ayuda integrado como **widget flotante**, accesible desde cualquier pantalla.
- Responde únicamente sobre el funcionamiento del sistema, tomando como base el documento `resources/ai/casa-yacobone.md`.
- **Filtro de tema** por similitud semántica: si la consulta no se relaciona con el sistema, el asistente lo indica en lugar de responder.
- Historial de la conversación guardado en el navegador, por usuario.
- Requiere [Ollama](https://ollama.com/download) en ejecución (ver instalación).

### Interfaz
- **Bootstrap 5** + Bootstrap Icons (vía CDN) con una hoja de estilos propia.
- **Modo claro / oscuro** con preferencia persistente en el navegador.
- Diseño responsive (barra lateral colapsable en móviles).

---

## Requisitos

- **PHP** >= 8.3
- **Composer**
- **SQLite** (por defecto, no requiere servidor de base de datos) o MySQL
- Extensiones PHP estándar de Laravel (`pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`)
- **[Ollama](https://ollama.com/download)** (solo para el Asistente IA)

---

## Instalación

```bash
# 1. Clonar el repositorio
git clone <url-del-repositorio> proyecto3
cd proyecto3

# 2. Instalar dependencias de PHP
composer install

# 3. Crear el archivo de entorno y generar la clave de la aplicación
copy .env.example .env       # Windows
# cp .env.example .env       # Linux / macOS
php artisan key:generate

# 4. Crear la base de datos SQLite
type nul > database/database.sqlite      # Windows
# touch database/database.sqlite         # Linux / macOS

# 5. Ejecutar migraciones y datos iniciales (roles, permisos y admin)
php artisan migrate --seed

# 6. (Opcional) Asistente IA: instalar Ollama y descargar los modelos
#    Descargar Ollama desde https://ollama.com/download y luego:
ollama pull qwen3:1.7b
ollama pull embeddinggemma:300m
```

> **Asistente IA:** el widget flotante necesita Ollama en ejecución. Las variables
> `OLLAMA_URL`, `OLLAMA_MODEL`, `OLLAMA_EMBEDDING_MODEL` y `OLLAMA_SCOPE_THRESHOLD`
> ya traen valores por defecto en `.env.example`; si cambiás alguna, ejecutá
> `php artisan config:clear`.
> Sin Ollama activo, el resto del sistema funciona normalmente y solo el asistente
> devuelve un error de conexión.

> **Nota:** en el entorno `local`, el `DatabaseSeeder` también carga **datos de demostración**
> (productos, cajas, ventas de los últimos 60 días, anulaciones y descartes) para poder
> presentar el sistema con información realista. En otros entornos no se cargan.

### Datos de demostración

| Rol       | Email                  | Contraseña    |
|-----------|------------------------|---------------|
| Admin     | `admin@local.test`     | `admin123`    |
| Vendedor  | `vendedor1@local.test` | `vendedor123` |
| Vendedor  | `vendedor2@local.test` | `vendedor123` |
| Vendedor  | `vendedor3@local.test` | `vendedor123` |

Para regenerar la base con datos de demostración desde cero:

```bash
php artisan migrate:fresh --seed
```

---

## Uso

```bash
php artisan serve
```

Luego abrir `http://localhost:8000` e iniciar sesión con alguno de los usuarios anteriores.

---

## Pruebas

El proyecto cuenta con una suite de pruebas de funcionalidad (**Pest**) que cubre
autenticación, autorización por roles, productos, cajas, ventas, anulaciones,
descartes de stock, movimientos y reportes.

```bash
php artisan test
```

---

## Estructura del proyecto

```
app/
├── Http/
│   ├── Controllers/    → Controladores (Caja, Venta, Producto, MovimientoStock, Reporte, ...)
│   ├── Middleware/     → Filtros (autenticación, rol admin)
│   └── Requests/       → Validación de formularios
├── Models/             → Modelos Eloquent (Caja, Venta, VentaDetalle, Producto, MovimientoStock, ...)
├── Policies/           → Autorización por recurso
└── helpers.php         → Funciones auxiliares (configuracion(), monto())

database/
├── factories/          → Datos falsos para pruebas
├── migrations/         → Esquema de la base de datos
└── seeders/            → Roles/permisos, admin y datos de demostración

resources/ai/           → Documentación que usa el Asistente IA
resources/views/        → Vistas Blade (dashboard, productos, cajas, ventas, reportes, stock)
routes/web.php          → Rutas de la aplicación
tests/Feature/          → Pruebas de funcionalidad
```

---

## Tecnologías

| Componente        | Tecnología                              |
|-------------------|-----------------------------------------|
| Framework         | Laravel 13                              |
| Lenguaje          | PHP 8.3                                 |
| Base de datos     | SQLite (desarrollo) / MySQL (opcional)  |
| Autenticación     | Laravel Breeze                          |
| Roles y permisos  | Spatie Laravel Permission               |
| Frontend          | Blade + Bootstrap 5 (CDN)               |
| Asistente IA      | Ollama (`qwen3:1.7b` + `embeddinggemma:300m`) |
| Pruebas           | Pest                                    |

---

## Licencia

Software de uso académico. Desarrollado como proyecto final de carrera.
