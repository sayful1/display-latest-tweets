<?php
/**
 * Twitter-WordPress-HTTP-Client
 * A class powered by WordPress API for for consuming Twitter API.
 */
if ( ! class_exists( 'Twitter_API_WordPress' ) ) {

	class Twitter_API_WordPress {

		/** @var string OAuth access token */
		private $oauth_access_token;

		/** @var string OAuth access token secrete */
		private $oauth_access_token_secret;

		/** @var string Consumer key */
		private $consumer_key;

		/** @var string consumer secret */
		private $consumer_secret;

		/** @var array POST parameters */
		private $post_fields;

		/** @var string GET parameters */
		private $get_field;

		/** @var array OAuth credentials */
		private $oauth_details;

		/** @var string Twitter's request URL or endpoint */
		private $request_url;

		/** @var string Request method or HTTP verb */
		private $request_method;

		/** Class constructor */
		public function __construct( $settings ) {

			if ( ! isset( $settings['oauth_access_token'] )
			     || ! isset( $settings['oauth_access_token_secret'] )
			     || ! isset( $settings['consumer_key'] )
			     || ! isset( $settings['consumer_secret'] )
			) {
				return new WP_Error( 'twitter_param_incomplete', 'Make sure you are passing in the correct parameters' );
			}

			$this->oauth_access_token        = $settings['oauth_access_token'];
			$this->oauth_access_token_secret = $settings['oauth_access_token_secret'];
			$this->consumer_key              = $settings['consumer_key'];
			$this->consumer_secret           = $settings['consumer_secret'];
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
		 * @param string $request_method Request HTTP verb eg GET or POST
		 *
		 * @return Twitter_API_WordPress|WP_Error
		 */
		public function build_oauth( $request_url, $request_method ) {
			if ( ! in_array( strtolower( $request_method ), array( 'post', 'get' ) ) ) {
				return new WP_Error( 'invalid_request', 'Request method must be either POST or GET' );
			}

			$oauth_credentials = array(
				'oauth_consumer_key'     => $this->consumer_key,
				'oauth_nonce'            => time(),
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_token'            => $this->oauth_access_token,
				'oauth_timestamp'        => time(),
				'oauth_version'          => '1.0'
			);

			if ( $this->is_get_method() ) {
				// remove question mark(?) from the query string
				$get_fields = str_replace( '?', '', explode( '&', $this->get_field ) );

				foreach ( $get_fields as $field ) {
					// split and add the GET key-value pair to the post array.
					// GET query are always added to the signature base string
					$split                          = explode( '=', $field );
					$oauth_credentials[ $split[0] ] = $split[1];
				}
			}

			// convert the oauth credentials (including the GET QUERY if it is used) array to query string.
			$signature = $this->_build_signature_base_string( $request_url, $request_method, $oauth_credentials );

			$oauth_credentials['oauth_signature'] = $this->_generate_oauth_signature( $signature );

			// save the request url for use by WordPress HTTP API
			$this->request_url = $request_url;

			// save the OAuth Details
			$this->oauth_details = $oauth_credentials;

			$this->request_method = $request_method;

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
			$hash_hmac_key = rawurlencode( $this->consumer_secret ) . '&' . rawurlencode( $this->oauth_access_token_secret );

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
			$url = $this->request_url . $this->get_field;

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
		public function getRequestMethod() {
			return strtolower( $this->request_method );
		}

		/**
		 * Set HTTP Request Method for current request
		 *
		 * @param string $request_method
		 *
		 * @return Twitter_API_WordPress|WP_Error
		 */
		public function setRequestMethod( $request_method ) {
			if ( ! in_array( strtolower( $request_method ), array( 'post', 'get' ) ) ) {
				return new WP_Error( 'invalid_request_method', 'Request method must be either POST or GET' );
			}

			$this->request_method = $request_method;

			return $this;
		}

		/**
		 * Check if current request method is GET
		 *
		 * @return bool
		 */
		private function is_get_method() {
			return ( 'get' === $this->getRequestMethod() );
		}

		/**
		 * Check if current request method is POST
		 *
		 * @return bool
		 */
		private function is_post_method() {
			return ( 'post' === $this->getRequestMethod() );
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
	}
}

