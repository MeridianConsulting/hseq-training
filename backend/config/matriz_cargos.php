<?php

declare(strict_types=1);

/**
 * Cargos por proceso según la hoja MATRIZ POR CARGO del programa HSEQ-PRG-10.
 * El nombre visible es el del Excel. Los alias cruzan meridian_personal.cargos
 * (no se infiere la lista desde quién está contratado hoy).
 *
 * @return array{por_proceso: array<string, list<string|array{nombre:string, alias?:list<string>}>>}
 */
return [
    'por_proceso' => [
        'GESTION ESTRATEGICA' => [
            'GERENTE GENERAL',
            'SUBGERENTE',
        ],
        'GESTION ADMINISTRATIVA Y FINANCIERA' => [
            'GERENTE ADMINISTRATIVA Y FINANCIERA',
            'GERENTE ADMON Y FINANCIERO',
            'ASISTENTE ADMINISTRATIVO',
        ],
        'GESTION HSEQ' => [
            'COORDINADOR HSEQ',
            'PROFESIONAL HSEQ',
            'SOPORTE HSEQ',
        ],
        'GESTION DE PROYECTOS' => [
            [
                'nombre' => 'PROFESIONAL DE PROYECTOS',
                'alias' => ['PROFESIONAL DE PROYECTOS'],
            ],
            [
                'nombre' => 'ASISTENTE DE COMPANY MAN D1',
                'alias' => [
                    'ASISTENTE DE COMPANY MAN D1',
                    'ING COMPANY D1',
                    'ASISTENTE COMPANY D1',
                ],
            ],
            [
                'nombre' => 'ASISTENTE DE COMPANY MAN D2',
                'alias' => [
                    'ASISTENTE DE COMPANY MAN D2',
                    'ASISTENTE COMPANY D2',
                ],
            ],
            [
                'nombre' => 'ASISTENTE DE COMPANY MAN D3',
                'alias' => [
                    'ASISTENTE DE COMPANY MAN D3',
                    'ASISTENTE COMPANY D3',
                    'SOPORTE OPERATIVO D3',
                ],
            ],
            [
                'nombre' => 'COMPANY MAN B1',
                'alias' => ['COMPANY MAN B1', 'COMPANYMAN B1'],
            ],
            [
                'nombre' => 'COMPANY MAN B2',
                'alias' => ['COMPANY MAN B2', 'COMPANYMAN B2'],
            ],
            [
                'nombre' => 'COMPANY MAN B3',
                'alias' => ['COMPANY MAN B3', 'COMPANYMAN B3'],
            ],
        ],
    ],
];
