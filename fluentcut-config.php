<?php

declare(strict_types=1);

return [
    'ffmpeg_path' => null,
    'ffprobe_path' => null,
    'default_width' => 1920,
    'default_height' => 1080,
    'default_fps' => 30,
    'default_codec' => 'libx264',
    'default_duration' => 1.0,
    'timeout' => 14400, // seconds
    'verbose' => false,
    'cache_enabled' => true,
    'cache_dir' => null,
    'clear_cache_after_render' => true,
];
