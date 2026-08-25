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

        /*
         * Capitales de departamento y municipios con actividad industrial o de
         * construccion, que son los que mas evaluaciones ocupacionales generan.
         */
        $municipalities = [
            'Amazonas' => ['Leticia', 'Puerto Nariño'],
            'Antioquia' => [
                'Medellín', 'Bello', 'Itagüí', 'Envigado', 'Sabaneta', 'Caldas', 'La Estrella',
                'Copacabana', 'Girardota', 'Barbosa', 'Rionegro', 'La Ceja', 'Marinilla',
                'Apartadó', 'Turbo', 'Caucasia', 'Puerto Berrío', 'Segovia', 'Yarumal',
            ],
            'Arauca' => ['Arauca', 'Arauquita', 'Saravena', 'Tame'],
            'Atlántico' => [
                'Barranquilla', 'Soledad', 'Malambo', 'Puerto Colombia', 'Galapa',
                'Sabanalarga', 'Baranoa', 'Sabanagrande',
            ],
            'Bolívar' => [
                'Cartagena', 'Magangué', 'Turbaco', 'Arjona', 'El Carmen de Bolívar',
                'Mompós', 'Santa Rosa del Sur',
            ],
            'Boyacá' => ['Tunja', 'Duitama', 'Sogamoso', 'Chiquinquirá', 'Paipa', 'Puerto Boyacá', 'Nobsa'],
            'Caldas' => ['Manizales', 'Villamaría', 'La Dorada', 'Chinchiná', 'Riosucio', 'Anserma'],
            'Caquetá' => ['Florencia', 'San Vicente del Caguán', 'Puerto Rico'],
            'Casanare' => ['Yopal', 'Aguazul', 'Villanueva', 'Tauramena', 'Monterrey', 'Paz de Ariporo'],
            'Cauca' => ['Popayán', 'Santander de Quilichao', 'Puerto Tejada', 'Guachené', 'Miranda', 'Patía'],
            'Cesar' => [
                'Valledupar', 'Aguachica', 'Agustín Codazzi', 'La Jagua de Ibirico',
                'Bosconia', 'Chiriguaná', 'Curumaní',
            ],
            'Chocó' => ['Quibdó', 'Istmina', 'Riosucio', 'Bahía Solano'],
            'Córdoba' => [
                'Montería', 'Lorica', 'Cereté', 'Sahagún', 'Montelíbano',
                'Planeta Rica', 'Tierralta', 'Ciénaga de Oro',
            ],
            'Cundinamarca' => [
                'Bogotá D.C.', 'Soacha', 'Zipaquirá', 'Facatativá', 'Chía', 'Mosquera', 'Madrid',
                'Funza', 'Fusagasugá', 'Girardot', 'Cajicá', 'Sibaté', 'Tocancipá', 'Cota',
                'La Calera', 'Ubaté', 'Villeta', 'Tabio', 'Tenjo', 'Gachancipá', 'Sopó',
            ],
            'Guainía' => ['Inírida'],
            'Guaviare' => ['San José del Guaviare'],
            'Huila' => ['Neiva', 'Pitalito', 'Garzón', 'La Plata', 'Campoalegre', 'Palermo', 'Rivera'],
            'La Guajira' => ['Riohacha', 'Maicao', 'Uribia', 'Fonseca', 'San Juan del Cesar', 'Albania', 'Barrancas'],
            'Magdalena' => ['Santa Marta', 'Ciénaga', 'Fundación', 'El Banco', 'Zona Bananera', 'Plato'],
            'Meta' => ['Villavicencio', 'Acacías', 'Granada', 'Puerto López', 'Puerto Gaitán', 'San Martín', 'Castilla la Nueva'],
            'Nariño' => ['Pasto', 'Tumaco', 'Ipiales', 'Túquerres', 'La Unión', 'Sandoná'],
            'Norte de Santander' => ['Cúcuta', 'Ocaña', 'Pamplona', 'Villa del Rosario', 'Los Patios', 'Tibú', 'Sardinata'],
            'Putumayo' => ['Mocoa', 'Puerto Asís', 'Orito', 'Villagarzón', 'Valle del Guamuez'],
            'Quindío' => ['Armenia', 'Calarcá', 'La Tebaida', 'Montenegro', 'Quimbaya', 'Circasia', 'Filandia'],
            'Risaralda' => ['Pereira', 'Dosquebradas', 'Santa Rosa de Cabal', 'La Virginia', 'Marsella'],
            'San Andrés y Providencia' => ['San Andrés', 'Providencia'],
            'Santander' => [
                'Bucaramanga', 'Floridablanca', 'Girón', 'Piedecuesta', 'Barrancabermeja',
                'San Gil', 'Socorro', 'Málaga', 'Vélez', 'Sabana de Torres', 'Puerto Wilches',
            ],
            'Sucre' => ['Sincelejo', 'Corozal', 'Sampués', 'San Marcos', 'Santiago de Tolú', 'Coveñas'],
            'Tolima' => [
                'Ibagué', 'Espinal', 'Melgar', 'Honda', 'Líbano', 'Chaparral',
                'San Sebastián de Mariquita', 'Purificación', 'Flandes',
            ],
            'Valle del Cauca' => [
                'Cali', 'Palmira', 'Buenaventura', 'Tuluá', 'Cartago', 'Guadalajara de Buga',
                'Jamundí', 'Yumbo', 'Candelaria', 'Zarzal', 'Florida', 'Pradera', 'Sevilla', 'Roldanillo',
            ],
            'Vaupés' => ['Mitú'],
            'Vichada' => ['Puerto Carreño'],
        ];

        foreach ($municipalities as $department => $names) {
            foreach ($names as $name) {
                City::updateOrCreate(
                    ['name' => $name, 'department' => $department],
                    ['active' => true],
                );
            }
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
