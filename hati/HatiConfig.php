<?php

namespace hati;

/**
 * Modify the server configuration here as per settings and requirements.
 * Make sure that you don't change the key. This is default configuration.
 * The Hati library folder has to in root directory.
*/

const CONFIG = [

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

    // database configuration
    'db_host'                           =>          '',
    'db_name'                           =>          '',
    'db_username'                       =>          '',
    'db_password'                       =>          '',

    // set default timezone for the entire project
    'time_zone'                         =>          'Europe/London',

    // SMTP protocol settings for mailing to be used by Perok class of dakghor package
    'mailer_email'                      =>          '',
    'mailer_pass'                       =>          '',
    'mailer_port'                       =>          587,
    'mailer_name'                       =>          '',

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