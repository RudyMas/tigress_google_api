<?php

namespace Tigress;

use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Exception;

/**
 * Class GoogleApiCalendar (PHP version 8.4)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2024.11.28.0
 * @package Tigress\GoogleApiCalendar
 */
class GoogleApiCalendar extends GoogleApiAuth
{
    /**
     * Get the version of the class.
     *
     * @return string
     */
    public static function version(): string
    {
        return '2024.11.28';
    }

    /**
     * Set up the connection with the Google Calendar API.
     *
     * @param string $authConfigPath
     * @param string $credentialsPath
     * @return void
     */
    public function config(string $authConfigPath, string $credentialsPath): void
    {
        $this->setAuthConfigPath($authConfigPath);
        $this->setCredentialsPath($credentialsPath);
    }

    /**
     * Execute the insert event request.
     *
     * @param string $subject
     * @param string $bodyText
     * @param string $startDate
     * @param string $endDate
     * @param string $locationAddress
     * @param string|null $locationName
     * @return Event
     * @throws Exception
     */
    public function addEvent(
        string $subject,
        string $bodyText,
        string $startDate,
        string $endDate,
        string $locationAddress,
        ?string $locationName = null
    ): Event
    {
        $data = [
            'summary' => $subject,
            'description' => $bodyText,
            'location' => $locationName,
            'start' => [
                'dateTime' => date('Y-m-d\TH:i:s', strtotime($startDate)),
                'timeZone' => 'Europe/Brussels',
            ],
            'end' => [
                'dateTime' => date('Y-m-d\TH:i:s', strtotime($endDate)),
                'timeZone' => 'Europe/Brussels',
            ],
        ];

        $service = new Calendar($this->client);
        $event = new Event($data);
        $calendarId = $locationAddress;
        return $service->events->insert($calendarId, $event);
    }
}