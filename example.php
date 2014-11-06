<?php

include_once('batchProcessHelper.php');

class userImportProcess extends batchProcessHelper {

	function load_data() {
		return csv_to_array(ABSPATH . 'example_data.csv');
	}

	function process_item($item) {
		$this->log('Processing item: ' . var_export($item, true));
		return true;
	}
}

function csv_to_array($filename='', $delimiter=',') {
	if(!file_exists($filename) || !is_readable($filename))
		return FALSE;

	$header = NULL;
	$data = array();
	if (($handle = fopen($filename, 'r')) !== FALSE)
	{
		while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
		{
			if(!$header)
				$header = $row;
			else
				$data[] = array_combine($header, $row);
		}
		fclose($handle);
	}
	return $data;
}

$process = new userImportProcess(array(
	'blog_id' => 51,
	'batch_size' => 10,
	'batch_identifier' => 'User Import'
));

$process->process();
