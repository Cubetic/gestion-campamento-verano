<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Verifica si una tabla contiene una columna.
 */
function skc_tabla_tiene_columna(string $tabla, string $columna): bool
{
	global $wpdb;

	$sql = $wpdb->prepare(
		"SHOW COLUMNS FROM {$tabla} LIKE %s",
		$columna
	);

	return (bool) $wpdb->get_var($sql);
}

/**
 * Verifica si una tabla contiene un indice por nombre.
 */
function skc_tabla_tiene_indice(string $tabla, string $indice): bool
{
	global $wpdb;

	$sql = $wpdb->prepare(
		"SHOW INDEX FROM {$tabla} WHERE Key_name = %s",
		$indice
	);

	return (bool) $wpdb->get_var($sql);
}

/**
 * Ejecuta la migracion de la Fase 1 para soporte multi-escuela y bilingue.
 */
function skc_migrar_base_datos_fase_1(): bool
{
	global $wpdb;

	$tabla_escuelas = $wpdb->prefix . 'skc_escuelas';
	$tabla_semanas = $wpdb->prefix . 'semanas_campamento';
	$tabla_horarios = $wpdb->prefix . 'horarios_semana';
	$charset_collate = $wpdb->get_charset_collate();

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	// Tabla nueva para soportar varias escuelas.
	$sql_escuelas = "CREATE TABLE IF NOT EXISTS $tabla_escuelas (
		id INT NOT NULL AUTO_INCREMENT,
		nombre VARCHAR(100) NOT NULL,
		nombre_ca VARCHAR(100) NOT NULL,
		slug VARCHAR(100) NOT NULL,
		producto_id BIGINT NULL,
		activa TINYINT(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug)
	) $charset_collate;";

	dbDelta($sql_escuelas);

	if (!empty($wpdb->last_error)) {
		error_log('SKC Fase 1: error creando tabla de escuelas: ' . $wpdb->last_error);
		return false;
	}

	// Insertar escuela base si no existe.
	$escuela_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$tabla_escuelas} WHERE slug = %s LIMIT 1",
			'duran-i-bas'
		)
	);

	if (!$escuela_id) {
		$insertado = $wpdb->insert(
			$tabla_escuelas,
			[
				'nombre' => 'Duran i Bas',
				'nombre_ca' => 'Duran i Bas',
				'slug' => 'duran-i-bas',
				'activa' => 1,
			],
			['%s', '%s', '%s', '%d']
		);

		if ($insertado === false) {
			error_log('SKC Fase 1: error insertando escuela base: ' . $wpdb->last_error);
			return false;
		}

		$escuela_id = (int) $wpdb->insert_id;
	} else {
		$escuela_id = (int) $escuela_id;
	}

	// Columna escuela_id en semanas.
	if (!skc_tabla_tiene_columna($tabla_semanas, 'escuela_id')) {
		$wpdb->query("ALTER TABLE {$tabla_semanas} ADD COLUMN escuela_id INT NOT NULL DEFAULT 1 AFTER id");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 1: error agregando escuela_id: ' . $wpdb->last_error);
			return false;
		}
	}

	$wpdb->update(
		$tabla_semanas,
		['escuela_id' => $escuela_id],
		['escuela_id' => 1],
		['%d'],
		['%d']
	);

	// Columna semana_ca para almacenar el texto catalan.
	if (!skc_tabla_tiene_columna($tabla_semanas, 'semana_ca')) {
		$wpdb->query("ALTER TABLE {$tabla_semanas} ADD COLUMN semana_ca VARCHAR(100) NOT NULL DEFAULT '' AFTER semana");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 1: error agregando semana_ca: ' . $wpdb->last_error);
			return false;
		}
	}

	// Relleno inicial de traducciones catalanas para semanas existentes.
	$traducciones = [
		'25 al 27 de junio' => '25 al 27 de juny',
		'31 junio al 4 de julio' => '31 juny al 4 de juliol',
		'30 de junio al 4 de julio' => '30 de juny al 4 de juliol',
		'7 al 11 de julio' => '7 al 11 de juliol',
		'14 al 18 de julio' => '14 al 18 de juliol',
		'21 al 25 de julio' => '21 al 25 de juliol',
		'28 de julio al 1 de agosto' => '28 de juliol al 1 de agost',
	];

	foreach ($traducciones as $semana_es => $semana_ca) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$tabla_semanas}
				 SET semana_ca = %s
				 WHERE semana = %s AND (semana_ca = '' OR semana_ca IS NULL)",
				$semana_ca,
				$semana_es
			)
		);
	}

	// Columna tipo_horario_ca.
	if (!skc_tabla_tiene_columna($tabla_horarios, 'tipo_horario_ca')) {
		$wpdb->query("ALTER TABLE {$tabla_horarios} ADD COLUMN tipo_horario_ca VARCHAR(50) NOT NULL DEFAULT '' AFTER tipo_horario");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 1: error agregando tipo_horario_ca: ' . $wpdb->last_error);
			return false;
		}
	}

	$wpdb->query("UPDATE {$tabla_horarios} SET tipo_horario_ca = 'mati' WHERE tipo_horario = 'mañana' AND (tipo_horario_ca = '' OR tipo_horario_ca IS NULL)");
	$wpdb->query("UPDATE {$tabla_horarios} SET tipo_horario_ca = 'complet' WHERE tipo_horario = 'completo' AND (tipo_horario_ca = '' OR tipo_horario_ca IS NULL)");

	// Cambiar tipo_horario de ENUM a VARCHAR para futuros horarios dinamicos.
	$tipo_columna_horario = $wpdb->get_var("SHOW COLUMNS FROM {$tabla_horarios} LIKE 'tipo_horario'", 1);

	if ($tipo_columna_horario && stripos((string) $tipo_columna_horario, 'enum(') === 0) {
		$wpdb->query("ALTER TABLE {$tabla_horarios} MODIFY COLUMN tipo_horario VARCHAR(50) NOT NULL");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 1: error modificando tipo_horario a VARCHAR: ' . $wpdb->last_error);
			return false;
		}
	}

	// Intentar agregar la FK solo si aun no existe.
	$fk_existente = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT CONSTRAINT_NAME
			 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = %s
			   AND COLUMN_NAME = 'escuela_id'
			   AND REFERENCED_TABLE_NAME = %s
			 LIMIT 1",
			$tabla_semanas,
			$tabla_escuelas
		)
	);

	if (!$fk_existente) {
		$wpdb->query("ALTER TABLE {$tabla_semanas} ADD CONSTRAINT fk_skc_semanas_escuela FOREIGN KEY (escuela_id) REFERENCES {$tabla_escuelas}(id) ON DELETE CASCADE");

		// Si falla la FK no bloqueamos toda la fase; queda registrada para revisar.
		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 1: no se pudo crear FK semanas->escuelas: ' . $wpdb->last_error);
			$wpdb->last_error = '';
		}
	}

	return true;
}

/**
 * Fase 2: permite repetir etiqueta de semana entre escuelas distintas.
 * Mantiene la unicidad solo dentro de cada escuela.
 */
function skc_migrar_base_datos_fase_2(): bool
{
	global $wpdb;

	$tabla_semanas = $wpdb->prefix . 'semanas_campamento';

	if (!skc_tabla_tiene_columna($tabla_semanas, 'escuela_id')) {
		error_log('SKC Fase 2: falta columna escuela_id en semanas.');
		return false;
	}

	$duplicados_misma_escuela = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM (
			SELECT escuela_id, semana, COUNT(*) AS total
			FROM {$tabla_semanas}
			GROUP BY escuela_id, semana
			HAVING COUNT(*) > 1
		) t"
	);

	if ($duplicados_misma_escuela > 0) {
		error_log('SKC Fase 2: existen semanas duplicadas dentro de la misma escuela. Revisar antes de crear UNIQUE(escuela_id, semana).');
		return false;
	}

	if (skc_tabla_tiene_indice($tabla_semanas, 'semana')) {
		$wpdb->query("ALTER TABLE {$tabla_semanas} DROP INDEX semana");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 2: error eliminando indice unico semana: ' . $wpdb->last_error);
			return false;
		}
	}

	if (!skc_tabla_tiene_indice($tabla_semanas, 'uniq_escuela_semana')) {
		$wpdb->query("ALTER TABLE {$tabla_semanas} ADD UNIQUE KEY uniq_escuela_semana (escuela_id, semana)");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 2: error creando indice uniq_escuela_semana: ' . $wpdb->last_error);
			return false;
		}
	}

	return true;
}

/**
 * Fase 3: añade nombre_horario a horarios para permitir una franja visible por semana.
 * No cambia todavia la logica funcional del plugin.
 */
function skc_migrar_base_datos_fase_3(): bool
{
	global $wpdb;

	$tabla_horarios = $wpdb->prefix . 'horarios_semana';

	if (!skc_tabla_tiene_columna($tabla_horarios, 'nombre_horario')) {
		$wpdb->query("ALTER TABLE {$tabla_horarios} ADD COLUMN nombre_horario VARCHAR(120) NOT NULL DEFAULT '' AFTER tipo_horario");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 3: error agregando nombre_horario: ' . $wpdb->last_error);
			return false;
		}
	}

	$wpdb->query(
		"UPDATE {$tabla_horarios}
		 SET nombre_horario = '9:00h a 14:30h'
		 WHERE tipo_horario = 'mañana' AND (nombre_horario = '' OR nombre_horario IS NULL)"
	);

	if (!empty($wpdb->last_error)) {
		error_log('SKC Fase 3: error rellenando nombre_horario para mañana: ' . $wpdb->last_error);
		return false;
	}

	$wpdb->query(
		"UPDATE {$tabla_horarios}
		 SET nombre_horario = '9:00h a 17:00h'
		 WHERE tipo_horario = 'completo' AND (nombre_horario = '' OR nombre_horario IS NULL)"
	);

	if (!empty($wpdb->last_error)) {
		error_log('SKC Fase 3: error rellenando nombre_horario para completo: ' . $wpdb->last_error);
		return false;
	}

	return true;
}

/**
 * Fase 4: permite asociar un segundo producto WooCommerce (p.ej. catalan)
 * a la misma escuela para compartir stock entre productos ES/CA.
 */
function skc_migrar_base_datos_fase_4(): bool
{
	global $wpdb;

	$tabla_escuelas = $wpdb->prefix . 'skc_escuelas';

	if (!skc_tabla_tiene_columna($tabla_escuelas, 'producto_id_ca')) {
		$wpdb->query("ALTER TABLE {$tabla_escuelas} ADD COLUMN producto_id_ca BIGINT NULL AFTER producto_id");

		if (!empty($wpdb->last_error)) {
			error_log('SKC Fase 4: error agregando producto_id_ca: ' . $wpdb->last_error);
			return false;
		}
	}

	return true;
}

/**
 * Ejecuta migraciones una sola vez por version de esquema.
 */
function skc_ejecutar_migraciones(): void
{
	$version_actual = get_option('skc_db_schema_version', '1.0.0');

	if (version_compare($version_actual, SKC_DB_SCHEMA_VERSION, '>=')) {
		return;
	}

	if (version_compare($version_actual, '1.1.0', '<')) {
		$resultado_fase_1 = skc_migrar_base_datos_fase_1();
		if (!$resultado_fase_1) {
			return;
		}
		$version_actual = '1.1.0';
	}

	if (version_compare($version_actual, '1.2.0', '<')) {
		$resultado_fase_2 = skc_migrar_base_datos_fase_2();
		if (!$resultado_fase_2) {
			return;
		}
		$version_actual = '1.2.0';
	}

	if (version_compare($version_actual, '1.3.0', '<')) {
		$resultado_fase_3 = skc_migrar_base_datos_fase_3();
		if (!$resultado_fase_3) {
			return;
		}
		$version_actual = '1.3.0';
	}

	if (version_compare($version_actual, '1.4.0', '<')) {
		$resultado_fase_4 = skc_migrar_base_datos_fase_4();
		if (!$resultado_fase_4) {
			return;
		}
		$version_actual = '1.4.0';
	}

	if (version_compare($version_actual, SKC_DB_SCHEMA_VERSION, '>=')) {
		update_option('skc_db_schema_version', SKC_DB_SCHEMA_VERSION);
	}
}
