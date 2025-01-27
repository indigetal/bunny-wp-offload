<?php
/**
 * Override Tutor default & integrate BunnyNet
 *
 * @package TutorLMSBunnyNetIntegration\Integration
 * @since v1.0.0
 */

namespace Tutor\BunnyNetIntegration\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add action & filter to override Tutor default
 * & incorporate BunnyNet
 *
 * @version  1.0.0
 * @package  TutorLMSBunnyNetIntegration\Integration
 * @category Integration
 * @author   Themeum <support@themeum.com>
 */
class BunnyNet {

	/**
	 * Register action & filter hooks
	 *
	 * @since v1.0.0
	 */
	public function __construct() {
		add_filter( 'tutor_preferred_video_sources', __CLASS__ . '::filter_preferred_sources' );
		add_filter( 'tutor_single_lesson_video', __CLASS__ . '::filter_lesson_video', 10, 3 );
		add_filter( 'tutor_course/single/video', __CLASS__ . '::filter_course_video' );
		add_action( 'tutor_after_video_meta_box_item', __CLASS__ . '::meta_box_item', 10, 2 );
		add_filter( 'should_tutor_load_template', __CLASS__ . '::filter_template_load', 99, 2 );
		add_action( 'tutor_after_video_source_icon', __CLASS__ . '::video_source_icon' );
	}

	/**
	 * Filter tutor default video sources
	 *
	 * @since v1.0.0
	 *
	 * @param array $video_source default video sources.
	 *
	 * @return array
	 */
	public static function filter_preferred_sources( array $video_source ): array {
		$video_source['bunnynet'] = array(
			'title' => __( 'BunnyNet', 'tutor-lms-bunnynet-integration' ),
			'icon'  => 'bunnynet',
		);

		return $video_source;
	}

	/**
	 * Filter single lesson video on the course content
	 * (aka spotlight) section
	 *
	 * @since v1.0.0
	 *
	 * @param string $content  tutor's default lesson content.
	 *
	 * @return string
	 */
	public static function filter_lesson_video( $content ) {
		$bunny_video_id = self::is_bunnynet_video_source();
		if ( false !== $bunny_video_id ) {
			$content = self::get_embed_video( $bunny_video_id );
		}
		return $content;
	}

	/**
	 * Filter course intro video if source if bunny net
	 *
	 * @since v1.0.0
	 *
	 * @param string $content course intro video content.
	 *
	 * @return string
	 */
	public static function filter_course_video( $content ) {
		$bunny_video_id = self::is_bunnynet_video_source();
		if ( false !== $bunny_video_id ) {
			$content = self::get_embed_video( $bunny_video_id );
		}
		return $content;
	}

	/**
	 * Add bunny net source field on the meta box
	 *
	 * @since v1.0.0
	 *
	 * @param string $style display style.
	 * @param object $post  post object.
	 *
	 * @return void
	 */
	public static function meta_box_item( $style, $post ):void {
		$video           = maybe_unserialize( get_post_meta( $post->ID, '_video', true ) );
		$video_source    = tutor_utils()->avalue_dot( 'source', $video, 'bunnynet' );
		$bunnynet_source = tutor_utils()->avalue_dot( 'source_bunnynet', $video );
		?>
		<div class="tutor-mt-16 video-metabox-source-item video_source_wrap_bunnynet tutor-dashed-uploader" style="<?php echo esc_attr( $style ); ?>">
			<input class="tutor-form-control" type="text" name="video[source_bunnynet]" value="<?php echo esc_attr( $bunnynet_source ); ?>" placeholder="<?php esc_html_e( 'Place Your BunnyNet Videos\'s Iframe URL Here', 'tutor-lms-bunnynet-integration' ); ?>">
		</div>
		<script>
			// Don't show input field if video source is not bunny net.
			var bunnyNet = document.querySelector('.video_source_wrap_bunnynet');
			var videoSource = document.querySelector('.tutor_lesson_video_source.no-tutor-dropdown');
			var icon = document.querySelector('i[data-for=bunnynet]');
			if (videoSource) {
				if (videoSource.value != 'bunnynet') {
					bunnyNet.style = 'display:none;'
				}
				
				if (videoSource.value == 'bunnynet') {
					icon.style = 'display:block;';
				} else {
					icon.style.display = 'display:none;';
				}

				videoSource.onchange = (e) => {
					console.log(e.target.value);
					if (e.target.value == 'bunnynet') {
						icon.style = 'display:block;';
					} else {
						icon.style = 'display:none;';
						console.log('none');
					}
				}
			}
		</script>
		<?php
	}

	/**
	 * If video source is bunny net then let not
	 * load the template from tutor
	 *
	 * @since v1.0.0
	 *
	 * @param boolean $should_load should load template.
	 * @param string  $template  template name.
	 *
	 * @return bool
	 */
	public static function filter_template_load( bool $should_load, string $template ):bool {
		if ( false !== self::is_bunnynet_video_source() && 'single.video.bunnynet' === $template ) {
			$should_load = false;
		}
		return $should_load;
	}

	/**
	 * Check video source is bunnynet
	 *
	 * @since v1.0.0
	 *
	 * @return mixed  video source if exists otherwise false
	 */
	public static function is_bunnynet_video_source() {
		$video_info = tutor_utils()->get_video_info();
		$response   = false;
		if ( $video_info ) {
			$bunny_video_id = tutor_utils()->array_get( 'source_bunnynet', $video_info );
			$video_source   = $video_info->source;
			if ( 'bunnynet' === $video_source && '' !== $bunny_video_id ) {
				$response = $bunny_video_id;
			}
		}
		return $response;
	}

	/**
	 * Get embedded bunny net video
	 *
	 * @since v1.0.0
	 *
	 * @param string $bunny_video_id video id for embedding.
	 *
	 * @return string video content
	 */
	private static function get_embed_video( $bunny_video_id ):string {
		ob_start();
		?>
		<div class="tutor-video-player">
			<div style="position: relative; padding-top: 56.25%;">
				<iframe src="<?php echo esc_attr( $bunny_video_id ); ?>" loading="lazy" style="border: none; position: absolute; top: 0; height: 100%; width: 100%;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Video source icon that will be visible on the
	 * video source dropdown
	 *
	 * @since v1.0.0
	 *
	 * @return void
	 */
	public static function video_source_icon() {
		echo '<i class="tutor-icon-video-camera-o" data-for="bunnynet"></i>';
	}
}
