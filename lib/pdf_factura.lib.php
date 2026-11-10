<?php
/* Copyright (C) 2026 TallerPro
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/taller/lib/pdf_factura.lib.php
 * \ingroup taller
 * \brief   Generador de PDF para facturas de TallerPro con diseño profesional
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');

/**
 * Genera el documento PDF de una factura de TallerPro conforme al diseño de referencia.
 *
 * @param DoliDB $db          Manejador de base de datos
 * @param Conf   $conf        Configuración global de Dolibarr
 * @param User   $user        Usuario actual
 * @param int    $fac_id      ID de la factura (rowid)
 * @param string $output_mode 'I' para visualización en navegador, 'D' para descarga forzada, 'F' para guardar archivo
 * @param string $file_path   Ruta de archivo destino si $output_mode == 'F'
 * @return mixed
 */
function tallerpro_generate_factura_pdf($db, $conf, $user, $fac_id, $output_mode = 'I', $file_path = '')
{
	require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
	require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

	// 1. Obtener datos de la factura (Dolibarr nativa llx_facture con extrafield vehiculo)
	$sql = "SELECT f.rowid, f.ref as numero_factura, f.datef as fecha, f.total_ht, f.total_tva, f.total_ttc as total, COALESCE(f.remise_percent, 0) as descuento_global, 21.0 as iva_porcentaje, f.note_public as notas, ";
	$sql .= "COALESCE(fe.tipo_doc, 'factura') as tipo_doc, ";
	$sql .= "s.nom as cliente_nombre, COALESCE(NULLIF(s.siren, ''), NULLIF(s.siret, ''), s.tva_intra) as cliente_cif, ";
	$sql .= "s.address as cliente_direccion, s.zip as cliente_cp, s.town as cliente_ciudad, s.phone as cliente_telefono, s.email as cliente_email, ";
	$sql .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio, v.vin as vehiculo_vin, v.km as vehiculo_km ";
	$sql .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
	$sql .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON f.fk_soc = s.rowid ";
	$sql .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
	$sql .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON fe.fk_vehiculo = v.rowid ";
	$sql .= "WHERE f.rowid = " . ((int) $fac_id) . " AND f.entity IN (" . getEntity('facture') . ")";

	$res = $db->query($sql);
	if (!$res || !($factura = $db->fetch_object($res))) {
		return false;
	}

	// 2. Obtener líneas de la factura desde llx_facturedet
	$lineas = array();
	$tot_bruto = 0.0;
	$tot_neto_lineas = 0.0;
	$tot_descuento_lineas = 0.0;

	$sql_lines = "SELECT fd.rowid, (CASE WHEN fd.product_type = 1 THEN 'reparacion' ELSE 'material' END) as tipo, ";
	$sql_lines .= "COALESCE(NULLIF(fd.ref_ext, ''), p.ref) as codigo, ";
	$sql_lines .= "(CASE WHEN fd.description IS NOT NULL AND fd.description != '' THEN fd.description ELSE fd.label END) as descripcion, ";
	$sql_lines .= "fd.qty as cantidad, fd.subprice as precio, fd.remise_percent as descuento, fd.total_ht as total_neto ";
	$sql_lines .= "FROM " . MAIN_DB_PREFIX . "facturedet as fd ";
	$sql_lines .= "LEFT JOIN " . MAIN_DB_PREFIX . "product as p ON fd.fk_product = p.rowid ";
	$sql_lines .= "WHERE fd.fk_facture = " . ((int) $fac_id) . " ORDER BY fd.rang ASC, fd.rowid ASC";
	$res_lines = $db->query($sql_lines);
	if ($res_lines && $db->num_rows($res_lines) > 0) {
		while ($l = $db->fetch_object($res_lines)) {
			$lineas[] = $l;
			$qty = (float) $l->cantidad;
			if ($qty <= 0) $qty = 1.0;
			$p_unit = (float) $l->precio;
			$l_bruto = $qty * $p_unit;
			$l_neto = (float) $l->total_neto;
			$l_dto = max(0, $l_bruto - $l_neto);

			$tot_bruto += $l_bruto;
			$tot_neto_lineas += $l_neto;
			$tot_descuento_lineas += $l_dto;
		}
	}

	$global_desc_percent = (float) $factura->descuento_global;
	$global_desc_amt = ($global_desc_percent > 0) ? ($tot_neto_lineas * ($global_desc_percent / 100)) : 0.0;

	$subtotal = $tot_bruto;
	$descuento = $tot_descuento_lineas + $global_desc_amt;
	$base = max(0, $subtotal - $descuento);
	$iva_pct = (float) ($factura->iva_porcentaje !== null ? $factura->iva_porcentaje : 21);
	$iva_amt = (float) $factura->total_tva > 0 ? (float) $factura->total_tva : round($base * ($iva_pct / 100), 2);
	$total_final = (float) $factura->total > 0 ? (float) $factura->total : ($base + $iva_amt);

	// 3. Fechas
	$fecha_ts = !empty($factura->fecha) ? strtotime($factura->fecha) : time();
	$fecha_formateada = date('d/m/Y', $fecha_ts);

	// 4. Datos del Taller Emisor (Prioridad: Configuración TallerPro -> Ficha Dolibarr mysoc)
	global $mysoc;
	if (empty($mysoc) || empty($mysoc->name)) {
		$mysoc = new Societe($db);
		$mysoc->setMysoc($conf);
	}
	require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';

	$taller_nombre = !empty($conf->global->TALLER_NOMBRE_COMERCIAL) ? $conf->global->TALLER_NOMBRE_COMERCIAL : (!empty($mysoc->name) ? $mysoc->name : 'TallerPro');
	$taller_nif = !empty($conf->global->TALLER_CIF) ? $conf->global->TALLER_CIF : (!empty($mysoc->idprof1) ? $mysoc->idprof1 : (!empty($mysoc->tva_intra) ? $mysoc->tva_intra : ''));
	$taller_dir = !empty($conf->global->TALLER_DIRECCION) ? $conf->global->TALLER_DIRECCION : (!empty($mysoc->address) ? $mysoc->address : '');
	$taller_pob = !empty($conf->global->TALLER_POBLACION) ? $conf->global->TALLER_POBLACION : ((!empty($mysoc->zip) ? $mysoc->zip . ' ' : '') . (!empty($mysoc->town) ? $mysoc->town : ''));
	$taller_tel = !empty($conf->global->TALLER_TELEFONO) ? $conf->global->TALLER_TELEFONO : (!empty($mysoc->phone) ? $mysoc->phone : '');
	$taller_email = !empty($conf->global->TALLER_EMAIL) ? $conf->global->TALLER_EMAIL : (!empty($mysoc->email) ? $mysoc->email : '');

	// Descifrar IBAN bancario protegido si está habilitado en configuración
	$mostrar_iban = isset($conf->global->TALLER_MOSTRAR_IBAN) ? ((int) $conf->global->TALLER_MOSTRAR_IBAN === 1) : true;
	$taller_iban = '';
	if ($mostrar_iban && !empty($conf->global->TALLER_IBAN)) {
		$raw_iban = dolDecrypt($conf->global->TALLER_IBAN);
		if (!empty($raw_iban) && strtoupper(trim($raw_iban)) !== 'NONE') {
			$taller_iban = trim(chunk_split($raw_iban, 4, ' '));
		}
	}

	// 5. Inicializar TCPDF
	$pdf = pdf_getInstance();
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetCreator('TallerPro');
	$pdf->SetAuthor($taller_nombre);
	$pdf->SetTitle('Factura ' . $factura->numero_factura);
	$pdf->SetMargins(14, 12, 14);
	$pdf->SetAutoPageBreak(TRUE, 10);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->AddPage();

	// 6. Cabecera Visual: Logotipo
	$logo_rendered = false;
	if (!empty($mysoc->logo)) {
		$logo_path = $conf->mycompany->dir_output . '/logos/' . $mysoc->logo;
		if (file_exists($logo_path)) {
			$pdf->Image($logo_path, 14, 10, 0, 15);
			$logo_rendered = true;
		}
	}

	if (!$logo_rendered) {
		$svg_logo = '<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
		  <rect width="100" height="100" rx="20" fill="#121316"/>
		  <path fill-rule="evenodd" clip-rule="evenodd" d="M 90.75 45.5 L 97.71 44.73 L 97.71 55.27 L 90.75 54.5 L 89.37 61.44 L 96.09 63.39 L 92.06 73.12 L 85.93 69.75 L 82 75.63 L 87.46 80.01 L 80.01 87.46 L 75.63 82 L 69.75 85.93 L 73.12 92.06 L 63.39 96.09 L 61.44 89.37 L 54.5 90.75 L 55.27 97.71 L 44.73 97.71 L 45.5 90.75 L 38.56 89.37 L 36.61 96.09 L 26.88 92.06 L 30.25 85.93 L 24.37 82 L 19.99 87.46 L 12.54 80.01 L 18 75.63 L 14.07 69.75 L 7.94 73.12 L 3.91 63.39 L 10.63 61.44 L 9.25 54.5 L 2.29 55.27 L 2.29 44.73 L 9.25 45.5 L 10.63 38.56 L 3.91 36.61 L 7.94 26.88 L 14.07 30.25 L 18 24.37 L 12.54 19.99 L 19.99 12.54 L 24.37 18 L 30.25 14.07 L 26.88 7.94 L 36.61 3.91 L 38.56 10.63 L 45.5 9.25 L 44.73 2.29 L 55.27 2.29 L 54.5 9.25 L 61.44 10.63 L 63.39 3.91 L 73.12 7.94 L 69.75 14.07 L 75.63 18 L 80.01 12.54 L 87.46 19.99 L 82 24.37 L 85.93 30.25 L 92.06 26.88 L 96.09 36.61 L 89.37 38.56 Z M 50,17 A 33 33 0 1 0 50,83 A 33 33 0 1 0 50,17 Z" fill="#ffffff"/>
		  <g transform="translate(50,50)">
		    <g transform="rotate(-45)">
		      <rect x="-3" y="-22" width="6" height="44" rx="1.5" fill="#ffffff"/>
		      <path d="M -8 -20 C -8 -27 -4 -31 0 -31 C 4 -31 8 -27 8 -20 C 8 -18 6 -17 3.5 -17 L 3.5 -23.5 C 3.5 -25 1.5 -26.5 0 -26.5 C -1.5 -26.5 -3.5 -25 -3.5 -23.5 L -3.5 -17 C -6 -17 -8 -18 -8 -20 Z" fill="#ffffff"/>
		      <path d="M -8 -20 C -8 -27 -4 -31 0 -31 C 4 -31 8 -27 8 -20 C 8 -18 6 -17 3.5 -17 L 3.5 -23.5 C 3.5 -25 1.5 -26.5 0 -26.5 C -1.5 -26.5 -3.5 -25 -3.5 -23.5 L -3.5 -17 C -6 -17 -8 -18 -8 -20 Z" transform="rotate(180)" fill="#ffffff"/>
		    </g>
		    <g transform="rotate(45)">
		      <rect x="-4.5" y="-29" width="9" height="57" rx="3" fill="#121316"/>
		      <rect x="-2" y="-24" width="4" height="27" fill="#ffffff"/>
		      <polygon points="-2.5,-24 2.5,-24 1.8,-28 -1.8,-28" fill="#ffffff"/>
		      <path d="M -5 3 C -5 1 -3 0 0 0 C 3 0 5 1 5 3 L 6 18 C 6 23.5 3 26 0 26 C -3 26 -6 23.5 -6 18 Z" fill="#ffffff"/>
		      <line x1="-1.8" y1="7" x2="-1.8" y2="19" stroke="#121316" stroke-width="1.2" stroke-linecap="round"/>
		      <line x1="1.8" y1="7" x2="1.8" y2="19" stroke="#121316" stroke-width="1.2" stroke-linecap="round"/>
		    </g>
		  </g>
		</svg>';
		$tmp_dir = DOL_DATA_ROOT . '/temp';
		if (!is_dir($tmp_dir)) {
			dol_mkdir($tmp_dir);
		}
		$svg_file = $tmp_dir . '/tallerpro_logo_' . getmypid() . '_' . uniqid() . '.svg';
		@file_put_contents($svg_file, $svg_logo);

		if (file_exists($svg_file)) {
			$pdf->ImageSVG($svg_file, 14, 12, 8.5, 8.5);
			@unlink($svg_file);
		}

		$pdf->SetFont('helvetica', 'B', 17);
		$pdf->SetTextColor(15, 23, 42);
		$pdf->SetXY(24, 12.8);
		$pdf->Cell(40, 7, 'TallerPro', 0, 0, 'L');
	}

	// Cabecera Derecha: Datos de la Empresa / Taller
	$pdf->SetFont('helvetica', 'B', 9.5);
	$pdf->SetTextColor(15, 23, 42);
	$pdf->SetXY(105, 10);
	$pdf->Cell(91, 4.5, $taller_nombre, 0, 1, 'R');

	$pdf->SetFont('helvetica', '', 7.8);
	$pdf->SetTextColor(71, 85, 105);
	if (!empty($taller_nif)) {
		$pdf->SetX(105);
		$pdf->Cell(91, 3.8, 'NIF / CIF: ' . $taller_nif, 0, 1, 'R');
	}
	$loc_line = trim($taller_dir . (!empty($taller_dir) && !empty($taller_pob) ? ', ' : '') . $taller_pob);
	if (!empty($loc_line)) {
		$pdf->SetX(105);
		$pdf->Cell(91, 3.8, $loc_line, 0, 1, 'R');
	}
	$contact_parts = array_filter(array(!empty($taller_tel) ? 'Tel: ' . $taller_tel : '', $taller_email));
	if (!empty($contact_parts)) {
		$pdf->SetX(105);
		$pdf->Cell(91, 3.8, implode(' · ', $contact_parts), 0, 1, 'R');
	}

	// Línea divisoria superior
	$pdf->SetLineStyle(array('width' => 0.4, 'color' => array(15, 23, 42)));
	$pdf->Line(14, 31, 196, 31);

	// 7. Preparación de datos de Cliente y Vehículo
	$cli_nom = !empty($factura->cliente_nombre) ? $factura->cliente_nombre : 'Sin cliente';
	$cli_cif = !empty($factura->cliente_cif) ? $factura->cliente_cif : '';
	$cli_dir = !empty($factura->cliente_direccion) ? $factura->cliente_direccion : '';
	$cli_pob = trim((!empty($factura->cliente_cp) ? $factura->cliente_cp . ' ' : '') . (!empty($factura->cliente_ciudad) ? $factura->cliente_ciudad : ''));
	$cli_tel = !empty($factura->cliente_telefono) ? $factura->cliente_telefono : (!empty($factura->cliente_email) ? $factura->cliente_email : '');

	$veh_mat = !empty($factura->vehiculo_matricula) ? $factura->vehiculo_matricula : 'Sin vehículo';
	$veh_desc = trim((!empty($factura->vehiculo_marca) ? $factura->vehiculo_marca . ' ' : '') . (!empty($factura->vehiculo_modelo) ? $factura->vehiculo_modelo : ''));
	if (!empty($factura->vehiculo_anio)) {
		$veh_desc .= ' (' . $factura->vehiculo_anio . ')';
	}
	$veh_vin = !empty($factura->vehiculo_vin) ? $factura->vehiculo_vin : '';
	$veh_km = !empty($factura->vehiculo_km) ? number_format((float) $factura->vehiculo_km, 0, ',', '.') . ' km' : '';

	$estado_txt = !empty($factura->estado) ? $factura->estado : 'Pendiente';
	$estado_bold = (strtolower(trim($estado_txt)) == 'pagada') ? '<b>Pagada</b>' : '<b>' . htmlspecialchars($estado_txt) . '</b>';

	$is_presupuesto = (!empty($factura->tipo_doc) && $factura->tipo_doc === 'presupuesto');
	$doc_to_label = $is_presupuesto ? 'PRESUPUESTO PARA' : 'FACTURADO A';
	$doc_title_label = $is_presupuesto ? ('Presupuesto Nº ' . htmlspecialchars($factura->numero_factura)) : ('Factura Nº ' . htmlspecialchars($factura->numero_factura));

	// Contenido principal HTML con espaciado amplio y equilibrado
	$pdf->SetXY(14, 36);
	$pdf->SetFont('helvetica', '', 8.5);

	$html = '
	<table width="100%" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<td width="35%" valign="top">
				<span style="font-size: 7.5pt; font-weight: bold; color: #94a3b8; letter-spacing: 0.05em;">' . $doc_to_label . '</span><br><br>
				<span style="font-size: 10.5pt; font-weight: bold; color: #0f172a;">' . htmlspecialchars($cli_nom) . '</span><br><br>
				<span style="color: #334155; font-size: 8.5pt; line-height: 1.5;">'
					. (!empty($cli_cif) ? 'NIF/CIF: ' . htmlspecialchars($cli_cif) . '<br>' : '')
					. (!empty($cli_dir) ? htmlspecialchars($cli_dir) . '<br>' : '')
					. (!empty($cli_pob) ? htmlspecialchars($cli_pob) . '<br>' : '')
					. (!empty($cli_tel) ? 'Tel: ' . htmlspecialchars($cli_tel) : '')
				. '</span>
			</td>
			<td width="37%" valign="top">
				<span style="font-size: 7.5pt; font-weight: bold; color: #94a3b8; letter-spacing: 0.05em;">VEHÍCULO</span><br><br>
				<span style="font-size: 10.5pt; font-weight: bold; color: #0f172a;">' . htmlspecialchars($veh_mat) . '</span>' . (!empty($veh_desc) ? ' <span style="font-size: 9.5pt; color: #334155;">· ' . htmlspecialchars($veh_desc) . '</span>' : '') . '<br><br>
				<span style="color: #334155; font-size: 8.5pt; line-height: 1.5;">'
					. (!empty($veh_vin) ? 'Bastidor (VIN): ' . htmlspecialchars($veh_vin) . '<br>' : '')
					. (!empty($veh_km) ? 'Kilometraje: ' . htmlspecialchars($veh_km) . '<br>' : '')
					. 'Fecha entrega: ' . $fecha_formateada . '
				</span>
			</td>
			<td width="28%" valign="top" align="right">
				<span style="font-size: 7.5pt; font-weight: bold; color: #94a3b8; letter-spacing: 0.05em;">DOCUMENTO</span><br><br>
				<span style="font-size: 13pt; font-weight: bold; color: #0f172a;">' . $doc_title_label . '</span><br><br>
				<span style="color: #334155; font-size: 8.5pt; line-height: 1.5;">
					Fecha emisión: ' . $fecha_formateada . '
				</span>
			</td>
		</tr>
	</table>

	<br><br><br><br>

	<table width="100%" cellpadding="8" cellspacing="0" border="0">
		<thead>
			<tr>
				<th width="52%" align="left" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">DESCRIPCIÓN</th>
				<th width="16%" align="center" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">CANTIDAD</th>
				<th width="16%" align="right" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">PRECIO UNIT.</th>
				<th width="16%" align="right" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">IMPORTE</th>
			</tr>
		</thead>
		<tbody>';

	if (empty($lineas)) {
		$html .= '
			<tr>
				<td colspan="4" align="center" style="color: #94a3b8; font-size: 9pt; padding: 22px 0;">
					(Sin líneas de detalle registradas)
				</td>
			</tr>';
	} else {
		foreach ($lineas as $l) {
			$is_mat = ($l->tipo == 'material');
			$tipo_label = $is_mat ? 'MATERIAL / RECAMBIO (NUEVO)' : 'MANO DE OBRA';

			$cant_str = '';
			$precio_str = '';
			if ($is_mat) {
				$cant_str = number_format((float) $l->cantidad, 0, ',', '.') . ' ud.';
				$precio_str = number_format((float) $l->precio, 2, ',', '.') . ' €';
			} else {
				// Mano de obra: precio fijo por obra, sin horas
				$cant_str = '—';
				$precio_str = number_format((float) $l->precio, 2, ',', '.') . ' €';
			}

			$desc_label = htmlspecialchars($l->descripcion);
			if (!empty($l->codigo)) {
				$desc_label .= ' (Ref: ' . htmlspecialchars($l->codigo) . ')';
			}

			$importe_str = number_format((float) $l->total_neto, 2, ',', '.') . ' €';

			$html .= '
			<tr>
				<td width="52%" align="left" style="padding: 12px 6px; border-bottom: 0.5px solid #f1f5f9;">
					<span style="font-size: 7pt; font-weight: bold; color: #64748b;">' . $tipo_label . '</span><br>
					<span style="font-size: 9.5pt; font-weight: bold; color: #0f172a;">' . $desc_label . '</span>
				</td>
				<td width="16%" align="center" style="font-size: 9pt; color: #1e293b; border-bottom: 0.5px solid #f1f5f9;"><br>' . $cant_str . '</td>
				<td width="16%" align="right" style="font-size: 9pt; color: #1e293b; border-bottom: 0.5px solid #f1f5f9;"><br>' . $precio_str . '</td>
				<td width="16%" align="right" style="font-size: 9.5pt; color: #0f172a; font-weight: bold; border-bottom: 0.5px solid #f1f5f9;"><br>' . $importe_str . '</td>
			</tr>';
		}
	}

	$html .= '
		</tbody>
	</table>

	<br><br><br><br>

	<table width="100%" cellpadding="0" cellspacing="0" border="0">
		<tr>';

	$notas_raw = trim((string) $factura->notas);
	$notas_txt = '';
	if (!empty($notas_raw)) {
		// Limpiar si contiene código JSON filtrado o prefijo técnico anterior
		if (strpos($notas_raw, '{') !== false && strpos($notas_raw, '}') !== false) {
			if ($notas_raw[0] === '{') {
				$dec = json_decode($notas_raw, true);
				$notas_txt = !empty($dec['notas_usuario']) ? trim($dec['notas_usuario']) : (!empty($dec['notas']) && is_string($dec['notas']) ? trim($dec['notas']) : '');
			} else {
				$clean = preg_replace('/\{.*?\}$/s', '', $notas_raw);
				if (preg_match('/^Factura de reparaci[oó]n\s+OT\s*#\d+/i', trim($clean))) {
					$clean = preg_replace('/^Factura de reparaci[oó]n\s+OT\s*#\d+[^\n]*\n?/i', '', trim($clean));
				}
				$notas_txt = trim($clean);
			}
		} elseif (preg_match('/^Factura de reparaci[oó]n\s+OT\s*#\d+/i', $notas_raw)) {
			$clean = preg_replace('/^Factura de reparaci[oó]n\s+OT\s*#\d+[^\n]*\n?/i', '', $notas_raw);
			$notas_txt = trim($clean);
		} else {
			$notas_txt = $notas_raw;
		}
	}
	$notas_html = !empty($notas_txt)
		? '<span style="font-size: 8.2pt; color: #334155; line-height: 1.5;">' . nl2br(htmlspecialchars($notas_txt)) . '</span>'
		: '<span style="font-size: 8pt; color: #94a3b8; font-style: italic; line-height: 1.5;">Sin notas</span>';

	$iban_block = '';
	if (!empty($taller_iban)) {
		$iban_block = '<div>
			<span style="font-size: 8.5pt; font-weight: bold; color: #0f172a;">Transferencia bancaria:</span><br>
			<span style="font-size: 8.5pt; color: #1e293b; font-family: monospace; font-weight: bold;">IBAN: ' . htmlspecialchars($taller_iban) . '</span>
		</div>';
	}

	// Caja de notas e IBAN fuera de la caja, debajo de ella
	$html .= '
			<td width="53%" valign="top">
				<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-color: #cbd5e1; background-color: #fafbfc;">
					<tr>
						<td height="52" valign="top" style="border: 0.8px solid #cbd5e1;">
							<span style="font-size: 8.3pt; font-weight: bold; color: #0f172a;">Notas:</span><br>
							' . $notas_html . '
						</td>
					</tr>
				</table>';

	if (!empty($iban_block)) {
		$html .= '<br><br>' . $iban_block;
	}

	$html .= '
			</td>
			<td width="5%"></td>';

	$html .= '
			<td width="42%" valign="top">
				<table width="100%" cellpadding="4" cellspacing="0" border="0">
					<tr>
						<td width="58%" align="left" style="font-size: 8.5pt; color: #475569; font-weight: bold;">SUBTOTAL</td>
						<td width="42%" align="right" style="font-size: 8.5pt; font-weight: bold; color: #0f172a;">' . number_format($subtotal, 2, ',', '.') . ' €</td>
					</tr>
					<tr>
						<td width="58%" align="left" style="font-size: 8.5pt; color: #475569; font-weight: bold;">DESCUENTO</td>
						<td width="42%" align="right" style="font-size: 8.5pt; font-weight: bold; color: #0f172a;">' . number_format($descuento, 2, ',', '.') . ' €</td>
					</tr>
					<tr>
						<td width="58%" align="left" style="font-size: 8.5pt; color: #475569; font-weight: bold;">SUBTOTAL - DTO</td>
						<td width="42%" align="right" style="font-size: 8.5pt; font-weight: bold; color: #0f172a;">' . number_format($base, 2, ',', '.') . ' €</td>
					</tr>
					<tr>
						<td width="58%" align="left" style="font-size: 8.5pt; color: #475569; font-weight: bold;">IVA (' . (int) $iva_pct . '%)</td>
						<td width="42%" align="right" style="font-size: 8.5pt; font-weight: bold; color: #0f172a;">' . number_format($iva_amt, 2, ',', '.') . ' €</td>
					</tr>
					<tr>
						<td colspan="2" style="border-bottom: 1.5px solid #0f172a; height: 6px;"></td>
					</tr>
					<tr>
						<td width="58%" align="left" style="font-size: 11pt; font-weight: bold; color: #0f172a; padding-top: 5px;">Total</td>
						<td width="42%" align="right" style="font-size: 12pt; font-weight: bold; color: #0f172a; padding-top: 5px;">' . number_format($total_final, 2, ',', '.') . ' €</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>';


	$pdf->writeHTML($html, true, false, true, false, '');

	// 8. Footer Legal y VERI*FACTU
	$y_footer = 252;
	$pdf->SetLineStyle(array('width' => 0.2, 'color' => array(226, 232, 240)));
	$pdf->Line(14, $y_footer, 196, $y_footer);

	// Dibujar icono VERI*FACTU
	$svg_vf = '<svg width="50" height="70" viewBox="0 0 50 70" xmlns="http://www.w3.org/2000/svg">
	  <rect x="3" y="3" width="44" height="64" rx="8" fill="none" stroke="#94a3b8" stroke-width="3"/>
	  <rect x="9" y="16" width="13" height="13" rx="1.5" fill="#94a3b8"/>
	  <rect x="28" y="16" width="13" height="13" rx="1.5" fill="#94a3b8"/>
	  <rect x="9" y="35" width="13" height="13" rx="1.5" fill="#94a3b8"/>
	  <rect x="28" y="35" width="13" height="13" rx="1.5" fill="#94a3b8"/>
	</svg>';
	$svg_vf_file = $tmp_dir . '/vf_logo_' . getmypid() . '_' . uniqid() . '.svg';
	@file_put_contents($svg_vf_file, $svg_vf);
	if (file_exists($svg_vf_file)) {
		$pdf->ImageSVG($svg_vf_file, 153, $y_footer + 3, 5, 7);
		@unlink($svg_vf_file);
	}

	$footer_html = '
	<table width="100%" cellpadding="3" cellspacing="0" border="0">
		<tr>
			<td width="74%" valign="top">
				<span style="font-size: 6.8pt; color: #475569; line-height: 1.35;">
					' . (!empty($taller_iban) ? '<b>Pago por transferencia:</b> ' . htmlspecialchars($taller_iban) . '<br>' : '') . '
					<b>Garantía legal:</b> De acuerdo con el R.D. 1457/1986, esta reparación tiene una garantía de 3 meses o 2.000 km recorridos (lo que antes suceda), cubriendo mano de obra y repuestos sustituidos. El cliente manifiesta renuncia expresa a la entrega de las piezas viejas retiradas para su correcto reciclaje.<br>
					Datos tratados conforme al RGPD para la facturación y mantenimiento del vehículo.
				</span>
			</td>
			<td width="2%" style="border-right: 0.5px solid #cbd5e1;"></td>
			<td width="24%" valign="top" align="left">
				<table cellpadding="0" cellspacing="0" border="0" width="100%">
					<tr>
						<td width="28%"></td>
						<td width="72%" align="left">
							<span style="font-size: 7.8pt; font-weight: bold; color: #0f172a; letter-spacing: 0.04em;">VERI*FACTU</span><br>
							<span style="font-size: 5.8pt; color: #64748b; line-height: 1.2;">Factura verificable<br>en la sede electrónica de la AEAT</span>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>';

	$pdf->SetXY(14, $y_footer + 2);
	$pdf->writeHTML($footer_html, true, false, true, false, '');

	$filename = 'Factura_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $factura->numero_factura) . '.pdf';

	if ($output_mode == 'I' || $output_mode == 'D') {
		// Encabezados HTTP
		if (!headers_sent()) {
			header('Content-Type: application/pdf');
			if ($output_mode == 'D') {
				header('Content-Disposition: attachment; filename="' . $filename . '"');
			} else {
				header('Content-Disposition: inline; filename="' . $filename . '"');
			}
			header('Cache-Control: private, max-age=0, must-revalidate');
			header('Pragma: public');
		}
		$pdf->Output($filename, $output_mode);
		return true;
	} elseif ($output_mode == 'S') {
		return $pdf->Output($filename, 'S');
	} else {
		$target_file = !empty($file_path) ? $file_path : $filename;
		$pdf->Output($target_file, 'F');
		return $target_file;
	}
}
