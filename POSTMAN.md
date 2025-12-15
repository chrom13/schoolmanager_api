# Guía de Uso de Postman - School Manager API

## Importar la Colección

1. Abre Postman
2. Click en "Import" (esquina superior izquierda)
3. Selecciona el archivo `postman_collection.json`
4. La colección "School Manager API" aparecerá en tu sidebar

## Variables de Colección

La colección usa 2 variables:

- **`base_url`**: `http://localhost:8080/api/v1` (ya configurada)
- **`token`**: Se guarda automáticamente al hacer Register o Login

## Flujo de Prueba Recomendado

### 1. Test Endpoint (sin autenticación)
- **GET** `/test`
- Verifica que la API está funcionando

### 2. Registrar Escuela
- **POST** `/auth/register`
- Crea una escuela nueva con un usuario director
- El token se guarda automáticamente en la variable `{{token}}`

**Datos de ejemplo:**
```json
{
    "nombre_escuela": "Colegio San Francisco",
    "slug": "colegio-san-francisco",
    "cct": "14DPR0001X",
    "rfc": "CSF850101ABC",
    "email_escuela": "contacto@colegiosf.edu.mx",
    "nombre": "Juan Pérez",
    "email": "juan.perez@colegiosf.edu.mx",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Formato CCT (Clave de Centro de Trabajo SEP):**
- 2 dígitos (estado)
- 3 letras (tipo)
- 4 dígitos (número)
- 1 letra (verificador)
- Ejemplo: `14DPR0001X`

### 3. Login (opcional)
- **POST** `/auth/login`
- Si ya tienes una cuenta, inicia sesión
- El token se guarda automáticamente

### 4. Ver Usuario Autenticado
- **GET** `/auth/me`
- Retorna información del usuario con su escuela

### 5. Crear Estructura Académica

#### 5.1 Crear Nivel
- **POST** `/niveles`
```json
{
    "nombre": "primaria"
}
```

**Valores válidos:** `preescolar`, `primaria`, `secundaria`, `preparatoria`

#### 5.2 Crear Grado
- **POST** `/grados`
```json
{
    "nivel_id": 1,
    "nombre": "3° Primaria",
    "orden": 3
}
```

#### 5.3 Crear Grupo
- **POST** `/grupos`
```json
{
    "grado_id": 1,
    "nombre": "Grupo A",
    "capacidad_maxima": 30
}
```

### 6. Listar Recursos

- **GET** `/niveles` - Lista todos los niveles con sus grados
- **GET** `/grados` - Lista todos los grados
- **GET** `/grados?nivel_id=1` - Filtra grados por nivel
- **GET** `/grupos` - Lista todos los grupos
- **GET** `/grupos?grado_id=1` - Filtra grupos por grado

### 7. Ver, Actualizar y Eliminar

Todos los recursos soportan:
- **GET** `/{recurso}/{id}` - Ver detalle
- **PUT** `/{recurso}/{id}` - Actualizar
- **DELETE** `/{recurso}/{id}` - Eliminar

### 8. Logout
- **POST** `/auth/logout`
- Revoca el token actual

## Endpoints Disponibles

### Públicos (sin autenticación)
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/test` | Verificar API funcionando |
| POST | `/auth/register` | Registrar escuela + director |
| POST | `/auth/login` | Iniciar sesión |

### Protegidos (requieren token)

#### Autenticación
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/auth/me` | Usuario autenticado |
| POST | `/auth/logout` | Cerrar sesión |

#### Niveles
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/niveles` | Listar niveles |
| POST | `/niveles` | Crear nivel |
| GET | `/niveles/{id}` | Ver nivel |
| PUT | `/niveles/{id}` | Actualizar nivel |
| DELETE | `/niveles/{id}` | Eliminar nivel |

#### Grados
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/grados` | Listar grados |
| GET | `/grados?nivel_id={id}` | Filtrar por nivel |
| POST | `/grados` | Crear grado |
| GET | `/grados/{id}` | Ver grado |
| PUT | `/grados/{id}` | Actualizar grado |
| DELETE | `/grados/{id}` | Eliminar grado |

#### Grupos
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/grupos` | Listar grupos |
| GET | `/grupos?grado_id={id}` | Filtrar por grado |
| POST | `/grupos` | Crear grupo |
| GET | `/grupos/{id}` | Ver grupo |
| PUT | `/grupos/{id}` | Actualizar grupo |
| DELETE | `/grupos/{id}` | Eliminar grupo |

## Autenticación

Todos los endpoints protegidos requieren el header:
```
Authorization: Bearer {token}
```

El token se obtiene al hacer Register o Login y se guarda automáticamente en la variable `{{token}}` de la colección.

## Respuestas

### Success (200/201)
```json
{
    "message": "Mensaje de éxito",
    "data": {
        // Datos del recurso
    }
}
```

### Error de Validación (422)
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "campo": ["Mensaje de error"]
    }
}
```

### No Autorizado (401)
```json
{
    "message": "Unauthenticated."
}
```

### No Encontrado (404)
```json
{
    "message": "Not found."
}
```

## Multi-Tenancy

Cada escuela ve solo sus propios datos. El sistema filtra automáticamente por `escuela_id` basado en el usuario autenticado.

Si usuario de Escuela A intenta ver datos, solo verá los de Escuela A. Imposible ver datos de Escuela B.

## Tips

1. **Ejecuta Register primero** para crear tu escuela y obtener el token
2. **El token se guarda automáticamente** al hacer Register o Login
3. **Crea en orden:** Nivel → Grado → Grupo
4. **Usa los filtros** (`?nivel_id=1`, `?grado_id=1`) para navegar la jerarquía
5. **Multi-tenancy funciona:** Crea otra escuela con otro email y verás datos aislados
