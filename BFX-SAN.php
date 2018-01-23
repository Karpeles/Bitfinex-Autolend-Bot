<?php

require_once( 'BFX-Autolend-Bot.php' );

$a = new Autolend();
$a->apiKey = '_________________________________________';			# Your API key, obtained from the Bitfinex site
$a->apiSecret = '_________________________________________';		# Your API secret, obtained from the Bitfinex site
$a->tries = 5;														# Number of times to retry API requests that fail
$a->currency = 'san';												# Currencey to lend out (usd, eth, btc...)
$a->placeAboveLowestAsk = 5000;									# Number of units to offer lending above the lowest ask, e.g. if the lowest ask is for 0.05 and placeAboveLowestAsk = 1,000,000 the bot will lend out at a rate where there are 1,000,000 units offered above the lowest ask
$a->minimumRate = 0.01;												# Rate to lend at will be set to this if calculated offer rate is below it
$a->periodSchedule = array(											# Number of days to lend by rate, e.g.
	'0.04' => 2,													# 0.05 => 3 - all lending between 0.05 and 0.059r will be for 3 days
	'0.05' => 3,													# all lending lower than the lowest specified rate will be for the period of the lowest specified rate, e.g.
	'0.06' => 5,													# if the lowsest specified rate is 0.04 => 2 lending between 0 and 0.39r will be for a period of 2 days
	'0.07' => 7,													# all lending higher than the highest specified rate will be for the period of the highest specified rate, e.g.
	'0.08' => 10,													# if the highest specified rate is 0.1 => 30 lending more than 0.1 will be for a period of 30 days
	'0.09' => 14,
	'0.1'  => 30
);
$a->reportFile = 'san-report.html';									# File path to save hourly funding report

$a->lend();

?>
