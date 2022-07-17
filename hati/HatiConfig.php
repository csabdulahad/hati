<?php

namespace hati;

/**
 * Modify the server configuration here as per settings and requirements.
 * Make sure that you don't change the key. This is default configuration.
 * The Hati library folder has to in root directory.
*/

const CONFIG = [

    // root folder name without any slashes either at front or at the end
    'root_folder'                       =>          '',

    'welcome_hati'                      =>          true,
    'app_name'                          =>          '',
    'session_auto_start'                =>          false,
    'session_msg_key'                   =>          'msg',

    // set default timezone for the entire project
    'time_zone'                         =>          'Europe/London',

    // by default, Hati loads with Composer auto loader. If it is false, then Hati
    // will use PSR-0 loader scheme to resolve dependencies. Many other dependencies
    // coming from composer which are currently in use by Hati or in future will
    // require to be loaded manually.
    'composer_loader'                   =>          true,

    // Hati adds a favicon link when Util::titleTag() gets called. The favicon can be
    // of any format but must be placed inside 'img' directory on the project root folder.
    // here the value should be the file name with extension.
    'favicon'                           =>          '',

    // php code file which is found every where throughout the project.
    // add the path to the file with directory structure and file name
    // without .php extension.
    'global_php'                        =>          '',

    // common js and css files auto sourcing(js) or linking(css) when the script calls
    // Util::js() or Util::css() function. The file names must be relative to the server
    // root without file extension and must be kept inside js and style folders respectively.
    // Files are checked for existence before adding to HTML document.
    'common_js_files'                   =>          [],
    'common_css_files'                  =>          [],

    // response output mode and its configuration
    'as_JSON_output'                    =>          false,

    // database configuration
    'db_host'                           =>          'localhost',
    'db_name'                           =>          '',
    'db_username'                       =>          '',
    'db_password'                       =>          '',

    // predefined sql queries will run once to prepare the db environment
    // with procedures, functions, CTE, optimization etc.
    // the file must be of type sql ahd the path must be a relative path
    // without '.sql' extension.
    'db_prepare_sql'                    =>          '',

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

    // various 3rd party libraries and framework version configuration
    'jquery'                            =>          '3.6.0',
    'angular_js'                        =>          '1.8.2',
    'jst'                               =>          '2.2.0',
    'bootstrap'                         =>          '5.1.3',
    'jquery_ui'                         =>          '1.13.0',
    'highlight_js'                      =>          'intellij-light', // css file name for theme

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