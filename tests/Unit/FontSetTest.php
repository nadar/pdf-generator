<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Exception\MissingFontStyleException;
use Nadar\PdfGenerator\Font\FontSet;
use Nadar\PdfGenerator\Font\FontWeight;
use PHPUnit\Framework\TestCase;

final class FontSetTest extends TestCase
{
    public function testFamilyRegistersTheFourCanonicalStyles(): void
    {
        $set = FontSet::make()->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf', 'Inter-Italic.ttf', 'Inter-BoldItalic.ttf');

        self::assertSame(['regular', 'bold', 'italic', 'bolditalic'], $set->weights('inter'));
        self::assertTrue($set->supportsStyle('inter', 'B'));
        self::assertTrue($set->supportsStyle('inter', 'BI'));
        self::assertSame('interbi', $set->cacheKey('inter', 'BI'));
    }

    public function testOmittedStylesAreNotRegistered(): void
    {
        $set = FontSet::make()->family('inter', 'Inter-Regular.ttf');

        self::assertSame(['regular'], $set->weights('inter'));
        self::assertFalse($set->supportsStyle('inter', 'B'));
        self::assertNull($set->cacheKey('inter', 'B'));
    }

    /**
     * A four-weight brand family is the common case, and TCPDF only models four
     * styles - so extra weights get their own TCPDF family behind one logical name.
     */
    public function testCustomWeightsGetTheirOwnTcpdfFamily(): void
    {
        $set = FontSet::make()
            ->face('brother', 'regular', 'Brother-1816-Regular.ttf')
            ->face('brother', 'medium', 'Brother-1816-Medium.ttf')
            ->face('brother', 'bold', 'Brother-1816-Bold.ttf')
            ->role('body', 'brother')
            ->role('lead', 'brother', 'medium')
            ->role('title', 'brother', 'bold');

        self::assertSame(['regular', 'medium', 'bold'], $set->weights('brother'));

        // regular and bold are styles of one TCPDF family
        self::assertSame('brother', $set->roleOrDefault('body')->family);
        self::assertSame('', $set->roleOrDefault('body')->style);
        self::assertSame('brother', $set->roleOrDefault('title')->family);
        self::assertSame('B', $set->roleOrDefault('title')->style);

        // medium becomes its own family, with the regular style
        self::assertSame('brothermedium', $set->roleOrDefault('lead')->family);
        self::assertSame('', $set->roleOrDefault('lead')->style);

        // and each still resolves to its own compiled definition
        self::assertSame('brother1816medium', $set->cacheKey('brothermedium', ''));
        self::assertSame('brother1816', $set->cacheKey('brother', ''));
        self::assertSame('brother1816b', $set->cacheKey('brother', 'B'));
    }

    public function testRoleAcceptsBothStyleCodesAndWeightNames(): void
    {
        $set = FontSet::make()
            ->family('inter', 'Inter-Regular.ttf', 'Inter-Bold.ttf')
            ->role('legacy', 'inter', 'B')
            ->role('modern', 'inter', 'bold');

        self::assertSame('B', $set->roleOrDefault('legacy')->style);
        self::assertSame('B', $set->roleOrDefault('modern')->style);
    }

    public function testRoleForUnregisteredWeightFailsWithAnActionableMessage(): void
    {
        $set = FontSet::make()->face('brother', 'regular', 'Brother-1816-Regular.ttf');

        $this->expectException(MissingFontStyleException::class);
        $this->expectExceptionMessageMatches('/requires weight "medium".*Registered weights: regular.*->face\(/s');
        $set->role('lead', 'brother', 'medium');
    }

    public function testRoleForUnknownFamilyNamesTheKnownOnes(): void
    {
        $set = FontSet::make()->family('inter', 'Inter-Regular.ttf');

        $this->expectException(MissingFontStyleException::class);
        $this->expectExceptionMessageMatches('/unknown font family "brother".*Registered families: inter/s');
        $set->role('lead', 'brother');
    }

    public function testDefaultRoleFallsBackToFirstFamilyThenCoreFont(): void
    {
        self::assertSame('helvetica', FontSet::make()->roleOrDefault(null)->family);

        $set = FontSet::make()->family('inter', 'Inter-Regular.ttf');
        self::assertSame('inter', $set->roleOrDefault(null)->family);
        self::assertSame('inter', $set->roleOrDefault('nope')->family);
    }

    public function testFacesAreKeyedByTcpdfFamilyAndStyle(): void
    {
        $faces = FontSet::make()
            ->face('brother', 'regular', 'Brother-1816-Regular.ttf')
            ->face('brother', 'bold', 'Brother-1816-Bold.ttf')
            ->face('brother', 'medium', 'Brother-1816-Medium.ttf')
            ->faces();

        self::assertSame(['brother|', 'brother|B', 'brothermedium|'], array_keys($faces));
        self::assertSame('brother/medium', $faces['brothermedium|']->label());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('weightAliases')]
    public function testWeightNormalization(string $input, string $expected): void
    {
        self::assertSame($expected, FontWeight::normalize($input));
    }

    /** @return iterable<string,array{0:string,1:string}> */
    public static function weightAliases(): iterable
    {
        yield 'empty style code' => ['', 'regular'];
        yield 'regular' => ['Regular', 'regular'];
        yield 'normal' => ['normal', 'regular'];
        yield 'bold code' => ['B', 'bold'];
        yield 'italic code' => ['I', 'italic'];
        yield 'oblique' => ['oblique', 'italic'];
        yield 'bold italic code' => ['BI', 'bolditalic'];
        yield 'reversed bold italic' => ['IB', 'bolditalic'];
        yield 'custom weight' => ['ExtraBold', 'extrabold'];
        yield 'punctuation stripped' => ['semi-bold', 'semibold'];
    }

    public function testCanonicalWeightsMapToTcpdfStyles(): void
    {
        self::assertSame(['inter', ''], FontWeight::toTcpdf('inter', 'regular'));
        self::assertSame(['inter', 'B'], FontWeight::toTcpdf('inter', 'bold'));
        self::assertSame(['inter', 'I'], FontWeight::toTcpdf('inter', 'italic'));
        self::assertSame(['inter', 'BI'], FontWeight::toTcpdf('inter', 'bolditalic'));
        self::assertSame(['intermedium', ''], FontWeight::toTcpdf('inter', 'medium'));
    }
}
