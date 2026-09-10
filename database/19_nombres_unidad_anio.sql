-- Muestra AÑO/AÑOS en nombres de vigencia y periodicidad.
-- El ENUM interno sigue siendo ANIOS. No borra historial.
-- Uso: mysql -u root --default-character-set=utf8mb4 meridian_capacitaciones < database/19_nombres_unidad_anio.sql
-- Preferible: php database/19_nombres_unidad_anio.php (respeta duplicados).

USE meridian_capacitaciones;

UPDATE vigencias
SET nombre = REPLACE(nombre, 'ANIOS', 'AÑOS')
WHERE nombre LIKE '%ANIOS%';

UPDATE vigencias
SET nombre = REPLACE(nombre, 'ANIO', 'AÑO')
WHERE nombre LIKE '%ANIO%';

UPDATE periodicidades
SET nombre = REPLACE(nombre, 'ANIOS', 'AÑOS')
WHERE nombre LIKE '%ANIOS%';

UPDATE periodicidades
SET nombre = REPLACE(nombre, 'ANIO', 'AÑO')
WHERE nombre LIKE '%ANIO%';
