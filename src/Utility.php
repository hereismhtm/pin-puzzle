<?php

declare(strict_types=1);

namespace PinPuzzle;

class Utility
{
    public static function createSecret(int $space = 27/*162 bits*/): string
    {
        $validChars = self::_getBase64LookupTable();

        $secret = '';
        $rnd = random_bytes($space);
        for ($i = 0; $i < $space; ++$i) {
            $secret .= $validChars[ord($rnd[$i]) & 63];
        }

        return $secret;
    }

    public static function createSecretNumber(int $space): string
    {
        $rnd = random_int(0, pow(10, $space) - 1);

        return str_pad((string) $rnd, $space, '0', STR_PAD_LEFT);
    }

    private static function _getBase64LookupTable(): array
    {
        return [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
            'Y', 'Z', 'a', 'b', 'c', 'd', 'e', 'f',
            'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n',
            'o', 'p', 'q', 'r', 's', 't', 'u', 'v',
            'w', 'x', 'y', 'z', '0', '1', '2', '3',
            '4', '5', '6', '7', '8', '9', '-', '_',
        ];
    }
}
