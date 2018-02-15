<?php
/**
 * Adds Display_Latest_Tweets_Widget widget.
 */
class Display_Latest_Tweets_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' 	=> 'widget_display_latest_tweets',
			'description' 	=> esc_html__( 'A widget that displays your recent tweets from twitter.', 'display-latest-tweets' )
		);
		parent::__construct(
			'display-latest-tweets',
			esc_html__( 'Latest Tweets', 'display-latest-tweets' ),
			$widget_ops
		);

		add_action( 'save_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_widget_cache' ) );
	}

	public function flush_widget_cache() {
		wp_cache_delete( 'display-latest-tweets' );
	}

    /**
     * Making request to Twitter API
     */
	private function twitter_timeline( $limit, $oauth_access_token, $oauth_access_token_secret, $consumer_key, $consumer_secret ) {
	 
	    /** Set access tokens here - see: https://dev.twitter.com/apps/ */
	    $settings = array(
	        'oauth_access_token'        => $oauth_access_token,
	        'oauth_access_token_secret' => $oauth_access_token_secret,
	        'consumer_key'              => $consumer_key,
	        'consumer_secret'           => $consumer_secret
	    );
	 
	    $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
	    $getfield = '?count=' . $limit;
	    $request_method = 'GET';
	     
	    $twitter_instance = new Twitter_API_WordPress( $settings );
	     
	    $query = $twitter_instance
	        ->set_get_field( $getfield )
	        ->build_oauth( $url, $request_method )
	        ->process_request();
	     
	    $timeline = json_decode($query);
	 
	    return $timeline;
	}

    /**
     * To make the tweet time more user-friendly
     */
	private function tweet_time( $time ) {
	    // Get current timestamp.
	    $now = strtotime( 'now' );
	 
	    // Get timestamp when tweet created.
	    $created = strtotime( $time );
	 
	    // Get difference.
	    $difference = $now - $created;
	 
	    // Calculate different time values.
	    $minute = 60;
	    $hour 	= $minute * 60;
	    $day 	= $hour * 24;
	    $week 	= $day * 7;
	 
	    if ( is_numeric( $difference ) && $difference > 0 ) {
	 
	        // If less than 3 seconds.
	        if ( $difference < 3 ) {
	            return __( 'right now', 'display-latest-tweets' );
	        }
	 
	        // If less than minute.
	        if ( $difference < $minute ) {
	            return floor( $difference ) . ' ' . __( 'seconds ago', 'display-latest-tweets' );;
	        }
	 
	        // If less than 2 minutes.
	        if ( $difference < $minute * 2 ) {
	            return __( 'about 1 minute ago', 'display-latest-tweets' );
	        }
	 
	        // If less than hour.
	        if ( $difference < $hour ) {
	            return floor( $difference / $minute ) . ' ' . __( 'minutes ago', 'display-latest-tweets' );
	        }
	 
	        // If less than 2 hours.
	        if ( $difference < $hour * 2 ) {
	            return __( 'about 1 hour ago', 'display-latest-tweets' );
	        }
	 
	        // If less than day.
	        if ( $difference < $day ) {
	            return floor( $difference / $hour ) . ' ' . __( 'hours ago', 'display-latest-tweets' );
	        }
	 
	        // If more than day, but less than 2 days.
	        if ( $difference > $day && $difference < $day * 2 ) {
	            return __( 'yesterday', 'display-latest-tweets' );;
	        }
	 
	        // If less than year.
	        if ( $difference < $day * 365 ) {
	            return floor( $difference / $day ) . ' ' . __( 'days ago', 'display-latest-tweets' );
	        }
	 
	        // Else return more than a year.
	        return __( 'over a year ago', 'display-latest-tweets' );
	    }
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
	    $title                     = $instance['title'];
	    $limit                     = $instance['update_count'];
	    $oauth_access_token        = $instance['oauth_access_token'];
	    $oauth_access_token_secret = $instance['oauth_access_token_secret'];
	    $consumer_key              = $instance['consumer_key'];
	    $consumer_secret           = $instance['consumer_secret'];
	 
	    echo $args['before_widget'];
	 
	    if ( ! empty( $title ) ) {
	        echo $args['before_title'] . $title . $args['after_title'];
	    }

	    $timelines = wp_cache_get( 'display-latest-tweets' );

	    // retrieve cache contents on success
	    if ( $timelines === false ){
		    // Get the tweets.
		    $timelines = $this->twitter_timeline( $limit, $oauth_access_token, $oauth_access_token_secret, $consumer_key, $consumer_secret );

		    wp_cache_set( 'display-latest-tweets', $timelines );
	    }
	 
	 
	    if ( $timelines ) {
	 
	        // Add links to URL and username mention in tweets.
	        $patterns = array( '@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '/@([A-Za-z0-9_]{1,15})/' );
	        $replace = array( '<a href="$1">$1</a>', '<a href="http://twitter.com/$1">@$1</a>' );
	                
	 		echo '<ul class="tweets">';
	        foreach ( $timelines as $timeline ) {
	            $result = preg_replace( $patterns, $replace, $timeline->text );
	 
	            echo '<li class="tweet">';
	                echo $result;
	                echo '<span class="tweet-time">'.$this->tweet_time( $timeline->created_at ).'</span>';
	            echo '</li>';
	        }
	        echo '</ul>';
	 
	    } else {
	    	if ( current_user_can( 'manage_options' ) ) {
		        esc_html_e( 'Error fetching twitter feeds. Please verify the Twitter settings in the widget.', 'display-latest-tweets' );
	    	}
	    }
	 
	    echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
     	$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Latest Tweets', 'display-latest-tweets' );
     	$update_count = ! empty( $instance['update_count'] ) ? $instance['update_count'] : 5;
     	$oauth_access_token = ! empty( $instance['oauth_access_token'] ) ? $instance['oauth_access_token'] : '';
     	$oauth_access_token_secret = ! empty( $instance['oauth_access_token_secret'] ) ? $instance['oauth_access_token_secret'] : '';
     	$consumer_key = ! empty( $instance['consumer_key'] ) ? $instance['consumer_key'] : '';
     	$consumer_secret = ! empty( $instance['consumer_secret'] ) ? $instance['consumer_secret'] : '';
		?>
	    <p>
	        <label for="<?php echo $this->get_field_id( 'title' ); ?>">
	            <?php esc_attr_e( 'Title: ', 'display-latest-tweets' ); ?>
	        </label>
	        <input
	        	type="text"
	        	class="widefat"
	        	id="<?php echo $this->get_field_id( 'title' ); ?>"
	        	name="<?php echo $this->get_field_name( 'title' ); ?>"
	        	value="<?php echo esc_attr( $title ); ?>" />
	    </p>
	    <p>
	        <label for="<?php echo $this->get_field_id( 'update_count' ); ?>">
	            <?php esc_attr_e( 'Number of Tweets to Display: ', 'display-latest-tweets' ); ?>
	        </label>
	        <input
	        	type="number"
	        	class="widefat"
	        	id="<?php echo $this->get_field_id( 'update_count' ); ?>"
	        	name="<?php echo $this->get_field_name( 'update_count' ); ?>"
	        	value="<?php echo esc_attr( $update_count ); ?>" />
	    </p>
	    <p>
	        <label for="<?php echo $this->get_field_id( 'consumer_key' ); ?>">
	            <?php esc_attr_e( 'Consumer Key: ', 'display-latest-tweets' ); ?>
	        </label>
	        <input
	        	class="widefat"
	        	id="<?php echo $this->get_field_id( 'consumer_key' ); ?>"
	        	name="<?php echo $this->get_field_name( 'consumer_key' ); ?>"
	        	type="text"
	        	value="<?php echo esc_attr( $consumer_key ); ?>" />

	        <small>Don't know your Consumer Key, Consumer Secret, Access Token and Access Token Secret? <a target="_blank" href="https://apps.twitter.com/">Click here</a></small>
	    </p>
	    <p>
	        <label for="<?php echo $this->get_field_id( 'consumer_secret' ); ?>">
	            <?php esc_attr_e( 'Consumer Secret: ', 'display-latest-tweets' ); ?>
	        </label>
	        <input
	        	type="text"
	        	class="widefat"
	        	id="<?php echo $this->get_field_id( 'consumer_secret' ); ?>"
	        	name="<?php echo $this->get_field_name( 'consumer_secret' ); ?>"
	        	value="<?php echo esc_attr( $consumer_secret ); ?>" />
	    </p>
	    <p>
	        <label for="<?php echo $this->get_field_id( 'oauth_access_token' ); ?>">
	            <?php esc_attr_e( 'Access Token: ', 'display-latest-tweets' ); ?>
	        </label>
	        <input
	        	type="text"
	        	class="widefat"
	        	id="<?php echo $this->get_field_id( 'oauth_access_token' ); ?>"
	        	name="<?php echo $this->get_field_name( 'oauth_access_token' ); ?>"
	        	value="<?php echo esc_attr( $oauth_access_token ); ?>" />
	    </p>
	    <p>
	        <label for="<?php echo $this->get_field_id( 'oauth_access_token_secret' ); ?>">
	            <?php esc_attr_e( 'Access Token Secret: ', 'display-latest-tweets' ); ?>
	        </label>
	        <input
	        	type="text"
	        	class="widefat"
	        	id="<?php echo $this->get_field_id( 'oauth_access_token_secret' ); ?>"
	        	name="<?php echo $this->get_field_name( 'oauth_access_token_secret' ); ?>"
	        	value="<?php echo esc_attr( $oauth_access_token_secret ); ?>" />
	    </p>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title'] 				= strip_tags( $new_instance['title'] );
	    $instance['update_count'] 		= intval( $new_instance['update_count'] );
	    $instance['consumer_key'] 		= strip_tags( $new_instance['consumer_key'] );
	    $instance['consumer_secret'] 	= strip_tags( $new_instance['consumer_secret'] );
	    $instance['oauth_access_token'] = strip_tags( $new_instance['oauth_access_token'] );
	    $instance['oauth_access_token_secret'] = strip_tags( $new_instance['oauth_access_token_secret'] );

		return $instance;
	}
}

// register Display_Latest_Tweets_Widget widget
add_action( 'widgets_init', function(){ register_widget( "Display_Latest_Tweets_Widget" ); });
