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
	$sql .= "s.nom as cliente_nombre, COALESCE(NULLIF(s.siren, ''), NULLIF(s.siret, ''), s.tva_intra) as cliente_cif, ";
	$sql .= "s.address as cliente_direccion, s.zip as cliente_cp, s.town as cliente_ciudad, s.phone as cliente_telefono, s.email as cliente_email, ";
	$sql .= "v.matricula as vehiculo_matricula, v.marca as vehiculo_marca, v.modelo as vehiculo_modelo, v.anio as vehiculo_anio, v.vin as vehiculo_vin, v.km as vehiculo_km ";
	$sql .= "FROM " . MAIN_DB_PREFIX . "facture as f ";
	$sql .= "LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON f.fk_soc = s.rowid ";
	$sql .= "LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields as fe ON fe.fk_object = f.rowid ";
	$sql .= "LEFT JOIN " . MAIN_DB_PREFIX . "taller_vehiculo as v ON fe.fk_vehiculo = v.rowid ";
	$sql .= "WHERE f.rowid = " . ((int) $fac_id);

	$res = $db->query($sql);
	if (!$res || !($factura = $db->fetch_object($res))) {
		return false;
	}

	// 2. Obtener líneas de la factura desde llx_facturedet
	$lineas = array();
	$tot_mat = 0.0;
	$tot_rep = 0.0;
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
			if ($l->tipo == 'material') {
				$tot_mat += (float) $l->total_neto;
			} else {
				$tot_rep += (float) $l->total_neto;
			}
		}
	}

	$subtotal = $tot_mat + $tot_rep;
	$descuento = (float) $factura->descuento_global;
	$base = max(0, $subtotal - $descuento);
	$iva_pct = (float) ($factura->iva_porcentaje !== null ? $factura->iva_porcentaje : 21);
	$iva_amt = $base * ($iva_pct / 100);
	$total_final = (float) $factura->total;
	if ($total_final <= 0 && ($base > 0 || $descuento > 0)) {
		$total_final = $base + $iva_amt;
	}

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

	// Descifrar IBAN bancario protegido
	$taller_iban = '';
	if (!empty($conf->global->TALLER_IBAN)) {
		$raw_iban = dolDecrypt($conf->global->TALLER_IBAN);
		if (!empty($raw_iban)) {
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
		$svg_logo = '<svg width="90" height="90" viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg">
		  <rect width="90" height="90" rx="18" fill="#0f172a"/>
		  <path d="M57 27a10 10 0 0 0-11.8 1.8l-5.6 5.6 13.6 13.6 5.6-5.6A10 10 0 0 0 57 27zm-20.4 12L19 56.6a7.5 7.5 0 0 0 10.6 10.6l17.6-17.6-10.6-10.6z" fill="#ffffff"/>
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

	// Contenido principal HTML con espaciado amplio y equilibrado
	$pdf->SetXY(14, 36);
	$pdf->SetFont('helvetica', '', 8.5);

	$html = '
	<table width="100%" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<td width="35%" valign="top">
				<span style="font-size: 7.5pt; font-weight: bold; color: #94a3b8; letter-spacing: 0.05em;">FACTURADO A</span><br><br>
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
				<span style="font-size: 13pt; font-weight: bold; color: #0f172a;">Factura Nº ' . htmlspecialchars($factura->numero_factura) . '</span><br><br>
				<span style="color: #334155; font-size: 8.5pt; line-height: 1.5;">
					Fecha emisión: ' . $fecha_formateada . '<br>
					Estado: ' . $estado_bold . '<br>
					Forma de pago: Tarjeta
				</span>
			</td>
		</tr>
	</table>

	<br><br><br><br>

	<table width="100%" cellpadding="8" cellspacing="0" border="0">
		<thead>
			<tr>
				<th width="46%" align="left" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">DESCRIPCIÓN</th>
				<th width="16%" align="center" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">CANTIDAD</th>
				<th width="15%" align="right" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">PRECIO UNIT.</th>
				<th width="8%" align="center" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">IVA</th>
				<th width="15%" align="right" style="font-size: 8pt; font-weight: bold; color: #475569; border-bottom: 1.5px solid #0f172a;">IMPORTE</th>
			</tr>
		</thead>
		<tbody>';

	if (empty($lineas)) {
		$html .= '
			<tr>
				<td colspan="5" align="center" style="color: #94a3b8; font-size: 9pt; padding: 22px 0;">
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
				$cant_num = (float) $l->cantidad;
				if ($cant_num < 1 && $cant_num > 0) {
					$minutos = round($cant_num * 60);
					$cant_str = number_format($cant_num, 2, ',', '.') . ' h (' . $minutos . ' min)';
				} else {
					$cant_str = number_format($cant_num, 2, ',', '.') . ' h';
				}
				$precio_str = number_format((float) $l->precio, 2, ',', '.') . ' €/h';
			}

			$desc_label = htmlspecialchars($l->descripcion);
			if (!empty($l->codigo)) {
				$desc_label .= ' (Ref: ' . htmlspecialchars($l->codigo) . ')';
			}

			$importe_str = number_format((float) $l->total_neto, 2, ',', '.') . ' €';

			$html .= '
			<tr>
				<td width="46%" align="left" style="padding: 12px 6px; border-bottom: 0.5px solid #f1f5f9;">
					<span style="font-size: 7pt; font-weight: bold; color: #64748b;">' . $tipo_label . '</span><br>
					<span style="font-size: 9.5pt; font-weight: bold; color: #0f172a;">' . $desc_label . '</span>
				</td>
				<td width="16%" align="center" style="font-size: 9pt; color: #1e293b; border-bottom: 0.5px solid #f1f5f9;"><br>' . $cant_str . '</td>
				<td width="15%" align="right" style="font-size: 9pt; color: #1e293b; border-bottom: 0.5px solid #f1f5f9;"><br>' . $precio_str . '</td>
				<td width="8%" align="center" style="font-size: 9pt; color: #1e293b; border-bottom: 0.5px solid #f1f5f9;"><br>' . (int) $iva_pct . '%</td>
				<td width="15%" align="right" style="font-size: 9.5pt; color: #0f172a; font-weight: bold; border-bottom: 0.5px solid #f1f5f9;"><br>' . $importe_str . '</td>
			</tr>';
		}
	}

	$html .= '
		</tbody>
	</table>

	<br><br><br><br>

	<table width="100%" cellpadding="0" cellspacing="0" border="0">
		<tr>';

	$notas_txt = trim((string) $factura->notas);
	$notas_html = !empty($notas_txt)
		? '<span style="font-size: 8.2pt; color: #334155; line-height: 1.5;">' . nl2br(htmlspecialchars($notas_txt)) . '</span>'
		: '<span style="font-size: 8pt; color: #94a3b8; font-style: italic; line-height: 1.5;">Sin notas</span>';

	$iban_block = '';
	if (!empty($taller_iban)) {
		$iban_block = '<div style="margin-bottom: 6px;">
			<span style="font-size: 8.3pt; font-weight: bold; color: #0f172a;">Transferencia bancaria:</span><br>
			<span style="font-size: 8.2pt; color: #1e293b; font-family: monospace; font-weight: bold;">IBAN: ' . htmlspecialchars($taller_iban) . '</span>
		</div><div style="height: 4px;"></div>';
	}

	// Caja única de notas y datos bancarios
	$html .= '
			<td width="53%" valign="top">
				<table width="100%" cellpadding="8" cellspacing="0" border="1" style="border-color: #cbd5e1; background-color: #fafbfc;">
					<tr>
						<td height="72" valign="top" style="border: 0.8px solid #cbd5e1;">
							' . $iban_block . '
							<span style="font-size: 8.3pt; font-weight: bold; color: #0f172a;">Notas:</span><br>
							' . $notas_html . '
						</td>
					</tr>
				</table>
			</td>
			<td width="5%"></td>';

	$html .= '
			<td width="42%" valign="top">
				<table width="100%" cellpadding="6" cellspacing="0" border="0">
					<tr>
						<td width="60%" align="left" style="font-size: 9pt; color: #475569;">Subtotal (Base Imponible)</td>
						<td width="40%" align="right" style="font-size: 9pt; font-weight: bold; color: #0f172a;">' . number_format($base, 2, ',', '.') . ' €</td>
					</tr>
					<tr>
						<td width="60%" align="left" style="font-size: 9pt; color: #475569;">IVA (' . (int) $iva_pct . '%)</td>
						<td width="40%" align="right" style="font-size: 9pt; font-weight: bold; color: #0f172a;">' . number_format($iva_amt, 2, ',', '.') . ' €</td>
					</tr>
					<tr>
						<td colspan="2" style="border-bottom: 1.5px solid #0f172a; height: 8px;"></td>
					</tr>
					<tr>
						<td width="60%" align="left" style="font-size: 11.5pt; font-weight: bold; color: #0f172a; padding-top: 6px;">TOTAL</td>
						<td width="40%" align="right" style="font-size: 12.5pt; font-weight: bold; color: #0f172a; padding-top: 6px;">' . number_format($total_final, 2, ',', '.') . ' €</td>
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
