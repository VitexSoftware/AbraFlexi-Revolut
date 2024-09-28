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

namespace AbraFlexi\RaiffeisenBank;

require_once '../vendor/autoload.php';
/**
 * Get List of bank accounts and import it into AbraFlexi.
 */
\Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'ACCOUNT_IBAN'], '../.env');

$banker = new \AbraFlexi\RW(null, ['evidence' => 'bankovni-ucet']);

if ((bool) \Ease\Functions::cfg('APP_DEBUG', false)) {
    $banker->logBanner();
}

$currentAccounts = $banker->getColumnsFromAbraFlexi(['id', 'kod', 'nazev', 'iban', 'bic', 'nazBanky', 'poznam'], ['limit' => 0], 'iban');

if (\array_key_exists(\Ease\Shared::cfg('ACCOUNT_IBAN'), $currentAccounts)) {
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
        $saved ? 'success' : 'error',
    );

    // TODO: Create Bank NumRow REVO+ & REVO-
}
