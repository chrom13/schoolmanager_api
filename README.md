# SISTEMA DE GESTIÓN ESCOLAR - Definición Conceptual

## Visión General

Sistema para administrar escuelas privadas pequeñas y medianas (50-500 alumnos) en México. Resuelve principalmente el caos de cobranza y facturación, con gestión académica básica.

### Problema que Resuelve
- **Cobranza:** Escuelas pierden 20-40% por morosidad, usan Excel, persiguen padres manualmente
- **Facturación:** Hacer facturas CFDI es manual, lento, propenso a errores
- **Gestión:** Todo en cuadernos/Excel, información dispersa, sin centralizar

### Propuesta de Valor
- Cobro automático mensual con tarjeta
- Factura electrónica automática al recibir pago
- Todo en un solo lugar: alumnos, pagos, calificaciones

---

## Usuarios del Sistema

### 1. Director/Administrador
**Qué hace:**
- Configurar la escuela (grados, grupos, precios)
- Ver dashboard de cobranza (cuánto deben, quién pagó)
- Generar cargos mensuales
- Ver reportes de morosidad

**Necesita:**
- Visibilidad de finanzas en tiempo real
- Control de cobranza
- Generar reportes para toma de decisiones

### 2. Personal Administrativo
**Qué hace:**
- Registrar alumnos nuevos
- Capturar pagos en efectivo
- Generar estados de cuenta
- Enviar recordatorios de pago

**Necesita:**
- Procesos rápidos y simples
- Evitar errores al capturar
- Automatizar tareas repetitivas

### 3. Maestros (Fase 2)
**Qué hace:**
- Pasar lista
- Capturar calificaciones
- Enviar avisos a padres

**Necesita:**
- Herramientas rápidas (no más de 5 minutos diarios)
- Acceso desde cualquier dispositivo

### 4. Padres
**Qué hace:**
- Ver información de sus hijos (calificaciones, asistencias)
- Pagar colegiaturas online
- Descargar facturas
- Recibir notificaciones

**Necesita:**
- Acceso fácil 24/7
- Pagar sin ir al banco
- Transparencia en cargos

---

## Conceptos Fundamentales

### Escuela
Una institución educativa que contrata el sistema. Cada escuela es independiente, sus datos nunca se mezclan con otras escuelas.

**Características:**
- Tiene subdomain único (ejemplo: colegio-sf.tuapp.com)
- Maneja su propia configuración de precios
- Tiene sus propios usuarios (director, admins, maestros)

### Estructura Académica
Jerarquía de organización:
```
Escuela
  └─ Niveles (Kinder, Primaria, Secundaria)
      └─ Grados (1°, 2°, 3°, etc)
          └─ Grupos (A, B, C)
              └─ Alumnos
```

**Ejemplo real:**
- Colegio San Francisco
  - Primaria
    - 3° Primaria
      - Grupo A (25 alumnos)
      - Grupo B (23 alumnos)

### Alumno
Estudiante inscrito en la escuela.

**Información:**
- Datos personales (nombre, CURP, fecha nacimiento)
- Pertenece a un grado y grupo
- Tiene 1 o más tutores (padres)
- Puede tener beca
- Puede tener descuentos

### Padre/Tutor
Responsable del alumno.

**Características:**
- Un padre puede tener varios hijos en la misma escuela
- Uno de los padres es el "responsable de pagos"
- Tiene acceso a la app para ver info y pagar

### Concepto de Cobro
Qué se cobra en la escuela.

**Tipos:**
- **One-time:** Se cobra una sola vez (Inscripción, Uniformes, Libros)
- **Mensual:** Se cobra cada mes (Colegiatura)
- **Opcional:** El padre decide si lo toma (Talleres, Transporte)

**Ejemplos:**
- Inscripción: $2,500 (one-time)
- Colegiatura: $1,800/mes (mensual)
- Taller de fútbol: $600/mes (opcional)

### Descuentos

**Pronto pago:**
- Si el padre paga antes del día X, se le descuenta Y%
- Ejemplo: Pagar antes del día 5 → 5% descuento

**Hermanos:**
- Si hay 2+ hijos del mismo padre, se descuenta en colegiaturas
- Ejemplo: 2do hermano 10%, 3er hermano 15%

### Beca
Descuento permanente asignado a un alumno específico.

**Tipos comunes:**
- Académica (por buen desempeño)
- Deportiva (por logros deportivos)
- Económica (por situación familiar)

**Ejemplo:**
- Juan tiene beca académica del 50%
- Su colegiatura es $1,800
- Paga: $900

### Cargo
Monto que un alumno debe pagar.

**Características:**
- Se genera para conceptos específicos
- Tiene fecha de vencimiento
- Puede tener descuentos aplicados
- Se asocia al padre responsable

**Ejemplo:**
- Cargo: Colegiatura Enero 2025
- Alumno: Juan García
- Monto original: $1,800
- Descuento pronto pago: -$90
- Beca 50%: -$855
- Monto final: $855
- Vencimiento: 5 Enero 2025

**Estados:**
- **Pendiente:** Aún no se ha pagado
- **Pagado:** Ya fue cubierto
- **Vencido:** Pasó la fecha límite sin pagar
- **Cancelado:** Se eliminó (error, baja del alumno)

### Pago
Cuando un padre realiza un pago.

**Métodos:**
- **Online:** Con tarjeta (Stripe)
- **Manual:** Efectivo o transferencia (registrado por admin)

**Un pago puede cubrir:**
- Un cargo completo
- Múltiples cargos
- Parte de un cargo (pago parcial)

**Ejemplo:**
- Padre paga $5,000
- Cubre:
  - Colegiatura enero: $1,800
  - Colegiatura febrero: $1,800
  - Libros: $1,400

### Factura CFDI
Comprobante fiscal electrónico (obligatorio en México).

**Proceso:**
1. Padre realiza pago
2. Sistema genera XML con datos del pago
3. Se envía a timbrar (PAC: SW Sapien)
4. Se recibe XML timbrado con UUID
5. Se genera PDF
6. Se envía por email al padre

**Datos incluidos:**
- Emisor (escuela)
- Receptor (padre)
- Conceptos (lo que se pagó)
- Monto total
- Sello digital del SAT

---

## Funcionalidades Principales (MVP)

### 1. Onboarding de Escuela
**Objetivo:** Que el director configure su escuela en 10 minutos.

**Pasos:**
1. Registro (nombre escuela, email, password)
2. Configurar datos fiscales (RFC, razón social)
3. Definir estructura (niveles, grados, grupos)
4. Configurar conceptos de cobro (inscripción, colegiatura, etc)
5. Definir precios por grado
6. Configurar descuentos (pronto pago, hermanos)
7. Definir tipos de becas disponibles

**Resultado:** Escuela lista para agregar alumnos.

### 2. Gestión de Alumnos
**Objetivo:** Tener todos los alumnos en el sistema.

**Funcionalidades:**
- **Agregar individual:** Form con datos del alumno + padres
- **Importar masivo:** Subir Excel con todos los alumnos
- **Editar:** Cambiar información, cambiar de grupo
- **Asignar beca:** Dar beca a alumno específico
- **Dar de baja:** Marcar como inactivo (no eliminar)

**Información capturada:**
- Alumno: nombre, apellidos, CURP, fecha nacimiento, grupo
- Padres: nombre, email, teléfono, RFC (para facturación)

### 3. Generación de Cargos
**Objetivo:** Crear automáticamente lo que cada alumno debe pagar.

**Casos de uso:**
- **Inicio de ciclo:** Generar inscripción para todos
- **Mensual:** Generar colegiaturas del mes
- **Individual:** Crear cargo específico para un alumno

**Proceso:**
1. Seleccionar alumnos (todos, por grado, por grupo, individual)
2. Seleccionar conceptos (colegiatura, uniformes, etc)
3. Definir fecha de vencimiento
4. Sistema calcula monto final (precio - descuentos - beca)
5. Genera cargos

**Ejemplo:**
- Generar colegiaturas de enero para todos los alumnos de primaria
- Fecha vencimiento: 5 enero 2025
- Sistema crea 120 cargos automáticamente

### 4. Cobro Online con Stripe
**Objetivo:** Que padres paguen desde su celular/computadora.

**Setup (una vez):**
1. Escuela conecta su cuenta Stripe (wizard guiado)
2. Sistema genera link personalizado por escuela
3. Padres pueden guardar su tarjeta

**Flujo de pago:**
1. Padre ve sus cargos pendientes en la app
2. Selecciona qué pagar
3. Confirma monto
4. Paga con tarjeta guardada (o nueva)
5. Recibe confirmación inmediata

**Cobro automático (opcional):**
- Padre autoriza cargo automático mensual
- Sistema cobra el día configurado (ej: día 1)
- Si falla, reintenta día 3, 5, 7
- Notifica al padre si no se pudo cobrar

### 5. Pagos Manuales
**Objetivo:** Registrar pagos en efectivo o transferencia.

**Proceso:**
1. Admin selecciona al padre
2. Ve cargos pendientes del padre
3. Selecciona qué cargos está pagando
4. Captura monto, método (efectivo/transferencia), fecha
5. Guarda pago
6. Sistema marca cargos como pagados

### 6. Facturación CFDI Automática
**Objetivo:** Generar factura sin intervención humana.

**Setup (una vez):**
1. Escuela sube su certificado CSD (del SAT)
2. Sistema lo valida
3. Facturación queda configurada

**Proceso automático:**
1. Se recibe pago (online o manual)
2. Sistema genera XML del CFDI
3. Firma XML con certificado de la escuela
4. Envía a SW Sapien para timbrar
5. Recibe XML timbrado
6. Genera PDF con el formato
7. Envía email al padre con XML + PDF
8. Padre puede descargar desde la app

**Padre puede:**
- Ver historial de facturas
- Descargar XML y PDF
- Solicitar factura si no la recibió

### 7. Estados de Cuenta
**Objetivo:** Ver qué debe cada alumno.

**Información mostrada:**
- Lista de cargos pendientes
- Lista de pagos realizados
- Total pendiente
- Total pagado
- Días de atraso (si aplica)

**Acciones:**
- Imprimir estado de cuenta
- Enviar por email al padre
- Generar link de pago directo

### 8. Dashboard de Cobranza
**Objetivo:** Director ve finanzas en tiempo real.

**Métricas principales:**
- Total de alumnos activos
- Total pendiente de cobro
- Morosidad (%)
- Ingresos del mes
- Comparativa vs mes anterior

**Visualizaciones:**
- Gráfica de ingresos mensuales
- Lista de alumnos morosos (top 10)
- Pagos recientes

**Reportes:**
- Reporte de cobranza (por período)
- Lista de morosos (exportar Excel)
- Ingresos por concepto

### 9. Portal de Padres (Web)
**Objetivo:** Padres acceden desde cualquier dispositivo.

**Funcionalidades MVP:**
- Ver información de sus hijos
- Ver cargos pendientes
- Ver historial de pagos
- Realizar pagos online
- Descargar facturas
- Ver avisos de la escuela

**Acceso:**
- URL: escuela.tuapp.com/padres
- Login con email y password
- PWA (se puede "instalar" como app)

---

## Flujos de Trabajo Principales

### Flujo 1: Nueva Escuela se Registra
```
1. Director entra a tuapp.com
2. Click en "Registrar escuela"
3. Llena form:
   - Nombre de la escuela
   - Subdomain deseado (ej: colegio-sf)
   - Email
   - Password
4. Sistema crea escuela y usuario admin
5. Redirect a wizard de configuración
6. Completa 5 pasos del wizard
7. Escuela lista para usar
```

### Flujo 2: Agregar Alumnos al Inicio de Ciclo
```
1. Admin entra a "Alumnos"
2. Click "Importar desde Excel"
3. Descarga plantilla
4. Llena Excel con datos de alumnos
5. Sube archivo
6. Sistema valida y muestra errores (si hay)
7. Confirma importación
8. Sistema crea alumnos y padres
```

### Flujo 3: Generar Colegiaturas del Mes
```
1. Admin entra a "Cobranza" > "Generar cargos"
2. Selecciona: "Todos los alumnos"
3. Concepto: "Colegiatura"
4. Mes: "Enero 2025"
5. Fecha vencimiento: "5 enero 2025"
6. Sistema muestra preview:
   - 120 alumnos
   - Monto total: $216,000
7. Click "Generar"
8. Sistema crea 120 cargos con descuentos aplicados
```

### Flujo 4: Padre Paga Online
```
1. Padre recibe notificación: "Tienes cargos pendientes"
2. Abre app (o web)
3. Ve: Colegiatura Enero - $1,710 (vence 5 enero)
4. Click "Pagar ahora"
5. Confirma monto
6. Paga con tarjeta guardada
7. Recibe confirmación: "Pago exitoso"
8. 2 minutos después recibe email con factura
```

### Flujo 5: Padre Paga en Efectivo en la Escuela
```
1. Padre va a la escuela
2. Paga $1,710 en efectivo a la secretaria
3. Secretaria entra al sistema
4. Busca al padre
5. Ve cargos pendientes
6. Selecciona: Colegiatura Enero
7. Captura:
   - Monto: $1,710
   - Método: Efectivo
   - Fecha: Hoy
8. Guarda pago
9. Sistema marca cargo como pagado
10. Imprime recibo para el padre
11. Padre recibe email con factura
```

### Flujo 6: Director Revisa Morosidad
```
1. Director entra al dashboard
2. Ve tarjeta: "Morosidad: 15% - $45,000"
3. Click en tarjeta
4. Sistema muestra lista de alumnos morosos:
   - Juan García - $3,600 (20 días atraso)
   - María López - $5,400 (35 días atraso)
   - ...
5. Selecciona alumnos
6. Click "Enviar recordatorio"
7. Sistema envía email/SMS a los padres
```

---

## Reglas de Negocio

### Cálculo de Monto Final de Cargo
```
1. Se parte del precio base del concepto
2. Si aplica descuento por hermanos: resta %
3. Si pagó antes de fecha (pronto pago): resta %
4. Si alumno tiene beca: resta %

Ejemplo:
- Precio base: $2,000
- Descuento hermanos 10%: -$200 = $1,800
- Beca 50%: -$900 = $900
- Monto final: $900
```

### Descuento por Hermanos (Automático)
```
Sistema detecta automáticamente:
- Si hay 2+ alumnos con el mismo padre responsable
- Aplica descuento configurado:
  - 2do hermano: 10%
  - 3er hermano: 15%
  - 4to+: 20%
```

### Morosidad
```
Un cargo se considera moroso cuando:
- fecha_vencimiento < hoy
- status = pendiente

Sistema marca automáticamente como "vencido"
```

### Facturación Automática
```
Se genera factura automáticamente cuando:
- Pago cambia a status "completado"
- Padre tiene RFC configurado

No se genera si:
- Padre no tiene RFC
- Pago está pendiente
- Es pago parcial (configurable)
```

---

## Multi-Tenancy (Aislamiento de Datos)

### Concepto
Cada escuela es un "tenant" independiente. Sus datos NUNCA se mezclan con otras escuelas.

### Implementación
- Cada escuela tiene subdomain único (colegio-sf.tuapp.com)
- Todas las tablas incluyen campo `escuela_id`
- Sistema filtra automáticamente por escuela en TODAS las consultas
- Imposible que Escuela A vea datos de Escuela B

### Ejemplo
```
Colegio San Francisco (escuela_id = 1)
- 120 alumnos
- 95 padres
- 450 cargos

Instituto Guadalupe (escuela_id = 2)
- 80 alumnos
- 68 padres
- 320 cargos

Queries automáticos:
SELECT * FROM alumnos WHERE escuela_id = 1
SELECT * FROM cargos WHERE escuela_id = 2
```

---

## Estrategia de Validación

### Primera Escuela (Piloto)
**Objetivo:** Validar que resuelve el problema real.

**Perfil ideal:**
- 80-150 alumnos
- Director accesible
- Dolor real en cobranza (20-40% morosidad)
- Geográficamente cercana (Guadalajara)

**Oferta:**
- 6 meses gratis
- Soporte directo
- Configuración personalizada

**A cambio:**
- 30 minutos de feedback semanal
- Honestidad brutal sobre qué funciona/no funciona

### Métricas de Éxito (Mes 6)
- 80%+ de pagos procesados en el sistema
- 60%+ de padres usando la app
- Morosidad bajó 10%+ vs antes
- NPS 8+/10
- Director dice: "Pagaría por esto"

### Decisión GO/NO-GO
**Continuar si:**
- 3+ escuelas felices
- Dispuestos a pagar $99/alumno/mes
- Referrals orgánicos
- Valor demostrable

**Pivotar o detener si:**
- Escuelas no lo usan después de 3 meses
- No ven valor suficiente para pagar
- Feedback indica que resolvemos problema equivocado

---

## Fases de Desarrollo

### Fase 1 - MVP (Mes 1-3)
**Objetivo:** Sistema funcional básico para validar.

**Incluye:**
- Onboarding de escuela
- CRUD de alumnos (individual e importación)
- Generación de cargos
- Cobro online con Stripe
- Registro de pagos manuales
- Facturación CFDI automática
- Portal básico para padres
- Dashboard de cobranza

**No incluye:**
- Calificaciones
- Asistencias
- Comunicación avanzada
- Reportes complejos
- Apps móviles nativas

### Fase 2 - Consolidación (Mes 4-6)
**Objetivo:** Estabilizar con 5-10 escuelas.

**Agregar:**
- Cobro automático recurrente
- Notificaciones push
- Calificaciones básicas
- Pase de lista
- Generación de boletas
- Reportes de morosidad

### Fase 3 - Crecimiento (Mes 7-12)
**Objetivo:** Llegar a 20-30 escuelas.

**Agregar:**
- Inscripciones online
- CRM de prospectos
- Comunicación padres-maestros
- Analytics avanzados
- Multi-plantel

---

## Pricing

### Modelo de Negocio
- **Base:** $99 MXN/alumno/mes
- **Transaction fee:** 2% sobre pagos procesados (via Stripe)
- **Facturación:** $50 MXN/escuela/mes (margen sobre PAC)

### Ejemplo: Escuela de 100 alumnos
```
Subscripción: 100 × $99 = $9,900/mes
Pagos procesados: 100 × $1,800 × 2% = $3,600/mes
Facturación: $50/mes
TOTAL: $13,550 MXN/mes por escuela
```

### Meta Personal
- $100,000 MXN/mes ganancia
- Requiere: ~8 escuelas de 100 alumnos
- Timeline: 6-9 meses

---

## Tecnología (High-Level)

### Stack
- **Backend:** Laravel (API)
- **Frontend:** React (Web)
- **Base de datos:** MySQL (MVP), PostgreSQL (futuro)
- **Pagos:** Stripe
- **Facturación:** SW Sapien
- **Hosting:** cPanel (MVP), AWS (después)

### Arquitectura
- Dos repositorios separados (backend y frontend)
- API REST para comunicación
- Autenticación con tokens
- Multi-tenant con `escuela_id` en todas las tablas

### Deployment
- **MVP:** Build local → Upload a cPanel vía FTP
- **Futuro:** Git push → Deploy automático a AWS

---

## Riesgos y Mitigaciones

### Riesgo: Escuela no ve valor
**Mitigación:** 
- Selección cuidadosa de piloto
- Onboarding presencial
- Feedback semanal
- Iterar rápido

### Riesgo: Bug mezcla datos entre escuelas
**Mitigación:**
- Filtros automáticos por `escuela_id`
- Testing exhaustivo multi-tenant
- Code review estricto

### Riesgo: Facturación falla
**Mitigación:**
- Testing con certificados reales
- Manejo robusto de errores
- Sistema de reintentos
- Soporte directo en primeros meses

### Riesgo: Over-engineering
**Mitigación:**
- MVP mínimo (solo features críticas)
- Validar con 5-10 escuelas antes de escalar
- No construir features "por si acaso"

---

## Siguientes Pasos

1. **Aprobar esta definición conceptual**
2. **Generar especificación técnica detallada:**
   - Esquema de base de datos
   - Endpoints de API
   - Componentes de UI
   - Flujo de autenticación
3. **Crear lista de tareas ordenadas para desarrollo**
4. **Comenzar con MVP**
