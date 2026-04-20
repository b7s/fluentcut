<?php

declare(strict_types=1);

namespace B7s\FluentCut\Enums;

enum Transition: string
{
    case Fade = 'fade';
    case FadeBlack = 'fadeblack';
    case FadeWhite = 'fadewhite';
    case FadeGrays = 'fadegrays';
    case WipeLeft = 'wipeleft';
    case WipeRight = 'wiperight';
    case WipeUp = 'wipeup';
    case WipeDown = 'wipedown';
    case SlideLeft = 'slideleft';
    case SlideRight = 'slideright';
    case SlideUp = 'slideup';
    case SlideDown = 'slidedown';
    case Dissolve = 'dissolve';
    case Pixelize = 'pixelize';
    case Radial = 'radial';
    case CircleOpen = 'circleopen';
    case CircleClose = 'circleclose';
    case CircleCrop = 'circlecrop';
    case RectCrop = 'rectcrop';
    case Distance = 'distance';
    case HorizontalBlur = 'hblur';
    case HorizontalLeftSlice = 'hlslice';
    case HorizontalRightSlice = 'hrslice';
    case VerticalUpSlice = 'vuslice';
    case VerticalDownSlice = 'vdslice';
    case DiagonalTopLeft = 'diagtl';
    case DiagonalTopRight = 'diagtr';
    case DiagonalBottomLeft = 'diagbl';
    case DiagonalBottomRight = 'diagbr';
    case None = 'none';

    public function toFFmpegXFilter(): string
    {
        return match ($this) {
            self::Fade => 'fade',
            self::FadeBlack => 'fadeblack',
            self::FadeWhite => 'fadewhite',
            self::FadeGrays => 'fadegrays',
            self::WipeLeft => 'wipeleft',
            self::WipeRight => 'wiperight',
            self::WipeUp => 'wipeup',
            self::WipeDown => 'wipedown',
            self::SlideLeft => 'slideleft',
            self::SlideRight => 'slideright',
            self::SlideUp => 'slideup',
            self::SlideDown => 'slidedown',
            self::Dissolve => 'dissolve',
            self::Pixelize => 'pixelize',
            self::Radial => 'radial',
            self::CircleOpen => 'circleopen',
            self::CircleClose => 'circleclose',
            self::CircleCrop => 'circlecrop',
            self::RectCrop => 'rectcrop',
            self::Distance => 'distance',
            self::HorizontalBlur => 'hblur',
            self::HorizontalLeftSlice => 'hlslice',
            self::HorizontalRightSlice => 'hrslice',
            self::VerticalUpSlice => 'vuslice',
            self::VerticalDownSlice => 'vdslice',
            self::DiagonalTopLeft => 'diagtl',
            self::DiagonalTopRight => 'diagtr',
            self::DiagonalBottomLeft => 'diagbl',
            self::DiagonalBottomRight => 'diagbr',
            self::None => 'fade',
        };
    }

    public function isCrossfade(): bool
    {
        return match ($this) {
            self::Fade, 
            self::FadeBlack, 
            self::FadeWhite, 
            self::FadeGrays,
            self::Dissolve => true,
            default => false,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Fade => 'Smooth crossfade between clips',
            self::FadeBlack => 'Fade through black',
            self::FadeWhite => 'Fade through white',
            self::FadeGrays => 'Fade through grayscale',
            self::WipeLeft => 'Wipe from right to left',
            self::WipeRight => 'Wipe from left to right',
            self::WipeUp => 'Wipe from bottom to top',
            self::WipeDown => 'Wipe from top to bottom',
            self::SlideLeft => 'Slide from left',
            self::SlideRight => 'Slide from right',
            self::SlideUp => 'Slide from bottom',
            self::SlideDown => 'Slide from top',
            self::Dissolve => 'Pixel dissolve blend',
            self::Pixelize => 'Pixelization effect',
            self::Radial => 'Radial wipe from center',
            self::CircleOpen => 'Circle opening from center',
            self::CircleClose => 'Circle closing to center',
            self::CircleCrop => 'Circular crop transition',
            self::RectCrop => 'Rectangular crop transition',
            self::Distance => 'Distance-based blend',
            self::HorizontalBlur => 'Horizontal blur transition',
            self::HorizontalLeftSlice => 'Horizontal slice from left',
            self::HorizontalRightSlice => 'Horizontal slice from right',
            self::VerticalUpSlice => 'Vertical slice upward',
            self::VerticalDownSlice => 'Vertical slice downward',
            self::DiagonalTopLeft => 'Diagonal from top-left',
            self::DiagonalTopRight => 'Diagonal from top-right',
            self::DiagonalBottomLeft => 'Diagonal from bottom-left',
            self::DiagonalBottomRight => 'Diagonal from bottom-right',
            self::None => 'Hard cut (no transition)',
        };
    }
}
