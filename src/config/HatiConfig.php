<?php

return [

	'favicon'                           =>          '',
	
	'api_registry_folder'				=>			'',

	'project_dir_as_include_path'       =>          true,
	'use_global_func'                   =>          true,

	'dev_API_benchmark'                 =>          false,
	'dev_API_delay'                     =>          0, // sec in integer

	'time_zone'                         =>          'Europe/London',
	'session_auto_start'                =>          false,
	'session_msg_key'                   =>          'msg',

	'global_php'                        =>          [],
	'common_js_files'                   =>          [],
	'common_css_files'                  =>          [],

	'mailer_host'						=>			'smtp.gmail.com',
	'mailer_email'                      =>          '',
	'mailer_pass'                       =>          '',
	'mailer_port'                       =>          587,
	'mailer_name'                       =>          '',
	'mailer_reply_to'                   =>          '',

	'doc_config'                        => [
		'ext'    => ['txt', 'doc', 'pdf', 'docx', 'ppt'],
		'size'   => 5, // MB
	],

	'img_config'                        => [
		'ext'    => ['png', 'gif', 'jpg', 'jpeg'],
		'size'   => 5, // MB
	],

	'video_config'                       => [
		'ext'    => ['mp4', 'wmv'],
		'size'   => 5, // MB
	],

	'audio_config'                       => [
		'ext'    => ['mp3', 'wav'],
		'size'   => 5, // MB
	]

];