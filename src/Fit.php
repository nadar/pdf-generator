<?php

namespace Nadar\PdfGenerator;

/** How an image is scaled into the box declared by an {@see ImageBox}. */
enum Fit
{
    /** Fill the whole box, cropping the overflowing axis. Keeps the aspect ratio. */
    case Cover;

    /** Fit entirely inside the box, leaving empty space. Keeps the aspect ratio. */
    case Contain;

    /** Stretch to the exact box. Does *not* keep the aspect ratio. */
    case Stretch;
}
