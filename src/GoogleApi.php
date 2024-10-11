<?php

namespace Tigress;

/**
 * Class GoogleApi (PHP version 8.3)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2024.10.4
 * @lastmodified 2024-10-11
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
            'GoogleApi' => '2024.10.4',
            'GoogleApiAuth' => GoogleApiAuth::version(),
            'GoogleApiCalendar' => GoogleApiCalendar::version(),
            'GoogleApiDrive' => GoogleApiDrive::version(),
            'GoogleApiScript' => GoogleApiScript::version(),
        ];
    }
}