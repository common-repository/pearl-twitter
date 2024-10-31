<?php
/**
 * Twitter widget to display your latest tweets
 */
if ( ! class_exists( 'Pearl_Twitter_Widget' ) ) {
	class Pearl_Twitter_Widget extends WP_Widget {

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

		/** @var string Twitter's request URL */
		private $request_url;

		/** @var string Request method or HTTP verb */
		private $request_method;

		/**
		 * Sets up the widgets name etc
		 */
		public function __construct() {
			$widget_ops = array(
				'classname'   => 'pearl_twitter_widget',
				'description' => esc_html__( 'Display your recent tweets.', 'pearl-twitter' ),
			);

			parent::__construct( 'pearl_twitter_widget', 'Easy Twitter Widget', $widget_ops );
		}

		public function widget( $args, $instance ) {

			echo $args['before_widget'];

			if ( ! isset( $instance['access_token'] )
			     || ! isset( $instance['access_token_secret'] )
			     || ! isset( $instance['consumer_key'] )
			     || ! isset( $instance['consumer_secret'] )
			     || empty( $instance['access_token'] )
			     || empty( $instance['access_token_secret'] )
			     || empty( $instance['consumer_key'] )
			     || empty( $instance['consumer_secret'] )
			) {
				esc_html_e( 'Make sure you have added twitter authorization parameters to the twitter widget properly.', 'pearl-twitter' );
			} else {

				$this->oauth_access_token        = $instance['access_token'];
				$this->oauth_access_token_secret = $instance['access_token_secret'];
				$this->consumer_key              = $instance['consumer_key'];
				$this->consumer_secret           = $instance['consumer_secret'];

				$getField = '?screen_name=' . $instance['username'];
				$getField .= ( 'no' == $instance['exclude_replies'] ) ? '&exclude_replies=false' : '&exclude_replies=true';
				$this->get_field = $getField;

				if ( ! empty( $instance['title'] ) ) {
					echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
				}

				$count         = empty( $instance['count'] ) ? 3 : $instance['count'];
				$username      = empty( $instance['username'] ) ? 'pearlthemes' : $instance['username'];
				$time          = empty( $instance['time'] ) ? 'no' : $instance['time'];
				$display_image = empty( $instance['display_image'] ) ? 'no' : $instance['display_image'];

				$tweets = $this->tweetbox_get_tweet( $count, $username, $args['widget_id'], $time, $display_image );

				echo $tweets;

			}

			echo $args['after_widget'];


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
			$this->getfield = $string;

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
		 * Build, generate and include the OAuth signature to the OAuth credentials
		 *
		 * @param string $request_url Twitter endpoint to send the request to
		 * @param string $request_method Request HTTP verb eg GET or POST
		 *
		 * @return $this
		 */
		public function build_oauth( $request_url, $request_method ) {

			if ( ! in_array( strtolower( $request_method ), array( 'post', 'get' ) ) ) {
				esc_html_e( 'Request method must be either POST or GET', 'pearl-twitter' );

				return;
			}

			$oauth_credentials = array(
				'oauth_consumer_key'     => $this->consumer_key,
				'oauth_nonce'            => time(),
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_token'            => $this->oauth_access_token,
				'oauth_timestamp'        => time(),
				'oauth_version'          => '1.0'
			);

			if ( ! is_null( $this->get_field ) ) {
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
		 * Generate the authorization HTTP header
		 * @return string
		 */
		public function authorization_header() {
			$header = 'OAuth ';

			$oauth_params = array();
			foreach ( $this->oauth_details as $key => $value ) {
				$oauth_params[] = "$key=\"" . rawurlencode( $value ) . '"';
			}

			$header .= implode( ', ', $oauth_params );

			return $header;
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
				'sslverify' => false
			);


			if ( ! is_null( $this->post_fields ) ) {
				$args['body'] = $this->post_fields;

				$response = wp_remote_post( $this->request_url, $args );

				return wp_remote_retrieve_body( $response );
			} else {

				// add the GET parameter to the Twitter request url or endpoint
				$url      = $this->request_url . $this->get_field;
				$response = wp_remote_get( $url, $args );

				return wp_remote_retrieve_body( $response );

			}

		}

		/**
		 * @param $text // tweet text
		 *
		 * @return mixed|string // add links and username urls to the text
		 */
		function build_tweet_string( $text ) {

			$text = $this->hyperlinks( $text );
			$text = $this->twitter_users( $text );
			$text = $this->encode_tweet( $text );

			return $text;
		}

		/**
		 * Find links and create the hyperlinks
		 */
		function hyperlinks( $text ) {
			$text = preg_replace( '/\b([a-zA-Z]+:\/\/[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i', "<a href=\"$1\" class=\"twitter-link\">$1</a>", $text );
			$text = preg_replace( '/\b(?<!:\/\/)(www\.[\w_.\-]+\.[a-zA-Z]{2,6}[\/\w\-~.?=&%#+$*!]*)\b/i', "<a href=\"http://$1\" class=\"twitter-link\">$1</a>", $text );
			// match name@address
			$text = preg_replace( "/\b([a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]*\@[a-zA-Z][a-zA-Z0-9\_\.\-]*[a-zA-Z]{2,6})\b/i", "<a href=\"mailto://$1\" class=\"twitter-link\">$1</a>", $text );
			//mach #trendingtopics. Props to Michael Voigt
			$text = preg_replace( '/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)#{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/#search?q=$2\" class=\"twitter-link\">#$2</a>$3 ", $text );

			return $text;
		}

		/**
		 * Find twitter usernames and link to them
		 */
		function twitter_users( $text ) {
			$text = preg_replace( '/([\.|\,|\:|\¡|\¿|\>|\{|\(]?)@{1}(\w*)([\.|\,|\:|\!|\?|\>|\}|\)]?)\s/i', "$1<a href=\"http://twitter.com/$2\" class=\"twitter-user\">@$2</a>$3 ", $text );

			return $text;
		}

		/**
		 * Encode single quotes in your tweets
		 */
		function encode_tweet( $text ) {
			$text = mb_convert_encoding( $text, "HTML-ENTITIES", "UTF-8" );

			return $text;
		}

		/**
		 * @param $count // number of tweets to display
		 * @param $username // twitter username
		 * @param $widget_id // current widget id
		 * @param string $time // tweet time display
		 * @param string $avatar // avatar of twitter user
		 *
		 * @return string
		 */
		public function tweetbox_get_tweet( $count, $username, $widget_id, $time = 'yes', $avatar = 'yes' ) {

			$output = $tweets = "";
			$cache  = get_transient( 'pearl' . '_tweetcache_id_' . $username . '_' . $widget_id );

			if ( $cache ) {
				$tweets = get_option( 'pearl' . '_tweetcache_' . $username . '_' . $widget_id );
			} else {

				$url            = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
				$request_method = 'GET';


				$tweets_json = $this
					->build_oauth( $url, $request_method )// build oauth using twitter keys and save to the $oauth_detail method
					->process_request(); // request to the twitter with header information containing $oauth_details and fetch feed

				$tweets_xml = json_decode( $tweets_json );
				$tweets     = array();


				foreach ( $tweets_xml as $tweet ) {
					$tweets[] = array(
						'text'    => $this->build_tweet_string( $tweet->text ),
						'created' => strtotime( $tweet->created_at ),
						'user'    => array(
							'name'        => (string) $tweet->user->name,
							'screen_name' => (string) $tweet->user->screen_name,
							'image'       => (string) $tweet->user->profile_image_url,
							'utc_offset'  => (int) $tweet->user->utc_offset[0],
							'follower'    => (int) $tweet->user->followers_count

						)
					);
				}

				set_transient( 'pearl' . '_tweetcache_id_' . $username . '_' . $widget_id, 'true', 60 * 30 );
				update_option( 'pearl' . '_tweetcache_' . $username . '_' . $widget_id, $tweets );


			}

			if ( isset( $tweets[0] ) ) {
				$time_format = apply_filters( 'pearl_widget_time', get_option( 'date_format' ) . " - " . get_option( 'time_format' ), 'tweetbox' );
				$i           = 0;

				foreach ( $tweets as $message ) {
					$output .= '<li class="tweet">';
					if ( $avatar == "yes" ) {
						$output .= '<div class="tweet-thumb"><a href="https://twitter.com/' . $username . '" title=""><img src="' . $message['user']['image'] . '" alt="" /></a></div>';
					}
					$output .= '<div class="tweet-text avatar_' . $avatar . '">' . $message['text'];
					if ( $time == "yes" ) {
						$output .= '<div class="tweet-time">' . date_i18n( $time_format, $message['created'] + $message['user']['utc_offset'] ) . '</div>';
					}
					$output .= '</div></li>';

					$i ++;
					if ( $i == $count || $i > $count ) {
						break;
					}
				}
			}


			if ( $output != "" ) {
				$filtered_message = "<ul class='tweets clearfix'>$output</ul>";
			} else {
				$filtered_message = '<ul class="tweets"><li>' . esc_html__( 'No public Tweets found!', 'pearl-twitter' ) . "</li></ul>";
			}

			return $filtered_message;
		}


		public function form( $instance ) {

			$instance = wp_parse_args( (array) $instance, array(
				'title'    => esc_html__( 'Latest Tweets', 'pearl-twitter' ),
				'count'    => '3',
				'username' => ''
			) );

			$title           = isset( $instance['title'] ) ? strip_tags( $instance['title'] ) : "";
			$count           = isset( $instance['count'] ) ? strip_tags( $instance['count'] ) : "";
			$username        = isset( $instance['username'] ) ? strip_tags( $instance['username'] ) : "pearlthemes";
			$exclude_replies = isset( $instance['exclude_replies'] ) ? strip_tags( $instance['exclude_replies'] ) : "";
			$time            = isset( $instance['time'] ) ? strip_tags( $instance['time'] ) : "";
			$display_image   = isset( $instance['display_image'] ) ? strip_tags( $instance['display_image'] ) : "";

			// twitter parameters
			$access_token        = isset( $instance['access_token'] ) ? strip_tags( $instance['access_token'] ) : "";
			$access_token_secret = isset( $instance['access_token_secret'] ) ? strip_tags( $instance['access_token_secret'] ) : "";
			$consumer_key        = isset( $instance['consumer_key'] ) ? strip_tags( $instance['consumer_key'] ) : "";
			$consumer_secret     = isset( $instance['consumer_secret'] ) ? strip_tags( $instance['consumer_secret'] ) : "";
			?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php esc_html_e( 'Title:', 'pearl-twitter' ); ?>
					<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>"/>
				</label>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'username' ); ?>"><?php esc_html_e( 'Enter your twitter username:', 'pearl-twitter' ); ?>
					<input class="widefat" id="<?php echo $this->get_field_id( 'username' ); ?>" name="<?php echo $this->get_field_name( 'username' ); ?>" type="text" value="<?php echo esc_attr( $username ); ?>"/>
				</label>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php esc_html_e( 'How many entries do you want to display:', 'pearl-twitter' ); ?> </label>
				<select class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>" name="<?php echo $this->get_field_name( 'count' ); ?>">
					<?php
						$list = "";
						for ( $i = 1; $i <= 20; $i ++ ) {
							$selected = "";
							if ( $count == $i ) {
								$selected = 'selected="selected"';
							}

							$list .= "<option $selected value='$i'>$i</option>";
						}
						$list .= "</select>";
						echo $list;
					?>


			</p>			<p>
				<label for="<?php echo $this->get_field_id( 'exclude_replies' ); ?>"><?php esc_html_e( 'Exclude @replies:', 'pearl-twitter' ); ?> </label>
				<select class="widefat" id="<?php echo $this->get_field_id( 'exclude_replies' ); ?>" name="<?php echo $this->get_field_name( 'exclude_replies' ); ?>">
					<?php
						$list    = "";
						$answers = array( 'yes', 'no' );
						foreach ( $answers as $answer ) {
							$selected = "";
							if ( $answer == $exclude_replies ) {
								$selected = 'selected="selected"';
							}

							$list .= "<option $selected value='$answer'>$answer</option>";
						}
						$list .= "</select>";
						echo $list;
					?>


			</p>			<p>
				<label for="<?php echo $this->get_field_id( 'time' ); ?>"><?php esc_html_e( 'Display time of tweet', 'pearl-twitter' ); ?></label>
				<select class="widefat" id="<?php echo $this->get_field_id( 'time' ); ?>" name="<?php echo $this->get_field_name( 'time' ); ?>">
					<?php
						$list    = "";
						$answers = array( 'yes', 'no' );
						foreach ( $answers as $answer ) {
							$selected = "";
							if ( $answer == $time ) {
								$selected = 'selected="selected"';
							}

							$list .= "<option $selected value='$answer'>$answer</option>";
						}
						$list .= "</select>";
						echo $list;
					?>
			</p>

			<p>
				<label for="<?php echo $this->get_field_id( 'display_image' ); ?>"><?php esc_html_e( 'Display Twitter User Avatar', 'pearl-twitter' ); ?></label>
				<select class="widefat" id="<?php echo $this->get_field_id( 'display_image' ); ?>" name="<?php echo $this->get_field_name( 'display_image' ); ?>">
					<?php
						$list    = "";
						$answers = array( 'yes', 'no' );
						foreach ( $answers as $answer ) {
							$selected = "";
							if ( $answer == $display_image ) {
								$selected = 'selected="selected"';
							}

							$list .= "<option $selected value='$answer'>$answer</option>";
						}
						$list .= "</select>";
						echo $list;
					?>
			</p>
			<hr>			<h4><?php esc_html_e( 'Twitter Parameters', 'pearl-twitter' ); ?></h4>
			<p>
				<label for="<?php echo $this->get_field_id( 'access_token' ); ?>"><?php esc_html_e( 'Access Token:', 'pearl-twitter' ); ?>
					<input class="widefat" id="<?php echo $this->get_field_id( 'access_token' ); ?>" name="<?php echo $this->get_field_name( 'access_token' ); ?>" type="text" value="<?php echo esc_attr( $access_token ); ?>"/>
				</label>
				<small>
					<a target="_blank" href="https://apps.twitter.com/app/new">Click here to create new twitter application</a> to get your Consumer Key, Consumer Secret, Access Token and Access Token Secret.
				</small>
			</p>			<p>
				<label for="<?php echo $this->get_field_id( 'access_token_secret' ); ?>"><?php esc_html_e( 'Access Token Secret:', 'pearl-twitter' ); ?>
					<input class="widefat" id="<?php echo $this->get_field_id( 'access_token_secret' ); ?>" name="<?php echo $this->get_field_name( 'access_token_secret' ); ?>" type="text" value="<?php echo esc_attr( $access_token_secret ); ?>"/>
				</label>
			</p>			<p>
				<label for="<?php echo $this->get_field_id( 'consumer_key' ); ?>"><?php esc_html_e( 'Consumer Key:', 'pearl-twitter' ); ?>
					<input class="widefat" id="<?php echo $this->get_field_id( 'consumer_key' ); ?>" name="<?php echo $this->get_field_name( 'consumer_key' ); ?>" type="text" value="<?php echo esc_attr( $consumer_key ); ?>"/>
				</label>
			</p>			<p>
				<label for="<?php echo $this->get_field_id( 'consumer_secret' ); ?>"><?php esc_html_e( 'Consumer Secret:', 'pearl-twitter' ); ?>
					<input class="widefat" id="<?php echo $this->get_field_id( 'consumer_secret' ); ?>" name="<?php echo $this->get_field_name( 'consumer_secret' ); ?>" type="text" value="<?php echo esc_attr( $consumer_secret ); ?>"/>
				</label>
			</p>
			<?php
		}

		public function update( $new_instance, $old_instance ) {

			$instance = $old_instance;
			foreach ( $new_instance as $key => $value ) {
				$instance[ $key ] = strip_tags( $new_instance[ $key ] );
			}

			delete_transient( 'pearl' . '_tweetcache_id_' . $instance['username'] . '_' . $this->id_base . "-" . $this->number );

			return $instance;
		}
	}
}