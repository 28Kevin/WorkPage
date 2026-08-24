<?php

namespace Database\Seeders;

use App\Models\Arl;
use App\Models\City;
use App\Models\Eps;
use App\Models\Risk;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $eps = [
            ['name' => 'Nueva EPS', 'code' => 'EPS037'],
            ['name' => 'Sura EPS', 'code' => 'EPS010'],
            ['name' => 'Sanitas EPS', 'code' => 'EPS005'],
            ['name' => 'Salud Total', 'code' => 'EPS002'],
            ['name' => 'Compensar', 'code' => 'EPS008'],
            ['name' => 'Famisanar', 'code' => 'EPS009'],
            ['name' => 'Coosalud', 'code' => 'EPS042'],
            ['name' => 'Mutual SER', 'code' => 'EPS048'],
            ['name' => 'Aliansalud', 'code' => 'EPS001'],
            ['name' => 'SOS - Servicio Occidental de Salud', 'code' => 'EPS018'],
        ];

        foreach ($eps as $item) {
            Eps::updateOrCreate(['name' => $item['name']], $item);
        }

        $arls = [
            ['name' => 'ARL SURA', 'certificate_url' => 'https://arlsura.com/'],
            ['name' => 'Positiva Compañía de Seguros', 'certificate_url' => 'https://www.positiva.gov.co/'],
            ['name' => 'Colmena Seguros', 'certificate_url' => 'https://www.colmenaseguros.com/'],
            ['name' => 'Seguros Bolívar ARL', 'certificate_url' => 'https://www.segurosbolivar.com/'],
            ['name' => 'Axa Colpatria ARL', 'certificate_url' => 'https://www.axacolpatria.co/'],
            ['name' => 'Liberty Seguros ARL', 'certificate_url' => 'https://www.libertycolombia.com.co/'],
            ['name' => 'Mapfre Seguros ARL', 'certificate_url' => 'https://www.mapfre.com.co/'],
        ];

        foreach ($arls as $item) {
            Arl::updateOrCreate(['name' => $item['name']], $item);
        }

        $cities = [
            ['name' => 'Bogotá D.C.', 'department' => 'Cundinamarca'],
            ['name' => 'Medellín', 'department' => 'Antioquia'],
            ['name' => 'Cali', 'department' => 'Valle del Cauca'],
            ['name' => 'Barranquilla', 'department' => 'Atlántico'],
            ['name' => 'Cartagena', 'department' => 'Bolívar'],
            ['name' => 'Bucaramanga', 'department' => 'Santander'],
            ['name' => 'Pereira', 'department' => 'Risaralda'],
            ['name' => 'Manizales', 'department' => 'Caldas'],
            ['name' => 'Cúcuta', 'department' => 'Norte de Santander'],
            ['name' => 'Ibagué', 'department' => 'Tolima'],
            ['name' => 'Santa Marta', 'department' => 'Magdalena'],
            ['name' => 'Villavicencio', 'department' => 'Meta'],
            ['name' => 'Neiva', 'department' => 'Huila'],
            ['name' => 'Armenia', 'department' => 'Quindío'],
            ['name' => 'Pasto', 'department' => 'Nariño'],
        ];

        foreach ($cities as $item) {
            City::updateOrCreate(
                ['name' => $item['name'], 'department' => $item['department']],
                $item,
            );
        }

        $risks = [
            ['name' => 'Trabajo en alturas', 'description' => 'Tareas con riesgo de caída a distinto nivel superior a 2 metros.'],
            ['name' => 'Espacio confinado', 'description' => 'Ingreso a recintos con aberturas limitadas de entrada y salida.'],
            ['name' => 'Manipulación de alimentos', 'description' => 'Preparación, almacenamiento y distribución de alimentos.'],
            ['name' => 'Trabajo en caliente', 'description' => 'Soldadura, corte y actividades con generación de chispa o llama.'],
            ['name' => 'Conducción de vehículos', 'description' => 'Operación de vehículos livianos, pesados o de carga.'],
            ['name' => 'Manejo de cargas', 'description' => 'Levantamiento y transporte manual de cargas.'],
            ['name' => 'Exposición a ruido', 'description' => 'Ambientes con niveles de presión sonora superiores a 85 dB.'],
            ['name' => 'Exposición a sustancias químicas', 'description' => 'Contacto con agentes químicos peligrosos.'],
            ['name' => 'Trabajo con energías peligrosas', 'description' => 'Intervención de sistemas eléctricos, neumáticos o hidráulicos.'],
            ['name' => 'Riesgo biológico', 'description' => 'Exposición a agentes biológicos infecciosos.'],
            ['name' => 'Trabajo en oficina', 'description' => 'Actividades administrativas con exposición a riesgo ergonómico.'],
            ['name' => 'Operación de maquinaria pesada', 'description' => 'Manejo de montacargas, retroexcavadoras y equipos similares.'],
        ];

        foreach ($risks as $item) {
            Risk::updateOrCreate(
                ['slug' => Str::slug($item['name'])],
                [...$item, 'slug' => Str::slug($item['name'])],
            );
        }
    }
}
