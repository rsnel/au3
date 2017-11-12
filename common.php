<? 
require('php-iban.php');
// maak deze file volgens UPDATE-BIC
require('BICs.php');

function fatal_error($string) {
	header('Content-Type: text/plain;charset=UTF-8');
	echo("foutmelding: $string");
	exit;
}

function verify_and_format_iban($IBAN) {
	if (!verify_iban($IBAN)) throw new Exception("IBAN $IBAN is ongeldig");
	return iban_to_machine_format($IBAN);
}

function verify_generate_bic($BIC, $IBAN, $BICs) {
	// bij een Nederlandse IBAN hoeft geen BIC gegeven te zijn
	// in dat geval genereren we de BIC
	if (!strncmp($IBAN, 'NL', 2)) {
		$BankIdentifier = substr($IBAN, 4, 4);
		if ($BIC == '') {
			// geen BIC gegeven
			if (!isset($BICs[$BankIdentifier]))
				throw new Exception("we weten de BIC niet van bank met Bank Identifier $BankIdentifier, ".
						'vraag de beheerder om de BIC toe te voegen (lees UPDATE-BIC), of vul de BIC handmatig in');
			return $BICs[$BankIdentifier];
		} else {
			if (!isset($BICs[$BankIdentifier])) {
				/* we weten de BIC van deze bank niet, dus we kunnen hem niet echt controleren */
				if (!preg_match('/^[A-Z]{6}[0-9A-Z]{2}([0-9A-Z]{3})?$/', $BIC)) throw new Exception("ongeldige BIC, $BIC");
				return $BIC;
			}
			if ($BIC != $BICs[substr($IBAN, 4, 4)]) throw new Exception("BIC $BIC van IBAN $IBAN klopt niet");
			return $BIC;
		}
	} else {
		// buitenlandse BIC kunnen we niet volledig controleren
		if (!preg_match('/^[A-Z]{6}[0-9A-Z]{2}([0-9A-Z]{3})?$/', $BIC)) throw new Exception("ongeldige BIC, $BIC");
		return $BIC;
	}
}

function check_and_format_date($date, $naam) {
	if (!preg_match('/^(\d+)-(\d+)-(\d\d\d\d)$/', $date, $matches))
		throw new Exception("ongeldig format \"$naam\", $date, moet zijn DD-MM-YYYY");
	if (!checkdate($matches[2], $matches[1], $matches[3]))
	       	throw new Exception("maand {$matches[2]} heeft geen dag {$matches[1]} in {$matches[3]} in \"$naam\", $datum");
	return str_pad($matches[3], 4, '0', STR_PAD_LEFT).'-'.str_pad($matches[2], 2, '0', STR_PAD_LEFT).'-'.str_pad($matches[1], 2, '0', STR_PAD_LEFT);
}

function check_and_format_text($text, $naam, $minsize = 0, $maxsize = 65535) {
	if (!preg_match('/^[a-zA-Z0-9\/\-\?:\(\)\.,\' ]*$/', $text))
		throw new Exception("tekst bevat niet-toegestane tekens, alleen A-Z a-z 0-9 / - ? : ( ) . , ' + zijn toegestaan, in \"$naam\", $text");
	if (strlen($text) < $minsize) throw new Exception("\"$naam\" moet minstens uit $minsize teken(s) bestaan");
	if (strlen($text) > $maxsize) throw new Exception("\"$naam\" mag niet uit meer dan $maxsize bestaan");
	return $text;
}

function line_empty($line) {
	foreach ($line as $elt) {
		if (trim($elt) != '') return false;
	}
	return true;
}

// extendXml($parent, 'tag1', 'tag2', 'tag3', 'text')
//	-> <tag1><tag2><tag3>text</tag3></tag2></tag1>
// extendXml($parent, 'tag1', 'tag2, 'tag3', NULL) 
// 	-> <tag1><tag2><tag3/></tag2></tag1>
// extendXml($parent, 'tag1') -> <tag1/> (special case)
//
// returns innermost tag
function extendXml($parent) {
	global $doc;
	$args = func_get_args();
	array_shift($args); // remove first arg, which is $parent

	if (count($args) == 0) fatal_error('te weinig argumenten voor extendXml');

	while (count($args) > 2) {
		$parent = $parent->appendChild($doc->createElement($args[0]));
		array_shift($args);
	}

	return $parent->appendChild($doc->createElement($args[0], isset($args[1])?$args[1]:NULL));
}

function check_columns($idx) {
	$args = func_get_args();
	array_shift($args);

	foreach ($args as $arg) {
		if (!isset($idx[strtolower($arg)])) fatal_error('kolom '.$arg.' ontbreekt in geuploade file');
	}
}

function get_field($line, $idx, $naam) {
	if (!isset($idx[strtolower($naam)])) fatal_error('kolom '.$naam.' gevraagd maar niet geeist!');
	return trim($line[$idx[strtolower($naam)]]);
}

?>
