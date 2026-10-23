<?php

namespace Shopmade\Jobs\Enums;

enum JobPhotoKind: string
{
    case Before = 'before';
    case After = 'after';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Before => 'Before',
            self::After => 'After',
            self::Other => 'Photo',
        };
    }
}
