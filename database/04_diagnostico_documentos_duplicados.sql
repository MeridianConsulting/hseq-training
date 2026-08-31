-- Diagnostico de documentos duplicados en meridian_personal.personas.
-- Solo SELECT. No altera ni elimina datos.
-- El UNIQUE actual es (tipo_documento_id, numero_documento).
-- HSEQ valida unicidad del numero_documento independiente del tipo.

USE meridian_personal;

-- Mismo numero de documento con distinto tipo (conflicto para la regla HSEQ).
SELECT
    numero_documento,
    COUNT(*) AS cantidad,
    GROUP_CONCAT(persona_id ORDER BY persona_id) AS persona_ids,
    GROUP_CONCAT(tipo_documento_id ORDER BY persona_id) AS tipos_documento
FROM personas
GROUP BY numero_documento
HAVING COUNT(*) > 1
ORDER BY cantidad DESC, numero_documento ASC;

-- Duplicados del UNIQUE compuesto (no deberia devolver filas si el indice esta sano).
SELECT
    tipo_documento_id,
    numero_documento,
    COUNT(*) AS cantidad,
    GROUP_CONCAT(persona_id ORDER BY persona_id) AS persona_ids
FROM personas
GROUP BY tipo_documento_id, numero_documento
HAVING COUNT(*) > 1
ORDER BY cantidad DESC;
