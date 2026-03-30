-- Script para limpiar y recrear la tabla concepto_precios correctamente
-- Ejecutar desde MySQL o desde el contenedor de Docker

-- 1. Eliminar la tabla si existe (para limpiar el error del índice duplicado)
DROP TABLE IF EXISTS `concepto_precios`;

-- 2. Recrear la tabla correctamente
CREATE TABLE `concepto_precios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `concepto_type` varchar(255) NOT NULL,
  `concepto_id` bigint unsigned NOT NULL,
  `tipo` enum('fecha_fija','dias_vencimiento') NOT NULL DEFAULT 'dias_vencimiento',
  `desde_fecha` date DEFAULT NULL,
  `hasta_fecha` date DEFAULT NULL,
  `desde_dias` int DEFAULT NULL,
  `hasta_dias` int DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `orden` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `concepto_precios_concepto_type_concepto_id_index` (`concepto_type`,`concepto_id`),
  KEY `concepto_precios_tipo_index` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Actualizar tabla conceptos_plantilla (eliminar campos de porcentajes)
ALTER TABLE `conceptos_plantilla`
  DROP COLUMN IF EXISTS `descuento_pronto_pago_porcentaje`,
  DROP COLUMN IF EXISTS `dias_descuento_pronto_pago`,
  DROP COLUMN IF EXISTS `recargo_mora_porcentaje`;

-- 4. Actualizar tabla conceptos_plan_pago (eliminar campos de montos)
ALTER TABLE `conceptos_plan_pago`
  DROP COLUMN IF EXISTS `monto`,
  DROP COLUMN IF EXISTS `descuento_pronto_pago`,
  DROP COLUMN IF EXISTS `recargo_mora`;
