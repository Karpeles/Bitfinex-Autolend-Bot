<?php

/**
* Wrapper for DOM and XPath Classes
*/
class XML
{
	
	const XML_FILE			= 1;
	const XML_STR			= 2;
	const HTML_FILE			= 4;
	const HTML_STR			= 8;
	const INSERT_BEFORE		= 16;
	const INSERT_AFTER		= 32;
	const CDATA				= 64;
	
	public $path			= './';			/**< Base directory */
	public $useXpath		= true;			/**< XPath is initialised when true */
	public $doc				= null;			/**< DOMDocument instance */
	public $xpath			= null;			/**< XPath instance */
	
	# Magic methods
	
	function __construct(){}
	
	/**
	* Handles non-existant methods
	* @param $method string, non-existant method name
	* @param $arguments array, associative array of arguements
	* @return mixed
	*/
	function __call( $method, $arguments )
	{
		if( method_exists( $this->doc, $method ) )
			return call_user_func_array( array( $this->doc, $method ), $arguments );
		else
			trigger_error( "Method: $method does not exist", E_USER_ERROR );
	}
	
	/**
	* Handles getting non-existant variables
	* @param $name string, non-existant variable name
	* @return mixed
	*/
	function __get( $name )
	{
		if( isset( $doc->$name ) )
			return $doc->$name;
		else
			return null;
	}
	
	# DOMDocument methods
	
	/**
	* Creates a DOMDocument
	* @param $encoding string, encoding of the XML document
	* @param $standalone string, standalone type
	* @param $namespaceURI string, namespace URI
	* @param $qualifiedName string, node name of document element
	* @param $publicId string, publicId for DTD
	* @param $systemId string, systemId for DTD.
	* @access public
	*/
	public function createDocument( $encoding, $standalone = false, $namespaceURI = false, $qualifiedName = false, $publicId = false, $systemId = false )
	{
		$implementation = new DOMImplementation;
		$standalone = ( $standalone == false  ) ? false : true;
		if( ( $qualifiedName ) && ( $publicId | $systemId ) )
		{
			$dtd = $implementation->createDocumentType( $qualifiedName, $publicId, $systemId);
			$this->doc = $implementation->createDocument( $namespaceURI, $qualifiedName, $dtd );
		}
		else
			$this->doc = $implementation->createDocument( $namespaceURI, $qualifiedName );
		$this->doc->encoding = $encoding;
		$this->doc->standalone = true;
		$this->doc->formatOutput = true;
		$this->doc->preserveWhiteSpace = false;
		$this->setupObjects();
	}
	
	/**
	* Create a DOMDocument from input
	* @param $doc string, file or string to load
	* @param $type int, input type
	* @access public
	*/
	public function load( $doc, $type = 1 )
	{
		$this->doc = new DOMDocument();
		$this->doc->formatOutput = true;
		$this->doc->preserveWhiteSpace = false;
		
		switch( $type )
		{
			case 1:
				$this->doc->load( $this->path . $doc );
			break;
			case 2:
				$this->doc->loadXML( $doc );
			break;
			case 4:
				$this->doc->loadHTMLFile( $this->path . $doc );
			break;
			case 8:
				$this->doc->loadHTML( $doc );
			break;
			default:
				trigger_error( '2nd argument must be a valid document type', E_USER_ERROR );
			break;
		}
		
		$this->setupObjects();
	}
	
	/**
	* Sets up DOM objects
	* @access private
	*/
	private function setupObjects()
	{
		$this->doc->resolveExternals = true;
		$this->doc->substituteEntities = true;
		
		if( $this->useXpath === true )
			$this->xpath = new DOMXpath( $this->doc );
	}
	
	# DOMNode methods
	
	/**
	* Creates a DOMNode
	* @param $name string, name of the node
	* @param $value string, content of the node
	* @param $attributes array, associative array of attributes
	* @param $flags int, options
	* @return object, DOMNode
	* @access public
	*/
	public function create( $name, $value = false, $attributes = array(), $flags = 0 )
	{
		try
		{
			$node = $this->doc->createElement( $name );
		}
		catch( Exception $e )
		{
			trigger_error( "Unable to create a &lt;$name&gt; element", E_USER_ERROR );
		}
		
		if( $value != false || $value != null || $value != '' )
		{
			if( ( $flags & XML::CDATA ) > 0 )
			{
				$txt = $this->doc->createCDATASection( $value );
				$node->appendChild( $txt );
			}
			else
			{
				$txt = $this->doc->createTextNode( $value );
				$node->appendChild( $txt );
			}
		}
		$node = $this->setAttributes( $node, $attributes );
		
		return $node;
	}

	/**
	* Creates a DOMNode and inserts it into a DOMElement
	* @param $parent DOMNode, node to append to
	* @param $name string, name of the node.
	* @param $value string, content of the node
	* @param $attributes array, associative array of attributes
	* @param $flags int, options
	* @return object, DOMNode
	* @access public
	*/
	public function insert( $parent, $name, $value = null, $attributes = array(), $flags = 0 )
	{
		if( !is_object( $parent ) )
			trigger_error( "Cannot insert a &lt;$name&gt; element to a non-existent parent", E_USER_ERROR );
		
		$node = $this->create( $name, $value, $attributes, $flags );
		if( ( ( $flags & XML::INSERT_BEFORE ) > 0 ) )
				$parent->parentNode->insertBefore( $node, $parent );
		elseif( ( ( $flags & XML::INSERT_AFTER ) > 0 ) )
			$this->insertAfter( $parent, $node );
		else
				$parent->appendChild( $node );
		
		return $node;
	}
	
	/**
	* Appends an XInclude element to a DOMNode
	* @param $parent DOMNode, node to append to
	* @param $file string, XML file to include
	* @param $autoparse bool, substitutes XIncludes elements in a DOMDocument when true 
	* @access public
	*/
	public function appendFile( $parent, $file, $auto = false )
	{
		if( is_object( $parent ) )
		{
			$xi = $this->doc->createElementNS( 'http://www.w3.org/2001/XInclude', 'xi:include' );
			$xi->setAttribute( 'href', $file );
			$parent->appendChild( $xi );
			if( $auto )
				$this->doc->xinclude();
			return $xi;
		}
		else
			trigger_error( 'Cannot append an XInclude element to a non-existent element', E_USER_ERROR );
	}
	
	/**
	* Appends node after a reference node
	* @param $parent DOMNode, node to append after
	* @param $node DOMNode, node to insert
	* @access public
	*/
	public function insertAfter( $parent, $node )
	{
		if( is_object( $parent ) )
		{
			if( $parent->nextSibling )
				return $parent->parentNode->insertBefore( $node, $parent->nextSibling );
			else
				return $parent->parentNode->appendChild( $node );
		}
		else
			trigger_error( "Cannot insert after a non-existent parent", E_USER_ERROR );
	}
	
	/**
	* Removes a DOMNode from the DOMDocument
	* @param $node DOMNode, node to remove from the DOMDocument
	* @access public
	*/
	public function removeNode( $node )
	{
		if( is_object( $node ) )
		{
			$parent = $node->parentNode;
			if( $parent )
				$parent->removeChild( $node );
		}
		else
			trigger_error( 'Trying to remove non-existent node', E_USER_WARNING );
	}
	
	/**
	* Adds attributes to a DOMNode
	* @param $el object, node to apply attributes.
	* @param $attributes array, associative array of attributes.
	* @return object, DOMNode.
	* @access public
	*/
	public function setAttributes( $node, $attributes )
	{
		if( is_object( $node ) )
		{
			foreach( $attributes as $name => $value )
				$node->setAttributeNode( new DOMAttr( $name, $value ) );
			return $node;
		}
		else
			trigger_error( 'Trying to add attributes to a non-existent node', E_USER_WARNING );
	}
	
	/**
	* Appends processing instruction to the DOMDocument
	* @param $target string, the target of the processing instruction
	* @param $data string, the content of the processing instruction
	* @return object, DOMNode
	* @access public
	*/
	public function appendProcessingInstruction( $target, $data )
	{
		$pi = $this->doc->createProcessingInstruction( $target, $data );
		$node = $this->doc->insertBefore( $pi, $this->doc->documentElement );
		return $node;
	}
	
	# XPath methods
	
	/**
	* Executes an XPath query
	* @param $query string, XPath query
	* @param $contextnode object, DOMNode to query within
	* @return object, DOMNodelist
	* @access public
	*/
	public function query( $query, $contextnode = false )
	{
		if( $this->useXpath == true )
			return ( !is_object( $contextnode ) ) ? $this->xpath->query( $query ) : $this->xpath->query( $query, $contextnode );
		else
			trigger_error( 'This document does not have an instance of XPath', E_USER_ERROR );
	}
	
	/**
	* Executes an XPath query and returns the first node
	* @param $query string, name of node
	* @return object, DOMNode
	* @access public
	*/
	public function get( $query, $contextnode = false )
	{
		if( $this->useXpath === true )
		{
			$obj = ( !is_object( $contextnode ) ) ? $this->xpath->query( $query ) : $this->xpath->query( $query, $contextnode );
			
			if( $obj->length > 0 )
				return $obj->item( 0 );
			else
				return false;
		}
		else
			trigger_error( 'This document does not have an instance of XPath', E_USER_ERROR );
	}
	
	# Arrary methods
	
	/**
	* Converts an associative array into XML nodes with values recursively
	* @param $el object DOMNode, element to append to
	* @param $arr array, array to convert
	* @param $key_replace string, text replacement for numeric keys
	* @param $cdata bool, text content will be created in a CDATA section when true
	* @access public
	*/
	public function arrayToXMLVal( DOMNode $el, array $arr, $key_replace = 'item', $cdata = false )
	{
		$cdata = ( $cdata == true ) ? XML::CDATA : 0;
		
		foreach( $arr as $key => $val )
		{
			if( is_int( $key ) )
			{
				$int = $key;
				$key = $key_replace;
			}
			else
				$int = null;
			if( is_array( $val ) )
			{
				$node = $this->insert( $el, $key );
				$this->arrayToXMLVal( $node, $val, $key_replace, $cdata  );
			}
			else
			{
				$node = $this->insert( $el, $key, $val, array(), $cdata );
			}
			if( $int !== null )
				$node->setAttribute( 'key', $int );
		}
	}
	
	/**
	* Converts an associative array into XML nodes with attributes recursively
	* @param $el object DOMNode, element to append to
	* @param $arr array, array to convert
	* @param $content_key, string, key to create as text content
	* @param $key_replace string, text replacement for numeric keys
	* @param $cdata bool, text content will be created in a CDATA section when true
	* @param $key_attr string, when string, numeric keys will be set as an attribute value with this name
	* @access public
	*/
	public function arrayToXMLAttr( DOMNode $el, array $arr, $content_key = false, $key_replace = 'item', $cdata = false, $key_attr = false )
	{
		foreach( $arr as $key => $val )
		{
			$initial_key = $key;
			$key = ( is_int( $key ) ) ? $key_replace : $key;
			if( is_array( $val ) )
			{
				$parent = $this->insert( $el, $key );
				if( is_int( $initial_key ) && $key_attr )
					$parent->setAttribute( $key_attr, $initial_key );
				foreach( $val as $akey => $attr )
				{
					if( !is_array( $attr ) && ( $attr != null ) )
					{
						if( $akey == $content_key )
						{
							if( $cdata == true )
								$txt = $this->doc->createCDATASection( $attr );
							else
								$txt = $this->doc->createTextNode( $attr );
								
							$parent->appendChild( $txt );
						}
						else
							$parent->setAttribute( $akey, $attr );
					}
					else
						$this->arrayToXMLAttr( $parent, array( $akey => $attr ), $content_key, $key_replace, $cdata, $key_attr );
				}
			}
			elseif( $val )
			{
				if( $key == $content_key )
				{
					if( $cdata == true )
						$txt = $this->doc->createCDATASection( $val );
					else
						$txt = $this->doc->createTextNode( $val );
					
					$el->appendChild( $txt );
				}
				else
					$el->setAttribute( $key, $val );
			}
		}
	}
	
}

?>