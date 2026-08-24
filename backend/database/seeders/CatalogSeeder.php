<?php

namespace Database\Seeders;

use App\Models\Afp;
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

        // Enlace directo a la pagina donde cada ARL entrega el certificado.
        $arls = [
            ['name' => 'ARL SURA', 'certificate_url' => 'https://www.sura.co/arl/afiliacion/consulta'],
            ['name' => 'Positiva Compañía de Seguros', 'certificate_url' => 'https://operacionesarl.positiva.gov.co'],
            ['name' => 'Colmena Seguros', 'certificate_url' => 'https://www.colmenaseguros.com/certificados-de-afiliacion'],
            ['name' => 'Seguros Bolívar ARL', 'certificate_url' => 'https://www.segurosbolivar.com/arl'],
            ['name' => 'Axa Colpatria ARL', 'certificate_url' => 'https://www.axacolpatria.co/arl/certificacion-afiliacion-arl'],
        ];

        foreach ($arls as $item) {
            Arl::updateOrCreate(['name' => $item['name']], [...$item, 'active' => true]);
        }

        /*
         * Liberty no es una ARL y Mapfre ya no opera en Colombia. Se desactivan
         * en vez de borrarse: hay examenes emitidos que todavia las referencian.
         */
        Arl::whereIn('name', ['Liberty Seguros ARL', 'Mapfre Seguros ARL'])
            ->update(['active' => false]);

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

        $afps = [
            ['name' => 'Porvenir'],
            ['name' => 'Protección'],
            ['name' => 'Colfondos'],
            ['name' => 'Skandia'],
            ['name' => 'Colpensiones'],
        ];

        foreach ($afps as $item) {
            Afp::updateOrCreate(['name' => $item['name']], $item);
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
            ['name' => 'Atmósfera peligrosa', 'description' => 'Ambientes con deficiencia de oxígeno, gases tóxicos o riesgo de explosión.'],
            ['name' => 'Ascenso y descenso', 'description' => 'Uso de escaleras, andamios y sistemas de acceso vertical.'],
            ['name' => 'Esfuerzo físico', 'description' => 'Tareas con demanda física sostenida o posturas forzadas.'],
            ['name' => 'Sistema anticaídas', 'description' => 'Uso de arnés, líneas de vida y puntos de anclaje certificados.'],
        ];

        foreach ($risks as $item) {
            Risk::updateOrCreate(
                ['slug' => Str::slug($item['name'])],
                [...$item, 'slug' => Str::slug($item['name'])],
            );
        }
    }
}
