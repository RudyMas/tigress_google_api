<?php

namespace Tigress;

/**
 * Class GoogleApi (PHP version 8.5)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2025.12.11.1
 * @package Tigress\GoogleApi
 */
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
            'GoogleApi' => '2025.12.11',
            'GoogleApiAuth' => GoogleApiAuth::version(),
            'GoogleApiCalendar' => GoogleApiCalendar::version(),
            'GoogleApiDrive' => GoogleApiDrive::version(),
            'GoogleApiScript' => GoogleApiScript::version(),
        ];
    }
}