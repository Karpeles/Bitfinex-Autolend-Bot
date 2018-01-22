<?php

/**
* CURL Request class
* CURL shortcuts to request URLIs
*/
class CURLRequest extends CURL
{
	
	public $opts = array();
	public $threads = 25;
	public $verbose = true;
	
	/**
	* Request a URL
	* @param $url string, URL to request
	* @param $ad_opts array, cURL options to use for the request
	* @param $post array, associative array of post data
	* @return array, array containing result of the request and request info 
	*/
	public function get( $url, array $ad_opts = array(), array $post = array() )
	{
		$opts = $this->opts;
		if( $ad_opts )
		{
			foreach( $ad_opts as $opt => $val )
				$opts[$opt] = $val;
		}
		if( $post )
		{
			$opts[CURLOPT_POST] = true;
			$opts[CURLOPT_POSTFIELDS] = http_build_query( $post );
		}
		
		$this->addSession( $url, $opts );
		$content = $this->exec();
		$info = $this->info();
		$this->clear();
		
		return array( 'content' => $content, 'info' => $info );
	}
	
	/**
	* Request an array of URLs using threads
	* @param $urls array, an array of URLs to request
	* @param $opts array, cURL options to use for the requests
	* @param $threads int, number of threads to use, defaults to 25
	* @return array, array containing result of the request and request info 
	*/
	public function getThreaded( array $urls, array $opts = array(), $threads = null )
	{
		$stack_no = 1;
		$result = array();
		
		$this->threads = ( $threads !== null ) ? $threads : $this->threads;
		$stacks = $this->buildStacks( $urls );
		if( $stacks )
		{
			foreach( $stacks as $stack )
			{
				$no = 0;
				
				foreach( $stack as $url )
					$this->addSession( $url, $opts );
				
				$content = $this->exec();
				$content = ( is_array( $content ) ) ? $content : array( $content );
				$info = $this->info();
				$this->clear();
				
				foreach( $stack as $url_id => $url )
				{
					$result[$url_id] = array( 'url' => $url, 'content' => $content[$no], 'info' => $info[$no] );
					$no++;
				}
				
				if( $this->verbose )
					echo "Downloaded stack: $stack_no of " . count( $stacks ) . "\n";
				$stack_no++;
			}
		}
		
		return $result;
	}
	
	/**
	* Build requst stacks
	* @param $urls array, an array of URLs to create stacks for
	* @return array, URL organised into stacks
	*/
	public function buildStacks( array $urls )
	{
		$stacks = array();
		
		$i = 0;
		$no = 1;
		foreach( $urls as $key => $url )
		{
			$stacks[$i][$key] = preg_replace( '/&amp;/', '&', $url );
			if( !preg_match( '/^[a-z]{3,}:\/\//i', $url ) )
				trigger_error( "$url does not use a valid protocol", E_USER_WARNING );
			if( $no % $this->threads == 0 )
				$i++;
			$no++;
		}
		
		return $stacks;
	}
	
}

?>