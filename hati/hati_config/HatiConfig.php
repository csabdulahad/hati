<?php

/**
 *
 * WARNING : DON'T MODIFY THIS CLASS ANY POINT BELOW THIS COMMENT.
 *
 * This is configuration object to prepare the working environment
 * properly. Please use hati.json file in order to customize your
 * great HATI.
 *
 * WARNING : DON'T MODIFY THIS CLASS ANY POINT BELOW THIS COMMENT.
 *
 * */

return [

    'favicon'                           =>          '',

    'project_dir_as_include_path'       =>          true,
    'use_global_func'                   =>          true,

    'as_JSON_output'                    =>          false,
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
        'folder' => 'upload/doc'
    ],

    'img_config'                        => [
        'ext'    => ['png', 'gif', 'jpg', 'jpeg'],
        'size'   => 5, // MB
        'folder' => 'upload/img'
    ],

    'video_config'                       => [
        'ext'    => ['mp4', 'wmv'],
        'size'   => 5, // MB
        'folder' => 'upload/video'
    ],

    'audio_config'                       => [
        'ext'    => ['mp3', 'wav'],
        'size'   => 5, // MB
        'folder' => 'upload/audio'
    ],

    'jquery'                            =>          '3.6.0',
    'jst'                               =>          '4.0.0',
    'bootstrap'                         =>          '5.1.3',
    'jquery_ui'                         =>          '1.13.0',
    'highlight_js'                      =>          'intellij-light', // css file name for theme

];