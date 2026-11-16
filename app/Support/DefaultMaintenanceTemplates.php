<?php

namespace App\Support;

use App\Models\MaintenanceTemplate;

/** Plantillas globales de mantenimiento (user_id nulo). Las crea una migración; no dependen de seeders. */
class DefaultMaintenanceTemplates
{
    /** Claves de categoría (sin tildes ni mayúsculas) y su nombre visible. */
    public const CATEGORIES = [
        'seguridad' => 'Seguridad',
        'motor' => 'Motor',
        'motor y fluidos' => 'Motor y fluidos',
        'filtros' => 'Filtros',
        'frenos' => 'Frenos',
        'ruedas' => 'Ruedas',
        'direccion y suspension' => 'Dirección y suspensión',
        'transmision' => 'Transmisión',
        'electrico' => 'Eléctrico',
        'general' => 'General',
    ];

    /** Claves anteriores con tilde que se fusionan en la clave sin tilde. */
    public const LEGACY_KEYS = [
        'dirección y suspensión' => 'direccion y suspension',
        'transmisión' => 'transmision',
        'eléctrico' => 'electrico',
    ];

    public static function all(): array
    {
        return [
            ['name' => 'Revisión de frenos', 'category' => 'seguridad', 'vehicle_types' => ['automovil', 'motocicleta', 'bicicleta', 'patineta'], 'power_sources' => null, 'default_interval_days' => 180, 'default_interval_usage' => 5000, 'description' => 'Inspección y ajuste del sistema de frenado.'],
            ['name' => 'Cambio de aceite de motor', 'category' => 'motor y fluidos', 'vehicle_types' => ['automovil', 'motocicleta'], 'power_sources' => ['gasolina', 'diesel', 'hibrido'], 'default_interval_days' => 180, 'default_interval_usage' => 5000, 'description' => 'Cambio de aceite de motor y filtro; confirma el intervalo indicado por el fabricante.'],
            ['name' => 'Cambio de filtro de aire del motor', 'category' => 'motor y fluidos', 'vehicle_types' => ['automovil', 'motocicleta'], 'power_sources' => ['gasolina', 'diesel', 'hibrido'], 'default_interval_days' => 365, 'default_interval_usage' => 15000, 'description' => 'Revisión o reemplazo del filtro de admisión del motor.'],
            ['name' => 'Cambio de filtro de cabina', 'category' => 'filtros', 'vehicle_types' => ['automovil'], 'power_sources' => null, 'default_interval_days' => 365, 'default_interval_usage' => 15000, 'description' => 'Reemplazo del filtro de aire acondicionado o cabina.'],
            ['name' => 'Revisión de refrigerante', 'category' => 'motor', 'vehicle_types' => ['automovil', 'motocicleta'], 'power_sources' => ['gasolina', 'diesel', 'hibrido'], 'default_interval_days' => 365, 'default_interval_usage' => 20000, 'description' => 'Comprobación del nivel y estado del líquido refrigerante.'],
            ['name' => 'Cambio de refrigerante', 'category' => 'motor y fluidos', 'vehicle_types' => ['automovil', 'motocicleta'], 'power_sources' => ['gasolina', 'diesel', 'hibrido'], 'default_interval_days' => 730, 'default_interval_usage' => 40000, 'description' => 'Drenaje y renovación del refrigerante según la especificación del vehículo.'],
            ['name' => 'Cambio de líquido de frenos', 'category' => 'frenos', 'vehicle_types' => ['automovil', 'motocicleta'], 'power_sources' => null, 'default_interval_days' => 730, 'default_interval_usage' => null, 'description' => 'Renovación del líquido hidráulico de frenos.'],
            ['name' => 'Mantenimiento general', 'category' => 'general', 'vehicle_types' => null, 'power_sources' => null, 'default_interval_days' => 365, 'default_interval_usage' => 10000, 'description' => 'Revisión preventiva general del vehículo.'],
            ['name' => 'Revisión de llantas', 'category' => 'seguridad', 'vehicle_types' => ['automovil', 'motocicleta', 'bicicleta', 'patineta'], 'power_sources' => null, 'default_interval_days' => 90, 'default_interval_usage' => 5000, 'description' => 'Presión, desgaste y estado de las llantas.'],
            ['name' => 'Rotación de llantas', 'category' => 'ruedas', 'vehicle_types' => ['automovil'], 'power_sources' => null, 'default_interval_days' => 180, 'default_interval_usage' => 10000, 'description' => 'Rotación para equilibrar el desgaste de las llantas.'],
            ['name' => 'Alineación y balanceo', 'category' => 'ruedas', 'vehicle_types' => ['automovil'], 'power_sources' => null, 'default_interval_days' => 365, 'default_interval_usage' => 10000, 'description' => 'Alineación de dirección y balanceo de las ruedas.'],
            ['name' => 'Cambio de llantas', 'category' => 'ruedas', 'vehicle_types' => ['automovil', 'motocicleta', 'bicicleta', 'patineta'], 'power_sources' => null, 'default_interval_days' => null, 'default_interval_usage' => 50000, 'description' => 'Reemplazo por desgaste, antigüedad o daño; inspecciona el indicador de desgaste.'],
            ['name' => 'Inspección de dirección y suspensión', 'category' => 'direccion y suspension', 'vehicle_types' => ['automovil', 'motocicleta'], 'power_sources' => null, 'default_interval_days' => 365, 'default_interval_usage' => 20000, 'description' => 'Revisión de amortiguadores, bujes, rótulas y componentes de dirección.'],
            ['name' => 'Cambio de aceite de caja manual', 'category' => 'transmision', 'vehicle_types' => ['automovil'], 'power_sources' => null, 'transmission_types' => ['manual'], 'default_interval_days' => null, 'default_interval_usage' => 60000, 'description' => 'Cambio del lubricante de transmisión manual según especificación.'],
            ['name' => 'Cambio de fluido de transmisión automática', 'category' => 'transmision', 'vehicle_types' => ['automovil'], 'power_sources' => null, 'transmission_types' => ['automatica', 'cvt', 'automatizada'], 'default_interval_days' => null, 'default_interval_usage' => 60000, 'description' => 'Cambio de ATF o fluido CVT según la especificación del fabricante.'],
            ['name' => 'Revisión de bujías', 'category' => 'motor', 'vehicle_types' => ['automovil', 'motocicleta'], 'power_sources' => ['gasolina', 'hibrido'], 'default_interval_days' => null, 'default_interval_usage' => 40000, 'description' => 'Inspección o reemplazo de bujías de encendido.'],
            ['name' => 'Cambio de correa de distribución', 'category' => 'motor', 'vehicle_types' => ['automovil'], 'power_sources' => ['gasolina', 'diesel'], 'default_interval_days' => 1825, 'default_interval_usage' => 80000, 'description' => 'Reemplazo preventivo de correa de distribución cuando el motor la utiliza.'],
            ['name' => 'Cambio de bomba de agua', 'category' => 'motor', 'vehicle_types' => ['automovil'], 'power_sources' => ['gasolina', 'diesel'], 'default_interval_days' => 1825, 'default_interval_usage' => 80000, 'description' => 'Revisión o reemplazo junto con la distribución cuando corresponda.'],
            ['name' => 'Cadena y transmisión', 'category' => 'transmision', 'vehicle_types' => ['motocicleta', 'bicicleta'], 'power_sources' => null, 'default_interval_days' => 90, 'default_interval_usage' => 1000, 'description' => 'Limpieza, lubricación y tensión de la transmisión.'],
            ['name' => 'Revisión de batería', 'category' => 'electrico', 'vehicle_types' => null, 'power_sources' => ['electrico', 'hibrido'], 'default_interval_days' => 180, 'default_interval_usage' => null, 'description' => 'Inspección del estado y conexiones de la batería.'],
            ['name' => 'Revisión del sistema eléctrico', 'category' => 'electrico', 'vehicle_types' => null, 'power_sources' => null, 'default_interval_days' => 365, 'default_interval_usage' => 20000, 'description' => 'Inspección de batería de 12 V, luces, alternador y conexiones.'],
        ];
    }

    public static function sync(): void
    {
        foreach (self::all() as $template) {
            MaintenanceTemplate::updateOrCreate(['name' => $template['name'], 'user_id' => null], $template);
        }
    }
}
