<?php

/*
 * CHG-141: vocabulario de Formación según el tipo de estudio (jerga colombiana).
 *
 * `units` se declara por familia de formación (academic = pregrado, especialización, maestría y doctorado) y
 * cada término es [singular, plural, género]. Cada persona puede renombrarlos por tipo en Formación → Ajustes
 * (`module_settings`, módulo `learning`, clave `vocabulary`). Las claves internas (`period`, `subject`…) no cambian.
 */
return [
    'types' => [
        'degree' => 'Pregrado',
        'specialization' => 'Especialización',
        'masters' => 'Maestría',
        'doctorate' => 'Doctorado',
        'course' => 'Curso',
        'personal' => 'Ruta personal',
    ],

    'units' => [
        'academic' => [
            'period' => ['semestre', 'semestres', 'm'],
            'subject' => ['materia', 'materias', 'f'],
            'topic' => ['tema', 'temas', 'm'],
        ],
        'course' => [
            'module' => ['módulo', 'módulos', 'm'],
            'topic' => ['tema', 'temas', 'm'],
        ],
        'personal' => [
            'stage' => ['etapa', 'etapas', 'f'],
            'course' => ['curso', 'cursos', 'm'],
            'topic' => ['tema', 'temas', 'm'],
        ],
    ],

    /** Respaldo para un tipo de contenido que la familia no declara. */
    'fallback' => [
        'period' => ['periodo', 'periodos', 'm'],
        'subject' => ['materia', 'materias', 'f'],
        'module' => ['módulo', 'módulos', 'm'],
        'topic' => ['tema', 'temas', 'm'],
        'stage' => ['etapa', 'etapas', 'f'],
        'course' => ['curso', 'cursos', 'm'],
    ],

    /** Longitud máxima de un término personalizado. */
    'term_max' => 30,
];
