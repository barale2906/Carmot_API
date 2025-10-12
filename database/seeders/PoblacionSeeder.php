<?php

namespace Database\Seeders;

use App\Models\Configuracion\Poblacion;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PoblacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $row = 1;

        if(($handle = fopen(public_path() . '/csv/poblaciones.csv', 'r')) !== false) {

            while(($data = fgetcsv($handle, 26000, ';')) !== false) {

                $row++;

                try {

                    Poblacion::create([
                        'pais'                  =>strval($data[0]),
                        'provincia'             =>strval($data[1]),
                        'nombre'                =>strval($data[2]),
                        'latitud'               =>doubleval($data[3]),
                        'longitud'              =>doubleval($data[4]),
                    ]);

                }catch(Exception $exception){
                    Log::info('Line: ' . $row . ' poblaciones with error: ' . $exception->getMessage().' código: '.$exception->getCode().' linea: '.$exception->getLine());
                }
            }
        }

        fclose($handle);

    }
}
