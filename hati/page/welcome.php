<?php
    use hati\Hati;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hati - Welcome</title>
    <style>
        body {
            background-color: #F5F9FC;
            /*color: #515C6B;*/
            font-family: Calibri, serif;
            font-size: 1.03em;
        }

        div {
            width: 720px;
            padding: 16px;
            margin: 0 auto;
            background-color: white;
            border: 1px ridge #e7eaf0;
        }

        h1 {
            margin-top: 0;
            text-align: center;
            color: #0D6EFD;
        }

        p {
            font-family: Calibri, serif;
        }

        span {
            vertical-align: middle;
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 8px;
            padding: 1px;
            border-radius: 50%;
        }

        span.a {
            background-color: #FF3366;
        }

        span.b {
            background-color: #00CC88;
        }

        table {
            width: 100%;
            border: 1px solid lightgray;
            border-collapse: collapse;
        }

        td {
            padding: 6px;
            border: 1px solid gray;
            vertical-align: middle;
            text-align: center;
        }

        thead tr {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
        }

        tbody tr td:first-child {
            text-align: left;
        }

    </style>
</head>
<body>

<div>
    <h1>Hati - A Speedy PHP Library</h1>
    Congratulations!<br>
    You have successfully loaded Hati. Consider modifying the following to configure the Hati
    <ul>
        <li><b>.htaccess</b> file to point the Hati loader</li>
        <li>Tune various settings in the <b>HatiConfig.php</b> inside the Hati library folder</li>
    </ul>
    <p>
        Below is a quick run test on the current configuration settings vs default. You should modify them according to your need.
    </p>
    <table>
        <thead>
            <tr>
                <td>Setting</td>
                <td>Default Value</td>
                <td>Current Value</td>
            </tr>
        </thead>
        <tbody>
            <?php
                $defaultConfig = [
                    'app_name' => '',
                    'root_folder' => '',
                    'favicon' => '',
                    'time_zone' => 'Europe/London',
                    'session_auto_start' => false,
                    'session_msg_key' => 'msg',
                    'as_JSON_output' => false,
                    'db_host' => '',
                    'db_name' => '',
                    'db_username' => '',
                    'db_password' => '',
                    'mailer_email' => '',
                    'mailer_pass' => '',
                    'mailer_name' => ''
                ];

                $currentConfig = Hati::configObj();

                foreach ($defaultConfig as $k => $v) {
                    echo '';
                    $status = $v === $currentConfig[$k] ? 'a' : 'b';
                    echo "<tr><td><span class='$status'></span>$k</td>";
                    echo "<td>". getStr($v) ."</td>";
                    echo "<td>". getStr($currentConfig[$k]) ."</td></tr>";
                }

                function getStr($v): string {
                    if (is_bool($v)) {
                        return $v ? "true" : "false";
                    } else if (empty($v)) return 'empty value';
                    return $v;
                }
            ?>
        </tbody>
    </table>

</div>
<div>
    Version: <?php echo Hati::version(); ?>
</div>

</body>
</html>
