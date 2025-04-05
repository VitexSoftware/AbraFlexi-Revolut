<?php

declare(strict_types=1);

/**
 * This file is part of the Revolut4AbraFlexi package
 *
 * https://github.com/VitexSoftware/AbraFlexi-Revolut
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use \Ease\Shared;

\define('APP_NAME', 'RevolutCSVtoAbraFlexi');

require_once '../vendor/autoload.php';

$options = getopt('i::e::o::', ['input::environment::output::']);

\Ease\Shared::init(
    ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ACCOUNT_IBAN'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);

$csvFile = \array_key_exists('i', $options) ? $options['i'] : (\array_key_exists('input', $options) ? $options['input'] : Shared::cfg('REVOLUT_CSV', 'php://stdin'));
$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : Shared::cfg('RESULT_FILE', 'php://stdout'));

/**
 * Gives you AbraFlexi Bank.
 *
 * @param string $accountIban
 *
 * @throws Exception
 *
 * @return \AbraFlexi\RO
 */
function getBank($accountIban)
{
    $banker = new \AbraFlexi\RO(null, ['evidence' => 'bankovni-ucet']);
    $candidat = $banker->getColumnsFromAbraFlexi('id', ['iban' => $accountIban]);

    if (empty($candidat) || !\array_key_exists('id', $candidat[0])) {
        throw new Exception('Bank account '.$accountIban.' not found in AbraFlexi');
    }

    $banker->loadFromAbraFlexi($candidat[0]['id']);

    return $banker;
}

$account = getBank(\Ease\Shared::cfg('ACCOUNT_IBAN'));

if (\Ease\Shared::cfg('APP_DEBUG', false)) {
    $account->logBanner();
}

if ($csvFile) {
    $row = 1;
    $transactions = [];
    $columns = [];

    if (($handle = fopen($csvFile, 'rb')) !== false) {
        while (($data = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) { // Added the escape parameter '\\'
            if ($row++ === 1) {
                $columns = $data;
                $num = \count($data);

                continue;
            }

            $transactions[] = array_combine($columns, $data);
        }

        fclose($handle);
    }

    $banker = new \AbraFlexi\Banka();

    foreach ($transactions as $transaction) {
        if (($transaction['Type'] === 'TOPUP') && ($transaction['State'] === 'COMPLETED')) {
            $candidates = $banker->getColumnsFromAbraFlexi(['id', 'kod'], ['cisDosle' => $transaction['Completed Date']]);

            if (empty($candidates)) {
                $banker->dataReset();
                $numRow = new \AbraFlexi\RO(\AbraFlexi\RO::code(\Ease\Shared::cfg('DOCUMENT_NUMROW', 'REVO+')), ['evidence' => 'rada-banka']);
                //            $id = $numRow->getDataValue('polozkyRady')[0]['preview'];
                //            $banker->setDataValue('kod', $id); // str_replace([' ', ':', '-'], '', $transaction['Completed Date'])
                $banker->setDataValue('bezPolozek', true);
                $banker->setDataValue('typDokl', \AbraFlexi\RO::code(\Ease\Shared::cfg('DOCUMENT_TYPE', 'STAND')));
                $banker->setDataValue('rada', \AbraFlexi\RO::code((string)$numRow));
                $banker->setDataValue('banka', $account);
                $banker->setDataValue('typPohybuK', 'typPohybu.prijem');
                $banker->setDataValue('popis', $transaction['Description']);
                $banker->setDataValue('stavUzivK', 'stavUziv.nactenoEl');
                $banker->setDataValue('datVyst', \AbraFlexi\RO::dateToFlexiDate(new \DateTime($transaction['Started Date'])));
                $banker->setDataValue('cisDosle', $transaction['Completed Date']);

                $banker->setDataValue('mena', \AbraFlexi\RO::code($transaction['Currency']));

                if ($transaction['Currency'] === 'CZK') {
                    $banker->setDataValue('sumOsv', $transaction['Amount']);
                } else {
                    $banker->setDataValue('sumOsvMen', $transaction['Amount']);
                }

                try {
                    $inserted = $banker->sync();
                    $banker->addStatusMessage(sprintf(_('payment %s imported: %s'), $banker->getRecordIdent(), (string) $inserted.' '.implode(',', $transaction)), 'success');
                } catch (\AbraFlexi\Exception $exc) {
                    echo $exc->getTraceAsString();

                    exit(1);
                }
            } else {
                $banker->setData($candidates[0]);
                $banker->addStatusMessage(sprintf(_('payment %s already present: %s'), $banker->getRecordIdent(), implode(',', $transaction)));
            }

            // Array
            // (
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
            // )
        }
    }
} else {
    $account->addStatusMessage(_('CSV File was not provided'), 'error');
}
