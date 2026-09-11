-- Deja una sola vigencia por duración: 1 año = 12 meses, 2 años = 24 meses.
-- Prefiere la fila en AÑOS. Reasigna capacitaciones e inactiva el duplicado.
-- Uso: php database/20_vigencias_equivalentes.php

USE meridian_capacitaciones;

UPDATE capacitaciones c
INNER JOIN vigencias dup ON dup.vigencia_id = c.vigencia_id
INNER JOIN vigencias can ON can.activo = 1
  AND can.unidad = 'ANIOS'
  AND can.cantidad * 12 = CASE
        WHEN dup.unidad = 'MESES' THEN dup.cantidad
        WHEN dup.unidad = 'ANIOS' THEN dup.cantidad * 12
        ELSE NULL
      END
SET c.vigencia_id = can.vigencia_id
WHERE dup.unidad = 'MESES'
  AND dup.cantidad IN (12, 24)
  AND dup.vigencia_id <> can.vigencia_id;

UPDATE vigencias dup
INNER JOIN vigencias can ON can.activo = 1
  AND can.unidad = 'ANIOS'
  AND can.cantidad * 12 = CASE
        WHEN dup.unidad = 'MESES' THEN dup.cantidad
        WHEN dup.unidad = 'ANIOS' THEN dup.cantidad * 12
        ELSE NULL
      END
SET dup.activo = 0
WHERE dup.activo = 1
  AND dup.unidad = 'MESES'
  AND dup.cantidad IN (12, 24)
  AND dup.vigencia_id <> can.vigencia_id;
