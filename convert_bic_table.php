#!/usr/bin/php5
<?
function fatal_error($string) {
	echo($string."\n");
	exit;
}

function find_month($month) {
	switch (strtolower($month)) {
		case 'januari': return 1;
		case 'februari': return 2;
		case 'maart': return 3;
		case 'april': return 4;
		case 'mei': return 5;
		case 'juni': return 6;
		case 'juli': return 7;
		case 'augustus': return 8;
		case 'september': return 9;
		case 'oktober': return 10;
		case 'november': return 11;
		case 'december': return 12;
		default:
			fatal_error('niet bestaande maand '.$maand.'??!?!');
	}
}

if (count($argv) != 2) fatal_error("Usage: see UPDATE-BIC");

if (!($fp = fopen($argv[1], 'r'))) fatal_error("unable to open {$argv[1]}");

$line = fgetcsv($fp, 0, "\t");

if (!preg_match('/^Laatste update: (\d+) ([a-z]+) (\d{4})$/', $line[0], $matches)) {
	echo($line[0]);
	fatal_error("eerste regel klopt niet, zie UPDATE-BIC");
}

$day = $matches[1];
$year = $matches[3];
$month = find_month($matches[2]);

if (!checkdate($month, $day, $year)) fatal_error('niet bestaande datum?!?!?');

$date_string = $year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'-'.str_pad($day, 2, '0', STR_PAD_LEFT);

$line = fgetcsv($fp, 0, "\t");
if (strtolower($line[0]) != 'bank identifier') fatal_error('eerste kolom heeft verkeerde header');
if (strtolower($line[1]) != 'bic') fatal_error('tweede kolom heeft verkeerde header');

$BICs = Array();

while (($line = fgetcsv($fp, 0, "\t"))) {
	if (!preg_match('/^[A-Z]{4}$/', $line[0])) fatal_error("Bank Identifier {$line[0]} bestaat niet uit 4 hoofdletters?!?!");
	if (!preg_match('/^[A-Z]{6}[0-9A-Z]{2}([0-9A-Z]{3})?$/', $line[1])) fatal_error("BIC {$line[1]} is geen geldige BIC?!?!");
	$BICs[$line[0]] = $line[1];
}

//print_r($BICs);
//echo($date_string);

fclose($fp);

if (!($fp = fopen("BICs-$date_string.php", 'x'))) fatal_error('unable to open BICs-'.$date_string.'.php for writing');

fwrite($fp, '<? $BICs = Array(');

foreach ($BICs as $key => $BIC) {
	fwrite($fp, " \"$key\" => \"$BIC\",\n");
}

fwrite($fp, '); ?>');
fclose($fp);
?>
