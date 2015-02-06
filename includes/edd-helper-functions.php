<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! function_exists( 'edd_get_related_downloads' ) ) {

	/**
	 * Related downlodas.
	 *
	 * Get ralated downloads based on the given download id.
	 *
	 * @since 1.0.0
	 *
	 * @param	int 	$download_id	Download (post) ID to get the related downloads for.
	 * @param	int 	$limit			Number of related downloads to get.
	 * @return	array					List of related download id's
	 */
	function edd_get_related_downloads( $download_id, $limit = 2 ) {

		global $wpdb;

		// Related products are found from category and tag
		$tags_array = array(0);
		$cats_array = array(0);

		// Get tags
		$terms = wp_get_post_terms( $download_id, 'download_tag' );
		foreach ( $terms as $term ) :
			$tags_array[] = $term->term_id;
		endforeach;

		// Get categories
		$terms = wp_get_post_terms( $download_id, 'download_category' );
		foreach ( $terms as $term ) :
			$cats_array[] = $term->term_id;
		endforeach;

		// Return random downloads when no categories/tags are set
		if ( sizeof( $cats_array ) == 1 && sizeof( $tags_array ) == 1 ) {
			return false;
		}

		// Sanitize
		$cats_array  = array_map( 'absint', $cats_array );
		$tags_array  = array_map( 'absint', $tags_array );
		$exclude_ids = array( $download_id );

		// Generate query
		$query['fields'] = "SELECT DISTINCT ID FROM {$wpdb->posts} p";
		$query['join']   = " INNER JOIN {$wpdb->postmeta} pm ON ( pm.post_id = p.ID )";
		$query['join']  .= " INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)";
		$query['join']  .= " INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)";
		$query['join']  .= " INNER JOIN {$wpdb->terms} t ON (t.term_id = tt.term_id)";

		$query['where']  = " WHERE 1=1";
		$query['where'] .= " AND p.post_status = 'publish'";
		$query['where'] .= " AND p.post_type = 'download'";
		$query['where'] .= " AND ( pm.meta_key = '_thumbnail_id' AND pm.meta_value <> '' )";
		$query['where'] .= " AND p.ID NOT IN ( " . implode( ',', $exclude_ids ) . " )";

		$query['where'] .= " AND ( ( tt.taxonomy = 'download_category' AND t.term_id IN ( " . implode( ',', $cats_array ) . " ) )";
		$andor = 'OR';

		$query['where'] .= " {$andor} ( tt.taxonomy = 'download_tag' AND t.term_id IN ( " . implode( ',', $tags_array ) . " ) ) )";


		$query['orderby']  = " ORDER BY RAND()";
		$query['limits']   = " LIMIT " . absint( $limit ) . " ";

		// Get the posts
		$related_posts = $wpdb->get_col( implode( ' ', $query ) );

		return $related_posts;

	}

}


if ( ! function_exists( 'receiptful_edd_get_random_downloads' ) ) {

	/**
	 * Random downloads.
	 *
	 * Get random downloads from the database.
	 *
	 * @param	int 	$limit 	Number of downloads to get.
	 * @return	array			List of random downloads.
	 */
	function receiptful_edd_get_random_downloads( $limit = 2 ) {

		$random_downloads = get_posts( array(
			'fields'		 	=> 'ids',
			'posts_per_page' 	=> $limit,
			'orderby'			=> 'rand',
			'post_status'		=> 'publish',
			'post_type'			=> 'download',
			'meta_query'		=> array(
				array(
					'key'		=> '_thumbnail_id',
					'compare'	=> 'EXISTS',
				),
			),
		) );

		return $random_downloads;

	}


}