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

namespace Vitexsoftware\AbraflexiRevolut\Tests;

use PHPUnit\Framework\TestCase;
use Vitexsoftware\AbraflexiRevolut\RevolutCsvHelper;

class RevolutCsvHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // normalizeAmount
    // -------------------------------------------------------------------------

    public function testNormalizeAmountSimplePositive(): void
    {
        $this->assertEqualsWithDelta(163.68, RevolutCsvHelper::normalizeAmount('163.68'), 0.001);
    }

    public function testNormalizeAmountSimpleNegative(): void
    {
        $this->assertEqualsWithDelta(-147.87, RevolutCsvHelper::normalizeAmount('-147.87'), 0.001);
    }

    public function testNormalizeAmountCzechCommaDecimal(): void
    {
        $this->assertEqualsWithDelta(1234.56, RevolutCsvHelper::normalizeAmount('1 234,56'), 0.001);
    }

    public function testNormalizeAmountNegativeCzechFormat(): void
    {
        $this->assertEqualsWithDelta(-1234.56, RevolutCsvHelper::normalizeAmount('-1 234,56'), 0.001);
    }

    public function testNormalizeAmountZero(): void
    {
        $this->assertEqualsWithDelta(0.0, RevolutCsvHelper::normalizeAmount('0.00'), 0.001);
    }

    // -------------------------------------------------------------------------
    // resolveMovementType — English types
    // -------------------------------------------------------------------------

    public function testResolveMovementTypeTopup(): void
    {
        $this->assertSame('typPohybu.prijem', RevolutCsvHelper::resolveMovementType('TOPUP', 100.0));
    }

    public function testResolveMovementTypeCardPayment(): void
    {
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('CARD_PAYMENT', -50.0));
    }

    public function testResolveMovementTypeFee(): void
    {
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('FEE', -5.0));
    }

    public function testResolveMovementTypeTransferNegative(): void
    {
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('TRANSFER', -200.0));
    }

    public function testResolveMovementTypeTransferPositive(): void
    {
        $this->assertSame('typPohybu.prijem', RevolutCsvHelper::resolveMovementType('TRANSFER', 300.0));
    }

    public function testResolveMovementTypeCardRefund(): void
    {
        $this->assertSame('skip', RevolutCsvHelper::resolveMovementType('CARD_REFUND', 10.0));
    }

    public function testResolveMovementTypeTempBlock(): void
    {
        $this->assertSame('skip', RevolutCsvHelper::resolveMovementType('TEMP_BLOCK', -30.0));
    }

    // -------------------------------------------------------------------------
    // resolveMovementType — Czech types
    // -------------------------------------------------------------------------

    public function testResolveMovementTypeDobijeni(): void
    {
        $this->assertSame('typPohybu.prijem', RevolutCsvHelper::resolveMovementType('Dobíjení', 163.68));
    }

    public function testResolveMovementTypePlatbaKartou(): void
    {
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('Platba kartou', -147.87));
    }

    public function testResolveMovementTypePoplatek(): void
    {
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('Poplatek', -165.99));
    }

    public function testResolveMovementTypePrevodNegative(): void
    {
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('Převod', -30.0));
    }

    public function testResolveMovementTypePrevodPositive(): void
    {
        $this->assertSame('typPohybu.prijem', RevolutCsvHelper::resolveMovementType('Převod', 400.0));
    }

    public function testResolveMovementTypeVraceniPenez(): void
    {
        $this->assertSame('skip', RevolutCsvHelper::resolveMovementType('Vrácení peněz na kartu', 0.74));
    }

    // -------------------------------------------------------------------------
    // resolveMovementType — unknown
    // -------------------------------------------------------------------------

    public function testResolveMovementTypeUnknown(): void
    {
        $this->assertNull(RevolutCsvHelper::resolveMovementType('SOME_NEW_TYPE', 100.0));
    }

    // -------------------------------------------------------------------------
    // getColumn
    // -------------------------------------------------------------------------

    public function testGetColumnEnglish(): void
    {
        $tx = ['Type' => 'TOPUP', 'Amount' => '100.00'];
        $this->assertSame('TOPUP', RevolutCsvHelper::getColumn($tx, 'Type'));
        $this->assertSame('100.00', RevolutCsvHelper::getColumn($tx, 'Amount'));
    }

    public function testGetColumnCzech(): void
    {
        $tx = ['Typ' => 'Dobíjení', 'Částka' => '163.68'];
        $this->assertSame('Dobíjení', RevolutCsvHelper::getColumn($tx, 'Type'));
        $this->assertSame('163.68', RevolutCsvHelper::getColumn($tx, 'Amount'));
    }

    public function testGetColumnMissing(): void
    {
        $tx = ['Foo' => 'bar'];
        $this->assertNull(RevolutCsvHelper::getColumn($tx, 'Type'));
    }

    // -------------------------------------------------------------------------
    // isCompleted
    // -------------------------------------------------------------------------

    public function testIsCompletedEnglish(): void
    {
        $this->assertTrue(RevolutCsvHelper::isCompleted('COMPLETED'));
    }

    public function testIsCompletedCzech(): void
    {
        $this->assertTrue(RevolutCsvHelper::isCompleted('DOKONČENO'));
    }

    public function testIsCompletedReverted(): void
    {
        $this->assertFalse(RevolutCsvHelper::isCompleted('REVERTED'));
    }

    public function testIsCompletedPending(): void
    {
        $this->assertFalse(RevolutCsvHelper::isCompleted('PENDING'));
    }

    // -------------------------------------------------------------------------
    // parseCsv
    // -------------------------------------------------------------------------

    public function testParseCsvEnglish(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'revolut_test_');
        file_put_contents($tmpFile, implode("\n", [
            'Type,Product,Started Date,Completed Date,Description,Amount,Fee,Currency,State,Balance',
            'TOPUP,Current,2025-09-04 08:04:10,2025-09-04 08:04:11,Payment from John,163.68,0.00,EUR,COMPLETED,170.09',
            'CARD_PAYMENT,Current,2025-09-04 11:57:48,2025-09-04 18:02:41,Warp,-147.87,0.00,EUR,COMPLETED,22.22',
        ]));

        $transactions = RevolutCsvHelper::parseCsv($tmpFile);
        unlink($tmpFile);

        $this->assertCount(2, $transactions);
        $this->assertSame('TOPUP', $transactions[0]['Type']);
        $this->assertSame('163.68', $transactions[0]['Amount']);
        $this->assertSame('CARD_PAYMENT', $transactions[1]['Type']);
        $this->assertSame('-147.87', $transactions[1]['Amount']);
    }

    public function testParseCsvCzech(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'revolut_test_');
        file_put_contents($tmpFile, implode("\n", [
            'Typ,Produkt,Datum zahájení,Datum dokončení,Popis,Částka,Poplatek,Měna,State,Zůstatek',
            'Dobíjení,Aktuální,2025-09-04 08:04:10,2025-09-04 08:04:11,Platba od HANA DVORAKOVA,163.68,0.00,EUR,DOKONČENO,170.09',
            'Platba kartou,Aktuální,2025-09-04 11:57:48,2025-09-04 18:02:41,Warp,-147.87,0.00,EUR,DOKONČENO,22.22',
        ]));

        $transactions = RevolutCsvHelper::parseCsv($tmpFile);
        unlink($tmpFile);

        $this->assertCount(2, $transactions);
        $this->assertSame('Dobíjení', $transactions[0]['Typ']);
        $this->assertSame('163.68', $transactions[0]['Částka']);
        $this->assertSame('Platba kartou', $transactions[1]['Typ']);
        $this->assertSame('-147.87', $transactions[1]['Částka']);
    }

    // -------------------------------------------------------------------------
    // Integration: abs(amount) should be used for AbraFlexi
    // -------------------------------------------------------------------------

    /**
     * Verify that abs(normalizedAmount) produces correct positive values
     * for amounts that are negative in the CSV (expenses).
     */
    public function testAbsAmountForExpenses(): void
    {
        $csvAmount = '-147.87';
        $normalized = RevolutCsvHelper::normalizeAmount($csvAmount);
        $absAmount = abs($normalized);

        $this->assertEqualsWithDelta(147.87, $absAmount, 0.001);
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('CARD_PAYMENT', $normalized));
    }

    /**
     * Verify that abs(normalizedAmount) keeps positive values for income.
     */
    public function testAbsAmountForIncome(): void
    {
        $csvAmount = '163.68';
        $normalized = RevolutCsvHelper::normalizeAmount($csvAmount);
        $absAmount = abs($normalized);

        $this->assertEqualsWithDelta(163.68, $absAmount, 0.001);
        $this->assertSame('typPohybu.prijem', RevolutCsvHelper::resolveMovementType('TOPUP', $normalized));
    }

    /**
     * Czech fee type: negative amount should produce positive abs for AbraFlexi.
     */
    public function testAbsAmountForCzechFee(): void
    {
        $csvAmount = '-165.99';
        $normalized = RevolutCsvHelper::normalizeAmount($csvAmount);
        $absAmount = abs($normalized);

        $this->assertEqualsWithDelta(165.99, $absAmount, 0.001);
        $this->assertSame('typPohybu.vydej', RevolutCsvHelper::resolveMovementType('Poplatek', $normalized));
    }
}
