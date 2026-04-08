<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Crea tablas base e inserta semanas iniciales (instalacion original del plugin).
 */
function crear_e_inicializar_tablas_campamento(): void
{
	global $wpdb;
	$tabla_semanas = $wpdb->prefix . 'semanas_campamento';
	$tabla_horarios = $wpdb->prefix . 'horarios_semana';

	$charset_collate = $wpdb->get_charset_collate();

	// Crear tabla de semanas.
	$sql1 = "CREATE TABLE IF NOT EXISTS $tabla_semanas (
		id INT NOT NULL AUTO_INCREMENT,
		semana VARCHAR(50) NOT NULL,
		plazas_totales INT NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY semana (semana)
	) $charset_collate;";

	// Crear tabla de horarios por semana.
	$sql2 = "CREATE TABLE IF NOT EXISTS $tabla_horarios (
		id INT NOT NULL AUTO_INCREMENT,
		semana_id INT NOT NULL,
		tipo_horario ENUM('mañana', 'completo') NOT NULL,
		plazas INT NOT NULL DEFAULT 0,
		plazas_reservadas INT NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY semana_tipo (semana_id, tipo_horario),
		FOREIGN KEY (semana_id) REFERENCES $tabla_semanas(id) ON DELETE CASCADE
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql1);
	dbDelta($sql2);

	// Insertar datos iniciales de semanas.
	$semanas = [
		'25 al 27 de junio',
		'31 junio al 4 de julio',
		'7 al 11 de julio',
		'14 al 18 de julio',
		'21 al 25 de julio',
		'28 de julio al 1 de agosto'
	];

	foreach ($semanas as $semana) {
		$existe = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM $tabla_semanas WHERE semana = %s",
			$semana
		));

		if (!$existe) {
			$wpdb->insert(
				$tabla_semanas,
				[
					'semana' => $semana,
					'plazas_totales' => 120
				]
			);

			$semana_id = $wpdb->insert_id;

			$wpdb->insert(
				$tabla_horarios,
				[
					'semana_id' => $semana_id,
					'tipo_horario' => 'mañana',
					'plazas' => 60
				]
			);

			$wpdb->insert(
				$tabla_horarios,
				[
					'semana_id' => $semana_id,
					'tipo_horario' => 'completo',
					'plazas' => 60
				]
			);
		}
	}
}
