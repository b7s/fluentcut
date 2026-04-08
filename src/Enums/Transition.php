<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

enum Transition: string
{
    case Fade = 'fade';
    case FadeBlack = 'fadeblack';
    case FadeWhite = 'fadewhite';
    case WipeLeft = 'wipeleft';
    case WipeRight = 'wiperight';
    case WipeUp = 'wipeup';
    case WipeDown = 'wipedown';
    case SlideLeft = 'slideleft';
    case SlideRight = 'slideright';
    case Dissolve = 'dissolve';
    case None = 'none';

    public function toFFmpegXFilter(): string
    {
        return match ($this) {
            self::Fade => 'fade',
            self::FadeBlack => 'fadeblack',
            self::FadeWhite => 'fadewhite',
            self::WipeLeft => 'wipeleft',
            self::WipeRight => 'wiperight',
            self::WipeUp => 'wipeup',
            self::WipeDown => 'wipedown',
            self::SlideLeft => 'slideleft',
            self::SlideRight => 'slideright',
            self::Dissolve => 'dissolve',
            self::None => 'none',
        };
    }

    public function isCrossfade(): bool
    {
        return match ($this) {
            self::Fade, self::FadeBlack, self::FadeWhite, self::Dissolve => true,
            default => false,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Fade => 'Smooth crossfade between clips',
            self::FadeBlack => 'Fade through black',
            self::FadeWhite => 'Fade through white',
            self::WipeLeft => 'Wipe from right to left',
            self::WipeRight => 'Wipe from left to right',
            self::WipeUp => 'Wipe from bottom to top',
            self::WipeDown => 'Wipe from top to bottom',
            self::SlideLeft => 'Slide clips from right',
            self::SlideRight => 'Slide clips from left',
            self::Dissolve => 'Pixel dissolve blend',
            self::None => 'Hard cut (no transition)',
        };
    }
}
