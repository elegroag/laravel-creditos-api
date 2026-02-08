<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('tipo_documentos_array')) {

    function tipo_documentos_collect()
    {
        return [
            ["key" => "1", "value" => "CEDULA DE CIUDADANIA"],
            ["key" => "10", "value" => "TARJETA DE MOVILIDAD FRONTERIZA"],
            ["key" => "11", "value" => "CARNE DIPLOMATICO"],
            ["key" => "12", "value" => "IDENTIFICACION DADA POR LA SECRETARIA DE EDUCACION"],
            ["key" => "13", "value" => "VISA"],
            ["key" => "14", "value" => "PERMISO PROTECCION TEMPORAL"],
            ["key" => "3", "value" => "NIT"],
            ["key" => "4", "value" => "CEDULA EXTRANJERIA"],
            ["key" => "6", "value" => "PASAPORTE"],
            ["key" => "8", "value" => "PERMISO ESPECIAL DE PERMANENCIA"],
            ["key" => "9", "value" => "CERTIFICADO CABILDO"],
        ];
    }
}

if (!function_exists('tipo_documentos_array')) {

    function tipo_documentos_array()
    {
        return [
            "1" => "CEDULA DE CIUDADANIA",
            "10" => "TARJETA DE MOVILIDAD FRONTERIZA",
            "11" => "CARNE DIPLOMATICO",
            "12" => "IDENTIFICACION DADA POR LA SECRETARIA DE EDUCACION",
            "13" => "VISA",
            "14" => "PERMISO PROTECCION TEMPORAL",
            "3" => "NIT",
            "4" => "CEDULA EXTRANJERIA",
            "6" => "PASAPORTE",
            "8" => "PERMISO ESPECIAL DE PERMANENCIA",
            "9" => "CERTIFICADO CABILDO",
        ];
    }
}

if (!function_exists('sexo_array')) {

    function sexo_array()
    {
        return [
            "F" => "FEMENINO",
            "I" => "INDETERMINADO",
            "M" => "MASCULINO",
        ];
    }
}

if (!function_exists('estado_civiles_array')) {

    function estado_civiles_array()
    {
        return [
            "1" => "SOLTERO",
            "2" => "CASADO",
            "3" => "VIUDO",
            "4" => "UNION LIBRE",
            "5" => "SEPARADO",
            "6" => "DIVORCIADO",
        ];
    }
}

if (!function_exists('zonas_array')) {

    function zonas_array()
    {
        return [
            "18001" => "FLORENCIA",
            "18029" => "ALBANIA",
            "18094" => "BELEN DE LOS ANDAQUIES",
            "18150" => "CARTAGENA DEL CHAIRA",
            "18205" => "CURILLO CAQUETA",
            "18247" => "EL DONCELLO",
            "18256" => "EL PAUJIL",
            "18410" => "LA MONTANITA",
            "18460" => "MILAN",
            "18479" => "MORELIA",
            "18592" => "PUERTO RICO",
            "18610" => "SAN JOSE DE LA FRAGUA",
            "18753" => "SAN VICENTE DEL CAGUAN",
            "18756" => "SOLANO",
            "18785" => "SOLITA",
            "18860" => "VALPARAISO DEL CAQUETA",
        ];
    }
}

if (!function_exists('estado_array')) {

    function estado_array()
    {
        return [
            "A" => "ACTIVO",
            "I" => "INACTIVO",
            "M" => "MUERTO",
        ];
    }
}

if (!function_exists('categorias_array')) {

    function categorias_array()
    {
        return [
            "A" => "A",
            "B" => "B",
            "C" => "C",
            "D" => "D",
        ];
    }
}

if (!function_exists('tipo_cotizantes_array')) {

    function tipo_cotizantes_array()
    {
        return [
            "1" => "DEPENDIENTE",
            "12" => "APRENDICES DEL SENA ETAPA ELECTIVA",
            "19" => "APRENDICES DEL SENA EN ETAPA PRODUCTIVA",
            "2" => "EMPLEADO SERVICIO DOMESTICO",
            "4" => "MADRE COMUNITARIA",
            "63" => "FACULTATIVO NO INDEPENDIENTES",
            "69" => "COLOMBIANO RESIDENTE EN EL EXTRANJERO",
        ];
    }
}

/**
 * Generar componente de nombre de archivo seguro
 */
if (!function_exists('safe_filename_component')) {

    function safe_filename_component(string $input): string
    {
        // Reemplazar caracteres no seguros
        $safe = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $input);

        // Limitar longitud
        if (strlen($safe) > 50) {
            $safe = substr($safe, 0, 50);
        }

        // Eliminar guiones bajos y guiones múltiples
        $safe = preg_replace('/[_\-]+/', '_', $safe);

        // Eliminar guiones bajos y guiones al inicio y final
        $safe = trim($safe, '_-');

        return $safe ?: 'solicitud';
    }
};


if (!function_exists('json_to_array')) {
    function json_to_array(string|array $json): array
    {
        // Si ya es un array, retornarlo directamente
        if (is_array($json)) {
            return $json;
        }

        if (is_null($json) || $json === "" || $json === "[]" || $json === "{}") {
            return [];
        }
        //expresion regular para validar json o array json vacio
        if (preg_match('/^\s*(\{\s*\}|\[\s*\])\s*$/', $json)) {
            return [];
        }
        return json_decode($json, true);
    }
}


/**
 * Format bytes to human readable format.
 */
if (!function_exists('format_bytes')) {
    function format_bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }
}
