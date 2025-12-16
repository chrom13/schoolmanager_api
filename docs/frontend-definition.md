# FRONTEND - Sistema de Gestión Escolar (React)

## Stack Técnico
- **Framework:** React 18 + Vite
- **Lenguaje:** TypeScript
- **Routing:** React Router v6
- **Estado Global:** Zustand
- **Consultas API:** TanStack Query (React Query)
- **HTTP Client:** Axios
- **UI Framework:** Tailwind CSS + shadcn/ui
- **Formularios:** React Hook Form + Zod
- **Tablas:** TanStack Table
- **Gráficas:** Recharts
- **Notificaciones:** React Hot Toast
- **Iconos:** Lucide React

---

## Arquitectura del Proyecto

### Estructura de Carpetas
```
schoolmanager-web/
├── public/
├── src/
│   ├── api/                    # Cliente HTTP y endpoints
│   │   ├── client.ts           # Axios instance configurado
│   │   ├── auth.api.ts
│   │   ├── alumnos.api.ts
│   │   ├── calificaciones.api.ts
│   │   └── ...
│   ├── components/             # Componentes reutilizables
│   │   ├── ui/                 # shadcn/ui components
│   │   ├── layout/
│   │   │   ├── AppLayout.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   └── Header.tsx
│   │   ├── forms/
│   │   └── tables/
│   ├── features/               # Features por módulo
│   │   ├── auth/
│   │   │   ├── components/
│   │   │   ├── hooks/
│   │   │   ├── pages/
│   │   │   └── schemas/
│   │   ├── alumnos/
│   │   ├── calificaciones/
│   │   ├── asistencias/
│   │   ├── cobranza/
│   │   └── ...
│   ├── hooks/                  # Custom hooks globales
│   ├── lib/                    # Utilidades y helpers
│   ├── routes/                 # Configuración de rutas
│   ├── stores/                 # Zustand stores
│   ├── types/                  # TypeScript types/interfaces
│   ├── App.tsx
│   └── main.tsx
├── .env.example
├── .env.development
├── package.json
├── tsconfig.json
├── tailwind.config.ts
└── vite.config.ts
```

---

## Roles y Permisos

### Tipos de Usuario
1. **Director** - Acceso total
2. **Admin** - Gestión operativa (sin configuración de escuela)
3. **Maestro** - Solo calificaciones y asistencias de sus grupos
4. **Padre** - Solo información de sus hijos

### Implementación
```typescript
// src/types/auth.types.ts
export enum UserRole {
  DIRECTOR = 'director',
  ADMIN = 'admin',
  MAESTRO = 'maestro',
  PADRE = 'padre'
}

// src/hooks/usePermissions.ts
export const usePermissions = () => {
  const { user } = useAuthStore();

  return {
    canManageEscuela: user?.rol === UserRole.DIRECTOR,
    canManageAlumnos: [UserRole.DIRECTOR, UserRole.ADMIN].includes(user?.rol),
    canViewCalificaciones: user?.rol !== UserRole.PADRE,
    // ...
  };
};
```

---

## FASE 1: Configuración Inicial y Autenticación

### Objetivos
- Setup del proyecto React + TypeScript + Vite
- Configuración de Tailwind + shadcn/ui
- Sistema de autenticación completo
- Layout base con navegación

### 1.1 Inicialización del Proyecto

**Tareas:**
```bash
# Crear proyecto
npm create vite@latest schoolmanager-web -- --template react-ts
cd schoolmanager-web

# Instalar dependencias base
npm install

# Tailwind CSS
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p

# shadcn/ui
npx shadcn-ui@latest init

# Dependencias principales
npm install react-router-dom zustand axios @tanstack/react-query
npm install react-hook-form @hookform/resolvers zod
npm install @tanstack/react-table
npm install recharts
npm install lucide-react
npm install react-hot-toast
npm install date-fns

# Tipos
npm install -D @types/node
```

**Configuraciones:**
- `tsconfig.json` - Path aliases (@/)
- `vite.config.ts` - Configurar puerto y proxy
- `tailwind.config.ts` - Colores y tema
- `.env.development` - Variables de entorno

### 1.2 API Client Setup

**Archivo: `src/api/client.ts`**
```typescript
import axios from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8080/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor - agregar token
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor - manejar errores
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default apiClient;
```

### 1.3 Autenticación

**Store: `src/stores/authStore.ts`**
```typescript
import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface User {
  id: number;
  nombre: string;
  email: string;
  rol: 'director' | 'admin' | 'maestro' | 'padre';
  escuela: {
    id: number;
    nombre: string;
  };
}

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  login: (token: string, user: User) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      isAuthenticated: false,
      login: (token, user) => {
        localStorage.setItem('auth_token', token);
        set({ token, user, isAuthenticated: true });
      },
      logout: () => {
        localStorage.removeItem('auth_token');
        set({ token: null, user: null, isAuthenticated: false });
      },
    }),
    {
      name: 'auth-storage',
    }
  )
);
```

**API: `src/api/auth.api.ts`**
```typescript
import apiClient from './client';

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterData {
  nombre_escuela: string;
  slug: string;
  cct: string;
  email_escuela: string;
  nombre: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export const authApi = {
  login: async (credentials: LoginCredentials) => {
    const { data } = await apiClient.post('/auth/login', credentials);
    return data;
  },

  register: async (registerData: RegisterData) => {
    const { data } = await apiClient.post('/auth/register', registerData);
    return data;
  },

  me: async () => {
    const { data } = await apiClient.get('/auth/me');
    return data;
  },

  logout: async () => {
    const { data } = await apiClient.post('/auth/logout');
    return data;
  },
};
```

**Páginas:**
- `src/features/auth/pages/LoginPage.tsx`
- `src/features/auth/pages/RegisterPage.tsx`

**Componentes:**
- `src/components/layout/ProtectedRoute.tsx` - Protección de rutas
- `src/components/layout/AppLayout.tsx` - Layout principal

### 1.4 Routing

**Archivo: `src/routes/index.tsx`**
```typescript
import { createBrowserRouter } from 'react-router-dom';
import { ProtectedRoute } from '@/components/layout/ProtectedRoute';
import { AppLayout } from '@/components/layout/AppLayout';

// Auth pages
import LoginPage from '@/features/auth/pages/LoginPage';
import RegisterPage from '@/features/auth/pages/RegisterPage';

// Dashboard
import DashboardPage from '@/features/dashboard/pages/DashboardPage';

export const router = createBrowserRouter([
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/register',
    element: <RegisterPage />,
  },
  {
    path: '/',
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          {
            index: true,
            element: <DashboardPage />,
          },
          // Más rutas aquí en fases siguientes
        ],
      },
    ],
  },
]);
```

---

## FASE 2: Dashboard y Navegación

### Objetivos
- Dashboard con métricas principales
- Sidebar con navegación
- Header con perfil de usuario
- Sistema de notificaciones

### 2.1 Layout Principal

**Componentes:**
```
AppLayout.tsx
├── Sidebar.tsx
│   ├── Logo
│   ├── Navigation Items
│   └── User Menu
└── Header.tsx
    ├── Breadcrumbs
    ├── Search (opcional)
    └── NotificationBell
```

**Navegación por Rol:**
```typescript
// src/config/navigation.ts
import { UserRole } from '@/types/auth.types';

export const navigationItems = [
  {
    label: 'Dashboard',
    icon: 'LayoutDashboard',
    href: '/',
    roles: [UserRole.DIRECTOR, UserRole.ADMIN],
  },
  {
    label: 'Alumnos',
    icon: 'Users',
    href: '/alumnos',
    roles: [UserRole.DIRECTOR, UserRole.ADMIN],
  },
  {
    label: 'Calificaciones',
    icon: 'GraduationCap',
    href: '/calificaciones',
    roles: [UserRole.DIRECTOR, UserRole.ADMIN, UserRole.MAESTRO],
  },
  {
    label: 'Asistencias',
    icon: 'Calendar',
    href: '/asistencias',
    roles: [UserRole.DIRECTOR, UserRole.ADMIN, UserRole.MAESTRO],
  },
  {
    label: 'Cobranza',
    icon: 'DollarSign',
    href: '/cobranza',
    roles: [UserRole.DIRECTOR, UserRole.ADMIN],
  },
  // ...
];
```

### 2.2 Dashboard

**Métricas a mostrar:**
- Total de alumnos activos
- Cobranza del mes (pendiente vs cobrado)
- Morosidad (% y monto)
- Gráfica de ingresos últimos 6 meses

**Componentes:**
- `StatCard.tsx` - Tarjeta de métrica
- `RevenueChart.tsx` - Gráfica de ingresos
- `RecentPayments.tsx` - Lista de pagos recientes
- `MorosidadCard.tsx` - Tarjeta de morosidad

---

## FASE 3: Gestión de Estructura Académica

### Objetivos
- CRUD de Niveles
- CRUD de Grados
- CRUD de Grupos
- CRUD de Materias
- Asignación de materias a grupos

### 3.1 Páginas

```
/estructura
├── /niveles              # Lista de niveles
├── /grados               # Lista de grados (filtrar por nivel)
├── /grupos               # Lista de grupos (filtrar por grado)
└── /materias             # Lista de materias
    └── /asignaciones     # Asignar materias a grupos
```

### 3.2 Componentes Clave

**DataTable genérico:**
```typescript
// src/components/tables/DataTable.tsx
import { useReactTable } from '@tanstack/react-table';

export function DataTable<TData>({
  data,
  columns,
  onEdit,
  onDelete
}: DataTableProps<TData>) {
  // Implementación con TanStack Table
}
```

**Formularios modales:**
- `CreateNivelModal.tsx`
- `CreateGradoModal.tsx`
- `CreateGrupoModal.tsx`
- `CreateMateriaModal.tsx`

### 3.3 Custom Hooks

```typescript
// src/features/estructura/hooks/useNiveles.ts
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

export const useNiveles = () => {
  const queryClient = useQueryClient();

  const { data: niveles, isLoading } = useQuery({
    queryKey: ['niveles'],
    queryFn: () => nivelesApi.getAll(),
  });

  const createMutation = useMutation({
    mutationFn: nivelesApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['niveles'] });
      toast.success('Nivel creado exitosamente');
    },
  });

  return {
    niveles,
    isLoading,
    create: createMutation.mutate,
    // ...
  };
};
```

---

## FASE 4: Gestión de Alumnos y Padres

### Objetivos
- Lista de alumnos con búsqueda y filtros
- Crear/editar alumno con padres
- Asignar/cambiar grupo
- Importación masiva desde Excel
- Perfil completo del alumno

### 4.1 Páginas

```
/alumnos
├── index                  # Lista de alumnos
├── /nuevo                 # Crear alumno
├── /:id                   # Perfil del alumno
│   ├── General            # Info básica
│   ├── Académico          # Calificaciones y asistencias
│   ├── Cobranza           # Cargos y pagos
│   └── Documentos         # Archivos adjuntos
└── /importar              # Importación masiva
```

### 4.2 Formulario de Alumno

**Secciones:**
1. Datos del Alumno
   - Nombre, apellidos, CURP, fecha nacimiento
   - Grupo actual
   - Foto (opcional)

2. Padres/Tutores (array dinámico)
   - Nombre completo, email, teléfono
   - RFC (para facturación)
   - Parentesco
   - Responsable de pagos (checkbox)
   - Contacto de emergencia (checkbox)

**Validación con Zod:**
```typescript
// src/features/alumnos/schemas/alumno.schema.ts
import { z } from 'zod';

export const alumnoSchema = z.object({
  nombre: z.string().min(1, 'Nombre requerido'),
  apellido_paterno: z.string().min(1, 'Apellido requerido'),
  apellido_materno: z.string().optional(),
  curp: z.string().regex(/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/, 'CURP inválido').optional(),
  fecha_nacimiento: z.date().optional(),
  grupo_id: z.number().optional(),
  padres: z.array(z.object({
    nombre_completo: z.string().min(1),
    email: z.string().email(),
    telefono: z.string().optional(),
    rfc: z.string().regex(/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/).optional(),
    parentesco: z.enum(['padre', 'madre', 'tutor', 'abuelo', 'otro']),
    responsable_pagos: z.boolean().default(false),
    contacto_emergencia: z.boolean().default(false),
  })).min(1, 'Debe tener al menos un padre/tutor'),
});
```

### 4.3 Importación Masiva

**Flujo:**
1. Descargar plantilla Excel
2. Usuario llena plantilla
3. Subir archivo
4. Preview de datos con validación
5. Confirmar importación
6. Ver resultados (éxitos y errores)

**Componente:**
```typescript
// src/features/alumnos/components/ImportExcel.tsx
- Dropzone para archivo
- Tabla de preview con validaciones
- Indicadores de errores por fila
- Botón de confirmar importación
```

---

## FASE 5: Ciclos Escolares y Períodos

### Objetivos
- Gestión de ciclos escolares
- Gestión de períodos (bimestres, trimestres)
- Selección de ciclo/período activo

### 5.1 Páginas

```
/configuracion/ciclos-escolares
├── index                  # Lista de ciclos
└── /:id/periodos          # Períodos del ciclo
```

### 5.2 Features

**Ciclo Escolar Activo:**
- Mostrar en header el ciclo actual
- Selector para cambiar de ciclo (solo ver histórico)
- Badge indicando "Ciclo Activo"

**Períodos:**
- Crear múltiples períodos para un ciclo
- Tipos: bimestre, trimestre, semestre, anual
- Validación de fechas (sin solapamiento)

---

## FASE 6: Calificaciones

### Objetivos
- Captura de calificaciones por materia y período
- Boletas de calificaciones
- Vista para maestros (solo sus materias)
- Vista para padres (solo sus hijos)

### 6.1 Páginas

**Para Admin/Director:**
```
/calificaciones
├── /captura               # Captura por grupo-materia-período
└── /boletas               # Generar boletas
```

**Para Maestros:**
```
/mis-calificaciones
└── /captura               # Solo sus grupos y materias
```

**Para Padres:**
```
/mis-hijos/:id/calificaciones  # Solo lectura
```

### 6.2 Captura de Calificaciones

**Flujo:**
1. Seleccionar grupo
2. Seleccionar materia
3. Seleccionar período
4. Tabla editable con alumnos del grupo
5. Guardar calificaciones

**Componente:**
```typescript
// Grid editable tipo Excel
- Columna: Nombre alumno
- Columna: Calificación (input numérico 0-100)
- Columna: Observaciones (textarea)
- Auto-save o guardar por lote
```

### 6.3 Boleta de Calificaciones

**Vista:**
- Logo de la escuela
- Datos del alumno
- Tabla de materias con calificaciones por período
- Promedio general
- Observaciones generales
- Botón de exportar a PDF

---

## FASE 7: Asistencias

### Objetivos
- Pase de lista diario
- Registro de asistencia grupal
- Reportes de asistencia por alumno
- Justificación de faltas

### 7.1 Páginas

```
/asistencias
├── /pase-lista            # Registro diario
└── /reportes              # Reportes por alumno/grupo
```

### 7.2 Pase de Lista

**Flujo:**
1. Seleccionar grupo
2. Seleccionar fecha
3. Mostrar lista de alumnos
4. Marcar estado de cada uno (presente, falta, retardo, justificada)
5. Guardar asistencia del grupo completo

**Componente:**
```typescript
// Lista de alumnos con botones de estado
- Foto + nombre del alumno
- Botones: Presente | Falta | Retardo | Justificada
- Color coding (verde, rojo, amarillo, azul)
- Observaciones por alumno
- Guardar todo al final
```

### 7.3 Reportes de Asistencia

**Métricas:**
- % de asistencia por alumno
- Total de faltas, retardos, justificadas
- Gráfica de asistencia por mes
- Exportar a Excel

---

## FASE 8: Conceptos de Cobro

### Objetivos
- CRUD de conceptos de cobro
- Asignación de precios por nivel/grado
- Configuración de periodicidad

### 8.1 Páginas

```
/configuracion/conceptos-cobro
├── index                  # Lista de conceptos
└── /nuevo                 # Crear concepto
```

### 8.2 Formulario

**Campos:**
- Nombre del concepto
- Descripción
- Precio base
- Periodicidad (único, mensual, bimestral, etc.)
- Aplicable a:
  - Todos los niveles
  - Nivel específico
  - Grado específico

**Ejemplos:**
- Colegiatura: Mensual, varía por nivel
- Inscripción: Único, varía por nivel
- Uniforme: Único, mismo precio para todos

---

## FASE 9: Generación de Cargos (Próxima fase del backend)

*(Nota: Esta fase requiere endpoints del backend que aún no están implementados)*

### Conceptos

**Cargos:**
- Se generan basados en conceptos de cobro
- Asignados a alumnos específicos
- Tienen fecha de vencimiento
- Calculan descuentos automáticos (hermanos, pronto pago, becas)

### 9.1 Páginas

```
/cobranza
├── /generar-cargos        # Wizard de generación
├── /cargos-pendientes     # Lista de cargos pendientes
└── /cargos-vencidos       # Lista de cargos morosos
```

### 9.2 Generación Masiva

**Wizard:**
1. Seleccionar alumnos (todos, por nivel, por grado, individual)
2. Seleccionar concepto de cobro
3. Definir mes/período
4. Definir fecha de vencimiento
5. Preview de cargos a generar
6. Confirmar generación

---

## Componentes UI Reutilizables (shadcn/ui)

### Instalar componentes necesarios:
```bash
npx shadcn-ui@latest add button
npx shadcn-ui@latest add input
npx shadcn-ui@latest add label
npx shadcn-ui@latest add card
npx shadcn-ui@latest add table
npx shadcn-ui@latest add dialog
npx shadcn-ui@latest add dropdown-menu
npx shadcn-ui@latest add form
npx shadcn-ui@latest add select
npx shadcn-ui@latest add badge
npx shadcn-ui@latest add alert
npx shadcn-ui@latest add toast
npx shadcn-ui@latest add tabs
npx shadcn-ui@latest add calendar
npx shadcn-ui@latest add popover
npx shadcn-ui@latest add command
npx shadcn-ui@latest add avatar
```

---

## Manejo de Estados

### TanStack Query (React Query)

**Configuración:**
```typescript
// src/lib/queryClient.ts
import { QueryClient } from '@tanstack/react-query';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5, // 5 minutos
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});
```

**Query Keys Organization:**
```typescript
// src/lib/queryKeys.ts
export const queryKeys = {
  niveles: {
    all: ['niveles'] as const,
    detail: (id: number) => ['niveles', id] as const,
  },
  alumnos: {
    all: ['alumnos'] as const,
    detail: (id: number) => ['alumnos', id] as const,
    byGrupo: (grupoId: number) => ['alumnos', 'grupo', grupoId] as const,
  },
  calificaciones: {
    byAlumno: (alumnoId: number, periodoId: number) =>
      ['calificaciones', 'alumno', alumnoId, 'periodo', periodoId] as const,
  },
  // ...
};
```

---

## Tipado TypeScript

### Tipos base

```typescript
// src/types/models.ts

export interface Escuela {
  id: number;
  nombre: string;
  slug: string;
  cct: string;
  rfc: string;
  email: string;
}

export interface Usuario {
  id: number;
  escuela_id: number;
  nombre: string;
  email: string;
  rol: 'director' | 'admin' | 'maestro' | 'padre';
  escuela: Escuela;
}

export interface Nivel {
  id: number;
  escuela_id: number;
  nombre: 'preescolar' | 'primaria' | 'secundaria' | 'preparatoria';
  activo: boolean;
}

export interface Grado {
  id: number;
  escuela_id: number;
  nivel_id: number;
  nombre: string;
  orden: number;
  activo: boolean;
  nivel?: Nivel;
}

export interface Grupo {
  id: number;
  escuela_id: number;
  grado_id: number;
  nombre: string;
  capacidad_maxima: number;
  maestro_id?: number;
  activo: boolean;
  grado?: Grado;
  maestro?: Usuario;
}

export interface Alumno {
  id: number;
  escuela_id: number;
  grupo_id?: number;
  nombre: string;
  apellido_paterno: string;
  apellido_materno?: string;
  nombre_completo: string;
  curp?: string;
  fecha_nacimiento?: string;
  foto_url?: string;
  activo: boolean;
  grupo?: Grupo;
  padres?: Padre[];
}

export interface Padre {
  id: number;
  escuela_id: number;
  nombre_completo: string;
  email: string;
  telefono?: string;
  rfc?: string;
  regimen_fiscal?: string;
  uso_cfdi?: string;
  codigo_postal?: string;
  activo: boolean;
  alumnos?: Alumno[];
}

export interface Materia {
  id: number;
  escuela_id: number;
  nombre: string;
  clave?: string;
  descripcion?: string;
  color?: string;
  activo: boolean;
}

export interface CicloEscolar {
  id: number;
  escuela_id: number;
  nombre: string;
  fecha_inicio: string;
  fecha_fin: string;
  activo: boolean;
  periodos?: Periodo[];
}

export interface Periodo {
  id: number;
  ciclo_escolar_id: number;
  nombre: string;
  numero: number;
  tipo: 'bimestre' | 'trimestre' | 'cuatrimestral' | 'semestre' | 'anual';
  fecha_inicio: string;
  fecha_fin: string;
  activo: boolean;
  cicloEscolar?: CicloEscolar;
}

export interface Calificacion {
  id: number;
  alumno_id: number;
  materia_id: number;
  periodo_id: number;
  calificacion: number;
  observaciones?: string;
  maestro_id?: number;
  alumno?: Alumno;
  materia?: Materia;
  periodo?: Periodo;
  maestro?: Usuario;
}

export interface Asistencia {
  id: number;
  alumno_id: number;
  grupo_id: number;
  fecha: string;
  estado: 'presente' | 'falta' | 'retardo' | 'justificada';
  observaciones?: string;
  alumno?: Alumno;
  grupo?: Grupo;
}

export interface ConceptoCobro {
  id: number;
  escuela_id: number;
  nombre: string;
  descripcion?: string;
  precio_base: number;
  periodicidad: 'unico' | 'mensual' | 'bimestral' | 'trimestral' | 'cuatrimestral' | 'semestral' | 'anual';
  nivel_id?: number;
  grado_id?: number;
  activo: boolean;
  nivel?: Nivel;
  grado?: Grado;
}
```

---

## Utilidades y Helpers

### Formateo

```typescript
// src/lib/formatters.ts

export const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
  }).format(amount);
};

export const formatDate = (date: string | Date): string => {
  return new Intl.DateTimeFormat('es-MX').format(new Date(date));
};

export const formatPercent = (value: number): string => {
  return `${value.toFixed(2)}%`;
};
```

### Validaciones

```typescript
// src/lib/validators.ts

export const isValidCURP = (curp: string): boolean => {
  const regex = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/;
  return regex.test(curp);
};

export const isValidRFC = (rfc: string): boolean => {
  const regex = /^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
  return regex.test(rfc);
};
```

---

## Plan de Desarrollo por Fases

### FASE 1 (Semana 1) ✅
- Setup inicial del proyecto
- Configuración de Tailwind + shadcn/ui
- Sistema de autenticación (login/register)
- Layout base con navegación

### FASE 2 (Semana 1-2)
- Dashboard con métricas básicas
- Sidebar con navegación completa
- Sistema de notificaciones

### FASE 3 (Semana 2)
- CRUD Niveles
- CRUD Grados
- CRUD Grupos
- CRUD Materias

### FASE 4 (Semana 3-4)
- CRUD Alumnos
- CRUD Padres
- Formulario integrado alumno + padres
- Importación masiva de Excel

### FASE 5 (Semana 4)
- Gestión de ciclos escolares
- Gestión de períodos

### FASE 6 (Semana 5)
- Captura de calificaciones
- Boletas de calificaciones
- Vista para maestros
- Vista para padres

### FASE 7 (Semana 6)
- Pase de lista
- Reportes de asistencia
- Justificación de faltas

### FASE 8 (Semana 6-7)
- CRUD Conceptos de cobro
- Configuración de precios

### FASE 9 (Pendiente backend)
- Generación de cargos
- Dashboard de cobranza
- Estados de cuenta

---

## Variables de Entorno

```env
# .env.development
VITE_API_URL=http://localhost:8080/api/v1
VITE_APP_NAME=School Manager
```

```env
# .env.production
VITE_API_URL=https://api.tuescuela.com/api/v1
VITE_APP_NAME=School Manager
```

---

## Scripts de package.json

```json
{
  "scripts": {
    "dev": "vite",
    "build": "tsc && vite build",
    "preview": "vite preview",
    "lint": "eslint . --ext ts,tsx --report-unused-disable-directives --max-warnings 0",
    "type-check": "tsc --noEmit"
  }
}
```

---

## Próximos Pasos

1. **Crear el proyecto:**
   ```bash
   npm create vite@latest schoolmanager-web -- --template react-ts
   ```

2. **Seguir FASE 1** de este documento

3. **Iterar rápidamente** validando con el backend existente

4. **Feedback continuo** mejorando UX según uso real
