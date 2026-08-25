<?php

namespace Nadar\PdfGenerator;

/** The geometric family behind a {@see Shape}. */
enum ShapeKind
{
    case Rect;
    case Circle;
    case RoundRect;
}
