<? 
ini_set("auto_detect_line_endings", true);
require('config.php');
require('common.php');

// check script argumenten
try {
	$Nm = check_and_format_text(trim($_POST['Nm']), 'naam', 1, 70);
	$CdtrAcct = verify_and_format_iban(strtoupper(trim($_POST['CdtrAcct'])));
	$CdtrAgt = verify_generate_bic(strtoupper(trim($_POST['CdtrAgt'])), $CdtrAcct, $BICs);
	$CdtrSchmeId = check_and_format_text(trim($_POST['CdtrSchmeId']), 'Id', 1);
} catch (Exception $e) {
	fatal_error("Incassant {$e->GetMessage()}");
}

// import 'tab gescheiden txt'-bestand

switch ($_FILES['tabseptxt']['error']) {
	case UPLOAD_ERR_INI_SIZE:
                fatal_error('ge-uploade file te groot volgens php.ini');
        case UPLOAD_ERR_FORM_SIZE:
                fatal_error('ge-uploade file te groot volgens policy van au3');
        case UPLOAD_ERR_PARTIAL:
                fatal_error('upload mislukt, file slechts gedeeltelijk aangekomen');
        case UPLOAD_ERR_NO_FILE:
                fatal_error('er is geen file geupload, omdat er geen geselecteerd was');
        case UPLOAD_ERR_NO_TMP_DIR:
                fatal_error('kan de file nergens kwijt, vraag de beheerder het probleem op te lossen (UPLOAD_ERR_NO_TMP_DIR)');
        case UPLOAD_ERR_CANT_WRITE:
                fatal_error('schijf vol?, vraag de beheerder het probleem op te lossen (UPLOAD_ERR_CANT_WRITE)');
        case UPLOAD_ERR_OK:
                break;
        default:
                fatal_error('onmogelijke error');
}

// the MsgId is 'I' YYYY MM DD HH MM SS SHA256(14 chars)
// PmtID is MsgId-P0000 (hex counter)
// E2EID is MsgId-E0000 (hex counter)
$fileId = strtoupper(substr(hash_file('SHA256', $_FILES['tabseptxt']['tmp_name']), 0, 14));
$time = time();
$MsgId = 'I'.date('YmdHis', $time).$fileId;
$filename = $MsgId.'-'.$_FILES['tabseptxt']['name'];


if (!($fp = fopen($_FILES['tabseptxt']['tmp_name'], 'r'))) fatal_error('unable to open uploaded file');

// attempt to store file somewhere else for reference, don't bother user if it fails
// since we already have a handle for the file succes or failure doesn't matter
move_uploaded_file($_FILES['tabseptxt']['tmp_name'], $datadir.$filename);

if (!($legenda = fgetcsv($fp, 0, "\t"))) fatal_error('unable to read fist line of uploaded file');

// inverteer legenda, zodat we kunnen zoeken op kolomnaam
$idx = Array();
foreach ($legenda as $key => $header) {
	$idx[strtolower(trim($header))] = $key;
}

// controleer of de benodigde kolommen aanwezig zijn
check_columns($idx, 'Incassodatum', 'Soort incasso', 'IBAN', 'BIC tegenrekening', 'T.n.v.',
		'Bedrag', 'Omschrijving', 'Kenmerk machtiging', 'Ondertekeningsdatum mandaat');

// telt regels in inputbestand, zodat foutmeldingen kunnen worden voorzien van regelnummer
$linecounter = 1;

$PmtInfs = Array();
$CtrlSum = 0;
$NbOfTxs = 0;

try {
	while (($line = fgetcsv($fp, 0, "\t"))) {
		$linecounter++;

		if (line_empty($line)) continue;

		$ReqdColltnDt = check_and_format_date(get_field($line, $idx, 'Incassodatum'), 'Incassodatum');

		$Soort_incasso = get_field($line, $idx, 'Soort incasso');
		// check incassosoort
		if ($Soort_incasso != 'FRST' &&
			$Soort_incasso != 'RCUR' &&
			$Soort_incasso != 'FNAL' &&
			$Soort_incasso != 'OOFF') 
			throw new Exception("ongeldige \"Soort incasso\", $Soort_incasso, moet zijn FRST, RCUR, FNAL of OOFF");
		$SeqTp = $Soort_incasso;

		// we kunnen de batch identificeren met ReqdColltnDt en SeqTp
		if (!isset($PmtInfs[$ReqdColltnDt.'-'.$SeqTp])) {
			// batch bestaat nog niet; maak nieuwe
			$PmtInfs[$ReqdColltnDt.'-'.$SeqTp] = Array();
			$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['ReqdColltnDt'] = $ReqdColltnDt;
			$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['SeqTp'] = $SeqTp;
			$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['NbOfTxs'] = 0;
			$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['CtrlSum'] = 0;
			$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['DrctDbtTxInfs'] = Array();
		}

		$DrctDbtTxInf = Array();

		$DrctDbtTxInf['IBAN'] = verify_and_format_iban(strtoupper(get_field($line, $idx, 'IBAN')));
		$DrctDbtTxInf['BIC'] = verify_generate_bic(strtoupper(get_field($line, $idx, 'BIC tegenrekening')), $DrctDbtTxInf['IBAN'], $BICs);
		$DrctDbtTxInf['Nm'] = check_and_format_text(get_field($line, $idx, 'T.n.v.'), 'T.n.v.', 1, 70);
		$DrctDbtTxInf['Ustrd'] = check_and_format_text(get_field($line, $idx, 'Omschrijving'), 'Omschrijving', 0, 140);
		$DrctDbtTxInf['MndtId'] = check_and_format_text(get_field($line, $idx, 'Kenmerk machtiging'), 'Kenmerk machtiging', 0, 140);
		$DrctDbtTxInf['DtOfSgntr'] = check_and_format_date(get_field($line, $idx, 'Ondertekeningsdatum mandaat'), 'Ondertekeningsdatum mandaat');

		// bedrag
		$bedrag = get_field($line, $idx, 'bedrag');
		if (!preg_match('/^(\d+)[.,](\d{2})$/', $bedrag, $matches)) fatal_error("bedrag $bedrag is ongeldig, moet in EUR op 2 decimalen");
		$cents = 100*$matches[1] + $matches[2];
		$DrctDbtTxInf['InstdAmt'] = $matches[1].'.'.$matches[2];

		// CtrlSum wordt later geconverteerd naar EURos
		$CtrlSum += $cents;
		$NbOfTxs += 1;
		$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['CtrlSum'] += $cents;
		$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['NbOfTxs'] += 1;
		$PmtInfs[$ReqdColltnDt.'-'.$SeqTp]['DrctDbtTxInfs'][] = $DrctDbtTxInf;
	}
} catch (Exception $e) {
	fatal_error("parsing line $linecounter: {$e->GetMessage()}");
}

// CtrlSum is nu in centen, maak er EURos van
$CtrlSum = number_format($CtrlSum/100, 2, '.', '');
foreach ($PmtInfs as $key => $PmtInf) {
	$PmtInfs[$key]['CtrlSum'] = number_format($PmtInf['CtrlSum']/100, 2, '.', '');
}

// alle data is ingelezen, we gaan nu de XML maken
$counter = 1; // PmtInf counter
$counter2 = 1; // overal DrctDbtTxInf counter (does not reset when new PmtInf occurs)

$doc = new DOMDocument('1.0', 'UTF-8');

$doc->formatOutput = true;

$xmlDocument = extendXml($doc, 'Document');
$xmlDocument->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');
$xmlDocument->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

$xmlCstmrDrctDbtInitn = extendXml($xmlDocument, 'CstmrDrctDbtInitn');

$xmlGrpHdr = extendXml($xmlCstmrDrctDbtInitn, 'GrpHdr');
extendXml($xmlGrpHdr, 'MsgId', $MsgId);
extendXml($xmlGrpHdr, 'CreDtTm', date('Y-m-d\TH:i:s', $time));
extendXml($xmlGrpHdr, 'NbOfTxs', $NbOfTxs);
extendXml($xmlGrpHdr, 'CtrlSum', $CtrlSum);
extendXml($xmlGrpHdr, 'InitgPty', 'Nm', $Nm);

foreach ($PmtInfs as $PmtInf) {
	$xmlPmtInf = extendXml($xmlCstmrDrctDbtInitn, 'PmtInf');
	extendXml($xmlPmtInf, 'PmtInfId', $MsgId.'-P'.str_pad(dechex($counter++), 4, '0', STR_PAD_LEFT));
	extendXml($xmlPmtInf, 'PmtMtd', 'DD');
	extendXml($xmlPmtInf, 'BtchBookg', 'true');
	extendXml($xmlPmtInf, 'NbOfTxs', $PmtInf['NbOfTxs']);
	extendXml($xmlPmtInf, 'CtrlSum', $PmtInf['CtrlSum']);

	$xmlPmtTpInf = extendXml($xmlPmtInf, 'PmtTpInf');
	extendXml($xmlPmtTpInf, 'SvcLvl', 'Cd', 'SEPA');
	extendXml($xmlPmtTpInf, 'LclInstrm', 'Cd', 'CORE');
	extendXml($xmlPmtTpInf, 'SeqTp', $PmtInf['SeqTp']);

	extendXml($xmlPmtInf, 'ReqdColltnDt', $PmtInf['ReqdColltnDt']);
	extendXml($xmlPmtInf, 'Cdtr', 'Nm', $Nm);
	extendXml($xmlPmtInf, 'CdtrAcct', 'Id', 'IBAN', $CdtrAcct);
	extendXml($xmlPmtInf, 'CdtrAgt', 'FinInstnId', 'BIC', $CdtrAgt);
	extendXml($xmlPmtInf, 'ChrgBr', 'SLEV');

	$xmlOthr = extendXml($xmlPmtInf, 'CdtrSchmeId', 'Id', 'PrvtId', 'Othr', NULL);
	extendXml($xmlOthr, 'Id', $CdtrSchmeId);
	extendXml($xmlOthr, 'SchmeNm', 'Prtry', 'SEPA');
	
	foreach ($PmtInf['DrctDbtTxInfs'] as $DrctDbtTxInf) {
		$xmlDrctDbtTxInf = extendXml($xmlPmtInf, 'DrctDbtTxInf');
		extendXml($xmlDrctDbtTxInf, 'PmtId', 'EndToEndId', $MsgId.'-E'.str_pad(dechex($counter2++), 4, '0', STR_PAD_LEFT));
		extendXml($xmlDrctDbtTxInf, 'InstdAmt', $DrctDbtTxInf['InstdAmt'])->setAttribute('Ccy', 'EUR');

		$xmlMndtRltdInf = extendXml($xmlDrctDbtTxInf, 'DrctDbtTx', 'MndtRltdInf', NULL);
		extendXml($xmlMndtRltdInf, 'MndtId', $DrctDbtTxInf['MndtId']);
		extendXml($xmlMndtRltdInf, 'DtOfSgntr', $DrctDbtTxInf['DtOfSgntr']);
		extendXml($xmlMndtRltdInf, 'AmdmntInd', 'false');

		extendXml($xmlDrctDbtTxInf, 'DbtrAgt', 'FinInstnId', 'BIC', $DrctDbtTxInf['BIC']);
		extendXml($xmlDrctDbtTxInf, 'Dbtr', 'Nm', $DrctDbtTxInf['Nm']);
		extendXml($xmlDrctDbtTxInf, 'DbtrAcct', 'Id', 'IBAN', $DrctDbtTxInf['IBAN']);
		extendXml($xmlDrctDbtTxInf, 'RmtInf', 'Ustrd', $DrctDbtTxInf['Ustrd']);
	}
}

$xml = $doc->saveXML();

// we slaan de gegenereerde xml op, zodat we er later nog naar kunnen kijken
if ($fp = fopen($datadir.$MsgId.'.xml', 'w')) {
	fwrite($fp, $xml);
	fclose($fp);
}

header('Content-Type: text/xml;charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$MsgId.'.xml"');

/* extra headers om internet explorer blij te maken */
header('Pragma: public');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Cache-Control: public');

echo($xml);
?>
