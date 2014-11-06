# Batch processing data with WordPress over HTTP

The `batchProcessHelper` class is meant to be used in cases where you do not have command-line access or are otherwise not allowed to execute command-line scripts on your host, but do have SFTP access.

If you find yourself in this situation and need to process a large data set, perhaps as a part of a user or content migration, you can use this utility to work with your host's PHP memory limit as well as PHP and Apache timeout settings.

## Usage

`batchProcessHelper` is meant to be extended. There are two methods you must define on classes that extend `batchProcessHelper`:

1. **`load_data`**

    This method accesses the data set you will be processing. You can open a local file or fetch an url, open a CSV or XML -- anything you need to do to access your data.
    
    `load_data` is expected to return an array of "items" (either associative arrays or objects). This will be your "queue" of data, which `batchProcessHelper` saves in a WordPress transient to keep track of what items need processing.

2. **`process_item`**

    This method should accept one argument: `$item` -- one of the items from your data queue.
    
    Do any data processing you need in this function, returning true on success or false on error.
    
### An example class (from example.php):

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
    
### Instantiating `userImportProcess`:


	$process = new userImportProcess(array(
		'blog_id' => 99,
		'batch_size' => 10,
		'batch_identifier' => 'User Import'
	));

Remember to call `process` to kick of the... process.

	$process->process();
	
### Specifying a `log_file`:

You can optionally specify a `log_file` when you instantiate your class.

	$process = new userImportProcess(array(
		'blog_id' => 51,
		'batch_size' => 10,
		'batch_identifier' => 'User Import',
		'log_file' => '/path/to/your/file.log'
	));

If you do not, `batchProcessHelper` will try to write a log file in `/tmp`.

As in the example class above, you can use the `log` method to add debug or error messages to the log file:

    $this->log('Processing item' . var_export($item, true));


## Run the example

The included example processes `example_data.csv` -- a list of dummy users.

NOTE: the example DOES NOT make any changes to your WordPress install. It's only meant to illustrate how you might use `batchProcessHelper`.

In this case, "processing" the data consists of outputting a log entry for each user and exiting.

To run the example script:

1. Copy the contents of this repository to a directory in the root of your WordPress install.

For example:

    ${wordpress_install_root_directory}/wp-scripts/

or:

    ${wordpress_install_root_directory}/migration_files/

The name of the directory doesn't matter, but it must be in the root of your WordPress install.

For this example, we'll assume you're using a directory named `wp-scripts`.

2. Open a browser and go to example.php:

    http://www.yourdomainhere.com/wp-scripts/example.php

The script will return a message indicating how many items it processed and how many items are left in the queue.

When it finishes processing all items, it will return the message, "Finished processing all items."

## Notes

### You must have a Super Admin account to use `batchProcessHelper`

`batchProcessHelper` checks that you are currently logged into your site and have Super Admin privileges. If you are not logged in or do not have Super Admin privileges, `batchProcessHelper` returns a 404 error.

### Restrict access to your `wp-scripts` directory

It's worth restricting access to your `wp-scripts` directory to specific IP addresses, especially if you plan on leaving the directory on your server after you're done running your batch process.

With Apache, add a .htaccess file to your `wp-scripts` directory with the contents:

    Order allow,deny
    Allow from 192.168.1.200/32

Replace 192.168.1.200 with the client IP address that should be granted access.
