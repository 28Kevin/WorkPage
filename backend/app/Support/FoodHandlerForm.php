<?php

namespace App\Support;

/**
 * Catalogo de los examenes del paquete de manipulacion de alimentos. Vive aqui
 * para que el formulario, la validacion y el PDF usen las mismas claves, igual
 * que ExamForm en el examen ocupacional.
 *
 * Cada campo trae el valor normal del examen: el formulario lo precarga y
 * quien diligencia lo confirma o lo corrige con el resultado real.
 *
 * Las etiquetas y los valores normales salen del formato de ejemplo; ajustarlos
 * es editar esta tabla, sin tocar el generador ni la plantilla.
 */
class FoodHandlerForm
{
    /**
     * Paneles de laboratorio, en el orden en que aparecen en el documento.
     * 'page' es la pagina del PDF y 'signature' quien la firma.
     */
    public const PANELS = [
        'frotis_garganta' => [
            'label' => 'Frotis de garganta',
            'page' => 1,
            'signature' => 'bacteriologist',
            'groups' => [
                'resultado' => [
                    'label' => 'Resultado',
                    'fields' => [
                        'coloracion' => [
                            'label' => 'Coloración de Gram',
                            'normal' => 'No se observan gérmenes patógenos',
                        ],
                        'flora' => [
                            'label' => 'Flora',
                            'normal' => 'Flora normal de vías respiratorias superiores',
                        ],
                        'observaciones' => [
                            'label' => 'Observaciones',
                            'normal' => '',
                        ],
                    ],
                ],
            ],
        ],

        'koh' => [
            'label' => 'KOH (examen micológico directo)',
            'page' => 1,
            'signature' => 'bacteriologist',
            'groups' => [
                'resultado' => [
                    'label' => 'Resultado',
                    'fields' => [
                        'muestra' => ['label' => 'Muestra', 'normal' => 'Uñas de manos'],
                        'resultado' => [
                            'label' => 'Resultado',
                            'normal' => 'No se observan elementos micóticos (hifas ni esporas)',
                        ],
                        'observaciones' => ['label' => 'Observaciones', 'normal' => ''],
                    ],
                ],
            ],
        ],

        'coprologico' => [
            'label' => 'Coprológico',
            'page' => 1,
            'signature' => 'bacteriologist',
            'groups' => [
                'macroscopico' => [
                    'label' => 'Examen macroscópico',
                    'fields' => [
                        'aspecto' => ['label' => 'Aspecto', 'normal' => 'Pastoso'],
                        'color' => ['label' => 'Color', 'normal' => 'Café'],
                        'moco' => ['label' => 'Moco', 'normal' => 'Negativo'],
                        'sangre_oculta' => ['label' => 'Sangre oculta', 'normal' => 'Negativo'],
                        'ph' => ['label' => 'pH', 'normal' => '7.0'],
                    ],
                ],
                'microscopico' => [
                    'label' => 'Examen microscópico',
                    'fields' => [
                        'leucocitos' => ['label' => 'Leucocitos', 'normal' => 'No se observan'],
                        'eritrocitos' => ['label' => 'Eritrocitos', 'normal' => 'No se observan'],
                        'levaduras' => ['label' => 'Levaduras', 'normal' => 'No se observan'],
                        'parasitos' => [
                            'label' => 'Parásitos',
                            'normal' => 'No se observan quistes, trofozoítos ni huevos de parásitos',
                        ],
                    ],
                ],
            ],
        ],

        'vdrl' => [
            'label' => 'Serología VDRL',
            'page' => 3,
            'signature' => 'bacteriologist',
            'groups' => [
                'resultado' => [
                    'label' => 'Resultado',
                    'fields' => [
                        'resultado' => ['label' => 'Resultado', 'normal' => 'No reactivo'],
                        'titulo' => ['label' => 'Título', 'normal' => 'No aplica'],
                        'metodo' => ['label' => 'Método', 'normal' => 'Aglutinación en placa'],
                    ],
                ],
            ],
        ],

        'uroanalisis' => [
            'label' => 'Uroanálisis',
            'page' => 3,
            'signature' => 'bacteriologist',
            'groups' => [
                'fisico' => [
                    'label' => 'Examen físico',
                    'fields' => [
                        'color' => ['label' => 'Color', 'normal' => 'Amarillo'],
                        'aspecto' => ['label' => 'Aspecto', 'normal' => 'Transparente'],
                        'densidad' => ['label' => 'Densidad', 'normal' => '1.020'],
                        'ph' => ['label' => 'pH', 'normal' => '6.0'],
                    ],
                ],
                'quimico' => [
                    'label' => 'Examen químico',
                    'fields' => [
                        'proteinas' => ['label' => 'Proteínas', 'normal' => 'Negativo'],
                        'glucosa' => ['label' => 'Glucosa', 'normal' => 'Negativo'],
                        'cetonas' => ['label' => 'Cetonas', 'normal' => 'Negativo'],
                        'sangre' => ['label' => 'Sangre', 'normal' => 'Negativo'],
                        'bilirrubina' => ['label' => 'Bilirrubina', 'normal' => 'Negativo'],
                        'urobilinogeno' => ['label' => 'Urobilinógeno', 'normal' => 'Normal'],
                        'nitritos' => ['label' => 'Nitritos', 'normal' => 'Negativo'],
                        'leucocitos_esterasa' => ['label' => 'Leucocitos (esterasa)', 'normal' => 'Negativo'],
                    ],
                ],
                'microscopico' => [
                    'label' => 'Examen microscópico (sedimento)',
                    'fields' => [
                        'leucocitos' => ['label' => 'Leucocitos', 'normal' => '0 - 2 x campo'],
                        'eritrocitos' => ['label' => 'Eritrocitos', 'normal' => '0 - 1 x campo'],
                        'celulas_epiteliales' => ['label' => 'Células epiteliales', 'normal' => 'Escasas'],
                        'bacterias' => ['label' => 'Bacterias', 'normal' => 'No se observan'],
                        'cilindros' => ['label' => 'Cilindros', 'normal' => 'No se observan'],
                        'cristales' => ['label' => 'Cristales', 'normal' => 'No se observan'],
                        'moco' => ['label' => 'Moco', 'normal' => 'Escaso'],
                    ],
                ],
            ],
        ],
    ];

    /** Quien firma cada pagina. El nombre y la imagen salen de la configuracion. */
    public const SIGNATURES = [
        'bacteriologist' => 'Bacterióloga',
        'physician' => 'Médico general',
        'food_engineer' => 'Ingeniera de alimentos',
    ];

    /**
     * Valores normales de todos los paneles, con la misma forma que se guarda
     * en la base: panel > grupo > campo.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public static function normalResults(): array
    {
        $results = [];

        foreach (self::PANELS as $panel => $definition) {
            foreach ($definition['groups'] as $group => $block) {
                foreach ($block['fields'] as $field => $meta) {
                    $results[$panel][$group][$field] = $meta['normal'];
                }
            }
        }

        return $results;
    }

    /**
     * Catalogo que consume el formulario del panel: la misma estructura, pero
     * como listas con clave y etiqueta para poder recorrerla en Vue.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function catalog(): array
    {
        return array_map(fn (string $panel) => [
            'key' => $panel,
            'label' => self::PANELS[$panel]['label'],
            'page' => self::PANELS[$panel]['page'],
            'signature' => self::PANELS[$panel]['signature'],
            'groups' => array_map(fn (string $group) => [
                'key' => $group,
                'label' => self::PANELS[$panel]['groups'][$group]['label'],
                'fields' => array_map(fn (string $field) => [
                    'key' => $field,
                    'label' => self::PANELS[$panel]['groups'][$group]['fields'][$field]['label'],
                    'normal' => self::PANELS[$panel]['groups'][$group]['fields'][$field]['normal'],
                ], array_keys(self::PANELS[$panel]['groups'][$group]['fields'])),
            ], array_keys(self::PANELS[$panel]['groups'])),
        ], array_keys(self::PANELS));
    }

    /**
     * Conserva solo las claves conocidas y rellena con el valor normal lo que
     * no venga: el documento nunca sale con un campo de laboratorio vacio.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, array<string, array<string, string>>>
     */
    public static function mergeResults(array $submitted): array
    {
        $results = [];

        foreach (self::PANELS as $panel => $definition) {
            foreach ($definition['groups'] as $group => $block) {
                foreach ($block['fields'] as $field => $meta) {
                    $value = $submitted[$panel][$group][$field] ?? null;

                    $results[$panel][$group][$field] = is_scalar($value)
                        ? trim((string) $value)
                        : $meta['normal'];
                }
            }
        }

        return $results;
    }

    /**
     * Reglas de lab_results. Se usa 'array:claves' como en el examen
     * ocupacional: asi una clave inventada falla la validacion en vez de
     * colarse y perderse en silencio.
     */
    public static function validationRules(): array
    {
        $rules = [
            'lab_results' => ['nullable', 'array:'.implode(',', array_keys(self::PANELS))],
        ];

        foreach (self::PANELS as $panel => $definition) {
            $rules['lab_results.'.$panel] = [
                'sometimes', 'array:'.implode(',', array_keys($definition['groups'])),
            ];

            foreach ($definition['groups'] as $group => $block) {
                $rules['lab_results.'.$panel.'.'.$group] = [
                    'sometimes', 'array:'.implode(',', array_keys($block['fields'])),
                ];

                $rules['lab_results.'.$panel.'.'.$group.'.*'] = ['nullable', 'string', 'max:300'];
            }
        }

        return $rules;
    }
}
