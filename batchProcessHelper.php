<?php
/**
 * A class to help process a chunk of data w/ WordPress over HTTP
 *
 * This class assumes it is installed in a directory at the root of your WordPress installation.
 *
 * For example:
 *
 * ${wordpress_root_dir}/wp-scripts
 *
 * or
 *
 * ${wordpress_root_dir}/scripts
 *
 * The name of the directory is irrelevant, but it must be within the root directory of your WP install.
 *
 * Put any related data-processing scripts in the same directory alongside this file to keep things organized.
 *
 * Keeping data-processing files in their own directory will also make it easier to restrict access to said files.
 **/

define('WP_USE_THEMES', false);
require(dirname(__DIR__) . '/wp-blog-header.php');

class batchProcessHelper {

	var $blog_id;
	var $batch_identifier;
	var $batch_size;
	var $log_statements = array();
	var $log_file;
	var $transient_key;

	function __construct($options=array()) {
		if (!is_user_logged_in())
			$this->handle_404();

		$user = wp_get_current_user();
		if (is_multisite())  {
			if (!is_super_admin($user->ID))
				$this->handle_404();
		} else {
			if (!in_array('administrator', $user->roles))
				$this->handle_404();
		}

		$this->init($options);
	}

	function init($options) {
		global $wp_filesystem;

		if (empty($wp_filesystem)) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			WP_Filesystem();
		}

		$this->blog_id = ($options['blog_id'])? $options['blog_id'] : null;

		if (!empty($this->blog_id))
			switch_to_blog($this->blog_id);

		$this->batch_identifier = $options['batch_identifier'];
		$this->batch_size = ($options['batch_size'])? $options['batch_size'] : 10;
		$this->transient_key = sanitize_title($this->batch_identifier) . '_data';
		$this->log_file = '/tmp/wp_batch_process_' . sanitize_title($this->batch_identifier) . '.log';

		if (!file_exists($this->log_file))
			$wp_filesystem->put_contents($this->log_file, '', FS_CHMOD_FILE);
	}

	/**
	 * These methods must be overridden
	 *
	 * load_data
	 * process_item
	 *
	 **/

	/**
	 * Open your source file or URL in this function. Return a serializable
	 * version of said data.
	 **/
	function load_data() { throw new Exception('Not Implemented'); }

	/**
	 * Process an individual item from your data queue/array.
	 *
	 * This method should return a boolean based on whether it was able
	 * to finish processing $item.
	 *
	 **/
	function process_item($item) { throw new Exception('Not Implemented'); }

	/**
	 * Iterate over each item in the data queue/array and run `process_item`
	 *
	 * Keeps track of how many items we've processed and refreshes the process
	 * once we hit the batch_size limit.
	 **/
	function process_data($data) {
		$counter = 0;

		foreach ($data as $item) {
			$result = $this->process_item($item);
			if ($result) {
				$counter++;
				$this->remove_item_from_data_transient($item);
				if ($counter == $this->batch_size)
					return $this->output_message_and_refresh();
			}
		}

		$this->output_message_and_refresh();
	}

	/**
	 * Call this function to start the process
	 **/
	function process() {
		$data = $this->get_data_transient();

		if (empty($data)) {
			$data = $this->load_data();
			$this->set_data_transient($data);
		}

		$this->process_data($data);

		if (!empty($this->blog_id))
			restore_current_blog();
	}

	/* Request repsonse */
	function output_message_and_refresh() {
		$data = $this->get_data_transient();
		$remaining = count($data);
		$refresh = true;

		if (count($data) > 0) {
			$message = "Finished processing {$this->batch_size} items. " .
				"There are $remaining items remaining. Processing next batch momentarily...";
		} else {
			$message = "Finished processing all items.";
			$refresh = false;
		}

		$this->log_output();

		header('HTTP/1.1 200 OK', true, 200);
		?>
		<!doctype html>
		<html>
			<head>
				<title><?php echo $this->batch_identifier; ?></title>
				<?php if ($refresh) { ?>
					<meta http-equiv="refresh" content="1">
				<?php } ?>
			</head>
			<body>
				<p><?php echo $message; ?></p>
				<p>Peak memory usage: <?php echo (memory_get_peak_usage()/1024/1024); ?> MiB</p>
				<p>Memory limit: <?php echo ini_get('memory_limit'); ?></p>
			</body>
		</html>
		<?php
	}

	/* CRUD functions for your data queue/array to persist between requests */
	function get_data_transient() {
		$ret = get_transient($this->transient_key);
		if (empty($ret))
			return array();
		else
			return $ret;
	}

	function set_data_transient($data) {
		set_transient($this->transient_key, $data, 0);
	}

	function clear_data_transient() {
		delete_transient($this->transient_key);
	}

	/**
	 * Remove an item from your data queue/array.
	 **/
	function remove_item_from_data_transient($item) {
		$data = $this->get_data_transient();
		$new_data = array();
		foreach ($data as $existing_item) {
			$existing_item= (array) $existing_item;
			if ($existing_item !== $item) {
				array_push($new_data, $existing_item);
			}
		}
		$this->set_data_transient($new_data);
	}

	/* Logging-related methods */
	function log($msg) {
		array_push($this->log_statements, $msg);
	}

	function log_output() {
		if (empty($this->log_file))
			return;

		global $wp_filesystem;

		$existing_log = (string) $wp_filesystem->get_contents($this->log_file);

		$to_append = '';
		foreach ($this->log_statements as $msg)
			$to_append .= $msg . "\n";

		$wp_filesystem->put_contents($this->log_file, $existing_log . $to_append, FS_CHMOD_FILE);
	}

	/* 404 Page */
	function handle_404() {
		status_header(404);
		nocache_headers();
?>
<head>
<title>Error response</title>
</head>
<body>
<h1>Error response</h1>
<p>Error code 404.
<p>Message: File not found.
</body>
<?php
		exit();
	}

}
