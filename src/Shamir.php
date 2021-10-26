<?php

declare(strict_types=1);

namespace Unitpay\Shamir;

use DivisionByZeroError;
use InvalidArgumentException;
use RuntimeException;
use SplFixedArray;
use Throwable;

use function count;
use function pack;
use function random_int;
use function sprintf;
use function strlen;
use function unpack;

final class Shamir
{
    /**
     * @param string $secret Secret string to be split
     * @param int $parts Number of shares to be generated, must be at least 2, and less than 256
     * @param int $threshold Number of shares needed to reconstruct secret, must be at least 2, and less than 256
     *
     * @throws Throwable
     *
     * @return string[] Shares, each one byte longer than the secret with a tag used to reconstruct the secret
     */
    public static function split(string $secret, int $parts, int $threshold): array
    {
        // sanity check the input
        if ($parts < $threshold) {
            throw new InvalidArgumentException('Parts cannot be less than threshold.');
        }
        if ($parts > 255) {
            throw new InvalidArgumentException('Parts cannot exceed 255.');
        }
        if ($threshold < 2) {
            throw new InvalidArgumentException('Threshold must be at least 2.');
        }
        if ($threshold > 255) {
            throw new InvalidArgumentException('Threshold cannot exceed 255.');
        }

        $secret_len = strlen($secret);

        if ($secret_len === 0) {
            throw new InvalidArgumentException('Cannot split an empty secret.');
        }

        $secret_arr = SplFixedArray::fromArray(unpack('C*', $secret), false);

        // generate random list of x coordinates
        $xCoordinates = self::perm(255);

        // allocate the output array, initialize the final byte of the output with
        // the offset. The representation of each output is {y1, y2, .., yN, x}
        $out = new SplFixedArray($parts);
        for ($idx = 0; $idx < $parts; $idx++) {
            $out[$idx] = new SplFixedArray($secret_len + 1);
            $out[$idx][$secret_len] = $xCoordinates[$idx] + 1; // @review: is there possible overflow
        }

        // construct a random polynomial for each byte of the secret
        // because we are using a field of size 256, we can only represent a single byte as the intercept
        // of the polynomial, so we must use a new polynomial for each byte
        foreach ($secret_arr as $idx => $val) {
            try {
                $p = self::makePolynomial($val, $threshold - 1);
            } catch (Throwable $e) {
                throw new RuntimeException(sprintf('Failed to generate polynomial: %s.', $e->getMessage()));
            }

            // generate a `parts` number of (x,y) pairs
            // we cheat by encoding the x value once as the final index, so that it only needs to be stored once.
            for ($i = 0; $i < $parts; $i++) {
                $x = $xCoordinates[$i] + 1;
                $out[$i][$idx] = self::evaluatePolynomial($p, $x); // y
            }
        }

        // convert to strings
        $result = [];
        foreach ($out as $item) {
            $result[] = pack('C*', ...$item);
        }
        return $result;
    }

    /**
     * @param string[] $parts Shares strings
     *
     * @return string Reconstructed secret
     */
    public static function reconstruct(array $parts): string
    {
        $partsCount = count($parts);
        if ($partsCount < 2) {
            throw new InvalidArgumentException('Less than two parts cannot be used to reconstruct the secret.');
        }

        // verify the parts are all the same length
        $firstPartLen = strlen($parts[0]);
        if ($firstPartLen < 2) {
            throw new InvalidArgumentException('Parts must be at least two bytes.');
        }

        $parts_arr = new SplFixedArray($partsCount);

        $idx = 0;
        foreach ($parts as $_part) {
            $part = SplFixedArray::fromArray(unpack('C*', $_part), false);
            if ($part->getSize() !== $firstPartLen) {
                throw new InvalidArgumentException('All parts must be the same length.');
            }
            $parts_arr[$idx++] = $part;
        }

        // create a buffer to store the reconstructed secret
        $secret = new SplFixedArray($firstPartLen - 1);

        // buffer to store the samples
        $x_samples = new SplFixedArray($partsCount);
        $y_samples = new SplFixedArray($partsCount);

        // set the x value for each sample and ensure no x_sample values are the same,
        // otherwise div() can be unhappy
        $checkMap = [];
        foreach ($parts_arr as $i => $part) {
            $samp = $part[$firstPartLen-1];
            if (isset($checkMap[$samp])) {
                throw new RuntimeException('Duplicate part detected.');
            }
            $checkMap[$samp] = true;
            $x_samples[$i] = $samp;
        }

        // reconstruct each byte
        foreach ($secret as $idx => $_) {
            // set the y value for each sample
            foreach ($parts_arr as $i => $part) {
                $y_samples[$i] = $part[$idx];
            }

            // interpolate the polynomial and compute the value at 0
            // evaluate the 0th value to get the intercept
            $secret[$idx] = self::interpolatePolynomial($x_samples, $y_samples, 0);
        }

        return pack('C*', ...$secret);
    }

    private static function div(int $a, int $b): int
    {
        if ($b === 0) {
            // leaks some timing information, but we don't care anyways as this (should never happen)
            throw new DivisionByZeroError('Divide by zero.');
        }

        $diff = ((Tables::logTable[$a] - Tables::logTable[$b]) + 255) % 255;
        /** @psalm-suppress PossiblyInvalidArrayOffset */
        $ret = Tables::expTable[$diff];

        // ensure we return zero if $a is zero but aren't subject to timing attacks
        return self::constantTimeSelect(self::constantTimeByteEq($a, 0), 0, $ret);
    }

    /**
     * Multiplies two numbers in GF(2^8)
     */
    private static function mult(int $a, int $b): int
    {
        $sum = (Tables::logTable[$a] + Tables::logTable[$b]) % 255;
        $ret = Tables::expTable[$sum];

        // ensure we return zero if either a or b are zero but aren't subject to timing attacks
        $ret = self::constantTimeSelect(self::constantTimeByteEq($a, 0), 0, $ret);
        return self::constantTimeSelect(self::constantTimeByteEq($b, 0), 0, $ret);
    }

    /**
     * Combines two numbers in GF(2^8), this can also be used for subtraction since it is symmetric
     */
    private static function add(int $a, int $b): int
    {
        return $a ^ $b;
    }

    /**
     * Constructs a random polynomial of the given degree but with the provided intercept value
     *
     * @throws Throwable
     */
    private static function makePolynomial(int $intercept, int $degree): SplFixedArray
    {
        $size = $degree + 1;
        // create a wrapper
        $coefficients = new SplFixedArray($size);
        // ensure the intercept is set
        $coefficients[0] = $intercept;
        for ($i = 1; $i < $size; $i++) {
            $coefficients[$i] = random_int(0, 255);
        }

        return $coefficients;
    }

    private static function evaluatePolynomial(SplFixedArray $coefficients, int $x): int
    {
        // special case the origin
        if ($x === 0) {
            return $coefficients[0];
        }
        // compute the polynomial value using Horner`s method.
        $degree = $coefficients->getSize() - 1;
        $out = $coefficients[$degree];
        for ($i = $degree - 1; $i >= 0; $i--) {
            $coeff = $coefficients[$i];
            $out = self::add(self::mult($out, $x), $coeff);
        }
        return $out;
    }

    /**
     * Takes N sample points and returns the value at a given $x using a lagrange interpolation
     */
    private static function interpolatePolynomial(SplFixedArray $x_samples, SplFixedArray $y_samples, int $x): int
    {
        $limit = $x_samples->getSize();
        $result = 0;
        for ($i = 0; $i < $limit; $i++) {
            $basis = 1;
            for ($j = 0; $j < $limit; $j++) {
                if ($i === $j) {
                    continue;
                }
                $num = self::add($x, $x_samples[$j]);
                $denom = self::add($x_samples[$i], $x_samples[$j]);
                $term = self::div($num, $denom);
                $basis = self::mult($basis, $term);
            }
            $group = self::mult($y_samples[$i], $basis);
            $result = self::add($result, $group);
        }
        return $result;
    }

    /**
     * Returns, as a slice of $n ints, a pseudo-random permutation of the integers  in the half-open interval [0,n)
     *
     * @throws Throwable
     */
    private static function perm(int $n): SplFixedArray
    {
        $m = new SplFixedArray($n);
        for ($i = 0; $i < $n; $i++) {
            $j = random_int(0, $i);
            $m[$i] = $m[$j];
            $m[$j] = $i;
        }
        return $m;
    }

    /**
     * Returns $x if $v === 1 and $y if $v === 0, its behavior is undefined if $v takes any other value
     */
    private static function constantTimeSelect(int $v, int $x, int $y): int
    {
        if ($v !== 0 && $v !== 1) {
            throw new RuntimeException('Undefined behavior.');
        }
        return ~($v-1) & $x | ($v-1) & $y;
    }

    /**
     * Returns 1 if $x === $y and 0 otherwise
     */
    private static function constantTimeByteEq(int $x, int $y): int
    {
        if ((((~0xFF) & $x) | ((~0xFF) & $y)) !== 0) { // check is both uint8
            throw new InvalidArgumentException('Not uint8 values passed.');
        }
        return ($x ^ $y) === 0 ? 1 : 0;
    }
}
