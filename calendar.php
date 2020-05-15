<?php
require __DIR__ . '/vendor/autoload.php';

/* commented out to exec through web
if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}
*/

abstract class Panels {
	const RIGHT = 'RIGHT';
	const LEFT = 'LEFT';
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Dashboard Calendar');
    $client->setScopes(Google_Service_Calendar::CALENDAR_READONLY);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

/**
 * custom date sort function for usort
 * @return sorted object
 */
function date_sort($a, $b) {
	return strtotime($a->baseStart) - strtotime($b->baseStart);
}

/**
 * all-day events have start->date, timed events have start->dateTime
 * consolidate into a single baseStart field for later sorting
 * @param $events incoming array of events
 * @return array of events with added baseStart field
 */
function sort_events($events) {
	foreach ($events as $event) {
		$start = $event->start->dateTime;
		if (empty($start)) {
			$start = $event->start->date;
		}
		$event->baseStart = $start;
	}
	return $events;
}

/**
 * take in all events for a given calendar and generate formatted output
 * @param $events incoming array of events
 * @param $panelSide which side of screen, for text alignment styles
 * @param $file caching file
 * @return void
 */
function generate_output($events, $panelSide, $file) {
	$output = '';
	if (empty($events)) {
		$output .= "No upcoming events found.\n";
	} else {
		
		// time tracking vars
		$now = new DateTime("now");
		$tomorrow = clone $now;
		$tomorrow->modify('+1 day');
		// beyond used to track headers for today, tomorrow, then beyond
		$beyond = clone $now;
		$beyond->modify('+2 days');

		// track multiple events within same day (don't repeat header)
		$runningDay = new DateTime();
		$isFirst = true;

		// more tracking for headers for today, tomorrow, etc
		$todayIndStart = 0; $todayIndEnd = 0;
		$tomIndStart = 0; $tomIndEnd = 0;

		// sort all events, since they could have come in from multiple concatenated calendars
		$events = sort_events($events);
		usort($events,"date_sort");

		foreach ($events as $event) {

			// event start
			$startBase = $event->start->dateTime;
			if (empty($startBase)) {
				$startBase = $event->start->date;
			}
			$start_date = new DateTime($startBase);
			$start_date->setTimezone(new DateTimeZone('America/New_York'));
			$startDayPart = $start_date->format("Y-m-d");

			// event end
			$endBase = $event->end->dateTime;
			if (empty($endBase)) {
				$endBase = $event->end->date;
			}
			$end_date = new DateTime($endBase);
			$end_date->setTimezone(new DateTimeZone('America/New_York'));

			$nowDayPart = $now->format("Y-m-d");
			$tomDayPart = $tomorrow->format("Y-m-d");
			$beyDayPart = $beyond->format("Y-m-d");

			// header blocks for today, tomorrow, beyond
			if ($runningDay != $startDayPart) {
				if (!$isFirst) {
					$output .=  '</div>' . "\n";
				}
				$output .=  '<div class="day-block">' . "\n";
			}
			$isFirst = false;

			if ($startDayPart === $nowDayPart && $todayIndStart == 0) {
				$output .=  "\t" . '<div class="day-header">Today</div>' . "\n";
				$todayIndStart = 1;
			} else if ($startDayPart > $nowDayPart && $todayIndEnd == 0) {
				$todayIndEnd = 1;
			}

			if ($startDayPart === $tomDayPart && $tomIndStart == 0) {
				$output .=  "\t" . '<div class="day-header">Tomorrow</div>' . "\n";
				$tomIndStart = 1;
			} else if ($startDayPart > $tomDayPart && $tomIndEnd == 0) {
				$tomIndEnd = 1;
			}

			if ($startDayPart >= $beyDayPart && $runningDay != $startDayPart) {
				$output .=  "\t" . '<div class="day-header">';
				if ($panelSide == Panels::RIGHT) {
					$output .=  '<span class="day-header-MON">' . $start_date->format("F j") . '</span>';
					$output .=  ' &nbsp; &nbsp; ';
					$output .=  '<span class="day-header-DAY">' . $start_date->format("l") . '</span>';
				} else {
					$output .=  '<span class="day-header-DAY">' . $start_date->format("l") . '</span>';
					$output .=  ' &nbsp; &nbsp; ';
					$output .=  '<span class="day-header-MON">' . $start_date->format("F j") . '</span>';
				}
				$output .=  '</div>' . "\n";
			}

			if (empty($event->start->dateTime)) {
				$display_date = '<span class="allday">All Day</span>';
			} else {
				// only show times for non all-day events
				$display_date = '<span class="settime">';
				$display_date .= $start_date->format("g:i") . ' <span class="ampm">' . $start_date->format("A") . '</span>';
				$display_date .= " - ";
				$display_date .= $end_date->format("g:i") . ' <span class="ampm">' . $end_date->format("A") . '</span>';
				$display_date .= '</span>';
			}

			$output .=  "\t" . '<div class="event-block event-block-' . $panelSide . '">' . "\n";
			$output .=  "\t\t" . '<div class="event-time">' . $display_date . '</div>' . "\n";
			$output .=  "\t\t" . '<div class="event-desc">' . $event->getSummary() . '</div>' . "\n";
			$output .=  "\t" . '</div>' . "\n";
			
			// iterate to track multiple events within same day
			$runningDay = $startDayPart;
		}
		$output .=  '</div>' . "\n";// . '</div>' . "\n";
	}
	file_put_contents($file, $output);
}

/**
 * grab events for a given calendar from Google API
 * @param $calendarId
 * @return $events
 */
function fetch_events($calendarId) {
	// calendarIds pulled from API page, live test:
	// https://developers.google.com/calendar/v3/reference/calendarList/list

	// Get the API client and construct the service object.
	$client = getClient();
	$service = new Google_Service_Calendar($client);

	$optParams = array(
	  'maxResults' => 10,
	  'orderBy' => 'startTime',
	  'singleEvents' => true,
	  'timeMin' => date('c'),
	  'timeMax' => Date('c', strtotime('+7 days'))
	);

	$results = $service->events->listEvents($calendarId, $optParams);
	$events = $results->getItems();

	return $events;
}

/**
 * main function to take in 1+ calendarIds, fetch events from API depending on 
 * local filesystem cache, write to output
 * @param $input array of 1+ calendarIds
 * @param $panel which side of screen, for text alignment styles
 * @param $debug if debug mode enabled, default FALSE/off
 * @return void
 */
function get_calendar($input,$panel,$debug = FALSE) {
	$mergedEvents = array();

	foreach ($input as $calendar) {
		$mode = Sources::CACHE;

		$cachetime = (28800); // 8 hours
		$where = "cache";
		if ( ! is_dir($where)) {
			mkdir($where);
		}
		
		// name cache file based on first member of calendarId array (yeah, this is weird)
		$hash = md5($input[0]);
		$file = $where . '/calendar-' . $hash . '.cache';
		
		// check the file
		$mtime = 0;
		if (file_exists($file)) {
			$mtime = filemtime($file);
		}
		$filetimemod = $mtime + $cachetime;
		
		// if the renewal date is smaller than now, return true; else false (no need for update)
		if ($filetimemod < time()) {
			$mode = Sources::LIVE;
			$events = fetch_events($calendar);
			$mergedEvents = array_merge($mergedEvents,$events);
		}
	}
	
	// if I had to pull from live API, generate output file for local filesystem cache
	if ($mode == Sources::LIVE) {
		generate_output($mergedEvents,$panel,$file);
		$mtime = filemtime($file);
	}
	if ($debug) {
		echo '<div class="calendar-asof">As of ' . date("n/j/Y, g:i a",$mtime) . ' (Source: ' . $mode . ')</div>' . "\n";
	}
	
	// output file; this will be present at this point whether this was liva or cache
	echo file_get_contents($file);
}

