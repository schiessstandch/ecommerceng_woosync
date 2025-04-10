<?php
/* Copyright (C) 2023       Open-Dsi		<support@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/ecommerceng/class/business/eCommerceClientWordpressApi.class.php
 *      \ingroup    ecommerceng
 *      \brief      This file for managing Client Wordpress API
 */

dol_include_once('/ecommerceng/class/client/eCommerceClientApi.class.php');
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\RequestException;
/**
 * 	Class to manage Client Wordpress API
 */
class eCommerceClientWordpressApi extends eCommerceClientApi
{
	/**
	 * @var string  Authentication type
	 */
	public $authentication_type;
	/**
	 * @var string  Authentication login
	 */
	public $authentication_login;
	/**
	 * @var string  Authentication password
	 */
	public $authentication_password;

	const SERVICE_NAME = 'ECOMMERCENG_WORDPRESS';

	/**
	 *  Connect to the Wordpress
	 *
	 * @param   eCommerceSite   $site   eCommerceSite object
	 * @return	int		                <0 if KO, >0 if OK
	 */
	public function connection($site)
	{
		global $conf, $langs;
		dol_syslog(__METHOD__ . " - wordpress_authentication_type={$site->wordpress_authentication_type}, wordpress_authentication_login={$site->wordpress_authentication_login}, wordpress_authentication_password={$site->wordpress_authentication_password}, wordpress_timeout={$site->wordpress_timeout}", LOG_DEBUG);
		$this->errors = array();

		$this->api_url = rtrim($site->webservice_address, '/');
		$this->api_url_prefix = '/wp-json/wp/v2';

		$this->authentication_type = $site->wordpress_authentication_type;
		$this->authentication_login = $site->wordpress_authentication_login;
		$this->authentication_password = $site->wordpress_authentication_password;
		$timeout = $site->wordpress_timeout > 0 ? $site->wordpress_timeout : 30;

		try {
			$stack = HandlerStack::create();

			// Define the retry middleware
			$retryMiddleware = Middleware::retry(
				function ($retries, $request, $response, $exception) {
					// Limit the number of retries to 5
					if ($retries >= 5) {
						return false;
					}

					// Retry on server errors (5xx HTTP status codes)
					if ($response && $response->getStatusCode() >= 500) {
						dol_syslog('ecommerceClientWordpressApi::connection is retrying on status code '.$response->getStatusCode(), LOG_WARNING);
						return true;
					}

					// Retry on connection exceptions
					if ($exception instanceof RequestException && $exception->getCode() === 0) {
						return true;
					}

					return false;
				},
				function ($retries) {
					// Define a delay function (e.g., exponential backoff)
					return (int) pow(2, $retries) * 1000; // Delay in milliseconds
				}
			);

			// Add the retry middleware to the handler stack
			$stack->push($retryMiddleware);

			$userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
			if (!empty($conf->global->ECOMMERCE_USER_AGENT)) {
				$userAgent = $conf->global->ECOMMERCE_USER_AGENT;
			}
			$options = [
				// Base URI is used with relative requests
				'base_uri' => $this->api_url,
				// You can set any number of default request options.
				'timeout' => $timeout,
				// 'cookies' => true,
				'headers' => array('User-Agent' => $userAgent),
				'handler' => $stack,
			];
			if (!empty($conf->global->ECOMMERCENG_WORDPRESS_NO_VERIFY_SSL)) $options['verify'] = false;

			$this->client = new Client($options);
		} catch (Exception $e) {
			$this->errors[] = $langs->trans('ECommerceErrorConnectAPI', $site->name);
			$this->errors[] = $e->getMessage();
			dol_syslog(__METHOD__ . " Error: " . $this->errorsToString(), LOG_ERR);
			return -1;
		}

		return 1;
	}

	/**
	 *  Load code
	 *
	 * @return	int	            <0 if KO, >0 if OK
	 */
	public function fetchCode()
	{
		global $langs;
		dol_syslog(__METHOD__, LOG_DEBUG);
		$langs->load('ecommerceng@ecommerceng');
		$this->errors = array();

		$api_bearer_token = $this->getTemporaryCode();
		if (is_numeric($api_bearer_token) && $api_bearer_token < 0) {
			return null;
		}

		return (string) $api_bearer_token;
	}

	/**
	 *  Get the temporary code for Wordpress
	 *
	 * @return	string|int		            <0 if KO, Temporary code for Wordpress
	 */
	protected function getTemporaryCode()
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$storage = new DoliStorage($this->db, $conf);

		try {
			// Check if we have temporary code
			$token = $storage->retrieveAccessToken(self::SERVICE_NAME . '_' . $conf->entity);
		} catch (Exception $e) {
			if ('Token not found in db, are you sure you stored it?' != $e->getMessage()) {
				$this->errors[] = $e->getMessage();
				dol_syslog(__METHOD__ . " Error: " . $this->errorsToString(), LOG_ERR);
				return -1;
			} else {
				// Retrieve temporary code from Wordpress
				$token = $this->retrieveTemporaryCode();
				if (!is_object($token)) {
					return -1;
				}
			}
		}

		// Is token expired or will temporary code expire in the next 30 seconds
		$expire = ($token->getEndOfLife() !== -9002 && $token->getEndOfLife() !== -9001 && time() > ($token->getEndOfLife() - 30));

		// Token expired so we refresh it
		if ($expire) {
			// Retrieve temporary code from Wordpress
			$token = $this->retrieveTemporaryCode();
			if (!is_object($token)) {
				return -1;
			}
		}

		return $token->getAccessToken();
	}

	/**
	 *  Retrieve the temporary code from Wordpress
	 *
	 * @return	string|int		            <0 if KO, Temporary code for Wordpress
	 */
	protected function retrieveTemporaryCode()
	{
		global $conf;
		dol_syslog(__METHOD__, LOG_DEBUG);

		$results = $this->_sendToApi(self::METHOD_POST, '/wp-json/api/v1/token', [
			GuzzleHttp\RequestOptions::FORM_PARAMS => [
				'username' => $this->authentication_login,
				'password' => $this->authentication_password,
			]
		], true);
		if (!is_array($results)) {
			return -1;
		}

		$storage = new DoliStorage($this->db, $conf);
		$token = new StdOAuth2Token();

		$token->setAccessToken($results['jwt_token']);
		$token->setLifetime($results['expires_in']);
		$token->setExtraParams($results);

		$storage->storeAccessToken(self::SERVICE_NAME . '_' . $conf->entity, $token);

		return $token;
	}

	/**
	 *  Send to the Api
	 *
	 * @param   string  $method     						Method request
	 * @param   string  $url        						Url request
	 * @param   array   $options    						Options request
	 * @param   bool  	$without_prefix						Without api url prefix
	 * @param   int  	$status_code						Status code returned
	 * @param   int  	$error_info							Error info returned
	 * @return	array                 						null if KO otherwise result data
	 */
	public function sendToApi($method, $url, $options = [], $without_prefix = false, &$status_code = null, &$error_info = null)
	{
		global $conf;
		dol_syslog(__METHOD__ . " method=" . $method . " url=" . $url . " options=" . json_encode($options), LOG_DEBUG);

		if (!empty($this->authentication_type)) {
			if ($this->authentication_type == 'wordpress_application') {
				$options['headers']['Authorization'] = 'Basic ' . base64_encode($this->authentication_login . ':' . $this->authentication_password);
			} else {
				$api_bearer_token = $this->fetchCode();
				if (!isset($api_bearer_token)) {
					return -1;
				}
				$options['headers']['Authorization'] = 'Bearer ' . $api_bearer_token;
			}
		}

		return parent::sendToApi($method, $url, $options, $without_prefix, $status_code, $error_info);
	}
}
