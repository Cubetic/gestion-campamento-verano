<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Genera variantes compatibles con etiquetas historicas guardadas en pedidos.
 * Esto evita romper reservas antiguas mientras la BD ya usa nombres normalizados.
 */
function skc_generar_aliases_semana(string $nombre_semana): array
{
    $semana = trim(preg_replace('/\s+/u', ' ', $nombre_semana));

    $candidatos = [$semana];
    $alias_historicos = [
        '30 de junio al 4 de julio' => ['31 junio al 4 de julio'],
        '31 junio al 4 de julio' => ['30 de junio al 4 de julio'],
        '30 de juny al 4 de juliol' => ['31 juny al 4 de juliol'],
        '31 juny al 4 de juliol' => ['30 de juny al 4 de juliol'],
        '28 de julio al 1 agosto' => ['28 de julio al 1 de agosto'],
        '28 de julio al 1 de agosto' => ['28 de julio al 1 agosto'],
        '28 de juliol al 1 agost' => ['28 de juliol al 1 de agost'],
        '28 de juliol al 1 de agost' => ['28 de juliol al 1 agost'],
    ];

    if (isset($alias_historicos[$semana])) {
        $candidatos = array_merge($candidatos, $alias_historicos[$semana]);
    }

    return array_values(array_unique(array_filter($candidatos)));
}

/**
 * Busca el ID de una semana por su etiqueta en castellano o catalan.
 * Compatibilidad: mantiene el nombre original y delega al helper con contexto.
 */
function skc_obtener_semana_id_por_nombre(string $nombre_semana, ?int $escuela_id = null): ?int
{
    return skc_obtener_semana_id_por_nombre_y_escuela($nombre_semana, $escuela_id);
}

/**
 * Obtiene la escuela asociada a un producto WooCommerce.
 */
function skc_obtener_escuela_id_por_producto(int $product_id): ?int
{
    if ($product_id <= 0) {
        return null;
    }

    global $wpdb;
    $tabla_escuelas = $wpdb->prefix . 'skc_escuelas';

    $tabla_existe = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tabla_escuelas)) === $tabla_escuelas;
    if (!$tabla_existe) {
        return null;
    }

    $escuela_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$tabla_escuelas} WHERE producto_id = %d ORDER BY activa DESC, id ASC LIMIT 1",
            $product_id
        )
    );

    return $escuela_id ? (int) $escuela_id : null;
}

/**
 * Busca el ID de una semana por su etiqueta usando, si existe, el contexto de escuela.
 */
function skc_obtener_semana_id_por_nombre_y_escuela(string $nombre_semana, ?int $escuela_id = null): ?int
{
    global $wpdb;

    $tabla_semanas = $wpdb->prefix . 'semanas_campamento';
    $candidatos = skc_generar_aliases_semana($nombre_semana);

    foreach ($candidatos as $candidato) {
        if (!empty($escuela_id)) {
            $semana_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla_semanas} WHERE escuela_id = %d AND (semana = %s OR semana_ca = %s) LIMIT 1",
                    $escuela_id,
                    $candidato,
                    $candidato
                )
            );
        } else {
            $semana_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$tabla_semanas} WHERE semana = %s OR semana_ca = %s LIMIT 1",
                    $candidato,
                    $candidato
                )
            );
        }

        if ($semana_id) {
            return (int) $semana_id;
        }
    }

    return null;
}