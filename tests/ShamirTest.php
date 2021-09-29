<?php

declare(strict_types=1);

namespace Unitpay\Shamir\Tests;

use Unitpay\Shamir\Shamir;

use SplFixedArray;
use InvalidArgumentException;
use RuntimeException;
use DivisionByZeroError;
use function strlen;

final class ShamirTest extends TestCase
{
    public function testConstantTimeSelectUndefinedBehavior(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Undefined behavior.');

        $this->invokeStaticMethod(Shamir::class, 'constantTimeSelect', [2, 1, 2]);
    }

    public function testConstantTimeSelect(): void
    {
        $this->assertSame(2, $this->invokeStaticMethod(Shamir::class, 'constantTimeSelect', [0, 1, 2]));
        $this->assertSame(1, $this->invokeStaticMethod(Shamir::class, 'constantTimeSelect', [1, 1, 2]));
    }

    public function testConstantTimeByteEqInvalidArgException1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not uint8 values passed.');

        $this->invokeStaticMethod(Shamir::class, 'constantTimeByteEq', [256, 0]);
    }

    public function testConstantTimeByteEqInvalidArgException2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not uint8 values passed.');

        $this->invokeStaticMethod(Shamir::class, 'constantTimeByteEq', [0, 256]);
    }

    public function testConstantTimeByteEq(): void
    {
        $this->assertSame(1, $this->invokeStaticMethod(Shamir::class, 'constantTimeByteEq', [4, 4]));
        $this->assertSame(1, $this->invokeStaticMethod(Shamir::class, 'constantTimeByteEq', [255, 255]));
        $this->assertSame(0, $this->invokeStaticMethod(Shamir::class, 'constantTimeByteEq', [1, 2]));
        $this->assertSame(0, $this->invokeStaticMethod(Shamir::class, 'constantTimeByteEq', [255, 0]));
    }

    public function testAdd(): void
    {
        $this->assertSame(0, $this->invokeStaticMethod(Shamir::class, 'add', [16, 16]));
        $this->assertSame(7, $this->invokeStaticMethod(Shamir::class, 'add', [3, 4]));
    }

    public function testMult(): void
    {
        $this->assertSame(9, $this->invokeStaticMethod(Shamir::class, 'mult', [3, 7]));
        $this->assertSame(0, $this->invokeStaticMethod(Shamir::class, 'mult', [3, 0]));
        $this->assertSame(0, $this->invokeStaticMethod(Shamir::class, 'mult', [0, 3]));
    }

    public function testDiv(): void
    {
        $this->assertSame(0, $this->invokeStaticMethod(Shamir::class, 'div', [0, 7]));
        $this->assertSame(1, $this->invokeStaticMethod(Shamir::class, 'div', [3, 3]));
        $this->assertSame(2, $this->invokeStaticMethod(Shamir::class, 'div', [6, 3]));
    }

    public function testDivEx1(): void
    {
        $this->expectException(DivisionByZeroError::class);
        $this->expectExceptionMessage('Divide by zero.');

        $this->invokeStaticMethod(Shamir::class, 'div', [5, 0]);
    }

    public function testPolynomialRand(): void
    {
        $coefs = $this->invokeStaticMethod(Shamir::class, 'makePolynomial', [42, 2]);
        $this->assertSame(42, $coefs[0]);
        $this->assertCount(3, $coefs);
    }

    public function testPolynomialEvaluate(): void
    {
        $coefs = $this->invokeStaticMethod(Shamir::class, 'makePolynomial', [42, 1]);
        $this->assertSame(42, $this->invokeStaticMethod(Shamir::class, 'evaluatePolynomial', [$coefs, 0]));

        $out = $this->invokeStaticMethod(Shamir::class, 'evaluatePolynomial', [$coefs, 1]);
        $exp = $this->invokeStaticMethod(Shamir::class, 'add', [
            42,
            $this->invokeStaticMethod(Shamir::class, 'mult', [1, $coefs[1]]),
        ]);
        $this->assertSame($out, $exp);
    }

    public function testInterpolateRand(): void
    {
        $x_vals = SplFixedArray::fromArray([1, 2, 3]);

        for ($i = 0; $i < 256; $i++) {
            $coefs = $this->invokeStaticMethod(Shamir::class, 'makePolynomial', [$i, 2]);
            $y_vals = SplFixedArray::fromArray([
                $this->invokeStaticMethod(Shamir::class, 'evaluatePolynomial', [$coefs, 1]),
                $this->invokeStaticMethod(Shamir::class, 'evaluatePolynomial', [$coefs, 2]),
                $this->invokeStaticMethod(Shamir::class, 'evaluatePolynomial', [$coefs, 3]),
            ]);

            $this->assertSame($i, $this->invokeStaticMethod(Shamir::class, 'interpolatePolynomial', [$x_vals, $y_vals, 0]));
        }
    }

    public function testSplitEx1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold must be at least 2.');
        Shamir::split('secret', 0, 0);
    }

    public function testSplitEx2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parts cannot be less than threshold.');
        Shamir::split('secret', 2, 3);
    }

    public function testSplitEx3(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parts cannot exceed 255.');
        Shamir::split('secret', 1000, 3);
    }

    public function testSplitEx4(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Threshold must be at least 2.');
        Shamir::split('secret', 10, 1);
    }

    public function testSplitEx5(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot split an empty secret.');
        Shamir::split('', 3, 2);
    }

    public function testSplit(): void
    {
        $secret = 'test';
        $parts = Shamir::split($secret, 5, 2);
        $this->assertCount(5, $parts);

        $len = strlen($secret) + 1;
        foreach ($parts as $share) {
            $this->assertSame($len, strlen($share));
        }
    }

    public function testReconstructEx1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Less than two parts cannot be used to reconstruct the secret.');
        Shamir::reconstruct([]);
    }

    public function testReconstructEx2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All parts must be the same length.');
        Shamir::reconstruct(['foo', 'ba']);
    }

    public function testReconstructEx3(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parts must be at least two bytes.');
        Shamir::reconstruct(['a', 'b']);
    }

    public function testReconstructEx4(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate part detected.');
        Shamir::reconstruct(['foo', 'foo']);
    }

    public function testReconstructSimple(): void
    {
        $secret = 'test test';
        $parts = Shamir::split($secret, 5, 3);

        $input = [];
        $input[] = $parts[0];
        $input[] = $parts[1];
        $input[] = $parts[2];
        $this->assertSame($secret, Shamir::reconstruct($input));

        $input[] = $parts[3];
        $this->assertSame($secret, Shamir::reconstruct($input));

        $input[] = $parts[4];
        $this->assertSame($secret, Shamir::reconstruct($input));
    }

    public function testReconstructBruteforce(): void
    {
        $secret = 'test test';
        $out = Shamir::split($secret, 5, 3);

        // bruteforce all 5*4*3 possible choices
        for ($i = 0; $i < 5; $i++) {
            for ($j = 0; $j < 5; $j++) {
                if ($j === $i) {
                    continue;
                }
                for ($k = 0; $k < 5; $k++) {
                    if ($k === $i || $k === $j) {
                        continue;
                    }
                    $this->assertSame($secret, Shamir::reconstruct([$out[$i], $out[$j], $out[$k]]));
                }
            }
        }
    }
}
