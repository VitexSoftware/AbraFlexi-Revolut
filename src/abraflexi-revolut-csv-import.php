<?php

define('APP_NAME', 'RevolutCSVtoAbraFlexi');
require_once '../vendor/autoload.php';
$csvFile = $argv[1];
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ACCOUNT_IBAN'], '../.env');

/**
 * Gives you AbraFlexi Bank
 *
 * @param string $accountIban
 *
 * @return \AbraFlexi\RO
 *
 * @throws Exception
 */
function getBank($accountIban)
{
    $banker = new \AbraFlexi\RO(null, ['evidence' => 'bankovni-ucet']);
    $candidat = $banker->getColumnsFromAbraFlexi('id', ['iban' => $accountIban]);
    if (empty($candidat) || !array_key_exists('id', $candidat[0])) {
        throw new Exception('Bank account ' . $accountIban . ' not found in AbraFlexi');
    } else {
        $banker->loadFromAbraFlexi($candidat[0]['id']);
    }
    return $banker;
}

$account = getBank(\Ease\Functions::cfg('ACCOUNT_IBAN'));

if (\Ease\Shared::cfg('APP_DEBUG', false)) {
    $account->logBanner();
}

$row = 1;
$transactions = [];
$columns = [];
if (($handle = fopen($csvFile, "r")) !== false) {
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        if ($row++ == 1) {
            $columns = $data;
            $num = count($data);
            continue;
        }
        $transactions[] = array_combine($columns, $data);
    }
    fclose($handle);
}

foreach ($transactions as $transaction) {
    if ($transaction['Type'] == 'TOPUP') {
        $banker = new \AbraFlexi\Banka();
        $candidates = $banker->getColumnsFromAbraFlexi('id', ['cisDosle' => $transaction['Completed Date']]);
        if (empty($candidates)) {
            $numRow = new \AbraFlexi\RO(\AbraFlexi\RO::code(\Ease\Functions::cfg('DOCUMENT_NUMROW', 'REVO+')), ['evidence' => 'rada-banka']);
            //            $id = $numRow->getDataValue('polozkyRady')[0]['preview'];
            //            $banker->setDataValue('kod', $id); // str_replace([' ', ':', '-'], '', $transaction['Completed Date'])
            $banker->setDataValue('bezPolozek', true);
            $banker->setDataValue('typDokl', \AbraFlexi\RO::code(\Ease\Functions::cfg('DOCUMENT_TYPE', 'STAND')));
            $banker->setDataValue('rada', \AbraFlexi\RO::code($numRow));
            $banker->setDataValue('banka', $account);
            $banker->setDataValue('typPohybuK', 'typPohybu.prijem');
            $banker->setDataValue('popis', $transaction['Description']);
            $banker->setDataValue('stavUzivK', 'stavUziv.nactenoEl');
            $banker->setDataValue('datVyst', \AbraFlexi\RO::dateToFlexiDate(new \DateTime($transaction['Started Date'])));
            $banker->setDataValue('cisDosle', $transaction['Completed Date']);

            $banker->setDataValue('mena', \AbraFlexi\RO::code($transaction['Currency']));
            if ($transaction['Currency'] == 'CZK') {
                $banker->setDataValue('sumOsv', $transaction['Amount']);
            } else {
                $banker->setDataValue('sumOsvMen', $transaction['Amount']);
            }

            try {
                $inserted = $banker->sync();
                $banker->addStatusMessage('payment imported: ' . strval($inserted) . ' ' . implode(',', $transaction), 'success');
            } catch (\AbraFlexi\Exception $exc) {
                echo $exc->getTraceAsString();
                exit(1);
            }
        } else {
            $banker->addStatusMessage('payment already present ' . implode(',', $transaction));
        }


        //Array
        //(
        //    [Type] => TOPUP
        //    [Product] => Current
        //    [Started Date] => 2023-02-27 13:17:32
        //    [Completed Date] => 2023-02-27 13:17:32
        //    [Description] => Payment from Gh-networks, S.r.o.
        //    [Amount] => 14857.50
        //    [Fee] => 0.00
        //    [Currency] => CZK
        //    [State] => COMPLETED
        //    [Balance] => 18811.29
        //)
    }
}
