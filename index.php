<? require('config.php'); ?>
<!DOCTYPE html5>
<html>
<head><title>Excel naar XML converter voor SEPA Incasso en Verzamelbetaling</title></head>
<body>

<h3>Excel naar XML converter</h3>

Deze eenvoudige webapplicatie vertaalt een Excel document <b>dat opgeslagen is als
&quot;Tekst (tab is scheidingsteken)&quot;</b> naar een xml bestand dat geschikt is
om naar ING te sturen.

<p>De tekst in vrije velden (naam, omschrijving) mag de tekens A-Z a-z 0-9 / -
? : { } . , ' + en spatie bevatten.

<h4>SEPA Incasso</h4>

<p>Het Excel document moet minstens de volgende kolommen hebben, de volgorde maakt 
niet uit. De kolomtitels moeten op de eerste regel van het bestand staan. Eventuele
overige kolommen worden genegeerd.

<ul>
<li>&quot;Incassodatum&quot; (DD-MM-YYYY)</li>
<li>&quot;Soort incasso&quot; (FRST/RCUR/FNAL/OOFF)</li>
<li>&quot;IBAN&quot;</li>
<li>&quot;BIC tegenrekening&quot; (gaat automatisch voor NL)</li>
<li>&quot;T.n.v.&quot; (max 70 tekens)</li>
<li>&quot;Bedrag&quot; (EUR met 2 cijfers achter de komma)</li>
<li>&quot;Omschrijving&quot; (max 140 tekens)</li>
<li>&quot;Kenmerk machtiging&quot; (max 35 tekens)</li>
<li>&quot;Ondertekeningsdatum mandaat&quot; (DD-MM-YYYY)</li>
</ul>

Alle betalingen met dezelfde &quot;Soort incasso&quot; en &quot;Incassodatum&quot; worden automatisch
gegroepeerd in dezelfde batch.

<p><form method="POST" enctype="multipart/form-data" accept-charset="UTF-8" action="maak_xml.php">
<table>
<tr><td>Incassant naam</td><td><input type="text" size="70" maxlength="70" name="Nm" value="<? echo($creditor); ?>"></td></tr>
<tr><td>Incassant IBAN</td><td><input type="text" size="32" maxlength="32" name="CdtrAcct" value="<? echo($IBAN); ?>"></td></tr>
<tr><td>Incassant BIC</td><td><input type="text" size="15" maxlength="11" name="CdtrAgt" value="<? echo($BIC); ?>"></td></tr>
<tr><td>Incassant Id</td><td><input type="text" size="32" maxlength="32" name="CdtrSchmeId" value="<? echo($SchemeId); ?>"></td></tr>
<tr><td>File met incasso's</td><td><input type="file" name="tabseptxt"></td></tr>
</table>
<input type="submit" value="Maak XML bestand">
</form>

<h4>SEPA Verzamelbetaling (klaar om te testen)</h4>

<p>Het Excel document moet minstens de volgende kolommen hebben, de volgorde maakt 
niet uit. De kolomtitels moeten op de eerste regel van het bestand staan. Eventuele
overige kolommen worden genegeerd.

<ul>
<li>&quot;Uitvoeringsdatum&quot; (DD-MM-YYYY)</li>
<li>&quot;Boekingstype&quot; (Totaalbedrag op rekeningafschrift, is dit steeds hetzelfde?)</li>
<li>&quot;IBAN&quot;</li>
<li>&quot;BIC tegenrekening&quot; (gaat automatisch voor NL)</li>
<li>&quot;T.n.v.&quot; (max 70 tekens)</li>
<li>&quot;Bedrag&quot; (EUR met 2 cijfers achter de komma)</li>
<li>&quot;Omschrijving&quot; (max 140 tekens)</li>
</ul>

<p><form method="POST" enctype="multipart/form-data" accept-charset="UTF-8" action="maak_xml2.php">
<table>
<tr><td>Incassant naam</td><td><input type="text" size="70" maxlength="70" name="Nm" value="<? echo($creditor); ?>"></td></tr>
<tr><td>Incassant IBAN</td><td><input type="text" size="32" maxlength="32" name="DbtrAcct" value="<? echo($IBAN); ?>"></td></tr>
<tr><td>Incassant BIC</td><td><input type="text" size="15" maxlength="11" name="DbtrAgt" value="<? echo($BIC); ?>"></td></tr>
<tr><td>File met incasso's</td><td><input type="file" name="tabseptxt"></td></tr>
</table>
<input type="submit" value="Maak XML bestand">
</form>

<p>Wil je andere standaardwaarden? Vraag aan de beheerder om <code>config.php</code> aan te passen.

<p><small>Deze webapplicatie <code>au3</code>, &copy; 2014 Rik Snel &lt;rik@snel.it&gt;. Powered by PHP <? echo(phpversion()); ?>.<br>
Deze software maakt gebruik van <code>php-iban</code>, licentie GPLv3, te vinden op <a href="http://code.google.com/p/php-iban">http://code.google.com/p/php-iban</a>.<br>
Released as <a href="http://www.gnu.org/philosophy/free-sw.html">free software</a> without warranties under <a href="http://www.fsf.org/licensing/licenses/agpl-3.0.html">GNU AGPL v3</a>.<br>
Sourcecode: git clone <a href="https://github.com/rsnel/au3/">https://github.com/rsnel/au3/</a>.
</small>

</body>
</html>
