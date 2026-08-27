<?php

declare(strict_types=1);

/**
 * Definicion de los catalogos propios del modulo HSEQ.
 *
 * La clave es el segmento que viaja en la URL (/api/catalogs/{tipo}). Solo se aceptan
 * los tipos declarados aqui, de modo que el nombre de tabla y columna nunca proviene
 * de la peticion.
 *
 * soft_delete indica si la tabla tiene columna `activo`. Las tablas areas, procesos y
 * roles no la tienen, por lo que en esas el borrado es definitivo y queda bloqueado
 * por las llaves foraneas cuando el registro esta en uso.
 */
return [
    'areas' => [
        'tabla' => 'areas',
        'pk' => 'area_id',
        'etiqueta' => 'Areas',
        'soft_delete' => false,
        'campos' => [
            'nombre' => 'required|string|max:120',
        ],
    ],

    'procesos' => [
        'tabla' => 'procesos',
        'pk' => 'proceso_id',
        'etiqueta' => 'Procesos',
        'soft_delete' => false,
        'campos' => [
            'nombre' => 'required|string|max:120',
        ],
    ],

    'roles' => [
        'tabla' => 'roles',
        'pk' => 'role_id',
        'etiqueta' => 'Roles',
        'soft_delete' => false,
        'campos' => [
            'nombre' => 'required|string|max:120',
        ],
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
    ],

    'modalidades' => [
        'tabla' => 'modalidades',
        'pk' => 'modalidad_id',
        'etiqueta' => 'Modalidades',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:60',
        ],
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
    ],

    'proveedores' => [
        'tabla' => 'proveedores_capacitadores',
        'pk' => 'proveedor_id',
        'etiqueta' => 'Proveedores capacitadores',
        'soft_delete' => true,
        'campos' => [
            'nombre' => 'required|string|max:150',
        ],
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
    ],
];
