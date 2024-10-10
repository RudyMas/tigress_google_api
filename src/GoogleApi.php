<?php

namespace Tigress;

class GoogleApi
{
    /**
     * Get the version of the class.
     *
     * @return array
     */
    public static function version(): array
    {
        return [
            'GoogleApi' => '1.0.0',
            'GoogleApiAuth' => GoogleApiAuth::version(),
            'GoogleApiCalendar' => GoogleApiCalendar::version(),
            'GoogleApiDrive' => GoogleApiDrive::version(),
            'GoogleApiScript' => GoogleApiScript::version(),
        ];
    }
}