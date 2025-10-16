<?php

namespace Database\Seeders;

use App\Models\Academico\Topico;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TopicoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear tópicos con datos más realistas
        $topicos = [
            [
                'nombre' => 'Introducción a la Programación',
                'descripcion' => 'Conceptos básicos de programación y algoritmos',
                'duracion' => 4.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Estructuras de Datos',
                'descripcion' => 'Arrays, listas, pilas, colas y árboles',
                'duracion' => 6.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Bases de Datos Relacionales',
                'descripcion' => 'SQL, normalización y diseño de bases de datos',
                'duracion' => 8.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Desarrollo Web Frontend',
                'descripcion' => 'HTML, CSS, JavaScript y frameworks modernos',
                'duracion' => 10.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Desarrollo Web Backend',
                'descripcion' => 'APIs REST, autenticación y autorización',
                'duracion' => 8.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Testing y Calidad de Código',
                'descripcion' => 'Pruebas unitarias, integración y buenas prácticas',
                'duracion' => 5.0,
                'status' => 1,
            ],
            [
                'nombre' => 'DevOps y Despliegue',
                'descripcion' => 'Docker, CI/CD y gestión de servidores',
                'duracion' => 6.0,
                'status' => 1,
            ],
            [
                'nombre' => 'Seguridad Informática',
                'descripcion' => 'Principios de seguridad y vulnerabilidades comunes',
                'duracion' => 4.0,
                'status' => 1,
            ],
        ];

        foreach ($topicos as $topico) {
            Topico::create($topico);
        }

        // Crear tópicos adicionales con factory
        Topico::factory(20)->create();
    }
}
