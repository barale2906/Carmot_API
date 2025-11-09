<?php

namespace Database\Seeders;

use App\Models\Academico\Tema;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder para el modelo Tema
 *
 * Crea temas de ejemplo con datos realistas para desarrollo y pruebas.
 */
class TemaSeeder extends Seeder
{
    /**
     * Ejecuta el seeder.
     *
     * @return void
     */
    public function run(): void
    {
        // Crear temas con datos más realistas
        $temas = [
            [
                'nombre' => 'Variables y Tipos de Datos',
                'descripcion' => 'Introducción a variables, constantes y tipos de datos básicos',
                'duracion' => 1.5,
                'status' => 1,
            ],
            [
                'nombre' => 'Estructuras de Control',
                'descripcion' => 'Condicionales (if/else) y bucles (for/while)',
                'duracion' => 2.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Funciones y Métodos',
                'descripcion' => 'Creación y uso de funciones, parámetros y valores de retorno',
                'duracion' => 2.5,
                'status' => 1,
            ],
            [
                'nombre' => 'Arrays y Listas',
                'descripcion' => 'Trabajo con arrays unidimensionales y multidimensionales',
                'duracion' => 2.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Pilas y Colas',
                'descripcion' => 'Implementación y uso de estructuras LIFO y FIFO',
                'duracion' => 2.5,
                'status' => 1,
            ],
            [
                'nombre' => 'Árboles y Grafos',
                'descripcion' => 'Estructuras de datos jerárquicas y relaciones complejas',
                'duracion' => 3.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Modelo Relacional',
                'descripcion' => 'Conceptos de tablas, relaciones y claves primarias/foráneas',
                'duracion' => 2.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Consultas SQL Básicas',
                'descripcion' => 'SELECT, INSERT, UPDATE, DELETE y operadores básicos',
                'duracion' => 3.0,
                'status' => 1,
            ],
            [
                'nombre' => 'JOINs y Subconsultas',
                'descripcion' => 'Relaciones entre tablas y consultas anidadas',
                'duracion' => 2.5,
                'status' => 1,
            ],
            [
                'nombre' => 'HTML5 y Semántica',
                'descripcion' => 'Estructura semántica y elementos modernos de HTML',
                'duracion' => 2.0,
                'status' => 1,
            ],
            [
                'nombre' => 'CSS3 y Flexbox',
                'descripcion' => 'Estilos avanzados y layouts flexibles',
                'duracion' => 2.5,
                'status' => 1,
            ],
            [
                'nombre' => 'JavaScript ES6+',
                'descripcion' => 'Arrow functions, destructuring, async/await',
                'duracion' => 3.0,
                'status' => 1,
            ],
            [
                'nombre' => 'React y Componentes',
                'descripcion' => 'Creación de componentes reutilizables y gestión de estado',
                'duracion' => 4.0,
                'status' => 1,
            ],
            [
                'nombre' => 'APIs REST',
                'descripcion' => 'Diseño y consumo de APIs RESTful',
                'duracion' => 3.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Autenticación JWT',
                'descripcion' => 'Implementación de tokens JWT para autenticación',
                'duracion' => 2.5,
                'status' => 1,
            ],
            [
                'nombre' => 'Middleware y Validación',
                'descripcion' => 'Procesamiento de requests y validación de datos',
                'duracion' => 2.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Pruebas Unitarias',
                'descripcion' => 'Escritura y ejecución de tests unitarios',
                'duracion' => 2.5,
                'status' => 1,
            ],
            [
                'nombre' => 'Pruebas de Integración',
                'descripcion' => 'Testing de componentes y sistemas completos',
                'duracion' => 2.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Docker y Contenedores',
                'descripcion' => 'Creación y gestión de contenedores Docker',
                'duracion' => 3.0,
                'status' => 1,
            ],
            [
                'nombre' => 'CI/CD con GitHub Actions',
                'descripcion' => 'Automatización de despliegues y pipelines',
                'duracion' => 2.5,
                'status' => 1,
            ],
            [
                'nombre' => 'OWASP Top 10',
                'descripcion' => 'Principales vulnerabilidades de seguridad web',
                'duracion' => 2.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Encriptación y Hashing',
                'descripcion' => 'Protección de datos sensibles y contraseñas',
                'duracion' => 1.5,
                'status' => 1,
            ],
        ];

        foreach ($temas as $tema) {
            Tema::create($tema);
        }

        // Crear temas adicionales con factory
        Tema::factory(30)->create();
    }
}
