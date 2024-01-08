<?php

/**
 * AbraFlexi Revolut - Inital setup.
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.com>
 * @copyright  (C) 2024 Spoje.Net
 */

namespace AbraFlexi\RaiffeisenBank;

require_once('../vendor/autoload.php');
/**
 * Get List of bank accounts and import it into AbraFlexi
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ACCOUNT_IBAN'], '../.env');

$banker = new \AbraFlexi\RW(null, ['evidence' => 'bankovni-ucet']);
if (boolval(\Ease\Functions::cfg('APP_DEBUG'))) {
    $banker->logBanner();
}
$currentAccounts = $banker->getColumnsFromAbraFlexi(['id', 'kod', 'nazev', 'iban', 'bic', 'nazBanky', 'poznam'], ['limit' => 0], 'iban');

if (array_key_exists(\Ease\Shared::cfg('ACCOUNT_IBAN'), $currentAccounts)) {
    $banker->addStatusMessage(sprintf('Account %s already exists in flexibee as %s', \Ease\Shared::cfg('ACCOUNT_IBAN'), $currentAccounts[\Ease\Shared::cfg('ACCOUNT_IBAN')]['kod']));
} else {
    $banker->dataReset();
    $banker->setDataValue('kod', 'REVOLUT');
    $banker->setDataValue('nazev', 'Revolut');
//    $banker->setDataValue('buc', $account->accountNumber);
    $banker->setDataValue('nazBanky', 'Revolut Bank UAB');
    $banker->setDataValue('popis', 'Revolut, Ltd.');
    $banker->setDataValue('iban', \Ease\Shared::cfg('ACCOUNT_IBAN'));
//    $banker->setDataValue('smerKod', \AbraFlexi\RO::code($account->bankCode));
    $banker->setDataValue('bic', 'REVOLT21');
    $saved = $banker->sync();
    $banker->addStatusMessage(
        sprintf('Account %s registered in flexibee as %s', 'Revolut', $banker->getRecordCode()),
        ($saved ? 'success' : 'error')
    );
}
