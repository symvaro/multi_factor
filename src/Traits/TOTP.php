<?php
declare(strict_types=1);
namespace ParagonIE\MultiFactor\Traits;
use ParagonIE\ConstantTime\Binary;
use ParagonIE\ConstantTime\Hex;

/**
 * Class TOTP
 * @package ParagonIE\MultiFactor\Traits
 */
trait TOTP
{
    /**
     * Generate a TOTP secret in accordance with RFC 6238
     *
     * @ref https://tools.ietf.org/html/rfc6238
     * @param string $sharedSecret The key to use for determining the TOTP
     * @param int $unixTimestamp   The current UNIX timestamp
     * @param int $timeZero        The start time for calculating the TOTP
     * @param int $timeStep        How many seconds should each TOTP live?
     * @param int $length          How many digits should each TOTP be?
     * @param string $algo         Hash function to use
     * @return string
     * @throws \OutOfRangeException
     */
    public function getTOTPCode(
        string $sharedSecret,
        int $unixTimestamp,
        int $timeZero = 0,
        int $timeStep = 30,
        int $length = 6,
        string $algo = 'sha1'
    ): string {
        if ($length < 1 || $length > 10) {
            throw new \OutOfRangeException(
                'Length must be between 1 and 10, as a consequence of RFC 6238.'
            );
        }
        $msg = $this->getTValue($unixTimestamp, $timeZero, $timeStep, true);
        $bytes = \hash_hmac($algo, $msg, $sharedSecret, true);

        $byteLen = Binary::safeStrlen($bytes);

        // Per the RFC
        $offset = \unpack('C', $bytes[$byteLen - 1])[1];
        $offset &= 0x0f;

        $unpacked = \array_values(
            \unpack('C*', Binary::safeSubstr($bytes, $offset, 4))
        );

        $intValue = (
              (($unpacked[0] & 0x7f) << 24)
            | (($unpacked[1] & 0xff) << 16)
            | (($unpacked[2] & 0xff) <<  8)
            | (($unpacked[3] & 0xff)      )
        );

        $intValue %= 10 ** $length;

        return \str_pad(
            (string) $intValue,
            $length,
            '0',
            \STR_PAD_LEFT
        );
    }

    /**
     * Get the binary T value
     *
     * @param int $unixTimestamp
     * @param int $timeZero
     * @param int $timeStep
     * @param bool $rawOutput
     * @return string
     */
    public function getTValue(
        int $unixTimestamp,
        int $timeZero = 0,
        int $timeStep = 30,
        bool $rawOutput = false
    ): string {
        $value = \intdiv(
            $unixTimestamp - $timeZero,
            $timeStep !== 0
                ? $timeStep
                : 1
        );
        $hex = \str_pad(
            \dechex($value),
            16,
            '0',
            STR_PAD_LEFT
        );
        if ($rawOutput) {
            return Hex::decode($hex);
        }
        return $hex;
    }
}
