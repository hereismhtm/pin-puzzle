<?php

declare(strict_types=1);
/**
 * PinPuzzle Library.
 * 
 * Personal Identification Number (PIN) transformer into three separate secret parts,
 * assemble them back together to get your PIN.
 * 
 * @version: v1.0.0
 * @author Mustafa Elhadi
 * @package PinPuzzle
 * @copyright Copyright 2023 PinPuzzle Library, Mustafa Elhadi.
 * @license https://opensource.org/licenses/MIT
 */

namespace PinPuzzle;

class PinPuzzle
{
    public const MIN_KEY_LEN = 3;
    public const MAX_KEY_LEN = 16;

    private const MAX_PIN_LEN = 8;
    private const ESM_HEAD_SIZE = 1;
    private const CHK_HASH_SIZE = 8;

    private string $soil;
    private int $water_well_capacity;
    private bool $water_well_salinity;

    public function __construct(
        string $uuid,
        int $key_length = PinPuzzle::MAX_KEY_LEN,
        bool $numeric_key = false
    ) {
        if ($key_length < self::MIN_KEY_LEN || $key_length > self::MAX_KEY_LEN) {
            throw new \ValueError("<p>PinRetriever: argument
            \$key_length ($key_length), set value between "
                . self::MIN_KEY_LEN . " & " . self::MAX_KEY_LEN . ".</p>");
        }

        $this->soil = $uuid;
        $this->water_well_capacity = $key_length;
        $this->water_well_salinity = $numeric_key;
    }

    public function assembly(PinInstruction $inst): string|false
    {
        $robj = new \ReflectionObject($inst);

        $plant = $this->grow(
            $robj->getProperty('seed')->getValue($inst),
            $robj->getProperty('water')->getValue($inst)
        );

        $esm_head_val = (int) substr($plant, -self::ESM_HEAD_SIZE);
        if ($esm_head_val == 0 || $esm_head_val > self::MAX_PIN_LEN) {
            return false;
        }
        $esm_size = self::esmSize($esm_head_val);

        $metadata = substr(
            $plant,
            - (self::CHK_HASH_SIZE + $esm_size)
        );

        $selector = $robj->getProperty('selector')->getValue($inst);

        if (
            substr($metadata, 0, self::CHK_HASH_SIZE)
            !== substr($selector, 0, self::CHK_HASH_SIZE)
        ) {
            return false;
        }

        $selector = substr($selector, self::CHK_HASH_SIZE);
        $esm = substr($plant, -$esm_size);

        $pos_string = '';
        $slct_ptr = 0;
        for ($i = 0; $i < $esm_size - self::ESM_HEAD_SIZE; $i++) {
            $n = (int) $esm[$i];

            $read = [
                ($n % 2 != 0) => 1,
                ($n % 2 == 0 && $n != 0) => 2,
                ($n == 0) => 3
            ][true];

            $j = 0;
            while ($j++ < $read)
                $pos_string .= $selector[$slct_ptr++];
            $pos_string .= '|';
        }

        $positions = explode('|', rtrim($pos_string, '|'));

        $pin = '';
        foreach ($positions as $value) {
            $pin .= $plant[(int) $value];
        }

        return $pin;
    }

    public function resolve(string $pin, ?string $key = null): PinInstruction
    {
        $pin = trim($pin);
        if (!is_numeric($pin)) {
            throw new \ValueError("<p>PinRetriever: argument
            \$pin (***), not a numeric string.</p>");
        }
        if (strlen($pin) > self::MAX_PIN_LEN) {
            throw new \ValueError("<p>PinRetriever: argument
            \$pin (***), set length not more than " . self::MAX_PIN_LEN . ".</p>");
        }

        $fruit = [];
        for ($i = 0; $i < strlen($pin); $i++) {
            $fruit[] = $pin[$i];
        }

        return $this->resolving($fruit, water: $key);
    }

    private function resolving(array &$fruit, ?string $water): PinInstruction
    {
        $inst = $this->tryFormingPuzzle1000($fruit, $water);
        if ($inst !== false)
            return $inst;
        else
            return $this->resolving($fruit, $water);
    }

    private function tryFormingPuzzle1000(
        array &$fruit,
        ?string $w
    ): PinInstruction|false {
        $inst = false;

        $pin = join('', $fruit);
        $pin_length = count($fruit);

        $water = $w ?? $this->_getWater($pin);

        $i = 0;
        do {
            $seed = (string) random_int(PHP_INT_MIN, PHP_INT_MAX);
            if ($seed[0] == '-')
                $seed[0] = '0';

            $plant = $this->grow($seed, $water);

            if ((int) substr($plant, -self::ESM_HEAD_SIZE) !=  $pin_length)
                continue;

            $selector = $this->harvest($plant, $fruit);

            if (!is_null($selector)) {
                $inst = (new PinInstruction())
                    ->processor($selector)
                    ->input($seed)
                    ->key($water);
                break;
            }
        } while (++$i < 1000);

        return $inst;
    }

    private function _getWater(string $pin): string
    {
        if ($this->water_well_salinity) {

            do {
                $w = Utility::createSecretNumber($this->water_well_capacity);
            } while ($w == $pin);

            return $w;
        } else {
            return Utility::createSecret($this->water_well_capacity);
        }
    }

    private function grow(string $seed, string $water): string
    {
        $h = hash('sha3-512', $this->soil . $seed . $water);

        return preg_replace('[\D]', '', $h);
    }

    private function harvest(string &$plant, array &$fruit): ?string
    {
        $is_ok = true;

        $positions = [];
        foreach ($fruit as $key => $value) {
            if (!$is_ok)
                break;

            $positions[$key /*Not-Necessary*/] = strpos($plant, $value);
            if ($positions[$key] === false) {
                $is_ok = false;
                break;
            }

            for ($i = $key - 1; $i > -1; $i--) {
                if ($positions[$i] == $positions[$key]) {

                    $positions[$key] = strpos($plant, $value, max($positions) + 1);
                    if ($positions[$key] === false) {
                        $is_ok = false;
                        break;
                    }
                }
            }
        }

        $esm_size = self::esmSize(count($fruit));

        if ($is_ok)
            $is_ok = self::isValidESM(
                $positions,
                esm: substr($plant, -$esm_size)
            );

        $selector = $is_ok ?
            self::checkHash($plant, $esm_size) . join('', $positions)
            : null;

        return $selector;
    }

    /** 
     * ESM: Embeded Selector Mask
     */
    private static function isValidESM(array $array, string $esm): bool
    {
        foreach ($array as $key => $index) {
            $array[$key] = strlen((string) $index);
        }

        foreach ($array as $key => $length) {
            $n = (int) $esm[$key];

            if (
                !match ($length) {
                    1 => ($n % 2 != 0),
                    2 => ($n % 2 == 0 && $n != 0),
                    3 => ($n == 0)
                }
            )
                return false;
        }

        return true;
    }

    private static function esmSize(int $pin_length): int
    {
        return $pin_length + self::ESM_HEAD_SIZE;
    }

    private static function checkHash(string &$plant, int $esm_size): string
    {
        return substr($plant, - (self::CHK_HASH_SIZE + $esm_size), self::CHK_HASH_SIZE);
    }
}
