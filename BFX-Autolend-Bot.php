<?php

set_time_limit( 360 );
ini_set( 'memory_limit', '100M' );
setlocale( LC_CTYPE, 'en_US.UTF-8' );
mb_regex_encoding( 'UTF-8' );
require_once( 'classes/CURL.php' );
require_once( 'classes/CURLRequest.php' );
require_once( 'classes/XML.php' );

class Autolend
{
	public $opts = array(
		CURLOPT_POST => 1,
		CURLOPT_POSTFIELDS => '',
		CURLOPT_FOLLOWLOCATION	=> 1,
		CURLOPT_RETURNTRANSFER	=> 1,
		CURLOPT_SSL_VERIFYHOST	=> 0,
		CURLOPT_SSL_VERIFYPEER	=> 0,
		CURLOPT_IPRESOLVE	=> CURL_IPRESOLVE_V4,
		CURLOPT_CONNECTTIMEOUT	=> 45,
		CURLOPT_TIMEOUT	=> 45
	);
	
	public $apiBaseUrl = 'https://api.bitfinex.com';
	public $apiKey = '';
	public $apiSecret = '';
	public $tries = 5;
	public $currency = null;
	public $minimumRate = 0;
	public $placeAboveLowestAsk = 1000000;
	public $periodSchedule = array();
	public $reportFile = '';
	
	public function lend()
	{
		if( !$this->currency ) die( "No currencey set\n" );
		if( !$this->apiKey || !$this->apiSecret ) die( "No API keys set\n" );
		$this->c = new CURLRequest();
		$this->c->retry = $this->tries;
		$this->currency = mb_strtolower( $this->currency );
		
		$asks = $this->getAsks();
		$rate = $this->getRate( $asks );
		if( $rate < $this->minimumRate )
			$rate = $this->minimumRate;
		$this->cancelOffers();
		$available = $this->getAvailable();
		if( $available != 0 )
			$this->newOffer( $available,  $rate );
		if( $this->reportFile )
			$this->updateTable();
	}
	
	public function updateTable()
	{
		$this->createReport();
		$date = date( 'Y-m-d', time() );
		$previousDate = date( 'Y-m-d', strtotime( '-1 day', strtotime( $date ) ) );
		$shortDate = date( 'j M', time() );
		$hour = date( 'G', time() );
		$data = $this->getHourData();
		
		$doc = new XML();
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		@$doc->load( file_get_contents( $this->reportFile ), XML::HTML_STR );
		$dayTr = $doc->get( '//tr[@id="d' . $date . '"]');
		if( !$dayTr )
			$this->insertDayRow( $doc, $date, $shortDate, $previousDate );
		$this->insertHourCell( $doc, $date, $hour, $data->percentageLent, $data->rate );
		$dailyProfitUpdated = $this->setPreviousDayProfit( $doc, $previousDate );
		if( $dailyProfitUpdated )
			$this->setTotalProfit( $doc );
		file_put_contents( $this->reportFile, $doc->saveHTML() );
	}
	
	public function setTotalProfit( $doc )
	{
		$totalEl = $doc->get( '//span[@id="total"]' );
		if( !$totalEl )
		{
			echo "WARNING: Total profit element not found\n";
			return false;
		}
		$els = $doc->query( '//td[@class="profit"]' );
		if( !$els ) return false;
		$total = 0;
		$days = 0;
		foreach( $els as $el )
		{
			$profit = preg_replace( '~[^0-9\.]~', '', $el->nodeValue );
			if( $profit )
			{
				$days++;
				$total += $profit;
			}
		}
		$totalEl->nodeValue = number_format( $total, 2 ) . ' ' . mb_strtoupper( $this->currency ) . ' in ' . $days . ' days';
		$dailyAvgEl = $doc->get( '//span[@id="avgDaily"]' );
		if( $dailyAvgEl )
		{
			$dailyAvg = number_format( ( $total / $days ), 2 );
			$dailyAvgEl->nodeValue = $dailyAvg . ' ' . mb_strtoupper( $this->currency );
		}
		$yearEstEl = $doc->get( '//span[@id="estYear"]' );
		if( $yearEstEl )
			$yearEstEl->nodeValue = number_format( ( $dailyAvg * 365 ), 2 ) . ' ' . mb_strtoupper( $this->currency );
		
	}
	
	public function getHourData()
	{
		$opts = $this->createOpts( '/v1/credits' );
		$r = $this->c->get( $this->apiBaseUrl . '/v1/credits', $opts );
		$credits = json_decode( $r['content'] );
		$data = new StdClass();
		$data->rate = 0;
		$data->totalLent = 0;
		foreach( $credits as $c )
		{
			if( $this->currency != mb_strtolower( $c->currency ) ) continue;
			$data->rate += ( ( $c->rate == 0 ) ? $this->frr : $c->rate / 365 ) * ( $c->amount / $this->walletTotal );
			$data->totalLent += $c->amount;
		}
		$data->rate = round( $data->rate, 3 );
		$data->percentageLent = ( $data->totalLent !== 0 ) ? round( ( ( $data->totalLent / $this->walletTotal ) * 100 ) ) : 0;
		return $data;
	}
	
	public function setPreviousDayProfit( $doc, $previousDate )
	{
		$profitEl = $doc->get( '//tr[@id="d' . $previousDate . '"]//td[@class="profit"]' );
		if( $profitEl && !$profitEl->nodeValue )
		{
			$opts = $this->createOpts( '/v1/history', array( 'currency' => $this->currency, 'limit' => 100 ) );
			$r = $this->c->get( $this->apiBaseUrl . '/v1/history', $opts );
			$entries = json_decode( $r['content'] );
			foreach( $entries as $e )
			{
				$entryDate = date( 'Y-m-d', $e->timestamp );
				if( $previousDate == $entryDate && preg_match( '~Margin Funding Payment~i', $e->description ) )
				{
					$profitEl->nodeValue = number_format( $e->amount, 2 ) . ' ' . mb_strtoupper( $this->currency );
					return true;
				}
			}
		}
	}
	
	public function insertHourCell( $doc, $date, $hour, $percentageLent, $rate )
	{
		$hourCell = $doc->get( '//tr[@id="d' . $date . '"]//td[contains( @class, "t' . $hour . '" )]' );
		if( $hourCell )
		{
			if( $hourCell->nodeValue ) return false;
			$hourCell->appendChild( new DOMText( $percentageLent . '%' ) );
			$hourCell->appendChild( new DOMElement( 'br' ) );
			$hourCell->appendChild( new DOMText( $rate ) );
			$colourClass = 'c' . floor( $percentageLent / 10 );
			$hourCell->setAttribute( 'class', 't' . $hour . ' ' . $colourClass );
		}
		else
			echo "WARNING: Could not find cell to insert hour data into\n";
	}
	
	public function insertDayRow( $doc, $date, $shortDate, $previousDate )
	{
		$previousEl = $doc->get( '//tbody//tr' );
		if( $previousEl )
			$dayTr = $doc->insert( $previousEl, 'tr', null, array( 'id' => 'd' . $date ), XML::INSERT_BEFORE );
		else
			$dayTr = $doc->insert( $doc->get( '//tbody' ), 'tr', null, array( 'id' => 'd' . $date ) );
		$doc->insert( $dayTr, 'td', $shortDate );
		for( $i = 0; $i <= 23; $i++ )
			$doc->insert( $dayTr, 'td', null, array( 'class' => 't' . $i ) );
		$doc->insert( $dayTr, 'td', null, array( 'class' => 'profit' ) );
	}
	
	public function createReport()
	{
		if( !file_exists( $this->reportFile ) )
		{
			$tpl = file_get_contents( 'data/tpl.html' );
			if( $tpl )
			{
				$status = file_put_contents( $this->reportFile, $tpl );
				if( !$status )
					die( "ERROR: Could not write report to {$this->reportFile}\n" );
				else
				{
					$doc = new XML();
					$doc->preserveWhiteSpace = false;
					$doc->formatOutput = true;
					@$doc->load( file_get_contents( $this->reportFile ), XML::HTML_STR );
					$currencyEl = $doc->get( '//span[@id="currency"]');
					$currencyEl->nodeValue = mb_strtoupper( $this->currency );
					file_put_contents( $this->reportFile, $doc->saveHTML() );
				}
			}
			else
				die( "ERROR: Unable to load report template\n" );
		}
	}
	
	public function newOffer( $available, $rate )
	{
		$period = $this->calculatePeriod( $rate );
		$params = array( 'currency' => $this->currency, 'amount' => ( string ) $available, 'rate' => ( string ) ( $rate * 365 ), 'period' => ( int ) $period, 'direction' => 'lend' );
		$opts = $this->createOpts( '/v1/offer/new', $params );
		$r = $this->c->get( $this->apiBaseUrl . '/v1/offer/new', $opts );
		$r = json_decode( $r['content'] );
		if( !isset( $r->id ) )
			echo "WARNING: Failed to open funding: $available {$this->currency} at $rate for $period days\n";
	}
	
	public function calculatePeriod( $rate )
	{
		$minRate = min( array_keys( $this->periodSchedule ) );
		$maxRate = max( array_keys( $this->periodSchedule ) );
		if( $rate <= $minRate )
			return $this->periodSchedule[$minRate];
		elseif( $rate >= $maxRate )
			return $this->periodSchedule[$maxRate];
		else
		{
			$floor = floor( $rate * 100 ) / 100;
			return $this->periodSchedule[( string ) $floor];
		}
	}
	
	public function cancelOffers()
	{
		$opts = $this->createOpts( '/v1/offers' );
		$r = $this->c->get( $this->apiBaseUrl . '/v1/offers', $opts );
		if( isset( $r['content'] ) )
		{
			$offers = json_decode( $r['content'] );
			foreach( $offers as $offer )
			{
				if( mb_strtolower( $offer->currency ) != $this->currency ) continue;
				$opts = $this->createOpts( '/v1/offer/cancel', array( 'offer_id' => $offer->id ) );
				$r = $this->c->get( $this->apiBaseUrl . '/v1/offer/cancel', $opts );
				$r = json_decode( $r['content'] );
				if( !isset( $r->id ) )
					echo "WARNING: Failed to close funding id: {$offer->id}\n";
			}
		}
	}
	
	public function createOpts( $url, $parms = array() )
	{
		$data = array( 'request' => $url, 'nonce' => ( string ) number_format( round( microtime( true ) * 100000 ), 0, '.', '' ) );
		if( $parms )
			$data = array_merge( $data, $parms );
		$payload = base64_encode( json_encode( $data ) );
		$signature = hash_hmac( 'sha384', $payload, $this->apiSecret );
		$opts = $this->opts;
		$opts[CURLOPT_HTTPHEADER] = array(
			'X-BFX-APIKEY: ' . $this->apiKey,
			'X-BFX-PAYLOAD: ' . $payload,
			'X-BFX-SIGNATURE: ' . $signature
		);
		return $opts;
	}
	
	public function getRate( $asks )
	{
		$tot = 0;
		$rate = null;
		foreach( $asks as $ask )
		{
			$tot += $ask->amount;
			if( !$rate && ( $tot >= $this->placeAboveLowestAsk ) )
				$rate = $ask->rate / 365;
			if( $ask->frr == 'Yes' )
				$this->frr = ( $ask->rate / 365 );
		}
		if( $rate )
			return $rate;
		else
			die( "ERROR: Downloaded lend book too small to find rate, set higher limit_asks in getAsks function\n" );
	}
	
	public function getAsks()
	{
		$lendbook = new StdClass();
		$tries = $this->tries;
		$t = 0;
		while( !isset( $lendbook->asks ) && $t <= $tries )
		{
			$lendbook = json_decode( file_get_contents( 'https://api.bitfinex.com/v1/lendbook/' . $this->currency . '?limit_asks=3000&limit_bids=0' ) );
			$t++;
		}
		if( $lendbook->asks )
			return $lendbook->asks;
		else
			die( "ERROR: Unable to retrieve the lendbook\n" );
	}
	
	public function getAvailable()
	{
		$opts = $this->createOpts( '/v1/balances' );
		$r = $this->c->get( $this->apiBaseUrl . '/v1/balances', $opts );
		$balances = json_decode( $r['content'] );
		$target = mb_strtolower( $this->currency );
		$matched = false;
		foreach( $balances as $b )
		{
			if( $b->type == 'deposit' && $b->currency == $target )
			{
				$matched = true;
				$this->walletTotal = $b->amount;
				$available = $b->available;
				break;
			}
		}
		if( $matched == true )
			return $available;
		else
			die( "ERROR: Could not find a deposit/margin wallet for {$this->currency}\n" );
	}
}

?>