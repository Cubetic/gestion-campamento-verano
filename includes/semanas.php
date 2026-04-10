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

/**
 * Normaliza texto de horarios para comparaciones robustas.
 */
function skc_normalizar_texto_horario(string $texto): string
{
    $normalizado = trim(preg_replace('/\s+/u', ' ', $texto));
    $normalizado = preg_replace('/^de\s+/iu', '', $normalizado);

    if (function_exists('mb_strtolower')) {
        $normalizado = mb_strtolower($normalizado, 'UTF-8');
    } else {
        $normalizado = strtolower($normalizado);
    }

    return trim($normalizado);
}

/**
 * Resuelve el tipo_horario configurado para una semana a partir del texto visible.
 */
function skc_resolver_tipo_horario_por_semana(int $semana_id, string $valor_horario): ?string
{
    if ($semana_id <= 0 || trim($valor_horario) === '') {
        return null;
    }

    global $wpdb;
    $tabla_horarios = $wpdb->prefix . 'horarios_semana';

    $horarios = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT tipo_horario, nombre_horario
             FROM {$tabla_horarios}
             WHERE semana_id = %d",
            $semana_id
        ),
        ARRAY_A
    );

    if (empty($horarios)) {
        return null;
    }

    $valor_normalizado = skc_normalizar_texto_horario($valor_horario);

    $coincidencias_exactas = [];
    $coincidencias_parciales = [];

    foreach ($horarios as $horario) {
        $nombre_horario = isset($horario['nombre_horario']) ? (string) $horario['nombre_horario'] : '';
        if ($nombre_horario === '') {
            continue;
        }

        $nombre_normalizado = skc_normalizar_texto_horario($nombre_horario);
        if ($nombre_normalizado !== '' && $nombre_normalizado === $valor_normalizado) {
            $coincidencias_exactas[] = isset($horario['tipo_horario']) ? (string) $horario['tipo_horario'] : '';
        }
    }

    $coincidencias_exactas = array_values(array_unique(array_filter($coincidencias_exactas)));
    if (count($coincidencias_exactas) === 1) {
        return $coincidencias_exactas[0];
    }
    if (count($coincidencias_exactas) > 1) {
        error_log('SKC Horarios: coincidencia exacta ambigua para semana_id=' . $semana_id . ' y valor="' . $valor_horario . '"');
        return null;
    }

    foreach ($horarios as $horario) {
        $nombre_horario = isset($horario['nombre_horario']) ? (string) $horario['nombre_horario'] : '';
        if ($nombre_horario === '') {
            continue;
        }

        $nombre_normalizado = skc_normalizar_texto_horario($nombre_horario);
        if (
            $nombre_normalizado !== '' && (
                strpos($valor_normalizado, $nombre_normalizado) !== false ||
                strpos($nombre_normalizado, $valor_normalizado) !== false
            )
        ) {
            $coincidencias_parciales[] = isset($horario['tipo_horario']) ? (string) $horario['tipo_horario'] : '';
        }
    }

    $coincidencias_parciales = array_values(array_unique(array_filter($coincidencias_parciales)));
    if (count($coincidencias_parciales) === 1) {
        return $coincidencias_parciales[0];
    }
    if (count($coincidencias_parciales) > 1) {
        error_log('SKC Horarios: coincidencia parcial ambigua para semana_id=' . $semana_id . ' y valor="' . $valor_horario . '"');
        return null;
    }

    return null;
}