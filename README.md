# School Manager API

Sistema de gestión escolar para escuelas privadas pequeñas y medianas (50-500 alumnos) en México.

## Stack Técnico

- Laravel 12
- PHP 8.3
- MySQL 8.0
- Redis
- Apache
- Docker + Docker Compose

## Instalación

### Requisitos
- Docker y Docker Compose instalados

### Setup del Proyecto

#### 1. Clonar el repositorio

```bash
git clone <repository-url>
cd schoolmanager_api
```

#### 2. Levantar contenedores

```bash
docker compose build
docker compose up -d
```

Contenedores disponibles:
- **schoolmanager-api**: PHP 8.3-FPM
- **schoolmanager-apache**: Servidor web Apache (puerto 8080)
- **schoolmanager-mysql**: Base de datos MySQL (puerto 3310)
- **schoolmanager-redis**: Cache y queue (puerto 6380)

#### 3. Instalar dependencias (si no se hizo)

```bash
docker compose exec app composer install
```

#### 4. Configurar variables de entorno

El archivo `.env` ya está configurado automáticamente con:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=schoolmanager_dev
DB_USERNAME=schoolmanager
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379
```

#### 5. Generar key de aplicación

```bash
docker compose exec app php artisan key:generate
```

#### 6. Ejecutar migraciones

```bash
docker compose exec app php artisan migrate
```

#### 7. Acceder a la aplicación

Abre tu navegador en: `http://localhost:8080`

## Comandos Útiles

### Docker

```bash
# Ver logs
docker compose logs -f app

# Detener contenedores
docker compose down

# Reiniciar contenedores
docker compose restart

# Ver contenedores corriendo
docker compose ps
```

### Base de Datos

```bash
# Acceder a MySQL
docker compose exec mysql mysql -u schoolmanager -psecret schoolmanager_dev

# Crear migration
docker compose exec app php artisan make:migration nombre

# Ejecutar migraciones
docker compose exec app php artisan migrate

# Rollback
docker compose exec app php artisan migrate:rollback

# Fresh (WARNING: borra todo)
docker compose exec app php artisan migrate:fresh
```

### Laravel

```bash
# Ejecutar comandos Artisan
docker compose exec app php artisan [comando]

# Instalar paquetes Composer
docker compose exec app composer require [paquete]

# Ejecutar tests
docker compose exec app php artisan test

# Limpiar cache
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
```

## Estructura del Proyecto

```
schoolmanager_api/
├── app/                    # Código de la aplicación
├── bootstrap/              # Bootstrap de Laravel
├── config/                 # Archivos de configuración
├── database/               # Migraciones y seeders
├── docker/                 # Configuraciones Docker
│   └── apache/            # Configuración Apache
├── docs/                   # Documentación del proyecto
│   ├── project-definition.md
│   └── api-definition.md
├── public/                 # Punto de entrada público
├── resources/              # Vistas, assets
├── routes/                 # Definición de rutas
├── storage/                # Archivos generados
├── tests/                  # Tests
├── docker-compose.yml      # Configuración Docker Compose
├── Dockerfile             # Imagen PHP personalizada
└── README.md              # Este archivo
```

## Documentación

Ver documentación completa en:
- [Definición del Proyecto](docs/project-definition.md) - Especificación conceptual completa
- [Definición de la API](docs/api-definition.md) - Guía técnica de implementación

## Estado del Proyecto

### ✅ Fase 1: Setup con Docker - COMPLETADO
- ✅ Contenedores Docker configurados y corriendo
- ✅ Laravel 12 instalado en la raíz del proyecto
- ✅ MySQL configurado (puerto 3310)
- ✅ Redis configurado (puerto 6380)
- ✅ Apache configurado (puerto 8080)
- ✅ Variables de entorno configuradas
- ✅ Migraciones iniciales ejecutadas

### ✅ Fase 2: Configuración Base - COMPLETADO
- ✅ Laravel Sanctum instalado y configurado
- ✅ CORS configurado
- ✅ API Routes configuradas (prefix: /api/v1)
- ✅ Stripe PHP SDK instalado
- ✅ Laravel Pint instalado (code style)
- ✅ Sistema de colas configurado con Redis

### ✅ Fase 3: Modelos Base y Multi-Tenancy - COMPLETADO
- ✅ Migración de Escuelas creada (con CCT de SEP)
- ✅ Migración de Usuarios creada
- ✅ Modelo Escuela implementado
- ✅ Modelo Usuario implementado con autenticación
- ✅ Trait BelongsToTenant implementado con global scope
- ✅ Middleware Tenant configurado
- ✅ Migraciones ejecutadas exitosamente

### ✅ Fase 5: Autenticación - COMPLETADO
- ✅ RegisterRequest con validación de CCT y RFC
- ✅ LoginRequest implementado
- ✅ AuthController creado con 4 métodos:
  - `register`: Crear escuela + usuario director
  - `login`: Autenticación con validaciones
  - `logout`: Revocar token actual
  - `me`: Obtener usuario autenticado
- ✅ Rutas API configuradas (públicas y protegidas)
- ✅ Transacciones DB en register para integridad

### 🔄 Próximo: Fase 6 - Estructura Académica
- Crear migraciones de Niveles, Grados y Grupos
- Implementar modelos con BelongsToTenant
- Crear controllers y rutas CRUD

## Licencia

Proyecto propietario.
