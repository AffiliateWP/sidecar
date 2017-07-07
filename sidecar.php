<?php
namespace Sandhills {

	/**
	 * Standalone library to provide a variety of database sanitization helpers when
	 * interacting with WordPress' wp-db class for custom queries.
	 *
	 * @since 1.0.0
	 */
	class Sidecar {

		/**
		 * The single Sidecar instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    \Sandhills\Sidecar
		 * @static
		 */
		private static $instance;

		/**
		 * Sidecar version.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $version = '1.0.0';

		/**
		 * Holds the wpdb global instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    \wpdb
		 */
		private $wpdb;

		/**
		 * The running SELECT clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $select_clause = '*';

		/**
		 * The running WHERE clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $where_clause = '';

		/**
		 * The running JOIN clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $join_clause = '';

		/**
		 * The running ORDERBY clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $orderby_clause = '';

		/**
		 * The running ORDER clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $order_clause = '';

		/**
		 * The running COUNT clause for the current instance.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 *
		 * @see Sidecar::clause()
		 */
		private $count_clause = '';

		/**
		 * Represents the current clause being worked with.
		 *
		 * Resets at the end of escape_input().
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $current_clause;

		/**
		 * Represents the current field(s) being worked with.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    string
		 */
		private $current_field;

		/**
		 * Represents the current input value(s).
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    mixed
		 */
		private $current_value;

		/**
		 * Stores clauses in progress for retrieval.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    array
		 */
		private $clauses_in_progress = array();

		/**
		 * Whitelist of clauses Sidecar is built to handle.
		 *
		 * @access private
		 * @since  1.0.0
		 * @var    array
		 */
		private $allowed_clauses = array( 'select', 'where', 'join', 'orderby', 'order', 'count' );

		/**
		 * Whitelist of allowed comparison operators.
		 *
		 * @access public
		 * @since  1.0.0
		 * @var    array
		 */
		private $allowed_compares = array(
			'=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN',
			'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS'
		);

		/**
		 * Sets up and retrieves the Sidecar instance.
		 *
		 * @access public
		 * @since  1.0.0
		 * @static
		 *
		 * @return \Sandhills\Sidecar Sidecar instance.
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof Sidecar ) ) {
				self::$instance = new Sidecar;

				self::$instance->setup();
			}

			return self::$instance;
		}

		/**
		 * Sets up needed values.
		 *
		 * @access private
		 * @since  1.0.0
		 */
		private function setup() {
			global $wpdb;

			if ( $wpdb instanceof \wpdb ) {
				$this->wpdb = $wpdb;
			}
		}

		/**
		 * Retrieves the current Sidecar version.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string
		 */
		public function version() {
			return $this->version;
		}

		/**
		 * Sets the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $clause Clause to set as current.
		 * @return \Sandhills\Sidecar Current sidecar instance.
		 */
		public function set_current_clause( $clause ) {
			$clause = strtolower( $clause );

			if ( in_array( $clause, $this->allowed_clauses, true ) ) {
				$this->current_clause = $clause;
			}

			return $this;
		}

		/**
		 * Retrieves the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Current clause name.
		 */
		public function get_current_clause() {
			return $this->current_clause;
		}

		/**
		 * Sets the current field.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $field Field to set as current.
		 * @return \Sandhills\Sidecar Current sidecar instance.
		 */
		public function set_current_field( $field ) {
			$this->current_field = sanitize_key( $field );

			return $this;
		}

		/**
		 * Retrieves the current field name.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Current field name.
		 */
		public function get_current_field() {
			return $this->current_field;
		}

		/**
		 * Resets the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 */
		public function reset_vars() {
			$this->current_clause = null;
			$this->current_field = null;
		}

		/**
		 * Validates that the given comparison operator is allowed.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string $operator Comparison operator.
		 * @return bool True if the operator is valid, otherwise false.
		 */
		public function validate_compare( $operator ) {
			$allowed = in_array( $operator, $this->allowed_compares, true );

			/**
			 * Filters whether the given comparison operator is "allowed".
			 *
			 * @since 1.0.0
			 *
			 * @param bool               $allowed  Whether the operator is allowed.
			 * @param string             $operator Comparison operator being checked.
			 * @param \Sandhills\Sidecar $this     Current Sidecar instance.
			 */
			return apply_filters( 'sidecar_valid_compare', $allowed, $operator, $this );
		}

		/**
		 * Builds a section of the WHERE clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $values           Single value of varying types, or array of values.
		 * @param string|callable $callback_or_type Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $compare          MySQL operator used for comparing the $value. Accepts '=', '!=',
		 *                                          '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN',
		 *                                          'NOT BETWEEN', 'EXISTS' or 'NOT EXISTS'.
		 *                                          Default is 'IN' when `$value` is an array, '=' otherwise.
		 * @return \Sandhills\Sidecar
		 */
		public function where( $field ) {
			if ( $field !== $this->get_current_field() ) {
				$this->set_current_field( $field );
			}

			$this->set_current_clause( 'where' );

			if ( ! is_callable( $callback_or_type ) ) {
				/*
				 * TODO: Decide whether to throw an exception if get_callback() stiill doesn't yield a callable.
				 *
				 * Could make implementing code a bit too long-winded having to try/catch all over the place.
				 * Mayyyybe it can be done via an abstraction layer, such as moving this business logic a
				 * level deeper.
				 */
				$callback = $this->get_callback( $callback_or_type );
			}

			if ( ! is_array( $values ) ) {
				$values = (array) $values;
 			}

 			if ( ! $this->validate_compare( $compare ) ) {
				$compare = '=';
		    }


		}

		/**
		 * Handles '=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function equals( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			$callback = $this->get_callback( $callback_or_type );

			$value = call_user_func( $callback, $value );

			$current_clause = $this->get_current_clause();
			$current_field  = $this->get_current_field();

			$this->clauses_in_progress[ $current_clause ][] = "{$current_field} = {$value}";

			return $this;
		}

		/**
		 * Handles '!=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function doesnt_equal( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			$callback = $this->get_callback( $callback_or_type );
			$value    = call_user_func( $callback, $value );
			return $this;
		}

		/**
		 * Handles '>' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function gt( $value, $callback_or_type = 'intval', $operator = 'OR' ) {

			return $this;
		}

		/**
		 * Handles '<' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function lt( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles '>=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function gte( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles '<=' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function lte( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'LIKE' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function like( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT LIKE' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_like( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'IN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function in( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT IN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_in( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'BETWEEN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function between( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT BETWEEN' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_between( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'EXISTS' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function exists( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Handles 'NOT EXISTS' value comparison.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param mixed           $value            Value of varying types, or array of values.
		 * @param string|callable $callback_or_type Optional. Sanitization callback to pass values through, or shorthand
		 *                                          types to use preset callbacks. Default 'intval'.
		 * @param string          $operator         Optional. If `$value` is an array, whether to use 'OR' or 'AND' when
		 *                                          building the expression. Default 'OR'.
		 * @return \Sandhills\Sidecar Current Sidecar instance.
		 */
		public function not_exists( $value, $callback_or_type = 'intval', $operator = 'OR' ) {
			return $this;
		}

		/**
		 * Retrieves the callback to use for the given type.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @param string|callable $type Standard type to retrieve a callback for, or an already-callable.
		 * @return callable Callback.
		 */
		public function get_callback( $type ) {

			if ( is_callable( $type ) ) {

				$callback = $type;

			} else {

				switch( $type ) {

					case 'int':
					case 'integer':
						$callback = 'intval';
						break;

					case 'float':
					case 'double':
						$callback = 'floatval';
						break;

					case 'string':
						$callback = 'sanitize_text_field';
						break;

					case 'key':
						$callback = 'sanitize_key';
						break;

					default:
						$callback = 'esc_sql';
						break;
				}

			}

			/**
			 * Filters the callback to use for a given type.
			 *
			 * @since 1.0.0
			 *
			 * @param callable           $callback Callback.
			 * @param string             $type     Type to retrieve a callback for.
			 * @param \Sandhills\Sidecar $this     Current Sidebar instance.
			 */
			return apply_filters( 'sidecar_callback_for_type', $callback, $type, $this );
		}

		/**
		 * Retrieves raw, sanitized SQL for the current clause.
		 *
		 * @access public
		 * @since  1.0.0
		 *
		 * @return string Raw, sanitized SQL.
		 */
		public function get_sql() {
			$sql            = '';
			$current_clause = $this->get_current_clause();

			if ( isset( $this->clauses_in_progress[ $current_clause ] ) ) {
				$sql = strtoupper( $current_clause ) . ' ' . $this->clauses_in_progress[ $current_clause ];

				$this->reset_vars();
			}

			return $sql;
		}
	}
}

namespace {

	/**
	 * Shorthand helper for retrieving the Sidecar instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \Sandhills\Sidecar Sidecar instance.
	 */
	function sidecar() {
		return \Sandhills\Sidecar::instance();
	}

}
