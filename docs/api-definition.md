# API Backend - Sistema de Gestión Escolar

## Stack Técnico
- Laravel 12
- PHP 8.3
- MySQL 8.0
- Redis (cache/queue)
- Docker + Docker Compose

---

## FASE 1: Setup con Docker

### 1.1 Estructura Inicial
```
- [ ] Crear carpeta proyecto: `mkdir tuapp-api && cd tuapp-api`
- [ ] Crear `docker-compose.yml`
- [ ] Crear `Dockerfile`
- [ ] Crear `.dockerignore`
```

### 1.2 Archivo docker-compose.yml
```yaml
- [ ] Crear archivo con:
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: tuapp-api
    restart: unless-stopped
    working_dir: /var/www
    volumes:
      - ./:/var/www
    networks:
      - tuapp-network
    depends_on:
      - mysql
      - redis

  nginx:
    image: nginx:alpine
    container_name: tuapp-nginx
    restart: unless-stopped
    ports:
      - "8000:80"
    volumes:
      - ./:/var/www
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - tuapp-network

  mysql:
    image: mysql:8.0
    container_name: tuapp-mysql
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: tuapp_dev
      MYSQL_ROOT_PASSWORD: root
      MYSQL_USER: tuapp
      MYSQL_PASSWORD: secret
    ports:
      - "3306:3306"
    volumes:
      - mysql-data:/var/lib/mysql
    networks:
      - tuapp-network

  redis:
    image: redis:alpine
    container_name: tuapp-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    networks:
      - tuapp-network

networks:
  tuapp-network:
    driver: bridge

volumes:
  mysql-data:
```

### 1.3 Dockerfile
```dockerfile
- [ ] Crear archivo con:
FROM php:8.3-fpm

# Argumentos
ARG user=tuapp
ARG uid=1000

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Limpiar cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crear usuario del sistema
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

# Directorio de trabajo
WORKDIR /var/www

USER $user
```

### 1.4 Nginx Config
```
- [ ] Crear carpeta: `mkdir -p docker/nginx`
- [ ] Crear `docker/nginx/default.conf` con:
server {
    listen 80;
    index index.php index.html;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/public;

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
        gzip_static on;
    }
}
```

### 1.5 Dockerignore
```
- [ ] Crear `.dockerignore` con:
vendor/
node_modules/
.git/
.env
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
```

### 1.6 Iniciar Contenedores
```
- [ ] Build: `docker-compose build`
- [ ] Start: `docker-compose up -d`
- [ ] Verificar: `docker-compose ps` (todos deben estar "Up")
```

### 1.7 Crear Proyecto Laravel
```
- [ ] Entrar al contenedor: `docker-compose exec app bash`
- [ ] Instalar Laravel: `composer create-project laravel/laravel .`
- [ ] Verificar versión: `php artisan --version`
- [ ] Salir: `exit`
```

### 1.8 Configurar .env
```
- [ ] Editar `.env`:
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tuapp_dev
DB_USERNAME=tuapp
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379
```

### 1.9 Testing Setup
```
- [ ] Probar DB: `docker-compose exec app php artisan migrate`
- [ ] Visitar: http://localhost:8000
- [ ] Debe mostrar pantalla de bienvenida Laravel
```

---

## FASE 2: Configuración Base

### 2.1 Instalar Sanctum
```
- [ ] `docker-compose exec app composer require laravel/sanctum`
- [ ] `docker-compose exec app php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"`
- [ ] `docker-compose exec app php artisan migrate`
```

### 2.2 Configurar CORS
```
- [ ] Editar `config/cors.php`:
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => env('FRONTEND_URL') ? [env('FRONTEND_URL')] : [],
'allowed_origins_patterns' => ['/^https?:\/\/.*\.tuapp\.com$/'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,

- [ ] Agregar a .env:
FRONTEND_URL=http://localhost:5173
```

### 2.3 Configurar API Routes
```
- [ ] Editar `bootstrap/app.php`:
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api/v1',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

### 2.4 Instalar Dependencias
```
- [ ] Stripe: `docker-compose exec app composer require stripe/stripe-php`
- [ ] Dev tools: `docker-compose exec app composer require --dev laravel/pint`
```

### 2.5 Configurar Queue
```
- [ ] Crear jobs table: `docker-compose exec app php artisan queue:table`
- [ ] Migrar: `docker-compose exec app php artisan migrate`
```

### 2.6 Git Setup
```
- [ ] `git init`
- [ ] Verificar `.gitignore` incluye:
/vendor
.env
/storage/*.key
docker-compose.override.yml

- [ ] Commit: `git add . && git commit -m "Initial Laravel 12 + Docker setup"`
```

---

## FASE 3: Modelos Base

### 3.1 Migration: Escuelas
```
- [ ] `docker-compose exec app php artisan make:migration create_escuelas_table`
- [ ] Editar migration:
id, nombre, slug(unique), rfc, razon_social, email, telefono, 
codigo_postal, regimen_fiscal, stripe_account_id(nullable), 
database_name(nullable), plan(enum), activo(boolean), timestamps
- [ ] Indexes: slug, activo
```

### 3.2 Migration: Usuarios
```
- [ ] `docker-compose exec app php artisan make:migration create_usuarios_table`
- [ ] Schema:
id, escuela_id(fk), nombre, email, password, 
rol(enum: director,admin,maestro,padre), activo(boolean), 
remember_token, timestamps
- [ ] Unique: [escuela_id, email]
- [ ] Index: [escuela_id, rol]
```

### 3.3 Modelos
```
- [ ] `docker-compose exec app php artisan make:model Escuela`
- [ ] Fillable, casts, relationships

- [ ] `docker-compose exec app php artisan make:model Usuario`
- [ ] Extends Authenticatable
- [ ] Use HasApiTokens
- [ ] Fillable, hidden, casts, relationships
```

### 3.4 Ejecutar Migraciones
```
- [ ] `docker-compose exec app php artisan migrate`
- [ ] Verificar en MySQL
```

---

## FASE 4: Multi-Tenancy

### 4.1 Trait BelongsToTenant
```
- [ ] Crear: `app/Traits/BelongsToTenant.php`
- [ ] Implementar:
  - Boot method que agrega escuela_id automáticamente
  - Global scope que filtra por escuela_id
  - Relación belongsTo Escuela
```

### 4.2 Middleware Tenant
```
- [ ] `docker-compose exec app php artisan make:middleware TenantMiddleware`
- [ ] Validar usuario tiene escuela_id
- [ ] Agregar tenant_escuela_id al request
- [ ] Registrar en bootstrap/app.php
```

### 4.3 Test Multi-Tenancy
```
- [ ] `docker-compose exec app php artisan make:test MultiTenancyTest`
- [ ] Test: Usuario escuela A no ve datos escuela B
- [ ] Test: Global scope funciona
- [ ] Run: `docker-compose exec app php artisan test --filter MultiTenancy`
```

---

## FASE 5: Autenticación

### 5.1 Controller
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/AuthController`
- [ ] Métodos: register, login, logout, me
```

### 5.2 Form Requests
```
- [ ] `docker-compose exec app php artisan make:request Auth/RegisterRequest`
- [ ] Validaciones: nombre_escuela, slug, rfc, email, password

- [ ] `docker-compose exec app php artisan make:request Auth/LoginRequest`
- [ ] Validaciones: email, password
```

### 5.3 Implementar Register
```
- [ ] Validar datos
- [ ] DB::transaction start
- [ ] Crear Escuela
- [ ] Crear Usuario director
- [ ] Generar token Sanctum
- [ ] Commit transaction
- [ ] Return: {escuela, user, token}
```

### 5.4 Implementar Login
```
- [ ] Validar credentials
- [ ] Verificar escuela activa
- [ ] Verificar usuario activo
- [ ] Generar token
- [ ] Return: {user, escuela, token}
```

### 5.5 Implementar Logout
```
- [ ] Revocar token actual: $request->user()->currentAccessToken()->delete()
- [ ] Return 204
```

### 5.6 Implementar Me
```
- [ ] Return usuario autenticado con escuela eager loaded
```

### 5.7 Rutas
```
- [ ] Editar routes/api.php:
// Public
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});
```

### 5.8 Testing
```
- [ ] `docker-compose exec app php artisan make:test AuthTest`
- [ ] Test: register exitoso
- [ ] Test: register slug duplicado falla
- [ ] Test: login exitoso
- [ ] Test: login password incorrecto falla
- [ ] Test: logout revoca token
- [ ] Test: me retorna usuario
- [ ] Run: `docker-compose exec app php artisan test --filter Auth`
```

---

## FASE 6: Estructura Académica

### 6.1 Migrations
```
- [ ] Niveles: `docker-compose exec app php artisan make:model Nivel -m`
Schema: id, escuela_id(fk), nombre(enum), activo, timestamps
Unique: [escuela_id, nombre]

- [ ] Grados: `docker-compose exec app php artisan make:model Grado -m`
Schema: id, escuela_id(fk), nivel_id(fk), nombre, orden, activo, timestamps

- [ ] Grupos: `docker-compose exec app php artisan make:model Grupo -m`
Schema: id, escuela_id(fk), grado_id(fk), nombre, capacidad_maxima, 
maestro_id(nullable,fk usuarios), activo, timestamps

- [ ] Migrar: `docker-compose exec app php artisan migrate`
```

### 6.2 Modelos
```
- [ ] Cada modelo usar trait BelongsToTenant
- [ ] Definir fillable, casts
- [ ] Relaciones:
  Nivel -> hasMany(Grado)
  Grado -> belongsTo(Nivel), hasMany(Grupo)
  Grupo -> belongsTo(Grado), belongsTo(Usuario as maestro)
```

### 6.3 Controllers
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/NivelController --api`
- [ ] `docker-compose exec app php artisan make:controller Api/V1/GradoController --api`
- [ ] `docker-compose exec app php artisan make:controller Api/V1/GrupoController --api`
- [ ] Implementar: index, store, show, update, destroy
```

### 6.4 Form Requests
```
- [ ] Para cada recurso: StoreXRequest, UpdateXRequest
```

### 6.5 Rutas
```
- [ ] En routes/api.php:
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::apiResource('niveles', NivelController::class);
    Route::apiResource('grados', GradoController::class);
    Route::apiResource('grupos', GrupoController::class);
});
```

---

## FASE 7: Alumnos y Padres

### 7.1 Migrations
```
- [ ] Alumnos: `docker-compose exec app php artisan make:model Alumno -m`
Schema: id, escuela_id, nombre, apellido_paterno, apellido_materno, 
curp(nullable), fecha_nacimiento, grupo_id(nullable,fk), 
foto_url(nullable), activo, timestamps

- [ ] Padres: `docker-compose exec app php artisan make:model Padre -m`
Schema: id, escuela_id, nombre_completo, email, telefono, rfc, 
regimen_fiscal, uso_cfdi, codigo_postal, stripe_customer_id(nullable), 
activo, timestamps
Unique: [escuela_id, email]

- [ ] Pivot: alumno_padre
Schema: alumno_id(fk), padre_id(fk), parentesco(enum), responsable_pagos(boolean)
Primary: [alumno_id, padre_id]

- [ ] Migrar: `docker-compose exec app php artisan migrate`
```

### 7.2 Modelos
```
- [ ] Usar trait BelongsToTenant
- [ ] Alumno -> belongsToMany(Padre), belongsTo(Grupo)
- [ ] Padre -> belongsToMany(Alumno)
```

### 7.3 Controller Alumnos
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/AlumnoController`
- [ ] index (filtros: grupo_id, grado_id, search, activo)
- [ ] show (incluir padres, grupo, cargos)
- [ ] store (con padres nested)
- [ ] update
- [ ] destroy (soft delete = activo=false)
```

### 7.4 Importar Alumnos
```
- [ ] `docker-compose exec app composer require maatwebsite/excel`
- [ ] Método: importar(Request $request)
- [ ] Validar Excel
- [ ] Procesar filas
- [ ] Return: {exitosos, errores: [{fila, mensaje}]}
```

### 7.5 Rutas
```
- [ ] Route::apiResource('alumnos', AlumnoController::class);
- [ ] Route::post('alumnos/importar', [AlumnoController::class, 'importar']);
```

---

## FASE 8: Conceptos de Cobro

### 8.1 Migrations
```
- [ ] ConceptoCobro: `docker-compose exec app php artisan make:model ConceptoCobro -m`
Schema: id, escuela_id, nombre, descripcion(nullable), 
tipo(enum: one_time,mensual,opcional), precio_base, clave_sat, 
aplica_todos_grados(boolean), activo, timestamps

- [ ] Pivot concepto_grado (si no aplica a todos)
Schema: concepto_id(fk), grado_id(fk), precio
Primary: [concepto_id, grado_id]

- [ ] Migrar
```

### 8.2 Modelo y Controller
```
- [ ] Trait BelongsToTenant
- [ ] Relación belongsToMany(Grado) via concepto_grado
- [ ] `docker-compose exec app php artisan make:controller Api/V1/ConceptoCobroController --api`
- [ ] Implementar CRUD
```

### 8.3 Rutas
```
- [ ] Route::apiResource('conceptos-cobro', ConceptoCobroController::class);
```

---

## FASE 9: Descuentos y Becas

### 9.1 Migrations
```
- [ ] Descuento: `docker-compose exec app php artisan make:model Descuento -m`
Schema: id, escuela_id, tipo(enum: pronto_pago,hermanos), 
porcentaje, configuracion(json), activo, timestamps

- [ ] Beca: `docker-compose exec app php artisan make:model Beca -m`
Schema: id, escuela_id, tipo, porcentaje, activo, timestamps

- [ ] AlumnoBeca (pivot)
Schema: alumno_id(fk), beca_id(fk), porcentaje, fecha_inicio, 
fecha_fin(nullable), notas(text,nullable), activo
```

### 9.2 Controllers
```
- [ ] DescuentoController, BecaController
- [ ] Método especial: asignarBecaAlumno
```

### 9.3 Service para Cálculo
```
- [ ] Crear: `app/Services/DescuentoService.php`
- [ ] Método: calcularDescuentos(Alumno $alumno, $montoBase)
- [ ] Lógica:
  1. Detectar hermanos automáticamente
  2. Aplicar descuento por hermanos si aplica
  3. Aplicar pronto pago si aplica
  4. Aplicar beca
  5. Return monto final
```

---

## FASE 10: Cargos

### 10.1 Migration
```
- [ ] Cargo: `docker-compose exec app php artisan make:model Cargo -m`
Schema: id, escuela_id, alumno_id(fk), padre_id(fk), concepto_cobro_id(fk),
descripcion, monto_original, descuento, monto_final, fecha_vencimiento,
status(enum: pendiente,pagado,vencido,cancelado), timestamps
Indexes: [escuela_id, status], [escuela_id, alumno_id], fecha_vencimiento
```

### 10.2 Controller
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/CargoController`
- [ ] index (filtros: alumno_id, status, fecha_desde, fecha_hasta)
- [ ] store
- [ ] generarMasivo (importante)
- [ ] update
- [ ] destroy
```

### 10.3 Service: CargoService
```
- [ ] Crear: `app/Services/CargoService.php`
- [ ] generarCargos(array $alumnoIds, array $conceptos, $fechaVencimiento)
- [ ] Para cada alumno:
  1. Obtener precio del concepto (según grado)
  2. Llamar DescuentoService para calcular monto final
  3. Crear Cargo
```

### 10.4 Job: ActualizarMorosidad
```
- [ ] `docker-compose exec app php artisan make:job ActualizarMorosidadJob`
- [ ] Buscar cargos con fecha_vencimiento < hoy AND status = pendiente
- [ ] Cambiar status a vencido
- [ ] Schedule en app/Console/Kernel.php: daily 1am
```

---

## FASE 11: Stripe Integration

### 11.1 Config
```
- [ ] Agregar a .env:
STRIPE_KEY=sk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### 11.2 Controller
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/StripeConnectController`
- [ ] onboardingUrl(): Genera Stripe Connect account link
- [ ] status(): Verifica estado de cuenta conectada
- [ ] refreshUrl(): Genera refresh URL si falló onboarding
```

### 11.3 Rutas
```
- [ ] Route::get('stripe/onboarding-url', [StripeConnectController::class, 'onboardingUrl']);
- [ ] Route::get('stripe/status', [StripeConnectController::class, 'status']);
```

---

## FASE 12: Pagos

### 12.1 Migrations
```
- [ ] Pago: `docker-compose exec app php artisan make:model Pago -m`
Schema: id, escuela_id, padre_id(fk), monto, 
metodo(enum: stripe,efectivo,transferencia,oxxo),
status(enum: pendiente,completado,fallido,reembolsado),
stripe_payment_intent_id(nullable), fecha_pago(datetime,nullable),
notas(text,nullable), timestamps

- [ ] Pivot pago_cargo
Schema: pago_id(fk), cargo_id(fk), monto_aplicado
```

### 12.2 Controller
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/PagoController`
- [ ] index
- [ ] show
- [ ] createIntent (Stripe): crea payment intent
- [ ] confirm (Stripe): confirma pago y asocia cargos
- [ ] manual: registra pago efectivo/transferencia
```

### 12.3 Service: PagoService
```
- [ ] Crear: `app/Services/PagoService.php`
- [ ] registrarPago($padreId, $cargosIds, $monto, $metodo, $paymentIntentId = null)
- [ ] Validar monto coincide con cargos
- [ ] Crear Pago
- [ ] Asociar cargos via pivot
- [ ] Marcar cargos como pagados
- [ ] Trigger facturación
```

### 12.4 Webhook Stripe
```
- [ ] Route::post('webhooks/stripe', [WebhookController::class, 'handleStripe']);
- [ ] Verificar signature
- [ ] Manejar eventos:
  - payment_intent.succeeded
  - payment_intent.payment_failed
  - invoice.payment_succeeded (recurrente)
```

---

## FASE 13: Facturación CFDI

### 13.1 Migrations
```
- [ ] Factura: `docker-compose exec app php artisan make:model Factura -m`
Schema: id, escuela_id, pago_id(fk), padre_id(fk), serie, folio,
uuid(unique,nullable), xml_original(text,nullable), xml_timbrado(text,nullable),
pdf_path(nullable), qr_code(text,nullable), subtotal, total,
status(enum: pendiente,timbrada,cancelada,error), fecha_timbrado(datetime,nullable),
error_mensaje(text,nullable), timestamps

- [ ] ConfiguracionEscuela
Schema: id, escuela_id(unique,fk), certificado_cer(text,nullable),
certificado_key(text,nullable), certificado_password(encrypted,nullable),
certificado_numero(nullable), certificado_vigencia(date,nullable),
dia_vencimiento_pago, dias_gracia, porcentaje_recargo, updated_at
```

### 13.2 Service: CFDIService
```
- [ ] Crear: `app/Services/CFDIService.php`
- [ ] generarXML(Pago $pago): string
- [ ] firmarXML(string $xml, Escuela $escuela): string
- [ ] timbrarConSWSapien(string $xmlFirmado): array
- [ ] generarPDF(Factura $factura): string
```

### 13.3 Service: SWTimbradoService
```
- [ ] Crear: `app/Services/SWTimbradoService.php`
- [ ] authenticate(): obtener token SW
- [ ] timbrar(string $xmlFirmado): array {uuid, xml_timbrado, qr_code}
```

### 13.4 Job: GenerarFacturaJob
```
- [ ] `docker-compose exec app php artisan make:job GenerarFacturaJob`
- [ ] __construct(Pago $pago)
- [ ] handle():
  1. Generar XML (CFDIService)
  2. Firmar XML
  3. Timbrar con SW Sapien
  4. Crear registro Factura
  5. Generar PDF
  6. Enviar email a padre
- [ ] Retry: 3 attempts
- [ ] Timeout: 120 segundos
```

### 13.5 Controller
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/FacturaController`
- [ ] index (filtros)
- [ ] show
- [ ] descargarXML
- [ ] descargarPDF
- [ ] cancelar
- [ ] reenviarEmail
```

---

## FASE 14: Dashboard y Reportes

### 14.1 Controller
```
- [ ] `docker-compose exec app php artisan make:controller Api/V1/DashboardController`
- [ ] resumen(): métricas principales
- [ ] reporteCobranza(fechaInicio, fechaFin)
- [ ] alumnosMorosos()
```

### 14.2 Implementar resumen()
```
- [ ] Total alumnos activos
- [ ] Total pendiente de cobro
- [ ] Morosidad %
- [ ] Pagos mes actual
- [ ] Facturas mes actual
```

### 14.3 Rutas
```
- [ ] Route::get('dashboard/resumen', [DashboardController::class, 'resumen']);
- [ ] Route::get('reportes/cobranza', [DashboardController::class, 'reporteCobranza']);
- [ ] Route::get('reportes/morosos', [DashboardController::class, 'alumnosMorosos']);
```

---

## FASE 15: Jobs Programados

### 15.1 Configurar Scheduler
```
- [ ] Editar app/Console/Kernel.php (o routes/console.php en Laravel 12)
- [ ] Schedule:
  ActualizarMorosidadJob: daily at 1am
  EnviarRecordatoriosPagoJob: daily at 8am
  CobroAutomaticoJob: monthly (día configurado)
```

### 15.2 Job: EnviarRecordatoriosPago
```
- [ ] `docker-compose exec app php artisan make:job EnviarRecordatoriosPagoJob`
- [ ] Buscar cargos que vencen en 3 días
- [ ] Enviar email a padres
```

### 15.3 Job: CobroAutomatico
```
- [ ] `docker-compose exec app php artisan make:job CobroAutomaticoJob`
- [ ] Para cada escuela:
  1. Obtener alumnos con cobro automático activo
  2. Stripe charge
  3. Si exitoso: registrar pago
  4. Si falla: reintentar en días configurados
```

### 15.4 Ejecutar Worker
```
- [ ] En docker-compose.yml agregar servicio:
queue-worker:
  build: .
  command: php artisan queue:work --tries=3
  depends_on: [mysql, redis]
  
- [ ] Restart containers: `docker-compose up -d`
```

---

## FASE 16: Testing Completo

### 16.1 Feature Tests
```
- [ ] AuthTest: ✓
- [ ] MultiTenancyTest: ✓
- [ ] AlumnoTest
- [ ] CargoTest
- [ ] PagoTest
- [ ] FacturaTest
```

### 16.2 Unit Tests
```
- [ ] DescuentoServiceTest
- [ ] CargoServiceTest
- [ ] PagoServiceTest
- [ ] CFDIServiceTest
```

### 16.3 Ejecutar Suite Completa
```
- [ ] `docker-compose exec app php artisan test`
- [ ] Todos los tests deben pasar
```

---

## FASE 17: Optimización y Producción

### 17.1 Indexes de Performance
```
- [ ] Revisar queries lentos
- [ ] Agregar indexes necesarios
- [ ] Migration para nuevos indexes
```

### 17.2 Configuración Producción
```
- [ ] Crear .env.production
- [ ] APP_ENV=production
- [ ] APP_DEBUG=false
- [ ] Configurar QUEUE_CONNECTION=redis
```

### 17.3 Comandos de Cache
```
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
```

### 17.4 Documentación API
```
- [ ] Instalar Scribe: `composer require --dev knuckleswtf/scribe`
- [ ] Generar docs: `php artisan scribe:generate`
```

---

## Comandos Útiles Docker

### Desarrollo Diario
```bash
# Start
docker-compose up -d

# Ver logs
docker-compose logs -f app

# Ejecutar comandos
docker-compose exec app php artisan migrate
docker-compose exec app php artisan test
docker-compose exec app composer require paquete

# Stop
docker-compose down

# Rebuild (después de cambios en Dockerfile)
docker-compose build --no-cache
```

### Database
```bash
# Acceder a MySQL
docker-compose exec mysql mysql -u tuapp -psecret tuapp_dev

# Crear migration
docker-compose exec app php artisan make:migration nombre

# Migrar
docker-compose exec app php artisan migrate

# Rollback
docker-compose exec app php artisan migrate:rollback

# Fresh (WARNING: borra todo)
docker-compose exec app php artisan migrate:fresh
```

### Testing
```bash
# Todos los tests
docker-compose exec app php artisan test

# Test específico
docker-compose exec app php artisan test --filter NombreTest

# Con coverage
docker-compose exec app php artisan test --coverage
```

---

## Checklist Pre-Deploy

### Antes de subir a producción
```
- [ ] Todos los tests pasan
- [ ] .env.production configurado
- [ ] Caches generados
- [ ] Jobs programados funcionan
- [ ] Webhook Stripe configurado
- [ ] SW Sapien credenciales en producción
- [ ] Backups automáticos configurados
- [ ] Monitoring configurado (Sentry opcional)
```

---

## Notas Importantes

### Multi-Tenancy
- TODAS las tablas tenant tienen `escuela_id`
- TODOS los modelos tenant usan trait `BelongsToTenant`
- NUNCA hacer query sin filtro de escuela_id

### Seguridad
- Passwords siempre hasheados con bcrypt
- Certificados CSD encriptados en DB
- API tokens con Sanctum (stateless)
- Validación exhaustiva de inputs

### Performance
- Usar eager loading para evitar N+1
- Indexes en foreign keys y campos de búsqueda
- Queue para operaciones lentas (facturación)
- Redis para cache y sessions

### Desarrollo
- Usar Docker para consistencia
- Tests antes de deploy
- Commits descriptivos
- Code style con Laravel Pint