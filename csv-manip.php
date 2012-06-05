<?php
/**
 * Functions to manipulate CVS imports and exports
 *
 * @package word-filter-plus
 * 
 * Copyright 2012 - eHermits, Inc. LTD - GNU 2
 * 
 */
 
if ( !class_exists(  'csv_manip'  )  ) {
	class csv_manip {
		function csv_manip(){
			$this->__construct();
		} // end csv_manip
	
		function __construct(){
		} // end __construct
		
		private function _valToCsvHelper( $val, $separator, $trimFunction ) {
			if ( $trimFunction ) $val = $trimFunction( $val );
			// If there is a separator ( ; ) or a quote ( " ) or a linebreak in the string, we need to quote it.
			$needQuote = FALSE;
			do {
				if ( strpos( $val, '"' ) !== FALSE ) {
					$val = str_replace( '"', '""', $val );
					$needQuote = TRUE;
					break;
				}
				if ( strpos( $val, $separator ) !== FALSE ) {
					$needQuote = TRUE;
					break;
				}
				if ( ( strpos( $val, "\n" ) !== FALSE ) || ( strpos( $val, "\r" ) !== FALSE ) ) { // \r is for mac
					$needQuote = TRUE;
					break;
				}
			} 
			while ( FALSE );
			if ( $needQuote ) {
				$val = '"' . $val . '"';
			}
			return $val;
		} // end _valToCsvHelper

		private function _define_newline() {
			$unewline = "\r\n";
			if ( strstr( strtolower( $_SERVER[ "HTTP_USER_AGENT" ] ), 'win' ) ) {
			   $unewline = "\r\n";
			} else if ( strstr( strtolower( $_SERVER[ "HTTP_USER_AGENT" ] ), 'mac' ) ) {
			   $unewline = "\r";
			} else {
			   $unewline = "\n";
			}
			return $unewline;
		} // end _define_newline

		private function _get_browser_type() {
			$USER_BROWSER_AGENT = '';
			if (ereg( 'OPERA(/| )([0-9].[0-9]{1,2})', strtoupper( $_SERVER[ "HTTP_USER_AGENT" ] ), $log_version ) ) {
				$USER_BROWSER_AGENT = 'OPERA';
			} else if ( ereg('MSIE ([0-9].[0-9]{1,2})',strtoupper( $_SERVER[ "HTTP_USER_AGENT" ] ), $log_version  )) {
				$USER_BROWSER_AGENT = 'IE';
			} else if ( ereg('OMNIWEB/([0-9].[0-9]{1,2})', strtoupper( $_SERVER[ "HTTP_USER_AGENT" ] ), $log_version ) ) {
				$USER_BROWSER_AGENT = 'OMNIWEB';
			} else if ( ereg('MOZILLA/([0-9].[0-9]{1,2})', strtoupper( $_SERVER[ "HTTP_USER_AGENT" ] ), $log_version ) ) {
				$USER_BROWSER_AGENT = 'MOZILLA';
			} else if ( ereg('KONQUEROR/([0-9].[0-9]{1,2})', strtoupper( $_SERVER[ "HTTP_USER_AGENT" ] ), $log_version ) ) {
		    	$USER_BROWSER_AGENT = 'KONQUEROR';
			} else {
		    	$USER_BROWSER_AGENT = 'OTHER';
			}
	
			return $USER_BROWSER_AGENT;
		} // end _get_browser_type

		private function _get_mime_type() {
			$USER_BROWSER_AGENT = $this->_get_browser_type();

			$mime_type = ( $USER_BROWSER_AGENT == 'IE' || $USER_BROWSER_AGENT == 'OPERA' )
				? 'application/octetstream'
				: 'application/octet-stream';
			return $mime_type;
		} // end _get_mime_type

		public function arrayToCsvString( $array, $separator=';', $trim='both', $removeEmptyLines=TRUE ) {
			if ( !is_array( $array ) || empty( $array ) ) return '';
			switch ( $trim ) {
				case 'none':
					$trimFunction = FALSE;
					break;
				case 'left':
					$trimFunction = 'ltrim';
					break;
				case 'right':
					$trimFunction = 'rtrim';
					break;
				default: // 'both':
					$trimFunction = 'trim';
				break;
			}
			$ret = array();
			reset( $array );
			if ( is_array( current( $array ) ) ) {
				while ( list( ,$lineArr ) = each( $array ) ) {
					if ( !is_array( $lineArr ) ) {
						// Could issue a warning ...
						$ret[] = array();
					} else {
						$subArr = array();
						while ( list( ,$val ) = each( $lineArr ) ) {
							$val      = $this->_valToCsvHelper( $val, $separator, $trimFunction );
							$subArr[] = $val;
						}
					}
					$ret[] = join( $separator, $subArr );
				}
				$crlf = $this->_define_newline();
				return join( $crlf, $ret );
			} else {
				while ( list( ,$val ) = each( $array ) ) {
					$val   = $this->_valToCsvHelper( $val, $separator, $trimFunction );
					$ret[] = $val;
				}
				return join( $separator, $ret );
			}
		} // end arrayToCsvString
		
		public function csv2table( $src_file, $table_name, $column_array, $start_row = 1, $truncate = 0 ) {
			global $wpdb;
			$errorMsg = "";
			
			if ( empty( $src_file ) ) {
				$errorMsg .= "<br />Input file is not specified";
				return $errorMsg;
			}

			$file_handle = fopen( $src_file, "r");
			if ( $file_handle === FALSE) {
				// File could not be opened...
				$errorMsg .= 'Source file could not be opened!<br />';
				$errorMsg .= "Error on fopen('$src_file')";	// Catch any fopen() problems.
				return $errorMsg;
			}

			if ( $truncate == 1 ) {
				$query = "truncate $table_name;";
				$results = $wpdb->query( $wpdb->prepare( $query ) );
			}

			$row = 1;
			while ( !feof( $file_handle ) ) {
				$line_of_text = fgetcsv( $file_handle, 1024 );
				if ( $row < $start_row ) {
					// Skip until we hit the row that we want to read from.
					$row++;
					continue;
				}

				$columns = count( $line_of_text );
				if ( $columns > 1 )	{
					$query_vals = "'" . $wpdb->escape( $line_of_text[0] ) . "'";
					$column_string = '`' . $column_array[0] . '`';
					for( $c=1; $c<$columns; $c++ ) {
						$line_of_text[ $c ] = utf8_encode( $line_of_text[ $c ]);
						$line_of_text[ $c ] = addslashes( $line_of_text[ $c ]);
						$query_vals .= ",'" . $wpdb->escape( $line_of_text[ $c ] ) . "'";
						$column_string .= ', `' . $column_array[ $c ] . '` ';
					}
					// echo "<br />Query Val: ".$query_vals."<br />";
					$query = "INSERT INTO $table_name ($column_string) VALUES ($query_vals)";
				
					// echo "<br />Query String: ". $query;
					$results = $wpdb->query( $wpdb->prepare( $query ) );
					if( empty( $results ) ) {
						$errorMsg .= "<br />Insert into the Database failed for the following Query:<br />";
						$errorMsg .= $query;
					}
				}
				$row++;
			}
			fclose( $file_handle );
			
			return $errorMsg;
		} // end csv2table

		public function createcsv( $table, $sep = ";", $fields) {
			global $wpdb;
			if ( isset( $fields ) && is_array( $fields ) ) {
				$csv = $this->arrayToCsvString( $fields, $sep );
				$csv .= $this->_define_newline();
				$column_string = '`' . $fields[0] . '`';
				$columns = count( $fields );
				for( $c=1; $c<$columns; $c++ ) {
					$column_string .= ', `' . $wpdb->escape( $fields[ $c ] ) . '` ';
				}
			} else {
				$column_string = '*';
			}
			
			if( $wpdb->get_var( $wpdb->prepare( 'show tables like "' . $table . '"' ) ) !== $table ) {
				wp_die( "Table to Export doesn't exist" );
			}
			
			$query = "SELECT $column_string FROM $table";
			$results = $wpdb->get_results( $wpdb->prepare( $query ), ARRAY_A );
			$csv .= $this->arrayToCsvString( $results, $sep );

			$now = gmdate( 'D, d M Y H:i:s' ) . ' GMT';

			header( 'Content-Type: ' . $this->_get_mime_type() );
			header( 'Expires: ' . $now );

			header( 'Content-Disposition: attachment; filename="'.$table.'.csv"' );
			header( 'Pragma: no-cache' );

			echo $csv;
			die();
		} // end createcsv
	}  // end class csv_manip
} // end !class_exists