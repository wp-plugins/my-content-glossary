<?php
/*
Plugin Name: My Content Management - Glossary Filter
Version: 1.3.6
Plugin URI: http://www.joedolson.com/articles/my-content-management/
Description: Adds custom glossary features: filters content for links to terms, etc. Companion plug-in to My Content Management.
Author: Joseph C. Dolson
Author URI: http://www.joedolson.com
*/
/*  Copyright 2011-2015  Joe Dolson (email : joe@joedolson.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( !is_plugin_active('my-content-management/my-content-management.php') ) {
	$activate = admin_url('plugins.php#my-content-management');
	add_action('admin_notices', create_function( '', "echo \"<div class='error'><p>My Content Management must be activated to use MCM Glossary Filter. <a href='$activate'>Visit your plugins page to activate</a>.</p></div>\";" ) );
}

add_shortcode( 'alphabet','mcm_glossary_alphabet' );
/*
*	Produces alphabetical representations of terms in glossary.
*/
function mcm_glossary_alphabet( $atts ) {
	extract( shortcode_atts( array(
		'numbers' => 'true',
		'inactive' => 'true'
		), $atts));
	$live = array(); 
	$return = '';
	$nums = range('0','9');
	$letters = range('a','z');
	if ( $numbers != 'false' ) {
		$letters = array_merge($nums, $letters);
	}
	// Use mcm_glossary_alphabet to provide a custom set of letters (e.g., other alphabets)
	$letters = apply_filters( 'mcm_glossary_alphabet', $letters, $atts );

	$words = get_option( 'mcm_glossary' );
	if ( !is_array( $words ) ) {
		$words = mcm_set_glossary();
	}
	foreach ( $words as $key=>$value ) {
		$this_letter = strtolower( substr( $key, 0, 1 ) );
		$live[]=$this_letter;
	}
	foreach ( $letters as $letter ) {
		if ( in_array( $letter, $live, true ) ) {
			$return .= "<li><a href='#glossary$letter'>$letter</a></li>";
		} else {
			$return .= ( $inactive != 'false' ) ? "<li class='inactive'>$letter</li>" : '';
		}
	}
	return "<ul class='glossary-alphabet' id='alpha'>".$return."</ul>";
}

add_action( 'publish_mcm_glossary', 'mcm_set_glossary', 20 );
function mcm_set_glossary() {
	$array = array();
	$args = array(
		'numberposts' => -1,
		'post_type' => 'mcm_glossary',
		'orderby' => 'title',
		'order'=>'asc'
	);
	$words = get_posts( $args );
	foreach ($words as $word ) {
		$term = $word->post_title;
		$link = get_permalink( $word->ID );
		$array[$term] = $link;
	}
	update_option( 'mcm_glossary',	$array );
	wp_reset_query();
	return $array;
}

add_filter( 'mcm_filter_posts','mcm_filter_glossary_list', 10, 8 );
function mcm_filter_glossary_list( $return, $post, $last_term, $elem, $type, $first, $last_post, $custom ) {
	if ( $type != 'mcm_glossary' && $type != 'glossary' ) return $return;
	$this_letter = ( isset( $post['id'] ) ? strtolower( substr( get_the_title( $post['id'] ), 0, 1 ) ) : false );
	$last_letter = strtolower( substr( $last_term, 0, 1 ) );
	$backtotop = (!$first)?"<a href='#alpha' class='return'>".__('Back to Top','my-content-management')."</a>":'';
	if ( $this_letter != $last_letter ) {
		$return .= "</$elem>$backtotop<h2 id='glossary$this_letter'>$this_letter</h2><$elem>";
	}
	return $return;
}

add_filter( 'the_content', 'mcm_glossary_filter', 10 );
add_filter( 'comment_text', 'mcm_glossary_filter', 10 );

/*
* Filter content to identify terms from glossary and link to definitions.
* Replaces first two occurrences only.
*/
function mcm_glossary_filter($content) {
	$post_types = get_post_types();
	global $post;
	$id = $post->ID;
	$ng = strtolower( get_post_custom_values( '_nogloss', $id ) );	// Set a custom field called '_nogloss' to 'no' on any post to deactivate glossary filtering.
	if ( in_array( 'mcm_glossary',$post_types ) ) {
		$words = get_option( 'mcm_glossary' );
		if ( !is_array( $words ) ) {
			$words = mcm_set_glossary();
		}
		if ( !is_singular( 'mcm_glossary' ) && ( isset( $ng[0] ) && $ng[0] != 'no' ) ) {
			$content = " $content ";
			if ( is_array( $words ) ) {
				foreach( $words as $key=>$value ) {
					$term = $key;
					$link = $value;
					$content = preg_replace( "|(?!<[^<>]*?)(?<![?./&])\b$term\b(?!:)(?![^<>]*?>)|msU","<a href=\"$link\">$term</a>" , $content, 2 );
				}
			}
			return trim( $content );
		} else {
			return $content;
		}
	}
	return $content;
}

add_shortcode('term','mcm_glossary_link');
function mcm_glossary_link($atts) {
	extract(shortcode_atts(array(
				'id' => '',
				'term' => ''
			), $atts));
	return "<a href='".get_permalink( $id )."' class='mcm-glossary'>$term</a>";
}