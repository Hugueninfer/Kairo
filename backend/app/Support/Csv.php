<?php

namespace App\Support;

/**
 * Cuidados com arquivos CSV abertos no Excel ou no Google Planilhas.
 */
class Csv
{
    /**
     * Neutraliza fórmulas: um texto que começa com =, +, -, @, tab ou quebra de linha seria
     * executado pela planilha (CSV injection). Números negativos continuam números.
     */
    public static function cell(string $value): string
    {
        if (preg_match('/^-?\d+([.,]\d+)?$/', $value) === 1) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
