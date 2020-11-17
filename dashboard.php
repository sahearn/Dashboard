<!DOCTYPE html>
<html lang="en">
<head>
	<title>Family Dashboard</title>
	<!-- why are you looking at my source? -->

	<meta http-equiv="refresh" content="900">
	
	<meta charset="utf-8">

	<meta name="description" content="Family Dashboard" />
	<meta name="keywords" content="family dashboard" />

	<link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Kanit:ital,wght@1,600&display=swap" rel="stylesheet">
	
	<link rel="stylesheet" href="style.css" />
</head>
<body>

<?
// enable debug mode: "?debug"
$queryString = htmlspecialchars($_SERVER['QUERY_STRING']);
$debug = FALSE;
if (!empty($queryString) && $queryString === 'debug') {
	$debug = TRUE;
}

date_default_timezone_set('America/New_York');

// add caching library
require 'cache.php';

// add calendar functions
require 'calendar.php';
?>

<div id="wrapper">
	<section class="columns"><h1 class="sectionHeader">Dashboard</h1>
		<div class="column">
			<div class="calendar-header calendar-header-kid">Kid's Calendar</div>
			<div id="calendar-kid">
				<div id="calendar-kid-content">
				<?
				// calendarIds pulled from API page, live test:
				// https://developers.google.com/calendar/v3/reference/calendarList/list

				$calendars = array('[**replace**]@group.calendar.google.com');
				get_calendar($calendars,Panels::LEFT,$debug);
				?>
				</div>
			</div>
		</div>
		
		<div class="column" id="clock-weather-container">
			<div id="clock-header">Today is</div>
			<div id="clock-date"><? echo date("l, F j, Y"); ?></div>
			<div id="clockDisplay" onload="showTime()"></div>

			<div id="weather-header">Current weather</div>
			<div id="weather">
				<?
				// 3 hour cache for each, don't skip cache
				$currentWeatherResult = cache_url('http://dataservice.accuweather.com/currentconditions/v1/2220256?apikey=[**replace**]',10800,FALSE);
				$forecastWeatherResult = cache_url('http://dataservice.accuweather.com/forecasts/v1/daily/1day/2220256?apikey=[**replace**]',10800,FALSE);

				if ($currentWeatherResult['code'] !== 200 || $forecastWeatherResult['code'] !== 200) {
					// show me errors
					echo '<span style="font-style:italic;">';
					if ($currentWeatherResult['code'] !== 200) {
						echo 'Current weather:<br>' . $currentWeatherResult['content'];
					}
					if ($currentWeatherResult['code'] !== 200 && $forecastWeatherResult['code'] !== 200) {
						echo '<br><br>' . "\n";
					}
					if ($forecastWeatherResult['code'] !== 200) {
						echo 'Forecast weather:<br>' . $forecastWeatherResult['content'];
					}
					echo '</span>' . "\n";
				} else {
					// output current conditions and forecast info
					$contents = $currentWeatherResult['content'];
					$json = json_decode($contents, false);

					$contents = $forecastWeatherResult['content'];
					$jsonFC = json_decode($contents, false);

					if ($json[0]->WeatherIcon < 10) {
						$weatherIcon = '0' . $json[0]->WeatherIcon;
					} else {
						$weatherIcon = $json[0]->WeatherIcon;
					}

					// tables?!  I know.  css floats were killing me on alignment here
					echo '<table><tr>' . "\n";
					echo '<td id="weather-cell-image"><img width="75" height="45" src="' . 'https://developer.accuweather.com/sites/default/files/' . $weatherIcon . '-s.png" alt="" /> &nbsp;</td>' . "\n";
					echo '<td id="weather-cell-temp"><span>' . round($json[0]->Temperature->Imperial->Value) . '&deg;</span></td>' . "\n";
					echo '</tr></table>' . "\n";
					echo '<span id="weather-description">' . ucfirst($json[0]->WeatherText) . '</span>' . "\n";
					echo '<br>' . "\n";
					echo '<span id="weather-hilo">Today\'s low: ' . round($jsonFC->DailyForecasts[0]->Temperature->Minimum->Value) . '&deg; / ';
					echo 'high: ' . round($jsonFC->DailyForecasts[0]->Temperature->Maximum->Value) . '&deg;</span>' . "\n";
					echo '<br>' . "\n";
					echo '<span class="weather-asof">As of ' . date("n/j/Y, g:i a", $json[0]->EpochTime);
					if ($debug) {
						echo ' (Source: ' . $currentWeatherResult['source'] . ')';
						echo '<br>' . "\n";
						echo 'Forecast as of ' . date("n/j/Y, g:i a", $jsonFC->DailyForecasts[0]->EpochDate);
						echo ' (Source: ' . $forecastWeatherResult['source'] . ')';
					}
					echo '</span>' . "\n";
				}
				?>
			</div>
		</div>

		<div class="column">
			<div class="calendar-header calendar-header-house">Household Calendar</div>
			<div id="calendar-house">
				<div id="calendar-house-content">
				<?
				// Events and Holidays, merged
				$calendars = array('[**replace**]@group.calendar.google.com',
								   '[**replace**]@group.calendar.google.com');
				get_calendar($calendars,Panels::RIGHT,$debug);
				?>
				</div>
			</div>
		</div>
	</section>
</div>

<div id="wrapper-lower">
	<section class="columns"><h1 class="sectionHeader">News & Quotes</h1>
		<div class="column" id="news">
			<div id="news-header">Top News Stories</div>
			<div id="news-content">
			<?
			$newsResult = cache_url('https://feeds.npr.org/1001/rss.xml');
			
			if($newsResult['code'] !== 200) {
				echo $newsResult['content'];
			} else {
				$xml = new SimpleXMLElement($newsResult['content']);
				$i = 0;
				foreach ($xml->channel->item as $item) {
					if ($i < 5) {
						// .textindent will bump out 2nd line if 1st line wraps
						echo '<div class="textindent"><a target="_blank" href="' . $item->link . '">' . $item->title . "</a></div>\n";
					}
					$i++;
				}
				$newsDate = new DateTime($xml->channel->lastBuildDate);
				$newsDate->setTimezone(new DateTimeZone('America/New_York'));
				echo '<span id="news-asof">As of ' . $newsDate->format("n/j/Y, g:i a");
				if ($debug) {
					echo ' (Source: ' . $newsResult['source'] . ')';
				}
				echo '</span>' . "\n";
			}
			?>
			</div>
		</div>
		<div class="column" id="quote">
			<div id="quote-header">Quote of the Moment</div>
			<div id="quoteText">
			<?
			// random quote generator
			$quoteList = explode("\n", file_get_contents('quotes.txt'));
			echo '"' . trim($quoteList[mt_rand(0, count($quoteList)-1)]) . '"' . "\n";
			?>
			</div>
		</div>
	</section>
</div>

<div id="watermark">Dashboard</div>

<script>
function showTime(){
	var date = new Date();
	var h = date.getHours(); // 0 - 23
	var m = date.getMinutes(); // 0 - 59
	var s = date.getSeconds(); // 0 - 59
	var session = "AM";

	if(h == 0){
		h = 12;
	}

	if(h > 12){
		h = h - 12;
		session = "PM";
	}

	// h = (h < 10) ? "0" + h : h;
	m = (m < 10) ? "0" + m : m;
	s = (s < 10) ? "0" + s : s;

	// var time = h + ":" + m + ":" + s + " " + session;
	var time = h + ":" + m + " <span class='ampm'>" + session + "</span>";
	document.getElementById("clockDisplay").innerHTML = time;
	
	// document.getElementById("clockDisplay").innerText = time;
	// document.getElementById("clockDisplay").textContent = time;

	setTimeout(showTime, 1000);
}
showTime();
</script>

</body>
</html>
