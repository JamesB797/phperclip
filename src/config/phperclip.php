<?php return [

	// Class used to generate file names for files
	'filename_generator' => 'TippingCanoe\Phperclip\FileNameGenerator',

	// Register File Processors
	'processors' => [

		'TippingCanoe\Phperclip\Processes\ImageProcessor',

		/*
		 * Implement your own file specific processing by adding more processors here
		 */

	],

	// Multiple storage options.
	'storage' => [

		'TippingCanoe\Phperclip\Storage\Filesystem' => [

			// Directory that Phperclip can manage everything under.
			'root' => public_path() . '/files/uploaded',

			// Public, client-accessible prefix pointing to wherever the root is hosted, including scheme.
			'public_prefix' => sprintf('%s/files/uploaded', Request::getSchemeAndHttpHost()),

		],

		// Amazon S3 Storage Driver
		/*
		'TippingCanoe\Phperclip\Storage\S3' => [
			'bucket' => 'clipped_files'
		],
		*/

		//    ],

		//
		// Amazon S3 Client
		//
		// Uncommenting these lines tells Phperclip to take care
		// of the Amazon S3 binding in the IoC.
		//
		// It may be that this binding is accomplished elsewhere in your
		// project and if so, you don't need to duplicate it here.
		//
		//'s3' => [
		//	'key' => 'YOUR_KEY_HERE',
		//	'secret' => 'YOUR_SECRET_HERE',
		//]
	],

	// Sample Image Filter

	// Example usage:
	//
	// Phperclip::saveFromFile($file, ['filters' => Config::get('phperclip.filters.shrink')]);
	//
	'filters' => [

		'shrink' => [

			'TippingCanoe\Phperclip\Processes\Image\FixRotation',
			[
				'TippingCanoe\Phperclip\Processes\Image\Resize',
				[
					'width' => 100,
					'height' => 100,
					'preserve_ratio' => true,
				]
			]
		]
	],

];