<?php
/*
 * Plugin Name: P2P Export/Import
 * Description: Demonstrates how P2P information can be added to an export redux and
 * 	used by importer redux
 * Version: 0.1
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: http://sparrowhawkcomputing.com/
 * Plugin URI: https://github.com/pbiron/p2p-export-import
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/pbiron/p2p-export-import
 */

/*
 * This plugin is intended to be used in conjunction with the WordPress Exporter Redux
 * plugin (http://github.com/pbiron/WordPress-Exporter) and WordPress Importer Redux
 * plugin (http://github.com/pbiron/WordPress-Importer).
 *
 * I thought it would be a good test of the utility of the `wxr_export_post` action
 * in the WordPress Exporter Redux plugin to generate an export of
 * https://developer.wordpress.org/reference/.
 *
 * The content of the WP Code Reference site is built by running `phpdoc-parser`
 * (https://github.com/WordPress/phpdoc-parser) on the the WordPress core source.
 * `phpdoc-parser` uses P2P (https://github.com/scribu/wp-posts-to-posts) to store
 * various relationships between posts (e.g., the information displayed in the
 * "Related" section of Code Reference pages) and P2P stores this information
 * in custom tables.  Generating an export with the standard exporter and then
 * importing that into another site will NOT result a complete mirror because the
 * P2P information will be missing.
 *
 * By hooking into `wxr_export_post` we can include the information
 * stored in the P2P custom tables.  Version 0.2 of the Importer redux provides
 * hooks to allow the export markup to be imported.  Those import hooks are
 * VERY provisional and might change as I gain more experience with what
 * a plugin that uses them needs!
 */

// load P2P
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

/**
 * Add extension markup to an export.
 */
class P2P_Export {
	/**
	 * Our namespaceURI.
	 *
	 * @var string
	 */
	const NAMESPACE_URI = 'http://scribu.net/wordpress/posts-to-posts/';

	/**
	 * Our preferred namespace prefix.
	 *
	 * Note that this prefix might be modified in P2P_Export::unique_prefix().

	 * @var string
	 */
	protected $prefix = 'p2p';

	/**
	 * Whether the p2pmeta table exists.
	 *
	 * This should always be true, but I'm not familiar enough with P2P to know for sure.
	 *
	 * @var string
	 */
	protected $meta_exists = false;

	/**
	 * Hook actions and filters
	 */
	function __construct() {
		global $wpdb;

		// if the p2p table doesn't exist there is nothing for us to do, so bail
		$p2p = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}p2p'" );
			if ( empty( $p2p ) ) {
			return;
		}
		scb_register_table( 'p2p' );

		$p2pmeta = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}p2pmeta'" );
		if ( ! empty( $p2pmeta ) ) {
			$this->meta_exists = true;
			scb_register_table( 'p2pmeta' );
		}

		add_filter( 'wxr_export_plugins', array( $this, 'register_plugin_for_export' ) );
		add_action( 'wxr_unique_prefix_' . self::NAMESPACE_URI, array( $this, 'unique_prefix' ) );
		add_action( 'wxr_export_post', array( $this, 'write_rows' ), 10, 2 );
	}

	/**
	 * Inform the exporter that we intend to output extension elements/attributes.
	 *
	 * @todo The $plugins param is actually an array of the hash below.  As far as I know
	 * there is no convention for this in the PHP Documentation Standards
	 * (https://make.wordpress.org/core/handbook/best-practices/inline-documentation-standards/php/#1-1-parameters-that-are-arrays)

	 * @param array $plugins {
	 *     @type string $prefix Our "preferred" namespace prefix.
	 *     @type string $namespaceURI The namespaceURI for our extension elements/attributes.
	 *     @type string $slug The "file path" for our plugin (i.e., the $plugin parameter to
	 *                        activate_plugin()).  The "new" importer will eventually be able
	 *                        to use this (and `$url`) to inform users peforming an import
	 *                        that unless this plugin is installed/activated, then some information
	 *                        in the WXR instance they are importing will not actually
	 *                        be imported.
	 *    @type string $url The URL from which our plugin can be downloaded if it is not already
	 *                      installed.
	 * }
	 *
	 * @filter wxr_export_plugins
	 */
	function register_plugin_for_export( $plugins ) {
		$plugins[] = array(
			'prefix' => $this->prefix,
			'namespaceURI' => self::NAMESPACE_URI,
			'slug' => basename( __DIR__) . '/' . basename( __FILE__),
			'url' => 'https://github.com/pbiron/p2p-export-import',
			);

		return $plugins;
	}

	/**
	 * Receive notification that the "preferred" namespace prefix we requested was already taken.
	 *
	 * @param string $prefix Prefix the exporter used in the namespace decl for our namespaceURI.
	 *
	 * @action wxr_unique_prefix_' . self::NAMESPACE_URI
	 */
	function unique_prefix( $prefix ) {
		$this->prefix = $prefix ;

		return;
	}

	/**
	 * Export P2P information for a post.
	 *
	 * An XML schema for the markup produced is located in ./xsd/p2p.xsd
	 *
	 * @param XMLWriter $writer
	 * @param WP_Post $post
	 *
	 * @action wxr_export_post
	 */
	function write_rows( $writer, $post ) {
		/**
		 * @global $wpdb
		 */
		global $wpdb;

		$p2ps = $wpdb->get_results ( $wpdb->prepare( "SELECT * FROM $wpdb->p2p WHERE p2p_from = %d", $post->ID ) );
		foreach ( $p2ps as $p2p ) {
			$to = get_post( $p2p->p2p_to );
			$writer->startElementNS( $this->prefix, 'p2p', null );

			// note: no need to write p2p_id nor p2p_from

			$writer->writeElementNS( $this->prefix, 'to', null, $to->post_name );
			$writer->writeElementNS( $this->prefix, 'to_type', null, $to->post_type );
			$writer->writeElementNS( $this->prefix, 'type', null, $p2p->p2p_type );

			if ( ! $this->meta_exists ) {
				continue;
			}

			$metas = $wpdb->get_results ( $wpdb->prepare( "SELECT * FROM $wpdb->p2pmeta WHERE p2p_id = %d", $p2p->p2p_id ) );
			foreach ( $metas as $meta ) {
				$writer->startElementNS( $this->prefix, 'meta', null );

				$writer->writeElementNS( $this->prefix, 'key', null, $meta->meta_key );
				$writer->writeElementNS( $this->prefix, 'value', null, $meta->meta_value );

				$writer->endElement(); // meta
			}

			$writer->endElement(); // p2p
		}
	}
}

new P2P_Export();

/**
 * P2P can create relationships from any object to any other object (e.g., from posts to users,
 * from posts to posts, from users to terms, etc).  At this time,  we assume that
 * ALL P2P extension markup in a WXR instance are from posts to posts, for two reasons:
 *
 * 1) that is all is necessary to create a mirror of https://developer.wordpress.org/reference/;
 * 2) I'm not all that familiar with how P2P stores other kinds of relationships and
 *    think that we'd also have to export additional information (probably at the /rss/channel
 *    level) to be able to handle other kind of relationships.
 *
 * When I find the time I'll figure out how to both export and import other types
 * of P2P relationships.
 */
class P2P_Import {
	/**
	 * Our namespaceURI
	 * @var string
	 */
	const NAMESPACE_URI = 'http://scribu.net/wordpress/posts-to-posts/';

	protected $exists = array();
	protected $requires_remapping = array();

	/**
	 * Map from XML element names to p2p column names
	 * @var array
	 */
	private $map = array(
		'from' => 'p2p_from',
		'to' => 'p2p_to',
		'type' => 'p2p_type',
		);


	/**
	 * Hook actions and filters
	 */
	function __construct() {
		add_action( 'import_start', array( $this, 'init_p2p' ) ) ;
		add_action( 'import_end', array( $this, 'post_process' ) );
		add_action( 'wxr_importer.parsed.post.' . self::NAMESPACE_URI,
			array( $this, 'parse_p2p' ), 10, 4);
	}

	/**
	 * Initialize P2P
	 *
	 * @action import_start
	 */
	function init_p2p () {
		P2P_Storage::init();
		// create the P2P tables if necessary
		P2P_Storage::install();
	}

	/**
	 * Process and import a p2p element on import
	 *
	 * @param int $post_id Post ID of imported post the p2p element was a child of.
	 * @param array $p2ps Array of DOMNodes representing p2p rows.
	 * @param array $parsed {
	 *     Parsed data for $post_id
	 *
	 *     @type array $data Parsed data.
	 *     @type array $meta Parsed metas.
	 *     @type array $comments Parsed comments.
	 *     @type array $terms Parsed terms.
	 * }
	 * @param DOMNode $node The DOMNode for $post_id.
	 *
	 * Note: For this demo plugin, we only need $post_id and $elements.  However,
	 * other plugins might need them and hence I've had them passed to this method
	 * just to check that they are being passed as they should be.
	 *
	 * @action wxr_importer.parsed.post.' . self::NAMESPACE_URI
	 */
	function parse_p2p( $post_id, $p2ps, $parsed, $node ) {
		foreach ( $p2ps as $p2p ) {
			global $wpdb;

			$data = $metas = array();

			foreach ( $p2p->childNodes as $child ) {
				// We only care about child elements
				if ( $child->nodeType !== XML_ELEMENT_NODE ) {
					continue;
				}
				// We only care about elements in our namespace
				if ( self::NAMESPACE_URI !== $child->namespaceURI ) {
					continue;
				}

				switch ( $child->localName ) {
					case 'to':
					case 'to_type':
					case 'type':
						$data[$child->localName] = $child->textContent;
						break;

					case 'meta':
						$p2p_parsed = $this->parse_meta_node( $child );
						if ( ! is_wp_error( $p2p_parsed ) ) {
							$metas[] = $p2p_parsed;
						}

						break;
				}
			}

			if ( empty( $data) ) {
				return;
			}

			$data['from'] = $post_id;

			$to_id = $this->post_exists( $data['to'], $data['to_type'] );
			if ( $to_id ) {
				$data['to'] = $to_id;
			}
			else {
				// the 'to' end of the relationship hasn't been imported yet
				// save this p2p for later processing
				// @todo generalize the caching mechanism in importer-redux
				// such that it could be usable by plugins like this
				$this->requires_remapping['post'][ $post_id ][] = compact( 'data', 'metas' );

				continue;
			}

			$data = $this->remap_xml_keys( $data );

			$this->insert_p2p( $data, $metas );
		}
	}

	/**
	 * Parse a meta node.
	 *
	 * @param DOMNode $node
	 * @return array
	 */
	protected function parse_meta_node( $node ) {
		$data = array();
		foreach ( $node->childNodes as $child ) {
			// We only care about child elements
			if ( $child->nodeType !== XML_ELEMENT_NODE ) {
				continue;
			}
			// We only care about elements in our namespace
			if ( self::NAMESPACE_URI !== $child->namespaceURI ) {
				continue;
			}

			switch ( $child->localName ) {
				case 'key':
				case 'value':
					$data[$child->localName] = $child->textContent;
					break;
			}
		}

		return $data;
	}

	/**
	 * Remap data keys from XML element name to what process_xxx() expects
	 *
	 * @param array $data Parsed XML data.  key is XML element name,
	 *                    value is element's value.
	 * @param array $keymap Map of XML element name to processing key.
	 *                      key is XML element name, value is what that
	 *                      key maps to (or true if key should map to itself)
	 * @return array
	 *
	 * @todo generalize the key remapping code in importer-redux so that it
	 * can be used by plugins like this
	 */
	protected function remap_xml_keys( $data ) {
		foreach ( $data as $key => $value ) {
			if ( ! isset( $this->map[ $key ] ) ) {
				unset( $data[ $key ] );

				continue;
			}

			if ( is_string( $this->map[$key] ) ) {
				$data[ $keymap[$key] ] = $data[ $key ];
				unset ( $data[ $key ] );
			}
		}

		return $data;
	}

	/**
	 * Insert a p2p row
	 *
	 * @param array $data {
	 *     @type int $p2p_from
	 *     @type int $p2p_to
	 *     @type string $p2p_type
	 * }
	 * @param array $metas {
	 *     This is actually an array of the following array
	 *
	 *     @type string $key
	 *     @type string $value
	 * }
	 */
	protected function insert_p2p( $data, $metas ) {
		/**
		 * @global wpdb $wpdb
		 */
		global $wpdb;

		$wpdb->insert( $wpdb->p2p, $data, array( '%d', '%s', '%d' ) );
		$p2p_id = $wpdb->insert_id;

		foreach ( $metas as $meta ) {
			add_metadata( 'p2p', $p2p_id, $meta['key'], $meta['value'] );
		}
	}

	/**
	 * Does the post exist?
	 *
	 * @param array $data Post data to check against.
	 * @return int|bool Existing post ID if it exists, false otherwise.
	 */
	protected function post_exists( $name, $post_type ) {
		// Constant-time lookup if we prefilled
		$exists_key = sha1( "{$name}:{$post_type}");

// 		if ( $this->options['prefill_existing_posts'] ) {
// 			return isset( $this->exists['post'][ $exists_key ] ) ? (int) $this->exists['post'][ $exists_key ] : false;
// 		}

		// No prefilling, but might have already handled it
		if ( isset( $this->exists['post'][ $exists_key ] ) ) {
			return (int) $this->exists['post'][ $exists_key ];
		}

		// Still nothing, try get_posts, and cache it
		$post = get_posts( array( 'name' => $name, 'post_type' => $post_type ) );
		if ( ! empty ( $post ) ) {
			$this->exists['post'][ $exists_key ] = $post[0]->ID;

			return $post[0]->ID;
		}

		return false;
	}

	/**
	 * Add P2P rows for which the 'to' end of the relationship hadn't been imported
	 * at the time the 'from' end was imported.
	 *
	 * @action import_end
	 */
	function post_process()
	{
		foreach ( $this->requires_remapping['post'] as $datas ) {
			foreach ( $datas as $data ) {
				$metas = $data['metas'];
				$data = $data['data'];
				$to_id = $this->post_exists( $data['to'], $data['to_type'] );
				if ( ! $to_id ) {
					// @todo should we signal an error here?
					continue;
				}

				$data['to'] = $to_id;
				$data = $this->remap_xml_keys( $data, $this->map );

				$this->insert_p2p( $data, $metas );
			}
		}
	}
}

new P2P_Import();

?>