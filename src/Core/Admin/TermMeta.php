<?php
/**
 * Created by Netivo for Netivo Modules
 * User: manveru
 * Date: 16.02.2026
 * Time: 12:38
 *
 */

namespace Netivo\Core\Admin;

use Exception;
use Netivo\Core\Traits\ResolvesViewName;

if ( ! defined( 'ABSPATH' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

/**
 * Abstract class for managing term meta functionality in WordPress.
 *
 * This class provides a foundation for adding, editing, and saving custom meta fields for taxonomy terms.
 * It includes hooks and methods for working with WordPress taxonomy form fields and processing term meta data.
 */
abstract class TermMeta {

	use ResolvesViewName;

	/**
	 * Name of the metadata field.
	 *
	 * @var string
	 */
	public static string $META_FIELD_NAME = '';

	/**
	 * Taxonomies to add meta fields to.
	 *
	 * @var mixed
	 */
	protected array|string $taxonomy;

	/**
	 * Path to Admin folder on server.
	 *
	 * @var string
	 */
	protected string $path = '';

	/**
	 * Name of the view file, taken from reflection name or class attribute
	 *
	 * @var string
	 */
	protected string $view_name = '';

	/**
	 * Term Meta box constructor.
	 *
	 * @param string $path Path to Admin folder.
	 */
	public function __construct( string $path ) {
		$this->path      = $path;
		$this->view_name = $this->resolve_view_attribute( 'Netivo\Attributes\View' ) ?? $this->resolve_view_name_from_filename();

		if ( ! is_array( $this->taxonomy ) ) {
			$this->taxonomy = array( $this->taxonomy );
		}

		$this->init_actions();

	}

	/**
	 * Initializes actions for handling custom taxonomy form fields.
	 *
	 * @return void
	 */
	protected function init_actions(): void {
		foreach ( $this->taxonomy as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', [ $this, 'display_add' ], 10 );
			add_action( $taxonomy . '_edit_form_fields', [ $this, 'display_edit' ], 10 );

			add_action( 'edited_' . $taxonomy, [ $this, 'do_save' ], 10, 2 );
			add_action( 'create_' . $taxonomy, [ $this, 'do_save' ], 10, 2 );
		}
	}

	/**
	 * Displays the add form for term metadata.
	 *
	 * @return void
	 * @throws Exception If the corresponding view file does not exist.
	 */
	public function display_add(): void {
		wp_nonce_field( 'save_' . static::$META_FIELD_NAME, static::$META_FIELD_NAME . '_nonce' );

		$filename = $this->path . '/admin/term-meta/' . $this->view_name . '.php';

		if ( file_exists( $filename ) ) {
			include $filename;
		} else {
			throw new Exception( "There is no view file for this admin action" );
		}
	}

	/**
	 * Displays the edit term meta box.
	 *
	 * This method includes the corresponding view file for editing term metadata.
	 * It ensures the request is verified using a nonce for security,
	 * and throws an exception if the required view file does not exist.
	 *
	 * @return void
	 * @throws Exception If the view file for the admin action is not found.
	 */
	public function display_edit(): void {
		wp_nonce_field( 'save_' . static::$META_FIELD_NAME, static::$META_FIELD_NAME . '_nonce' );

		$filename = $this->path . '/admin/term-meta/' . $this->view_name . '-edit.php';

		if ( file_exists( $filename ) ) {
			include $filename;
		} else {
			throw new Exception( "There is no view file for this admin action" );
		}
	}

	/**
	 * Saves meta data for a term.
	 *
	 * @param int $term_id The ID of the term being saved.
	 * @param ?int $tt_id Optional. The term taxonomy ID. Default is null.
	 *
	 * @return int The term ID after processing.
	 */
	public function do_save( int $term_id, ?int $tt_id = null ): int {
		if ( ! isset( $_POST[ static::$META_FIELD_NAME . '_nonce' ] ) ) {
			return $term_id;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ static::$META_FIELD_NAME . '_nonce' ] ) ), 'save_' . static::$META_FIELD_NAME ) ) {
			return $term_id;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return $term_id;
		}

		return $this->save( $term_id, $tt_id );
	}

	/**
	 * Saves data associated with a term.
	 *
	 * @param int $term_id The ID of the term to save data for.
	 * @param ?int $tt_id Optional. The term taxonomy ID. Defaults to null.
	 *
	 * @return int The ID of the term after saving.
	 */
	public abstract function save( int $term_id, ?int $tt_id = null ): int;

}