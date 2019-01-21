<?php

if ( ! defined( 'ABSPATH' ) ) {
	die; // If this file is called directly, abort.
}

if ( ! class_exists( 'Twitter_API_WordPress' ) ) {
	/**
	 * Twitter-WordPress-HTTP-Client
	 * A class powered by WordPress API for for consuming Twitter API.
	 */
	class Twitter_API_WordPress {

		/**
		 * Twitter API Endpoint for user timeline
		 */
		const USER_TIMELINE = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

		/**
		 * Consumer key
		 *
		 * @var string
		 */
		private $consumer_key;

		/**
		 * Consumer secret
		 *
		 * @var string
		 */
		private $consumer_secret;

		/**
		 * OAuth access token
		 *
		 * @var string
		 */
		private $access_token;

		/**
		 * OAuth access token secrete
		 *
		 * @var string
		 */
		private $access_token_secret;

		/**
		 * POST parameters
		 *
		 * @var array
		 */
		private $post_fields;

		/**
		 * GET parameters
		 *
		 * @var string
		 */
		private $get_field;

		/**
		 * OAuth credentials
		 *
		 * @var array
		 */
		private $oauth_details;

		/**
		 * Twitter's request URL or endpoint
		 *
		 * @var string
		 */
		private $request_url;

		/**
		 * Request method or HTTP verb
		 *
		 * @var string
		 */
		private $request_method;

		/**
		 * Twitter_API_WordPress constructor.
		 *
		 * @param array $settings
		 */
		public function __construct( $settings = array() ) {
			$this->initiate_settings( $settings );
		}

		/**
		 * Initiate API settings
		 *
		 * @param array $args
		 */
		public function initiate_settings( array $args = array() ) {
			$args = wp_parse_args( $args, array(
				'consumer_key'        => '',
				'consumer_secret'     => '',
				'access_token'        => '',
				'access_token_secret' => '',
			) );
			$this->set_consumer_key( $args['consumer_key'] );
			$this->set_consumer_secret( $args['consumer_secret'] );
			$this->set_access_token( $args['access_token'] );
			$this->set_access_token_secret( $args['access_token_secret'] );
		}

		/**
		 * Check if API setting valid
		 *
		 * @return bool
		 */
		private function is_settings_valid() {
			$key          = $this->get_consumer_key();
			$secret       = $this->get_consumer_secret();
			$token        = $this->get_access_token();
			$token_secret = $this->get_access_token_secret();

			if ( empty( $key ) || empty( $secret ) || empty( $token ) || empty( $token_secret ) ) {
				return false;
			}

			return true;
		}

		/**
		 * Get user timeline
		 *
		 * @param int $count
		 *
		 * @return array|\WP_Error
		 */
		public function get_user_timeline( $count = 5 ) {
			if ( ! $this->is_settings_valid() ) {
				return new \WP_Error( 'twitter_incomplete_parameters', 'Make sure you are passing in the correct parameters' );
			}

			$timeline = $this
				->set_request_method( 'GET' )
				->set_get_field( array( 'count' => intval( $count ) ) )
				->build_oauth( self::USER_TIMELINE )
				->process_request();

			return json_decode( $timeline );
		}


		/**
		 * Store the POST parameters
		 *
		 * @param array $array array of POST parameters
		 *
		 * @return $this
		 */
		public function set_post_fields( array $array ) {
			$this->post_fields = $array;

			return $this;
		}


		/**
		 * Store the GET parameters
		 *
		 * @param $string
		 *
		 * @return $this
		 */
		public function set_get_field( $string ) {
			$this->get_field = $string;

			return $this;
		}


		/**
		 * Build, generate and include the OAuth signature to the OAuth credentials
		 *
		 * @param string $request_url Twitter endpoint to send the request to
		 *
		 * @return Twitter_API_WordPress|\WP_Error
		 */
		public function build_oauth( $request_url ) {
			$request_method = $this->get_request_method();

			$oauth_credentials = array(
				'oauth_consumer_key'     => $this->get_consumer_key(),
				'oauth_nonce'            => time(),
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_token'            => $this->access_token,
				'oauth_timestamp'        => time(),
				'oauth_version'          => '1.0'
			);

			if ( $this->is_get_method() ) {
				if ( is_string( $this->get_get_field() ) ) {
					// remove question mark(?) from the query string
					$get_fields = str_replace( '?', '', explode( '&', $this->get_get_field() ) );
					$params     = array();
					foreach ( $get_fields as $field ) {
						// split and add the GET key-value pair to the post array.
						// GET query are always added to the signature base string
						$split = explode( '=', $field );

						$params[ $split[0] ] = $split[1];
					}

					foreach ( $params as $key => $value ) {
						$oauth_credentials[ $key ] = $value;
					}
				}

				if ( is_array( $this->get_get_field() ) ) {
					foreach ( $this->get_get_field() as $key => $value ) {
						$oauth_credentials[ $key ] = $value;
					}
				}
			}

			// convert the oauth credentials (including the GET QUERY if it is used) array to query string.
			$signature = $this->_build_signature_base_string( $request_url, $request_method, $oauth_credentials );

			$oauth_credentials['oauth_signature'] = $this->_generate_oauth_signature( $signature );

			// save the request url for use by WordPress HTTP API
			$this->request_url = $request_url;

			// save the OAuth Details
			$this->oauth_details = $oauth_credentials;

			return $this;
		}


		/**
		 * Create a signature base string from list of arguments
		 *
		 * @param string $request_url request url or endpoint
		 * @param string $method HTTP verb
		 * @param array $oauth_params Twitter's OAuth parameters
		 *
		 * @return string
		 */
		private function _build_signature_base_string( $request_url, $method, $oauth_params ) {
			// save the parameters as key value pair bounded together with '&'
			$string_params = array();

			ksort( $oauth_params );

			foreach ( $oauth_params as $key => $value ) {
				// convert oauth parameters to key-value pair
				$string_params[] = "$key=$value";
			}

			return "$method&" . rawurlencode( $request_url ) . '&' . rawurlencode( implode( '&', $string_params ) );
		}


		private function _generate_oauth_signature( $data ) {

			// encode consumer and token secret keys and subsequently combine them using & to a query component
			$hash_hmac_key = rawurlencode( $this->consumer_secret ) . '&' . rawurlencode( $this->access_token_secret );

			$oauth_signature = base64_encode( hash_hmac( 'sha1', $data, $hash_hmac_key, true ) );

			return $oauth_signature;
		}


		/**
		 * Process and return the JSON result.
		 *
		 * @return string
		 */
		public function process_request() {
			$header = $this->authorization_header();

			$args = array(
				'headers'   => array( 'Authorization' => $header ),
				'timeout'   => 45,
				'sslverify' => $this->is_ssl()
			);

			// If current request method is POST
			if ( $this->is_post_method() ) {
				$args['body'] = $this->post_fields;

				$response = wp_remote_post( $this->request_url, $args );

				return wp_remote_retrieve_body( $response );
			}


			// add the GET parameter to the Twitter request url or endpoint
			if ( is_array( $this->get_get_field() ) ) {
				$url = add_query_arg( $this->get_get_field(), $this->request_url );
			} else {
				$url = $this->request_url . $this->get_get_field();
			}

			$response = wp_remote_get( $url, $args );

			return wp_remote_retrieve_body( $response );
		}


		/**
		 * Build authorization header for HTTP/HTTPS request
		 *
		 * @return string
		 */
		private function authorization_header() {
			$header = 'OAuth ';

			$oauth_params = array();
			foreach ( $this->oauth_details as $key => $value ) {
				$oauth_params[] = "$key=\"" . rawurlencode( $value ) . '"';
			}

			$header .= implode( ', ', $oauth_params );

			return $header;
		}

		/**
		 * Get current HTTP request method
		 *
		 * @return string
		 */
		public function get_request_method() {
			return $this->request_method;
		}

		/**
		 * Set HTTP Request Method for current request
		 *
		 * @param string $request_method
		 *
		 * @return Twitter_API_WordPress|WP_Error
		 */
		public function set_request_method( $request_method ) {
			if ( ! in_array( strtolower( $request_method ), array( 'post', 'get' ) ) ) {
				return new \WP_Error( 'invalid_request_method', 'Request method must be either POST or GET' );
			}

			$this->request_method = strtoupper( $request_method );

			return $this;
		}

		/**
		 * Check if current request method is GET
		 *
		 * @return bool
		 */
		private function is_get_method() {
			return ( 'GET' == $this->get_request_method() );
		}

		/**
		 * Check if current request method is POST
		 *
		 * @return bool
		 */
		private function is_post_method() {
			return ( 'POST' == $this->get_request_method() );
		}

		/**
		 * Determines if SSL is used.
		 *
		 * @return bool True if SSL, otherwise false.
		 */
		private function is_ssl() {
			if ( isset( $_SERVER['HTTPS'] ) ) {
				if ( 'on' == strtolower( $_SERVER['HTTPS'] ) ) {
					return true;
				}

				if ( '1' == $_SERVER['HTTPS'] ) {
					return true;
				}
			}

			if ( isset( $_SERVER['SERVER_PORT'] ) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
				return true;
			}

			if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ) {
				return true;
			}

			return false;
		}

		/**
		 * Get GET field
		 *
		 * @return string|array
		 */
		public function get_get_field() {
			return $this->get_field;
		}

		/**
		 * Get consumer key
		 *
		 * @return string
		 */
		public function get_consumer_key() {
			return $this->consumer_key;
		}

		/**
		 * Set consumer key
		 *
		 * @param string $consumer_key
		 *
		 * @return self
		 */
		public function set_consumer_key( $consumer_key ) {
			$this->consumer_key = $consumer_key;

			return $this;
		}

		/**
		 * Get consumer secret
		 *
		 * @return string
		 */
		public function get_consumer_secret() {
			return $this->consumer_secret;
		}

		/**
		 * Set consumer key
		 *
		 * @param string $consumer_secret
		 *
		 * @return self
		 */
		public function set_consumer_secret( $consumer_secret ) {
			$this->consumer_secret = $consumer_secret;

			return $this;
		}

		/**
		 * Get access token
		 *
		 * @return string
		 */
		public function get_access_token() {
			return $this->access_token;
		}

		/**
		 * Set access token
		 *
		 * @param string $access_token
		 *
		 * @return self
		 */
		public function set_access_token( $access_token ) {
			$this->access_token = $access_token;

			return $this;
		}

		/**
		 * Get access token secret
		 *
		 * @return string
		 */
		public function get_access_token_secret() {
			return $this->access_token_secret;
		}

		/**
		 * Set access token secret
		 *
		 * @param string $access_token_secret
		 *
		 * @return self
		 */
		public function set_access_token_secret( $access_token_secret ) {
			$this->access_token_secret = $access_token_secret;

			return $this;
		}
	}
}

