<?php

namespace Nadar\PdfGenerator\Tests\Unit;

use Nadar\PdfGenerator\Fit;
use Nadar\PdfGenerator\ImageBox;
use Nadar\PdfGenerator\Shape;
use Nadar\PdfGenerator\ShapeKind;
use Nadar\PdfGenerator\Value\Color;
use PHPUnit\Framework\TestCase;

final class ImageBoxTest extends TestCase
{
    /** Cover fills the box and lets the long axis hang over, ready to be clipped. */
    public function testCoverKeepsRatioAndOverflowsTheBox(): void
    {
        $box = new ImageBox('p', 0, 0, 30, 30, Fit::Cover);

        // landscape 4:3 into a square: height matches, width overflows symmetrically
        self::assertPlacement([-5.0, 0.0, 40.0, 30.0], $box->placement(400, 300));
        // portrait 3:4 into a square: width matches, height overflows
        self::assertPlacement([0.0, -5.0, 30.0, 40.0], $box->placement(300, 400));
        // square into a square: exact
        self::assertPlacement([0.0, 0.0, 30.0, 30.0], $box->placement(500, 500));
    }

    /** Contain fits entirely inside and centres the leftover space. */
    public function testContainKeepsRatioAndStaysInsideTheBox(): void
    {
        $box = new ImageBox('p', 0, 0, 30, 30, Fit::Contain);

        self::assertPlacement([0.0, 3.75, 30.0, 22.5], $box->placement(400, 300));
        self::assertPlacement([3.75, 0.0, 22.5, 30.0], $box->placement(300, 400));
    }

    public function testStretchIgnoresTheRatio(): void
    {
        $box = new ImageBox('p', 5, 7, 30, 10, Fit::Stretch);

        self::assertPlacement([5.0, 7.0, 30.0, 10.0], $box->placement(400, 300));
    }

    public function testCoverAndContainAgreeOnNonSquareBoxes(): void
    {
        $cover = new ImageBox('p', 0, 0, 60, 20, Fit::Cover);
        $contain = new ImageBox('p', 0, 0, 60, 20, Fit::Contain);

        // a 1:1 source in a 3:1 box
        self::assertPlacement([0.0, -20.0, 60.0, 60.0], $cover->placement(100, 100));
        self::assertPlacement([20.0, 0.0, 20.0, 20.0], $contain->placement(100, 100));
    }

    public function testDegenerateDimensionsFallBackToTheBox(): void
    {
        $box = new ImageBox('p', 1, 2, 30, 10);

        self::assertPlacement([1.0, 2.0, 30.0, 10.0], $box->placement(0, 0));
    }

    /** Designs describe a round photo as a centre and a diameter. */
    public function testCircleFactoryConvertsCentreAndDiameter(): void
    {
        $box = ImageBox::circle('photo', cx: 30.34, cy: 68.3, diameter: 30.85);

        self::assertEqualsWithDelta(14.915, $box->x, 0.0001);
        self::assertEqualsWithDelta(52.875, $box->y, 0.0001);
        self::assertSame(30.85, $box->w);
        self::assertSame(30.85, $box->h);
        self::assertSame(ShapeKind::Circle, $box->shape->kind);
        self::assertEqualsWithDelta(30.34, $box->bounds()->center()[0], 0.0001);
        self::assertEqualsWithDelta(68.3, $box->bounds()->center()[1], 0.0001);
    }

    public function testRoundedFactory(): void
    {
        $box = ImageBox::rounded('r', 10, 20, 40, 30, radius: 4.0);

        self::assertSame(ShapeKind::RoundRect, $box->shape->kind);
        self::assertSame(4.0, $box->shape->radius);
    }

    public function testShapeDefaultsToRect(): void
    {
        self::assertSame(ShapeKind::Rect, (new ImageBox('p', 0, 0, 1, 1))->shape->kind);
    }

    public function testOffsetPreservesEveryOtherProperty(): void
    {
        $box = ImageBox::circle('photo', cx: 30, cy: 40, diameter: 20, placeholder: Color::hex('#ff920c'));
        $moved = $box->offset(0.0, 40.3);

        self::assertSame($box->x, $moved->x);
        self::assertEqualsWithDelta($box->y + 40.3, $moved->y, 0.0001);
        self::assertSame(ShapeKind::Circle, $moved->shape->kind);
        self::assertSame('#FF920C', $moved->placeholder?->toHex());
        self::assertSame($box->dpi, $moved->dpi);
    }

    public function testRejectsNonPositiveDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/positive width and height/');
        new ImageBox('p', 0, 0, 0, 10);
    }

    public function testRejectsNonPositiveDpi(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageBox('p', 0, 0, 10, 10, dpi: 0);
    }

    public function testNegativeCornerRadiusIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Shape::roundRect(-1.0);
    }

    /**
     * @param array{0:float,1:float,2:float,3:float} $expected
     * @param array{0:float,1:float,2:float,3:float} $actual
     */
    private static function assertPlacement(array $expected, array $actual): void
    {
        foreach ($expected as $i => $value) {
            self::assertEqualsWithDelta($value, $actual[$i], 0.0001, "component {$i}");
        }
    }
}
