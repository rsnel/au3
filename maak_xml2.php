<? 
ini_set("auto_detect_line_endings", true);
require('config.php');
require('common.php');

// check script argumenten
try {
	$Nm = check_and_format_text(trim($_POST['Nm']), 'naam', 1, 70);
	$DbtrAcct = verify_and_format_iban(strtoupper(trim($_POST['DbtrAcct'])));
	$DbtrAgt = verify_generate_bic(strtoupper(trim($_POST['DbtrAgt'])), $DbtrAcct, $BICs);
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
$MsgId = 'V'.date('YmdHis', $time).$fileId;
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
check_columns($idx, 'Uitvoeringsdatum', 'IBAN', 'BIC tegenrekening', 'T.n.v.',
	'Bedrag', 'Omschrijving');

// telt regels in inputbestand, zodat foutmeldingen kunnen worden voorzien van regelnummer
$linecounter = 1;

$PmtInfs = Array();
$CtrlSum = 0;
$NbOfTxs = 0;

try {
	while (($line = fgetcsv($fp, 0, "\t"))) {
		$linecounter++;

		if (line_empty($line)) continue;

		$ReqdExctnDt = check_and_format_date(get_field($line, $idx, 'Uitvoeringsdatum'), 'Uitvoeringsdatum');

		// het batchvinkje werkt niet
		//if (isset($_POST['batch'])) { // batch by date
			$BatchBy = $ReqdExctnDt;
		//} else { // do not batch
		//	$BatchBy = $linecounter;
		//}

		// we kunnen de batch identificeren met ReqdColltnDt
		if (!isset($PmtInfs[$BatchBy])) {
			// batch bestaat nog niet; maak nieuwe
			$PmtInfs[$BatchBy] = Array();
			$PmtInfs[$BatchBy]['ReqdExctnDt'] = $ReqdExctnDt;
			$PmtInfs[$BatchBy]['NbOfTxs'] = 0;
			$PmtInfs[$BatchBy]['CtrlSum'] = 0;
			$PmtInfs[$BatchBy]['CdtTrfTxInfs'] = Array();
		}

		$CdtTrfTxInf = Array();

		$CdtTrfTxInf['IBAN'] = verify_and_format_iban(strtoupper(get_field($line, $idx, 'IBAN')));
		$CdtTrfTxInf['BIC'] = verify_generate_bic(strtoupper(get_field($line, $idx, 'BIC tegenrekening')), $CdtTrfTxInf['IBAN'], $BICs);
		$CdtTrfTxInf['Nm'] = check_and_format_text(get_field($line, $idx, 'T.n.v.'), 'T.n.v.', 1, 70);
		$CdtTrfTxInf['Ustrd'] = check_and_format_text(get_field($line, $idx, 'Omschrijving'), 'Omschrijving', 0, 140);

		// bedrag
		$bedrag = get_field($line, $idx, 'bedrag');
		if (!preg_match('/^(\d+)[.,](\d{2})$/', $bedrag, $matches)) fatal_error("bedrag $bedrag is ongeldig, moet in EUR op 2 decimalen");
		$cents = 100*$matches[1] + $matches[2];
		$CdtTrfTxInf['InstdAmt'] = $matches[1].'.'.$matches[2];

		// CtrlSum wordt later geconverteerd naar EURos
		$CtrlSum += $cents;
		$NbOfTxs += 1;
		$PmtInfs[$BatchBy]['CtrlSum'] += $cents;
		$PmtInfs[$BatchBy]['NbOfTxs'] += 1;
		$PmtInfs[$BatchBy]['CdtTrfTxInfs'][] = $CdtTrfTxInf;
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
$xmlDocument->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03');
$xmlDocument->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

$xmlCstmrCdtTrfInitn = extendXml($xmlDocument, 'CstmrCdtTrfInitn');

$xmlGrpHdr = extendXml($xmlCstmrCdtTrfInitn, 'GrpHdr');
extendXml($xmlGrpHdr, 'MsgId', $MsgId);
extendXml($xmlGrpHdr, 'CreDtTm', date('Y-m-d\TH:i:s', $time));
extendXml($xmlGrpHdr, 'NbOfTxs', $NbOfTxs);
extendXml($xmlGrpHdr, 'CtrlSum', $CtrlSum);
extendXml($xmlGrpHdr, 'InitgPty', 'Nm', $Nm);

foreach ($PmtInfs as $PmtInf) {
	$xmlPmtInf = extendXml($xmlCstmrCdtTrfInitn, 'PmtInf');
	extendXml($xmlPmtInf, 'PmtInfId', $MsgId.'-P'.str_pad(dechex($counter++), 4, '0', STR_PAD_LEFT));
	extendXml($xmlPmtInf, 'PmtMtd', 'TRF');
	extendXml($xmlPmtInf, 'NbOfTxs', $PmtInf['NbOfTxs']);
	extendXml($xmlPmtInf, 'CtrlSum', $PmtInf['CtrlSum']);

	$xmlPmtTpInf = extendXml($xmlPmtInf, 'PmtTpInf');
	extendXml($xmlPmtTpInf, 'InstrPrty', 'NORM');
	extendXml($xmlPmtTpInf, 'SvcLvl', 'Cd', 'SEPA');

	extendXml($xmlPmtInf, 'ReqdExctnDt', $PmtInf['ReqdExctnDt']);
	extendXml($xmlPmtInf, 'Dbtr', 'Nm', $Nm);
	extendXml($xmlPmtInf, 'DbtrAcct', 'Id', 'IBAN', $DbtrAcct);
	extendXml($xmlPmtInf, 'DbtrAgt', 'FinInstnId', 'BIC', $DbtrAgt);
	extendXml($xmlPmtInf, 'ChrgBr', 'SLEV');

	foreach ($PmtInf['CdtTrfTxInfs'] as $CdtTrfTxInf) {
		$xmlCdtTrfTxInf = extendXml($xmlPmtInf, 'CdtTrfTxInf');
		extendXml($xmlCdtTrfTxInf, 'PmtId', 'EndToEndId', $MsgId.'-E'.str_pad(dechex($counter2++), 4, '0', STR_PAD_LEFT));
		extendXml($xmlCdtTrfTxInf, 'Amt', 'InstdAmt', $CdtTrfTxInf['InstdAmt'])->setAttribute('Ccy', 'EUR');

		extendXml($xmlCdtTrfTxInf, 'CdtrAgt', 'FinInstnId', 'BIC', $CdtTrfTxInf['BIC']);
		extendXml($xmlCdtTrfTxInf, 'Cdtr', 'Nm', $CdtTrfTxInf['Nm']);
		extendXml($xmlCdtTrfTxInf, 'CdtrAcct', 'Id', 'IBAN', $CdtTrfTxInf['IBAN']);
		extendXml($xmlCdtTrfTxInf, 'RmtInf', 'Ustrd', $CdtTrfTxInf['Ustrd']);
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
