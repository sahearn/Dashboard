# Dashboard
Fast and lightweight family dashboard, including calendars, weather, and news

## Background
We have a family laptop that sits in the kitchen - when it's not in use I wanted a fullscreen browser window up showing a dashboard with info for all of us.  This would include family Google calendars, current weather and simple forecast, and other info like news and maybe random quotes.

Here were the problems:
- Since this is a personal project, I didn't have the time or interest in learning some big app framework
- I wanted this to be fast and very lightweight: so no stuff like JQuery, object caching, or databases, even if that would make some features easier.  Maybe I'll revisit those someday.
- Nothing out there looked or did exactly what I wanted, or had features I was able to use with my hosting provider

## What You'll Need
- a web host
- PHP
- a sense of humor as you make sense of my code

## My Approach
So the quick and dirty:
- This is all homegrown PHP (at the time using PHP 7.2.30) and validated HTML5.
- Dashboard layout starts with standard CSS flex columns.
- v0.1 of this was heavily cron dependent, and I didn't bother with the Google Calendar API.  I didn't want to make my calendars public, so I had a job pull the individual ics files.  Then I wrote my own ics parser which handled scheduled events, all-day events, and recurring events.  It was brutal and ugly, but it worked solidly.  Maybe I'll publish it to git someday for reference.
  - Then I came to my senses and took 5 minutes to learn and implement the Calendar API.
- my web host doesn't allow for memcache[d] or anything, so I wrote my own filesystem-based caching based on SaltwaterC's cache_url.
- Weather pulls from accuweather's API, and news is just top 5 headlines from NPR's RSS.  As above, both were cron'd in v0.1, but now use my homegrown caching.

## Installation
1. Grab/create relevant files
  - dashboard.php: the main page (or call it index.php or whatever)
  - calendar.php: for pulling and generating calendar content
  - style.css: stylesheet
  - cache.php: curl caching
  - quotes.txt: random quote file
  - texture.jpg: or whatever you want for the background (except my styles/colors are somewhat based on the provided background)
2. Create/use accuweather API account, fill in your details
3. Create/use Google Calendar API
  - authenticate your project, get credentials, etc
4. Replace news feed with your preferred source
5. Fun quotes!
6. Profit.

... in progress...
