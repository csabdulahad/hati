<?php

namespace hati;

/**
 * Modify the server configuration here as per settings and requirements.
 * Make sure that you don't change the key. This is default configuration.
 * The Hati library folder has to in root directory.
*/

const CONFIG = [

    // by default, Hati loads with Composer auto loader. If it is false, then Hati
    // will use PSR-0 loader scheme to resolve dependencies. Many other dependencies
    // coming from composer which are currently in use by Hati or in future will
    // require to be loaded manually.
    'composer_loader'                   =>          true,

    'welcome_hati'                      =>          true,
    'app_name'                          =>          '',
    'session_auto_start'                =>          false,
    'session_msg_key'                   =>          'msg',

    // add the full path to favicon including http:// and .ico extension
    'favicon'                           =>          '',

    // response output mode and its configuration
    'as_JSON_output'                    =>          false,

    // root folder name without any slashes either at front or at the end
    'root_folder'                       =>          '',

    // php code file which is found every where throughout the project.
    // add the path to the file with directory structure and file name
    // without .php extension.
    'global_php'                        =>          '',

    // database configuration
    'db_host'                           =>          '',
    'db_name'                           =>          '',
    'db_username'                       =>          '',
    'db_password'                       =>          '',

    // set default timezone for the entire project
    'time_zone'                         =>          'Europe/London',

    // SMTP protocol settings for mailing to be used by Perok class
    'mailer_email'                      =>          '',
    'mailer_pass'                       =>          '',
    'mailer_port'                       =>          587,
    'mailer_name'                       =>          '',
    'mailer_reply_to'                   =>          '',

    // API benchmarking. When it is turned on the Hati, after loading dependencies
    // marks the starting point of the benchmark in the Hati constructor. After the
    // script execution before outputting the JSON buffer, it calculates the benchmark
    // time and adds to the response object.
    'dev_API_benchmark'                 =>          false,

    // API testing delay; API output wait specified number of sec before the output.
    // Hati adds this in response object of the output to indicate the developers
    // about this delay so that the developers don't accidentally forget or remove
    // the delay from the production release.
    'dev_API_delay'                     =>          0, // sec in integer

    // settings for file extension and the Kuli uploader

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
    ]

];