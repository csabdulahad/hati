<?php

namespace hati;

/**
 * Modify the server configuration here as per settings and requirements.
 * Make sure that you don't change the key. This is default configuration.
 * The Hati library folder has to in root directory.
*/

const CONFIG = [

    'welcome_hati'                      =>          true,
    'app_name'                          =>          'Hati',
    'session_auto_start'                =>          true,
    'session_msg_key'                   =>          'msg',

    // response output mode and its configuration
    'as_JSON_output'                    =>          false,

    // root folder name without any slashes either at front or at the end
    'root_folder'                       =>          'hati',

    // database configuration
    'db_host'                           =>          'localhost',
    'db_name'                           =>          'hati',
    'db_username'                       =>          'root',
    'db_password'                       =>          '',

    // set default timezone for the entire project
    'time_zone'                         =>          'Europe/London',


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