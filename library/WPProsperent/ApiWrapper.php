<?php
/**
 * @package WordPress
 * @subpackage WPProsperent
 *
 * Copyright (c) 2010 WPProsperent.com
 *
 * This file is part of WP Prosperent Plugin
 */

/**
 * Prosperent API wrapper class
 */
class WPProsperent_ApiWrapper extends Prosperent_Api
{
	/**
	 * Determines if Prosperent service is available
	 * @var boolean
	 */
	protected static $_prosperentServiceAvailable = true;
	
	/**
	 * Constructor
	 *
	 * @param array $properties
	 * @return void
	 */
	public function __construct($properties = array())
	{
		$extraProperties = array(
			'userAgent'  => $_SERVER['HTTP_USER_AGENT'],
			'visitor_ip' => $_SERVER['REMOTE_ADDR'],
			'sid'        => $_SERVER['HTTP_HOST']
		);
		
		$properties = array_merge($extraProperties, $properties);
		parent::__construct($properties);
		
		$this->set_exceptionHandler('WPProsperent_Exception');
	}
	
	/**
	 * Performs API key validation
	 *
	 * @param string $apiKey The Prosperent API key
	 * @return mixed
	 */
	public static function checkApiKey($apiKey)
	{
		$url = self::$api_url . 'search?api_key=' . urlencode($apiKey) . '&query=apple&visitor_ip=127.0.0.1&debugMode=true';
		$response = wp_remote_fopen($url);
		
		if ($response)
		{
			$json = json_decode($response);
			
			if (!isset($json->errors))
			{
				$result = 'Unable to decode JSON response.';
			}
			elseif (empty($json->errors))
			{
				$result = true;
			}
			else
			{
				$result = $json->errors[0]->msg;
			}
		}
		else
		{
			$result = 'Unable to login to Prosperent server.';
		}
		
		return $result;
	}
	
	/**
	 * Searches API and returns the parsed data from JSON response
	 *
	 * @param string $query
	 * @return array
	 */
	public function fetchData($query)
	{
		$this->set_query($query);
		
		// Fetch the data if Prosperent service is available
		$response = null;
		$result = null;
		
		try
		{
			if (self::$_prosperentServiceAvailable)
			{
				$response = $this->fetch();
				
				if (!$this->hasErrors() && $this->getData())
				{
					$result = array (
						'data' => $this->getData(),
						'currentPage' => $response['page'],
						'totalRecords' => $response['totalRecords']
					);
				}
			}
		}
		catch (Exception $e)
		{
			if ($this->get_debugMode())
			{
				echo '<pre>Exception Code: ', $e->getCode(), '<br/>Details: ', $e->__toString(), '</pre>';
			}
					
			/*
			 * Error code 28 means:
			 * Operation timeout. The specified time-out period was reached according to the conditions.
			 */
			if ($e->getCode() == 28)
			{
				self::$_prosperentServiceAvailable = false;
			}
		}

		// Debug info
		if ($this->get_debugMode())
		{
			echo '<pre>',
				(!self::$_prosperentServiceAvailable ? '<span style="color:red; font-weight:bold">Prosperent service is unavailable!</span><br/>' : ''),
				'URL: ', $this->getUrl($this->getProperties()), '<br/>',
				'Query: ', var_export($query), '<br/>',
				'API Response: ', var_export($response, true),
			'</pre>';
		}
		
		return $result;
	}
	
	/**
	 * Searches API using the search query from the SERP referrer
	 *
	 * @param string $additionalQuery
	 * @return array
	 */
	public function fetchDataFromSerp($additionalQuery = '')
	{
		$data = null;
		
		if (!empty($_SERVER['HTTP_REFERER']))
		{
			// Try to extract the search query
			$query = self::getQueryFromReferrer($_SERVER['HTTP_REFERER']);
			
			if (strlen($query))
			{
				if (strlen($additionalQuery))
				{
					$query .= ' ' . $additionalQuery;
				}
				
				$data = $this->fetchData($query);
			}
		}
		
		return $data;
	}
}