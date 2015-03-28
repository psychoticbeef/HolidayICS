<?php

class Calendar {
	public $prefix_title = '';
	public $title = '';
	public $events = array();

	public function __construct($prefix, $title) {
		$this->prefix_title = $prefix;
		$this->title = $title;
	}	

	public function print_header() {
		return <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
X-WR-CALNAME:$this->prefix_title $this->title

EOF;
	}

	public function print_footer() {
		return 'END:VCALENDAR';
	}

	public function print_events() {
		$result = '';
		foreach ($this->events as $event) $result .= $event->__toString();
		return $result;
	}

	public function __toString() {
		return $this->print_header() . $this->print_events() . $this->print_footer();
	}
}

class Event {
	public $created;
	public $uid;
	public $repeating;
	public $start;
	public $end;
	public $title;

	public function __construct($created, $repeating, $start, $title, $uid = null) {
		$this->created = $created->format('Ymd');
		$this->created .= 'T000000Z';
		$this->uid = is_null($uid) ? uniqid('', true) : $uid;
		$this->start = $start->format('Ymd');
		$this->end = $start->add(new DateInterval('P1D'))->format('Ymd');
		$this->title = $title;
		$this->repeating = $repeating;
	}

	public function __toString() {
		$result = <<<EOF
BEGIN:VEVENT
CREATED:$this->created
UID:$this->uid

EOF;
		if ($this->repeating) $result .= 'RRULE:FREQ=YEARLY' . PHP_EOL;
		$result .= <<<EOF
DTEND;VALUE=DATE:$this->end
SUMMARY:$this->title
DTSTART;VALUE=DATE:$this->start
DTSTAMP:$this->created
SEQUENCE:0
END:VEVENT

EOF;
		return $result;
	}
}

function get_uuid(&$event, $year = 0) {
	if ($year == 0) $key = 'uid';
	else $key = 'uid_' . $year;
	if (!array_key_exists($key, $event)) $event[$key] = uniqid('', true);
	return $event[$key];
}

function get_easter_events(&$events, $year) {
	$result = array();
	$created = new DateTime();
	$created->setDate($year - 3, 1, 1);
	foreach ($events as $title => &$value) {
		$easter = new DateTime('@' . easter_date($year));
		$interval = new DateInterval(str_replace('-', '', $value['diff']));
		if (strpos($value['diff'], '-') !== false) $interval->invert = true;
		$date = $easter->add($interval);
		$event = new Event($created, false, $date, $title, get_uuid($value, $year));
		$result []= $event;
	}
	return $result;
}

function get_repeated_events(&$events, $created) {
	$result = array();
	foreach ($events as $title => &$value) {
		if (!array_key_exists('date', $value)) {
			echo 'Error: Missing date for ' . $title;
			die();
		}
		$date = new DateTime($created->format('Y') . $value['date']);
		$result []= new Event($created, true, $date, $title, get_uuid($value));
	}
	return $result;
}

function create_calendar($name, &$obj, $start, $end) {
	$calendar = new Calendar('Feiertage', $name);
	for ($i = $start; $i <= $end; $i++) {
		$calendar->events = array_merge($calendar->events, get_easter_events($obj['easter'], $i));
	}
	$created  = new DateTime();
	$created->setDate(2014, 1, 1);
	$calendar->events = array_merge($calendar->events, get_repeated_events($obj['repeat'], $created));
	return $calendar;
}

function parse($start, $end) {
	$result = array();
	$json = json_decode(file_get_contents('calendar.json'), true);
	if (is_null($json)) {
		echo 'Could not decode json.';
		die();
	}
	foreach ($json as $key => &$value) {
		$result []= create_calendar($key, $value, $start, $end);
	}
	file_put_contents('calendar.json', json_encode($json, JSON_PRETTY_PRINT));
	return $result;
}

date_default_timezone_set('Europe/Berlin');
$calendars = parse(2014, 2016);
foreach ($calendars as $calendar) {
	file_put_contents('/var/www/htdocs/feiertage/' . $calendar->title . '.ics', str_replace("\n", "\r\n", $calendar));
}


