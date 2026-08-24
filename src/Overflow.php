<?php

namespace Nadar\PdfGenerator;

enum Overflow
{
    case None;
    case Shrink;
    case Clip;
    case Truncate;
    case ShrinkThenClip;
}
