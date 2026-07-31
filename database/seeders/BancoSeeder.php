<?php

namespace Database\Seeders;

use App\Models\Configuracion\Banco;
use Illuminate\Database\Seeder;

class BancoSeeder extends Seeder
{
    /**
     * Pobla el catálogo de bancos con las principales entidades financieras de Colombia.
     */
    public function run(): void
    {
        $bancos = [
            ['nombre' => 'Bancolombia',                       'codigo' => '007'],
            ['nombre' => 'Banco de Bogotá',                   'codigo' => '001'],
            ['nombre' => 'Davivienda',                        'codigo' => '051'],
            ['nombre' => 'BBVA Colombia',                     'codigo' => '013'],
            ['nombre' => 'Banco Popular',                     'codigo' => '002'],
            ['nombre' => 'Banco de Occidente',                'codigo' => '023'],
            ['nombre' => 'Banco Agrario de Colombia',         'codigo' => '040'],
            ['nombre' => 'Banco Caja Social',                 'codigo' => '032'],
            ['nombre' => 'Banco GNB Sudameris',               'codigo' => '012'],
            ['nombre' => 'Banco Santander de Negocios Colombia', 'codigo' => '065'],
            ['nombre' => 'Banco AV Villas',                   'codigo' => '052'],
            ['nombre' => 'Banco Colpatria',                   'codigo' => '019'],
            ['nombre' => 'Banco ProCredit',                   'codigo' => '058'],
            ['nombre' => 'Banco Mundo Mujer',                 'codigo' => '047'],
            ['nombre' => 'Banco W',                           'codigo' => '053'],
            ['nombre' => 'Banco Finandina',                   'codigo' => '063'],
            ['nombre' => 'Banco Falabella',                   'codigo' => '062'],
            ['nombre' => 'Bancamía',                          'codigo' => '059'],
            ['nombre' => 'Nequi',                             'codigo' => null],
            ['nombre' => 'Daviplata',                         'codigo' => null],
            ['nombre' => 'Movii',                             'codigo' => null],
            ['nombre' => 'Nubank Colombia',                   'codigo' => null],
            ['nombre' => 'Rappipay',                          'codigo' => null],
            ['nombre' => 'Bancoomeva',                        'codigo' => '061'],
            ['nombre' => 'Coofinep Cooperativa Financiera',   'codigo' => '291'],
            ['nombre' => 'Cotrafa Cooperativa Financiera',    'codigo' => '289'],
            ['nombre' => 'Confiar Cooperativa Financiera',    'codigo' => '292'],
            ['nombre' => 'JFK Cooperativa Financiera',        'codigo' => '286'],
        ];

        foreach ($bancos as $datos) {
            Banco::firstOrCreate(
                ['nombre' => $datos['nombre']],
                ['codigo' => $datos['codigo'], 'status' => 1]
            );
        }
    }
}
