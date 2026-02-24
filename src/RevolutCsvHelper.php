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

namespace Vitexsoftware\AbraflexiRevolut;

class RevolutCsvHelper
{
    /**
     * Map of English column names to their Czech equivalents.
     */
    public const COLUMN_MAP = [
        'Type' => 'Typ',
        'Started Date' => 'Datum zahájení',
        'Completed Date' => 'Datum dokončení',
        'Amount' => 'Částka',
        'Currency' => 'Měna',
        'Description' => 'Popis',
        'State' => 'State', // State column keeps the same name in both formats
    ];

    /**
     * Normalize a CSV amount string to float.
     * Handles Czech formatting like "1 234,56".
     */
    public static function normalizeAmount(string $amount): float
    {
        return (float) str_replace([',', ' '], ['.', ''], $amount);
    }

    /**
     * Determine the AbraFlexi movement type (příjem/výdej) based on transaction type and amount.
     *
     * @param string $type   Transaction type from CSV (English or Czech)
     * @param float  $amount Normalized amount
     *
     * @return string|null 'typPohybu.prijem', 'typPohybu.vydej', 'skip', or null for unknown
     */
    public static function resolveMovementType(string $type, float $amount): ?string
    {
        switch ($type) {
            case 'TOPUP':
            case 'REVERTED':
            case 'Dobíjení':
                return 'typPohybu.prijem';
            case 'FEE':
            case 'CARD_PAYMENT':
            case 'Platba kartou':
            case 'Poplatek':
                return 'typPohybu.vydej';
            case 'TRANSFER':
            case 'Převod':
                return $amount < 0 ? 'typPohybu.vydej' : 'typPohybu.prijem';
            case 'CARD_REFUND':
            case 'Vrácení peněz na kartu':
            case 'TEMP_BLOCK':
                return 'skip';
            default:
                return null;
        }
    }

    /**
     * Get a column value from a transaction row, trying English key first, then Czech.
     *
     * @param array  $transaction The transaction row
     * @param string $englishKey  The English column name
     *
     * @return string|null The value or null if not found
     */
    public static function getColumn(array $transaction, string $englishKey): ?string
    {
        if (\array_key_exists($englishKey, $transaction)) {
            return $transaction[$englishKey];
        }

        $czechKey = self::COLUMN_MAP[$englishKey] ?? null;

        if ($czechKey !== null && \array_key_exists($czechKey, $transaction)) {
            return $transaction[$czechKey];
        }

        return null;
    }

    /**
     * Check if a transaction state indicates completion.
     */
    public static function isCompleted(string $state): bool
    {
        return $state === 'COMPLETED' || $state === 'DOKONČENO';
    }

    /**
     * Parse a CSV file into an array of transactions.
     *
     * @param string $csvFile Path to the CSV file
     *
     * @return array Array of associative arrays (column => value)
     */
    public static function parseCsv(string $csvFile): array
    {
        $row = 1;
        $transactions = [];
        $columns = [];

        if (($handle = fopen($csvFile, 'rb')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) {
                if ($row++ === 1) {
                    $columns = $data;

                    continue;
                }

                $transactions[] = array_combine($columns, $data);
            }

            fclose($handle);
        }

        return $transactions;
    }
}
