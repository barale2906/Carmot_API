<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            CursoSeeder::class,
            ModuloSeeder::class,
            TopicoSeeder::class,
            TemaSeeder::class,
            //ReferidoSeeder::class,
            //SeguimientoSeeder::class,
            //AgendaSeeder::class,
            PoblacionSeeder::class,
            poblacionstatuSeeder::class,
            //SedeSeeder::class,
            //AreaSeeder::class,
            //GrupoSeeder::class,
            //CicloSeeder::class,
            //ProgramacionSeeder::class,
            //MatriculaSeeder::class,
            //EsquemaCalificacionSeeder::class,
            //NotaEstudianteSeeder::class,
            //TipoNotaEsquemaSeeder::class,
            //AsistenciaConfiguracionSeeder::class,
            //AsistenciaClaseProgramadaSeeder::class,
            //AsistenciaSeeder::class,
            ConceptoPagoSeeder::class,
            // Seeders del módulo financiero - Listas de precios
            //LpTipoProductoSeeder::class,
            //LpProductoSeeder::class,
            //LpListaPrecioSeeder::class,
            //LpPrecioProductoSeeder::class,
            //DescuentoSeeder::class,
            //ReciboPagoSeeder::class,
        ]);
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
