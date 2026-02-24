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

use Ease\Shared;

\define('APP_NAME', 'RevolutCSVtoAbraFlexi');

require_once '../vendor/autoload.php';

$options = getopt('i::e::o::', ['input::environment::output::']);

Shared::init(
    ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ACCOUNT_IBAN'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
$csvFile = \array_key_exists('i', $options) ? $options['i'] : (\array_key_exists('input', $options) ? $options['input'] : Shared::cfg('REVOLUT_CSV', 'php://stdin'));
$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : Shared::cfg('RESULT_FILE', 'php://stdout'));

$exitcode = 0;
$report = [
    'input' => $csvFile,
    'account' => Shared::cfg('ACCOUNT_IBAN'),
    'imported' => 0, // Počet úspěšně importovaných transakcí
    'skipped' => 0, // Počet přeskočených transakcí
    'errors' => [], // Pole pro ukládání chyb
    'exitcode' => 0,
];

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

$account = getBank(Shared::cfg('ACCOUNT_IBAN'));

if (Shared::cfg('APP_DEBUG', false)) {
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
    $banker->addStatusMessage(sprintf(_('Importing %d transactions from %s file'), \count($transactions), $csvFile));

    foreach ($transactions as $transaction) {
        if (($transaction['State'] === 'COMPLETED') || ($transaction['State'] === 'DOKONČENO')) {
            $type = \array_key_exists('Type', $transaction) ? $transaction['Type'] : $transaction['Typ'];
            $completed = \array_key_exists('Completed Date', $transaction) ? $transaction['Completed Date'] : $transaction['Datum dokončení'];
            $started = \array_key_exists('Started Date', $transaction) ? $transaction['Started Date'] : $transaction['Datum zahájení'];
            $amount = \array_key_exists('Amount', $transaction) ? $transaction['Amount'] : $transaction['Částka'];
            $currency = \array_key_exists('Currency', $transaction) ? $transaction['Currency'] : $transaction['Měna'];
            $desc = \array_key_exists('Description', $transaction) ? $transaction['Description'] : $transaction['Popis'];

            $transNumber = $completed;

            // Normalize CSV amount to float (handle Czech formatting like "1 234,56")
            $normalizedAmount = (float) str_replace([',', ' '], ['.', ''], $amount);

            // Fetch any records with same incoming number and then compare amount/currency/description
            $candidates = $banker->getColumnsFromAbraFlexi(['id', 'kod', 'sumOsv', 'sumOsvMen', 'mena', 'popis', 'cisDosle'], ['cisDosle' => $transNumber]);

            $isDuplicate = false;

            if (!empty($candidates)) {
                foreach ($candidates as $cand) {
                    // Determine candidate amount (local vs foreign)
                    $candAmount = null;

                    if (\array_key_exists('sumOsv', $cand) && $cand['sumOsv'] !== null && $cand['sumOsv'] !== '') {
                        $candAmount = (float) str_replace([',', ' '], ['.', ''], $cand['sumOsv']);
                    } elseif (\array_key_exists('sumOsvMen', $cand) && $cand['sumOsvMen'] !== null && $cand['sumOsvMen'] !== '') {
                        $candAmount = (float) str_replace([',', ' '], ['.', ''], $cand['sumOsvMen']);
                    }

                    $candCurrency = \array_key_exists('mena', $cand) ? $cand['mena'] : null;
                    $candDesc = \array_key_exists('popis', $cand) ? $cand['popis'] : '';

                    // Compare amounts with small tolerance and currency + description substring
                    if ($candAmount !== null && abs($candAmount - $normalizedAmount) < 0.01) {
                        if ($candCurrency === $currency) {
                            // If description matches at least partially, treat as duplicate
                            $descMatch = false;

                            if ($desc && $candDesc) {
                                $shortDesc = mb_substr(trim($desc), 0, 50);

                                if ($shortDesc !== '' && mb_stripos($candDesc, $shortDesc) !== false) {
                                    $descMatch = true;
                                }
                            }

                            if ($descMatch || $candCurrency === $currency) {
                                $banker->setData($cand);
                                $banker->addStatusMessage(sprintf(_('payment %s already present: %s'), $banker->getRecordIdent(), implode(',', $transaction)));
                                ++$report['skipped'];
                                $isDuplicate = true;

                                break;
                            }
                        }
                    }
                }
            }

            if ($isDuplicate === false) {
                $banker->dataReset();
                $numRow = new \AbraFlexi\RO(\AbraFlexi\Code::ensure(Shared::cfg('DOCUMENT_NUMROW', 'REVO+')), ['evidence' => 'rada-banka']);
                $banker->setDataValue('bezPolozek', true);
                $banker->setDataValue('typDokl', \AbraFlexi\Code::ensure(Shared::cfg('DOCUMENT_TYPE', 'STAND')));
                $banker->setDataValue('rada', \AbraFlexi\Code::ensure((string) $numRow));
                $banker->setDataValue('banka', $account);

                // Nastavení typu pohybu podle typu transakce
                switch ($type) {
                    case 'TOPUP':
                    case 'REVERTED':
                    case 'Dobíjení':
                        $banker->setDataValue('typPohybuK', 'typPohybu.prijem'); // Příjem

                        break;
                    case 'FEE':
                    case 'CARD_PAYMENT':
                    case 'Platba kartou':
                    case 'Poplatek':
                        $banker->setDataValue('typPohybuK', 'typPohybu.vydej'); // Výdej

                        break;
                    case 'TRANSFER':
                    case 'Převod':
                        if ($normalizedAmount < 0) {
                            $banker->setDataValue('typPohybuK', 'typPohybu.vydej'); // Výdej
                        } else {
                            $banker->setDataValue('typPohybuK', 'typPohybu.prijem'); // Příjem
                        }

                        break;
                    case 'CARD_REFUND':
                    case 'Vrácení peněz na kartu':
                        $report['skipped']++;

                        continue 2;

                        break;
                    case 'TEMP_BLOCK':
                        $report['skipped']++;

                        continue 2;

                        break;

                    default:
                        $banker->addStatusMessage(sprintf(_('Unknown transaction type %s'), $type), 'warning');
                        ++$report['skipped'];

                        continue 2;
                }

                $banker->setDataValue('popis', $desc);

                $banker->setDataValue('stavUzivK', 'stavUziv.nactenoEl');
                $banker->setDataValue('datVyst', \AbraFlexi\Date::fromDateTime(new \DateTime($started)));
                $banker->setDataValue('cisDosle', $completed);

                $banker->setDataValue('mena', \AbraFlexi\Code::ensure($currency));

                if ($currency === 'CZK') {
                    $banker->setDataValue('sumOsv', abs($normalizedAmount));
                } else {
                    $banker->setDataValue('sumOsvMen', abs($normalizedAmount));
                }

                try {
                    $inserted = $banker->sync();
                    $banker->addStatusMessage(sprintf(_('payment %s imported: %s'), $banker->getRecordIdent(), (string) $inserted.' '.implode(',', $transaction)), 'success');
                    ++$report['imported'];
                } catch (\AbraFlexi\Exception $exc) {
                    $report['errors'][] = [
                        'transaction' => $transaction,
                        'error' => $exc->getMessage(),
                    ];
                    $report['exitcode'] = 1;
                }
            } else {
                $banker->setData($candidates[0]);
                $banker->addStatusMessage(sprintf(_('payment %s already present: %s'), $banker->getRecordIdent(), implode(',', $transaction)));
                ++$report['skipped'];
            }
        } else {
            ++$report['skipped'];
        }
    }
} else {
    $account->addStatusMessage(_('CSV File was not provided'), 'error');
}

$banker->addStatusMessage('import done', 'debug');

$report['exitcode'] = $exitcode;
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$banker->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
