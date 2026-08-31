<?php

declare(strict_types=1);

/**
 * Definicion de los catalogos propios del modulo HSEQ.
 *
 * La clave es el segmento de URL (/api/catalogs/{tipo}). Tabla y columnas nunca
 * provienen de la peticion.
 *
 * soft_delete: la tabla tiene columna `activo`. El DELETE de la API inactiva;
 * no hay borrado fisico.
 *
 * dependencias: tablas/columnas para contar usos (mensajes). No se usan para borrar.
 */
return [
    'areas' => [
        'tabla' => 'areas',
        'pk' => 'area_id',
        'etiqueta' => 'Areas',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:120',
        ],
        'dependencias' => [
            ['tabla' => 'matriz_aplicabilidad', 'columna' => 'area_id', 'etiqueta' => 'filas de matriz'],
            ['tabla' => 'asignaciones_capacitacion', 'columna' => 'area_id', 'etiqueta' => 'asignaciones'],
            ['tabla' => 'plan_anual_detalle', 'columna' => 'area_id', 'etiqueta' => 'detalle de plan anual'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar esta área porque tiene información asociada. Puede inactivarla para evitar su uso en nuevos registros.',
    ],

    'procesos' => [
        'tabla' => 'procesos',
        'pk' => 'proceso_id',
        'etiqueta' => 'Procesos',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:120',
        ],
        'dependencias' => [
            ['tabla' => 'matriz_aplicabilidad', 'columna' => 'proceso_id', 'etiqueta' => 'filas de matriz'],
            ['tabla' => 'asignaciones_capacitacion', 'columna' => 'proceso_id', 'etiqueta' => 'asignaciones'],
            ['tabla' => 'plan_anual_detalle', 'columna' => 'proceso_id', 'etiqueta' => 'detalle de plan anual'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar este proceso porque tiene información asociada. Puede inactivarlo para evitar su uso en nuevos registros.',
    ],

    'roles' => [
        'tabla' => 'roles',
        'pk' => 'role_id',
        'etiqueta' => 'Roles',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:120',
        ],
        'dependencias' => [
            ['tabla' => 'user_roles', 'columna' => 'role_id', 'etiqueta' => 'usuarios'],
            ['tabla' => 'rol_permisos', 'columna' => 'role_id', 'etiqueta' => 'permisos del rol'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar este rol porque tiene información asociada. Puede inactivarlo para evitar su uso en nuevos registros.',
    ],

    'categorias' => [
        'tabla' => 'categorias_capacitacion',
        'pk' => 'categoria_id',
        'etiqueta' => 'Categorias de capacitacion',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ],
        'dependencias' => [
            ['tabla' => 'capacitaciones', 'columna' => 'categoria_id', 'etiqueta' => 'capacitaciones'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar esta categoría porque tiene capacitaciones asociadas. Puede inactivarla para evitar su uso en nuevos registros.',
    ],

    'tipos-capacitacion' => [
        'tabla' => 'tipos_capacitacion',
        'pk' => 'tipo_capacitacion_id',
        'etiqueta' => 'Tipos de capacitacion',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:255',
        ],
        'dependencias' => [
            ['tabla' => 'capacitaciones', 'columna' => 'tipo_capacitacion_id', 'etiqueta' => 'capacitaciones'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar este tipo porque tiene capacitaciones asociadas. Puede inactivarlo para evitar su uso en nuevos registros.',
    ],

    'modalidades' => [
        'tabla' => 'modalidades',
        'pk' => 'modalidad_id',
        'etiqueta' => 'Modalidades',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:60',
        ],
        'dependencias' => [
            ['tabla' => 'capacitaciones', 'columna' => 'modalidad_default_id', 'etiqueta' => 'capacitaciones'],
            ['tabla' => 'sesiones_capacitacion', 'columna' => 'modalidad_id', 'etiqueta' => 'sesiones'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar esta modalidad porque tiene información asociada. Puede inactivarla para evitar su uso en nuevos registros.',
    ],

    'periodicidades' => [
        'tabla' => 'periodicidades',
        'pk' => 'periodicidad_id',
        'etiqueta' => 'Periodicidades',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:80',
            'cantidad' => 'required|integer|min:1',
            'unidad' => 'required|in:DIAS,MESES,ANIOS',
        ],
        'dependencias' => [
            ['tabla' => 'capacitaciones', 'columna' => 'periodicidad_default_id', 'etiqueta' => 'capacitaciones'],
            ['tabla' => 'matriz_aplicabilidad', 'columna' => 'periodicidad_id', 'etiqueta' => 'filas de matriz'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar esta periodicidad porque tiene información asociada. Puede inactivarla para evitar su uso en nuevos registros.',
    ],

    'vigencias' => [
        'tabla' => 'vigencias',
        'pk' => 'vigencia_id',
        'etiqueta' => 'Vigencias',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:80',
            'cantidad' => 'required|integer|min:1',
            'unidad' => 'required|in:DIAS,MESES,ANIOS',
        ],
        'dependencias' => [
            ['tabla' => 'capacitaciones', 'columna' => 'vigencia_id', 'etiqueta' => 'capacitaciones'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar esta vigencia porque tiene capacitaciones asociadas. Puede inactivarla para evitar su uso en nuevos registros.',
    ],

    'proveedores' => [
        'tabla' => 'proveedores_capacitadores',
        'pk' => 'proveedor_id',
        'etiqueta' => 'Proveedores capacitadores',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:150',
        ],
        'dependencias' => [
            ['tabla' => 'capacitaciones', 'columna' => 'proveedor_default_id', 'etiqueta' => 'capacitaciones'],
            ['tabla' => 'sesiones_capacitacion', 'columna' => 'proveedor_id', 'etiqueta' => 'sesiones'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar este proveedor porque tiene información asociada. Puede inactivarlo para evitar su uso en nuevos registros.',
    ],

    'ubicaciones' => [
        'tabla' => 'ubicaciones',
        'pk' => 'ubicacion_id',
        'etiqueta' => 'Ubicaciones',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string|max:255',
        ],
        'dependencias' => [
            ['tabla' => 'sesiones_capacitacion', 'columna' => 'ubicacion_id', 'etiqueta' => 'sesiones'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar esta ubicación porque tiene sesiones asociadas. Puede inactivarla para evitar su uso en nuevos registros.',
    ],

    'fuentes-normativas' => [
        'tabla' => 'fuentes_normativas',
        'pk' => 'fuente_normativa_id',
        'etiqueta' => 'Fuentes normativas',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:180',
            'descripcion' => 'nullable|string',
        ],
        'dependencias' => [
            ['tabla' => 'capacitaciones', 'columna' => 'fuente_normativa_id', 'etiqueta' => 'capacitaciones'],
        ],
        'mensaje_dependencias' => 'No es posible eliminar esta fuente normativa porque tiene capacitaciones asociadas. Puede inactivarla para evitar su uso en nuevos registros.',
    ],
];
