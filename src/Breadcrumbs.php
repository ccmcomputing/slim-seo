<?php
namespace SlimSEO;

class Breadcrumbs {
	public function __construct() {
		add_shortcode( 'slim_seo_breadcrumbs', [ $this, 'output' ] );
	}

	public function output( $atts ) {
		if ( is_front_page() ) {
			return '';
		}

		$atts = wp_parse_args(
			$atts,
			array(
				'separator'         => '&raquo;',
				'home_label'        => __( 'Home', 'slim-seo' ),
				'home_class'        => 'breadcrumb--first',
				'before'            => '',
				'after'             => '',
				'before_item'       => '',
				'after_item'        => '',
				'taxonomy'          => 'category',
				'display_last_item' => true,
			)
		);

		$items = array();
		$title = '';

		$output  = $atts['before'];
		$output .= '<nav class="breadcrumbs" aria-label="breadcrumbs" itemscope itemtype="http://schema.org/BreadcrumbList">';

		// HTML template for items.
		$tpl_link = $atts['before_item'] . '
			<span class="breadcrumb%s" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
				<a href="%s" itemprop="item"><span itemprop="name">%s</span></a>
				<meta itemprop="position" content="%d">
			</span>
			' . $atts['after_item'];
		// Text template for items.
		$tpl_text = $atts['before_item'] . '
			<span class="breadcrumb breadcrumb--last">
				<span aria-current="page">%s</span>
			</span>
			' . $atts['after_item'];

		// Home.
		$position = 1;
		$items[]  = sprintf( $tpl_link, $atts['home_class'] ? " {$atts['home_class']}" : '', esc_url( home_url( '/' ) ), $atts['home_label'], $position++ );

		if ( is_home() && ! is_front_page() ) {
			$page  = get_option( 'page_for_posts' );
			$title = get_the_title( $page );
		} elseif ( is_post_type_archive() ) {

			// If is a custom post type archive.
			$post_type = get_post_type();
			if ( 'post' !== $post_type ) {
				$post_type_object = get_post_type_object( $post_type );
				$title            = $post_type_object->labels->name;
			}
		} elseif ( is_single() ) {

			// Add post type archive link.
			$post_type = get_post_type();
			if ( 'post' !== $post_type ) {
				$post_type_object       = get_post_type_object( $post_type );
				$post_type_archive_link = get_post_type_archive_link( $post_type );
				$items[]                = sprintf( $tpl_link, '', $post_type_archive_link, $post_type_object->labels->name, $position++ );
			}

			// Terms.
			$terms = get_the_terms( get_the_ID(), $atts['taxonomy'] );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$term    = current( $terms );
				$terms   = $this->get_term_parents( $term->term_id, $atts['taxonomy'] );
				$terms[] = $term->term_id;
				foreach ( $terms as $term_id ) {
					$term    = get_term( $term_id, $atts['taxonomy'] );
					$items[] = sprintf( $tpl_link, '', get_term_link( $term, $atts['taxonomy'] ), $term->name, $position++ );
				}
			}

			$title = get_the_title();
		} elseif ( is_page() ) {
			$pages = $this->get_post_parents( get_queried_object_id() );
			foreach ( $pages as $page ) {
				$items[] = sprintf( $tpl_link, '', get_permalink( $page ), get_the_title( $page ), $position++ );
			}
			$title = get_the_title();
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$current_term = get_queried_object();
			$terms        = $this->get_term_parents( get_queried_object_id(), $current_term->taxonomy );
			foreach ( $terms as $term_id ) {
				$term    = get_term( $term_id, $current_term->taxonomy );
				$items[] = sprintf( $tpl_link, '', get_category_link( $term_id ), $term->name, $position++ );
			}
			$title = $current_term->name;
		} elseif ( is_search() ) {
			/* translators: search query */
			$title = sprintf( __( 'Search results for: %s', 'slim-seo' ), get_search_query() );
		} elseif ( is_404() ) {
			$title = __( 'Not Found', 'slim-seo' );
		} elseif ( is_author() ) {
			// Queue the first post, that way we know what author we're dealing with (if that is the case).
			the_post();
			$title = '<span class="vcard"><a class="url fn n" href="' . get_author_posts_url( get_the_author_meta( 'ID' ) ) . '" title="' . esc_attr( get_the_author() ) . '" rel="me">' . get_the_author() . '</a></span>';
			rewind_posts();
		} elseif ( is_day() ) {
			$title = get_the_date();
		} elseif ( is_month() ) {
			$title = get_the_date( 'F Y' );
		} elseif ( is_year() ) {
			$title = get_the_date( 'Y' );
		} else {
			$title = __( 'Archives', 'slim-seo' );
		} // End if().

		$items[] = sprintf( $tpl_text, $title );

		$output .= implode( $atts['separator'], $items );
		$output .= '</nav>';
		$output .= $atts['after'];

		return $output;
	}

	/**
	 * Searches for term parents' IDs of hierarchical taxonomies, including current term.
	 * This function is similar to the WordPress function get_category_parents() but handles any type of taxonomy.
	 *
	 * @param int|string    $term_id  The term ID.
	 * @param object|string $taxonomy The taxonomy of the term whose parents we want.
	 *
	 * @return array Array of parent terms' IDs.
	 */
	private function get_term_parents( $term_id = '', $taxonomy = 'category' ) {
		$list = array();
		if ( empty( $term_id ) || empty( $taxonomy ) ) {
			return $list;
		}

		do {
			$list[] = $term_id;

			// Get next parent term.
			$term    = get_term( $term_id, $taxonomy );
			$term_id = $term->parent;
		} while ( $term_id );

		// Reverse the array to put them in the proper order for the trail.
		$list = array_reverse( $list );
		array_pop( $list );

		return $list;
	}

	/**
	 * Gets parent posts' IDs, include current post.
	 *
	 * @param int|string $post_id ID of the post whose parents we want.
	 *
	 * @return array Array of parent posts' IDs.
	 */
	private function get_post_parents( $post_id = '' ) {
		$list = array();
		if ( empty( $post_id ) ) {
			return $list;
		}

		do {
			$list[] = $post_id;

			// Get next parent post.
			$post    = get_post( $post_id );
			$post_id = $post->post_parent;
		} while ( $post_id );

		// Reverse the array to put them in the proper order for the trail.
		$list = array_reverse( $list );
		array_pop( $list );

		return $list;
	}
}
