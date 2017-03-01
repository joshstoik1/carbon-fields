<?php

namespace Carbon_Fields\Field;

use Carbon_Fields\App;
use Carbon_Fields\Datastore\Datastore_Interface;
use Carbon_Fields\Datastore\Datastore_Holder_Interface;
use Carbon_Fields\Value_Set\Value_Set;
use Carbon_Fields\Helper\Helper;
use Carbon_Fields\Exception\Incorrect_Syntax_Exception;

/**
 * Base field class.
 * Defines the key container methods and their default implementations.
 * Implements factory design pattern.
 **/
class Field implements Datastore_Holder_Interface {
	/**
	 * Stores all the field Backbone templates
	 *
	 * @see factory()
	 * @see add_template()
	 * @var array
	 */
	protected $templates = array();

	/**
	 * Globally unique field identificator. Generated randomly
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Stores the initial <kbd>$type</kbd> variable passed to the <code>factory()</code> method
	 *
	 * @see factory
	 * @var string
	 */
	public $type;

	/**
	 * Array of ancestor field names
	 *
	 * @var array
	 **/
	protected $hierarchy = array();

	/**
	 * Array of complex entry ids
	 *
	 * @var array
	 **/
	protected $hierarchy_index = array();

	/**
	 * Field value
	 *
	 * @var Value_Set
	 */
	protected $value_set;

	/**
	 * Default field value
	 *
	 * @var mixed
	 */
	protected $default_value = '';

	/**
	 * Sanitized field name used as input name attribute during field render
	 *
	 * @see factory()
	 * @see set_name()
	 * @var string
	 */
	protected $name;

	/**
	 * Field name prefix
	 *
	 * @see set_name()
	 * @var string
	 */
	protected $name_prefix = '_';

	/**
	 * The base field name which is used in the container.
	 *
	 * @see set_base_name()
	 * @var string
	 */
	protected $base_name;

	/**
	 * Field name used as label during field render
	 *
	 * @see factory()
	 * @see set_label()
	 * @var string
	 */
	protected $label;

	/**
	 * Additional text containing information and guidance for the user
	 *
	 * @see help_text()
	 * @var string
	 */
	protected $help_text;

	/**
	 * Field DataStore instance to which save, load and delete calls are delegated
	 *
	 * @see set_datastore()
	 * @see get_datastore()
	 * @var Datastore_Interface
	 */
	protected $datastore;

	/**
	 * Flag whether the datastore is the default one or replaced with a custom one
	 *
	 * @see set_datastore()
	 * @see get_datastore()
	 * @var boolean
	 */
	protected $has_default_datastore = true;

	/**
	 * The type of the container this field is in
	 *
	 * @see get_context()
	 * @var string
	 */
	protected $context;

	/**
	 * Whether or not this value should be auto loaded. Applicable to theme options only.
	 *
	 * @see set_autoload()
	 * @var bool
	 **/
	protected $autoload = false;

	/**
	 * Whether or not this field will be initialized when the field is in the viewport (visible).
	 *
	 * @see set_lazyload()
	 * @var bool
	 **/
	protected $lazyload = false;

	/**
	 * The width of the field.
	 *
	 * @see set_width()
	 * @var int
	 **/
	protected $width = 0;

	/**
	 * Custom CSS classes.
	 *
	 * @see add_class()
	 * @var array
	 **/
	protected $classes = array();

	/**
	 * Whether or not this field is required.
	 *
	 * @see set_required()
	 * @var bool
	 **/
	protected $required = false;

	/**
	 * Stores the field conditional logic rules.
	 *
	 * @var array
	 **/
	protected $conditional_logic = array();

	/**
	 * Whether the field should be included in the response of the requests to the REST API
	 *
	 * @see  set_visible_in_rest_api
	 * @see  get_visible_in_rest_api
	 * @var boolean
	 */
	protected $visible_in_rest_api = false;

	/**
	 * Clone the Value_Set object as well
	 *
	 * @var array
	 **/
	public function __clone() {
		$this->set_value_set( clone $this->get_value_set() );
	}

	/**
	 * Create a new field of type $type and name $name and label $label.
	 *
	 * @param string $type
	 * @param string $name lower case and underscore-delimited
	 * @param string $label (optional) Automatically generated from $name if not present
	 * @return Field
	 **/
	public static function factory( $type, $name, $label = null ) {
		$type = str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $type ) ) );

		$class = __NAMESPACE__ . '\\' . $type . '_Field';

		if ( ! class_exists( $class ) ) {
			Incorrect_Syntax_Exception::raise( 'Unknown field "' . $type . '".' );
			$class = __NAMESPACE__ . '\\Broken_Field';
		}

		$field = new $class( $type, $name, $label );

		return $field;
	}

	/**
	 * An alias of factory().
	 *
	 * @see Field::factory()
	 * @return Field
	 **/
	public static function make( $type, $name, $label = null ) {
		return static::factory( $type, $name, $label );
	}

	/**
	 * Create a field from a certain type with the specified label.
	 * 
	 * @param string $type  Field type
	 * @param string $name  Field name
	 * @param string $label Field label
	 */
	protected function __construct( $type, $name, $label ) {
		App::verify_boot();
		
		$this->type = $type;
		$this->set_base_name( $name );
		$this->set_name( $name );
		$this->set_label( $label );

		// Pick random ID
		$random_string = md5( mt_rand() . $this->get_name() . $this->get_label() );
		$random_string = substr( $random_string, 0, 5 ); // 5 chars should be enough
		$this->id = 'carbon-' . $random_string;

		$this->init();
	}

	/**
	 * Cleans up an object class for usage as HTML class
	 *
	 * @param string $type
	 * @return string
	 */
	protected function clean_type( $type ) {
		$remove = array(
			'_',
			'\\',
			'CarbonFields',
			'Field',
		);
		$clean_class = str_replace( $remove, '', $type );

		return $clean_class;
	}

	/**
	 * Returns the type of the field based on the class.
	 * The class is stripped by the "CarbonFields" prefix.
	 * Also the "Field" suffix is removed.
	 * Then underscores and backslashes are removed.
	 *
	 * @return string
	 */
	public function get_type() {
		$class = get_class( $this );

		return $this->clean_type( $class );
	}

	/**
	 * Activate the field once the container is attached.
	 */
	public function activate() {
		$this->admin_init();
		$this->add_template( $this->get_type(), array( $this, 'template' ) );

		add_action( 'admin_footer', array( get_class(), 'admin_hook_scripts' ), 5 );
		add_action( 'admin_footer', array( get_class(), 'admin_hook_styles' ), 5 );
		add_action( 'admin_footer', array( get_class( $this ), 'admin_enqueue_scripts' ), 5 );

		do_action( 'crb_field_activated', $this );
	}

	/**
	 * Get array of hierarchy field names
	 *
	 * @return array
	 **/
	public function get_hierarchy() {
		return $this->hierarchy;
	}

	/**
	 * Set array of hierarchy field names
	 **/
	public function set_hierarchy( $hierarchy ) {
		$this->hierarchy = $hierarchy;
	}

	/**
	 * Get array of hierarchy indexes
	 *
	 * @return array
	 **/
	public function get_hierarchy_index() {
		return $this->hierarchy_index;
	}

	/**
	 * Set array of hierarchy indexes
	 **/
	public function set_hierarchy_index( $hierarchy_index ) {
		$hierarchy_index = ( ! empty( $hierarchy_index ) ) ? $hierarchy_index : array( 0 );
		$this->hierarchy_index = $hierarchy_index;
	}

	/**
	 * Return whether the field is a root field and holds a single value
	 *
	 * @return bool
	 **/
	public function is_simple_root_field() {
		$hierarchy = $this->get_hierarchy();
		return (
			empty( $hierarchy )
			&&
			(
				$this->get_value_set()->get_type() === Value_Set::TYPE_SINGLE_VALUE
				||
				$this->get_value_set()->get_type() === Value_Set::TYPE_MULTIPLE_PROPERTIES
			)
		);
	}

	/**
	 * Perform instance initialization
	 **/
	public function init() {}

	/**
	 * Instance initialization when in the admin area.
	 * Called during field boot.
	 **/
	public function admin_init() {}

	/**
	 * Enqueue admin scripts.
	 * Called once per field type.
	 **/
	public static function admin_enqueue_scripts() {}

	/**
	 * Prints the main Underscore template
	 **/
	public function template() { }

	/**
	 * Returns all the Backbone templates
	 *
	 * @return array
	 **/
	public function get_templates() {
		return $this->templates;
	}

	/**
	 * Adds a new Backbone template
	 **/
	protected function add_template( $name, $callback ) {
		$this->templates[ $name ] = $callback;
	}

	/**
	 * Get value from datastore
	 *
	 * @param bool $fallback_to_default
	 * @return mixed
	 **/
	protected function get_value_from_datastore( $fallback_to_default = true ) {
		$value_tree = $this->get_datastore()->load( $this );
		
		$value = null;
		if ( isset( $value_tree['value_set'] ) ) {
			$value = $value_tree['value_set'];
		}

		if ( $value === null && $fallback_to_default ) {
			$value = $this->get_default_value();
		}

		return $value;
	}

	/**
	 * Load value from datastore
	 **/
	public function load() {
		$value = $this->get_value_from_datastore();
		$this->set_value( $value );
	}

	/**
	 * Save value to storage
	 **/
	public function save() {
		$delete_on_save = ! in_array( $this->get_value_set()->get_type(), array( Value_Set::TYPE_SINGLE_VALUE, Value_Set::TYPE_MULTIPLE_PROPERTIES ) );
		$delete_on_save = apply_filters( 'carbon_fields_should_delete_field_value_on_save', $delete_on_save, $this );
		if ( $delete_on_save ) {
			$this->delete();
		}

		$save = apply_filters( 'carbon_fields_should_save_field_value', true, $this->get_value(), $this );
		if ( $save ) {
			$this->get_datastore()->save( $this );
		}
	}

	/**
	 * Delete value from storage
	 */
	public function delete() {
		$this->get_datastore()->delete( $this );
	}

	/**
	 * Load the field value from an input array based on it's name
	 *
	 * @param array $input Array of field names and values.
	 **/
	public function set_value_from_input( $input ) {
		if ( isset( $input[ $this->get_name() ] ) ) {
			$this->set_value( $input[ $this->get_name() ] );
		} else {
			$this->set_value( array() );
		}
	}

	/**
	 * Return whether the datastore instance is the default one or has been overriden
	 *
	 * @return boolean
	 **/
	public function has_default_datastore() {
		return $this->has_default_datastore;
	}

	/**
	 * Get the DataStore instance
	 *
	 * @return Datastore_Interface $datastore
	 **/
	public function get_datastore() {
		return $this->datastore;
	}

	/**
	 * Set datastore instance
	 *
	 * @param Datastore_Interface $datastore
	 * @return object $this
	 **/
	public function set_datastore( Datastore_Interface $datastore, $set_as_default = false ) {
		if ( $set_as_default && ! $this->has_default_datastore() ) {
			return $this; // datastore has been overriden with a custom one - abort changing to a default one
		}
		$this->datastore = $datastore;
		$this->has_default_datastore = $set_as_default;
		return $this;
	}

	/**
	 * Return the type of the container this field is in
	 *
	 * @return string
	 **/
	public function get_context() {
		return $this->context;
	}

	/**
	 * Assign the type of the container this field is in
	 *
	 * @param string
	 * @return object $this
	 **/
	public function set_context( $context ) {
		$this->context = $context;
		return $this;
	}

	/**
	 * Get the Value_Set object
	 *
	 * @return Value_Set
	 **/
	public function get_value_set() {
		if ( $this->value_set === null ) {
			$this->set_value_set( new Value_Set() );
		}
		return $this->value_set;
	}

	/**
	 * Set the Value_Set object
	 *
	 * @param Value_Set $value_set
	 **/
	public function set_value_set( $value_set ) {
		$this->value_set = $value_set;
	}

	/**
	 * Alias for $this->get_value_set()->get(); with fallback to default value
	 *
	 * @return mixed
	 **/
	public function get_value() {
		if ( $this->get_value_set()->get() === null ) {
			$this->set_value( $this->get_default_value() );
		}
		return $this->get_value_set()->get();
	}

	/**
	 * Alias for $this->get_value_set()->get_set(); with fallback to default value
	 *
	 * @return array<array>
	 **/
	public function get_full_value() {
		if ( $this->get_value_set()->get_set() === null ) {
			$this->set_value( $this->get_default_value() );
		}
		return $this->get_value_set()->get_set();
	}

	/**
	 * Return a differently formatted value for end-users
	 *
	 * @return mixed
	 **/
	public function get_formatted_value() {
		return $this->get_value();
	}

	/**
	 * Alias for $this->get_value_set()->set( $value );
	 **/
	public function set_value( $value ) {
		$this->get_value_set()->set( $value );
	}

	/**
	 * Get default field value
	 *
	 * @return mixed
	 **/
	public function get_default_value() {
		return $this->default_value;
	}

	/**
	 * Set default field value
	 *
	 * @param mixed $default_value
	 **/
	public function set_default_value( $default_value ) {
		$this->default_value = $default_value;
		return $this;
	}

	/**
	 * Return the field base name.
	 *
	 * @return string
	 **/
	public function get_base_name() {
		return $this->base_name;
	}

	/**
	 * Set field base name as defined in the container.
	 **/
	public function set_base_name( $name ) {
		$this->base_name = $name;
	}

	/**
	 * Return the field name
	 *
	 * @return string
	 **/
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set field name.
	 * Use only if you are completely aware of what you are doing.
	 *
	 * @param string $name Field name, either sanitized or not
	 **/
	public function set_name( $name ) {
		if ( empty( $name ) ) {
			Incorrect_Syntax_Exception::raise( 'Field name can\'t be empty' );
		}

		$regex = '/\A[a-z0-9_\-\[\]]+\z/'; // symbols ]-[ are supported in a hidden way - required for widgets to work (WP imposes dashes and square brackets on field names)
		if ( ! preg_match( $regex, $name ) ) {
			Incorrect_Syntax_Exception::raise( 'Field name can only contain lowercase alphanumeric characters and underscores.' );
		}

		$name_prefix = $this->get_name_prefix();
		$name = ( substr( $name, 0, strlen( $name_prefix ) ) !== $name_prefix ? $name_prefix . $name : $name );

		$this->name = $name;
	}

	/**
	 * Return the field name prefix
	 *
	 * @return string
	 **/
	public function get_name_prefix() {
		return $this->name_prefix;
	}

	/**
	 * Set field name prefix
	 * Use only if you are completely aware of what you are doing.
	 *
	 * @param string $name_prefix
	 **/
	public function set_name_prefix( $name_prefix ) {
		$name_prefix = strval( $name_prefix );
		$old_prefix_length = strlen( $this->name_prefix );
		$this->name_prefix = '';
		$this->set_name( substr( $this->get_name(), $old_prefix_length ) );

		$this->name_prefix = $name_prefix;
		$this->set_name( $this->name_prefix . $this->get_name() );
	}

	/**
	 * Return field label.
	 *
	 * @return string
	 **/
	public function get_label() {
		return $this->label;
	}

	/**
	 * Set field label.
	 *
	 * @param string $label If null, the label will be generated from the field name
	 **/
	public function set_label( $label ) {
		// Try to guess field label from it's name
		if ( is_null( $label ) ) {
			// remove the leading underscore(if it's there)
			$label = preg_replace( '~^_~', '', $this->name );

			// remove the leading "crb_"(if it's there)
			$label = preg_replace( '~^crb_~', '', $label );

			// split the name into words and make them capitalized
			$label = mb_convert_case( str_replace( '_', ' ', $label ), MB_CASE_TITLE );
		}

		$this->label = $label;
	}

	/**
	 * Return the field help text
	 *
	 * @return object $this
	 **/
	public function get_help_text() {
		return $this->help_text;
	}

	/**
	 * Set additional text to be displayed during field render,
	 * containing information and guidance for the user
	 *
	 * @return object $this
	 **/
	public function set_help_text( $help_text ) {
		$this->help_text = $help_text;
		return $this;
	}

	/**
	 * Alias for set_help_text()
	 *
	 * @see set_help_text()
	 * @return object $this
	 **/
	public function help_text( $help_text ) {
		return $this->set_help_text( $help_text );
	}

	/**
	 * Return whether or not this value should be auto loaded.
	 *
	 * @return bool
	 **/
	public function get_autoload() {
		return $this->autoload;
	}

	/**
	 * Whether or not this value should be auto loaded. Applicable to theme options only.
	 *
	 * @param bool $autoload
	 * @return object $this
	 **/
	public function set_autoload( $autoload ) {
		$this->autoload = $autoload;
		return $this;
	}

	/**
	 * Return whether or not this field should be lazyloaded.
	 *
	 * @return bool
	 **/
	public function get_lazyload() {
		return $this->lazyload;
	}

	/**
	 * Whether or not this field will be initialized when the field is in the viewport (visible).
	 *
	 * @param bool $lazyload
	 * @return object $this
	 **/
	public function set_lazyload( $lazyload ) {
		$this->lazyload = $lazyload;
		return $this;
	}

	/**
	 * Get the field width.
	 *
	 * @return int $width
	 **/
	public function get_width() {
		return $this->width;
	}

	/**
	 * Set the field width.
	 *
	 * @param int $width
	 * @return object $this
	 **/
	public function set_width( $width ) {
		$this->width = (int) $width;
		return $this;
	}

	/**
	 * Get the field custom CSS classes.
	 *
	 * @return array
	 **/
	public function get_classes() {
		return $this->classes;
	}

	/**
	 *  Add custom CSS class to the field html container.
	 *
	 * @param string|array $classes
	 * @return object $this
	 **/
	public function add_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array_values( array_filter( explode( ' ', $classes ) ) );
		}

		$this->classes = array_map( 'sanitize_html_class', $classes );
		return $this;
	}

	/**
	 * Whether this field is mandatory for the user
	 *
	 * @param bool $required
	 * @return object $this
	 **/
	public function set_required( $required = true ) {
		$this->required = $required;
		return $this;
	}

	/**
	 * Return whether this field is mandatory for the user
	 *
	 * @return bool
	 **/
	public function is_required() {
		return $this->required;
	}

	/**
	 * HTML id attribute getter.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * HTML id attribute setter
	 * @param string $id
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}

	/**
	 * Set the field visibility conditional logic.
	 *
	 * @param array
	 */
	public function set_conditional_logic( $rules ) {
		$this->conditional_logic = $this->parse_conditional_rules( $rules );

		return $this;
	}

	/**
	 * Get the conditional logic rules
	 *
	 * @return array
	 */
	public function get_conditional_logic() {
		return $this->conditional_logic;
	}

	/**
	 * Validate and parse the conditional logic rules.
	 *
	 * @param array $rules
	 * @return array
	 */
	protected function parse_conditional_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			Incorrect_Syntax_Exception::raise( 'Conditional logic rules argument should be an array.' );
		}

		$allowed_operators = array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN' );

		$parsed_rules = array(
			'relation' => Helper::get_relation_type_from_array( $rules ),
			'rules' => array(),
		);

		foreach ( $rules as $key => $rule ) {
			if ( $key === 'relation' ) {
				continue; // Skip the relation key as it is already handled above
			}

			// Check if the rule is valid
			if ( ! is_array( $rule ) || empty( $rule['field'] ) ) {
				Incorrect_Syntax_Exception::raise( 'Invalid conditional logic rule format. ' .
				'The rule should be an array with the "field" key set.' );
			}

			// Check the compare operator
			if ( empty( $rule['compare'] ) ) {
				$rule['compare'] = '=';
			}
			if ( ! in_array( $rule['compare'], $allowed_operators ) ) {
				Incorrect_Syntax_Exception::raise( 'Invalid conditional logic compare operator: <code>' .
					$rule['compare'] . '</code><br>Allowed operators are: <code>' .
				implode( ', ', $allowed_operators ) . '</code>' );
			}
			if ( $rule['compare'] === 'IN' || $rule['compare'] === 'NOT IN' ) {
				if ( ! is_array( $rule['value'] ) ) {
					Incorrect_Syntax_Exception::raise( 'Invalid conditional logic value format. ' .
					'An array is expected, when using the "' . $rule['compare'] . '" operator.' );
				}
			}

			// Check the value
			if ( ! isset( $rule['value'] ) ) {
				$rule['value'] = '';
			}

			$parsed_rules['rules'][] = $rule;
		}

		return $parsed_rules;
	}

	/**
	 * Set the REST visibility of the field
	 * 
	 * @param bool $visible
	 */
	public function set_visible_in_rest_api( $visible ) {
		$this->visible_in_rest_api = $visible;
	}
	
	/**
	 * Get the REST visibility of the field
	 * 
	 * @return bool
	 */
	public function get_visible_in_rest_api() {
		return $this->visible_in_rest_api;
	}

	/**
	 * Configuration function for setting the field visibility in the response of the requests to the REST API
	 * 
	 * @param bool $visible
	 * @return Field $this
	 */
	public function show_in_rest( $visible = true ) {
		$this->set_visible_in_rest_api( $visible );
		return $this;
	}

	/**
	 * Returns an array that holds the field data, suitable for JSON representation.
	 * This data will be available in the Underscore template and the Backbone Model.
	 *
	 * @param bool $load  Should the value be loaded from the database or use the value from the current instance.
	 * @return array
	 */
	public function to_json( $load ) {
		if ( $load ) {
			$this->load();
		}

		$field_data = array(
			'id' => $this->get_id(),
			'type' => $this->get_type(),
			'label' => $this->get_label(),
			'name' => $this->get_name(),
			'base_name' => $this->get_base_name(),
			'value' => $this->get_value(),
			'default_value' => $this->get_default_value(),
			'help_text' => $this->get_help_text(),
			'context' => $this->get_context(),
			'required' => $this->is_required(),
			'lazyload' => $this->get_lazyload(),
			'width' => $this->get_width(),
			'classes' => $this->get_classes(),
			'conditional_logic' => $this->get_conditional_logic(),
		);

		return $field_data;
	}

	/**
	 * Hook administration scripts.
	 */
	public static function admin_hook_scripts() {
		wp_enqueue_media();
		wp_enqueue_script( 'carbon-fields', \Carbon_Fields\URL . '/assets/js/fields.js', array( 'carbon-app', 'carbon-containers' ), \Carbon_Fields\VERSION );
		wp_localize_script( 'carbon-fields', 'crbl10n',
			array(
				'title' => __( 'Files', \Carbon_Fields\TEXT_DOMAIN ),
				'geocode_zero_results' => __( 'The address could not be found. ', \Carbon_Fields\TEXT_DOMAIN ),
				'geocode_not_successful' => __( 'Geocode was not successful for the following reason: ', \Carbon_Fields\TEXT_DOMAIN ),
				'max_num_items_reached' => __( 'Maximum number of items reached (%s items)', \Carbon_Fields\TEXT_DOMAIN ),
				'max_num_rows_reached' => __( 'Maximum number of rows reached (%s rows)', \Carbon_Fields\TEXT_DOMAIN ),
				'cannot_create_more_rows' => __( 'Cannot create more than %s rows', \Carbon_Fields\TEXT_DOMAIN ),
				'complex_no_rows' => __( 'There are no %s yet. Click <a href="#">here</a> to add one.', \Carbon_Fields\TEXT_DOMAIN ),
				'complex_add_button' => __( 'Add %s', \Carbon_Fields\TEXT_DOMAIN ),
				'complex_min_num_rows_not_reached' => __( 'Minimum number of rows not reached (%1$d %2$s)', \Carbon_Fields\TEXT_DOMAIN ),
				'message_form_validation_failed' => __( 'Please fill out all fields correctly. ', \Carbon_Fields\TEXT_DOMAIN ),
				'message_required_field' => __( 'This field is required. ', \Carbon_Fields\TEXT_DOMAIN ),
				'message_choose_option' => __( 'Please choose an option. ', \Carbon_Fields\TEXT_DOMAIN ),

				'enter_name_of_new_sidebar' => __( 'Please enter the name of the new sidebar:', \Carbon_Fields\TEXT_DOMAIN ),
			)
		);
	}

	/**
	 * Hook administration styles.
	 */
	public static function admin_hook_styles() {
		wp_enqueue_style( 'thickbox' );
	}
}
