<?php
/**
 * Plugin Name: Forminator Dashboard Widget
 * Version: 1.0
 * Plugin URI:  https://premium.wpmudev.org/project/forminator/
 * Description: Display latest form submissions as Dashboard Widget
 * Author: WPMU DEV
 * Author URI: http://premium.wpmudev.org
 */

/*
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

// It is a good practice to make sure plugin only runs when all conditions for successful run are satisfied.
// For this we use `forminator_loaded` action and only set up our plugin if that action is called.
add_action( 'forminator_loaded', 'forminator_dash_widget' );

function forminator_dash_widget() {
	// Widget should be added after `wp_dashboard_setup` hook called.
	add_action( 'wp_dashboard_setup', 'add_forminator_dash_widget' );
}

function add_forminator_dash_widget() {
	// Instantiate Forminator_Submissions_Dash_Widget class.
	$widget = Forminator_Submissions_Dash_Widget::get_instance();
	$widget->register_widget();
}

/**
 * Class Forminator_Submissions_Dash_Widget.
 *
 */
class Forminator_Submissions_Dash_Widget {

	/**
	 * Class instance
	 *
	 * @var null|Forminator_Submissions_Dash_Widget
	 */
	private static $instance = null;

	/**
	 * Form ID
	 *
	 * @var null|int
	 */
	private $id = null;

	/**
	 * Number of submissions
	 *
	 * @var int
	 */
	private $limit = 5;

	/**
	 * Get instance
	 *
	 * @return Forminator_Submissions_Dash_Widget
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add perquisites if needed
	 */
	public function __construct() {
	}

	/**
	 * Register the dashboard widget
	 */
	public function register_widget() {
		wp_add_dashboard_widget(
			__CLASS__,
			__( 'Forminator Submissions' ), // Widget title
			array( $this, 'display' ), // Render callback method
			array( $this, 'configure' ) // Configuration callback method
		);
	}

	/**
	 * Setup permissions here if needed
	 *
	 * @return bool
	 */
	public function user_allowed() {
		return true;
	}

	/**
	 * Default options of widget
	 *
	 * @return array
	 */
	private function get_default_options() {
		return array(
			'id'    => $this->id,
			'limit' => $this->limit,
		);
	}

	/**
	 * Widget display callback
	 */
	public function display() {
		// Check if user allowed to view the widget
		if ( ! $this->user_allowed() ) {
			echo esc_html( __( 'You are not allowed to view this widget content' ) );
		} else {
			// Get widget options
			$options = $this->get_options( $this->get_default_options() );

			$this->id    = $options['id'];
			$this->limit = $options['limit'];

			// Get submissions
			$this->get_submissions();
		}
	}

	/**
	 * Configure Widget
	 * Configure form id and entries limit
	 */
	public function configure() {
		// Check if user allowed to view the widget
		if ( ! $this->user_allowed() ) {
			echo esc_html( __( 'You are not allowed to view this widget content' ) );
		} else {
			$options = $this->get_options( $this->get_default_options() );

			$post_data = $_POST;

			if ( isset( $post_data['id'] ) ) {
				$options['id'] = $post_data['id'];
			}
			if ( isset( $post_data['limit'] ) ) {
				$options['limit'] = $post_data['limit'];
			}
			$this->update_options( $options );

			?>
			<label>
				<?php esc_html_e( 'ID' ); ?>
				<input type="text" class="widefat" name="id" value="<?php echo esc_attr( $options['id'] ); ?>"/>
			</label>
			<label>
				<?php esc_html_e( 'Entries Limit' ); ?>
				<input type="text" class="widefat" name="limit" value="<?php echo esc_attr( $options['limit'] ); ?>"/>
			</label>
			<hr/>

			<?php
		}
	}

	/**
	 * Update widget options
	 *
	 * @param array $options
	 *
	 * @return bool
	 */
	public function update_options( $options = array() ) {
		//Fetch all dashboard widget options from the db...
		$opts = get_option( 'dashboard_widget_options' );

		//Get just our widget's options, or set empty array
		$forminator_options = ( isset( $opts[ __CLASS__ ] ) ) ? $opts[ __CLASS__ ] : array();

		// merge old one with new one
		$opts[ __CLASS__ ] = array_merge( $forminator_options, $options );

		// update option
		return update_option( 'dashboard_widget_options', $opts );
	}

	/**
	 * Get widget Options
	 *
	 * @param array $default
	 *
	 * @return array
	 */
	public function get_options( $default = array() ) {
		//Fetch ALL dashboard widget options from the db...
		$opts = get_option( 'dashboard_widget_options' );

		//Get just our widget's options, or set default
		$forminator_options = ( isset( $opts[ __CLASS__ ] ) ) ? $opts[ __CLASS__ ] : $default;

		return $forminator_options;
	}

	/**
	 * Get Form detail and its submissions
	 *
	 * @return bool
	 */
	protected function get_submissions() {
		// Check if we have configured form ID
		if ( empty( $this->id ) ) {
			echo esc_html( __( 'Please configure which form to display its submissions.' ) );

			return false;
		}

		$module  = null;
		$entries = array();

		/**
		 * Call Forminator_API to get form details
		 */
		$module = Forminator_API::get_form( $this->id );

		// Check if form loaded successfully
		if ( is_wp_error( $module ) ) {
			$module = null;
		} else {
			/**
			 * Call Forminator_API to get form entries
			 */
			$entries = Forminator_API::get_form_entries( $module->id );
		}

		// Render submissions table
		$this->render_form_submissions( $module, $entries );
	}

	/**
	 * Render Form Submissions table on html widget
	 *
	 * @param Forminator_Custom_Form_Model  $form
	 * @param Forminator_Form_Entry_Model[] $entries
	 */
	public function render_form_submissions( $form, $entries ) {
		$field_labels = array();

		// Get fields labels
		if ( ! is_null( $form ) ) {
			if ( is_array( $form->fields ) ) {
				foreach ( $form->fields as $field ) {
					/** @var Forminator_Form_Field_Model $field */
					$field_labels[ $field->slug ] = $field->get_label_for_entry();
				}
			}
		}
		?>
		<h3><?php echo( is_null( $form ) ? esc_html( __( 'Not Found' ) ) : esc_html( $form->name ) ); ?></h3>
		<table class="widefat fixed" cellspacing="0">
			<thead>
			<tr>
				<th id="id" class="manage-column id" scope="col"><?php esc_html_e( 'ID' ); ?></th>
				<th id="entry" class="manage-column entry" scope="col"><?php esc_html_e( 'Entry' ); ?></th>
				<th id="date" class="manage-column date" scope="col"><?php esc_html_e( 'Time' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
			// set limit
			$limit = count( $entries );
			if ( $limit > $this->limit ) {
				$limit = $this->limit;
			}
			?>
			<?php for ( $i = 0; $i < $limit; $i ++ ) : ?>
				<?php
				/** @var Forminator_Form_Entry_Model $entry */
				$entry = $entries[ $i ];
				?>
				<tr class="<?php echo( ( $i % 2 ) ? 'alternate' : '' ); ?>">
					<td class="id"><?php echo esc_html( $entry->entry_id ); ?></td>
					<td class="entry">
						<ul>
							<?php foreach ( $entry->meta_data as $field_id => $meta ) : ?>
								<?php if ( isset( $field_labels[ $field_id ] ) ) : // only display entry with field label exist ?>
									<?php $field_value = $meta['value']; ?>
									<li>
										<?php echo esc_html( $field_labels[ $field_id ] ); ?> :
										<?php if ( is_array( $field_value ) ) : // show key too when its array value (multiname, multiple choices) ?>
											<?php foreach ( $field_value as $key => $val ) : ?>
												<?php echo esc_html( $key ); ?>: <?php echo esc_html( $val ); ?><br/>
											<?php endforeach; ?>
										<?php else : ?>
											<?php echo esc_html( $field_value ); ?>
										<?php endif; ?>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</td>
					<td class="date">
						<?php echo esc_html( $entry->time_created ); ?>
					</td>
				</tr>
			<?php endfor; ?>
			</tbody>
			<tfoot>
			<tr>
				<th id="id" class="manage-column id" scope="col"><?php esc_html_e( 'ID' ); ?></th>
				<th id="entry" class="manage-column entry" scope="col"><?php esc_html_e( 'Entry' ); ?></th>
				<th id="date" class="manage-column date" scope="col"><?php esc_html_e( 'Time' ); ?></th>
			</tr>
			</tfoot>
		</table>
		<?php if ( ! is_null( $form ) ) : ?>
			<ul class="">
				<li class="all">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=forminator-entries&form_type=forminator_forms&form_id=' . $form->id ) ); ?>"><?php esc_html_e( 'View Submissions' ); ?>
						<span class="count">(<span class="all-count"><?php echo esc_html( count( $entries ) ); ?></span>)
					</span>
					</a>
				</li>
			</ul>
		<?php endif; ?>
		<?php
	}

}
